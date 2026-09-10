<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'مدیران پنل';

    protected static ?string $modelLabel = 'مدیر پنل';

    protected static ?string $pluralModelLabel = 'مدیران پنل';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات مدیر پنل')
                    ->description('ساخت و مدیریت مدیران پنل فقط برای مدیر ارشد در دسترس است. اسرار ورود دومرحله‌ای هرگز در فرم نمایش داده نمی‌شوند.')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('نام')->required()->maxLength(255),
                        Forms\Components\TextInput::make('email')->label('ایمیل')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')->label('شماره تماس')->tel()->maxLength(20),
                        Forms\Components\TextInput::make('company')->label('مجموعه')->maxLength(100),
                        Forms\Components\Select::make('roles')
                            ->label('نقش‌های دسترسی')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('دسترسی واقعی از نقش‌های Spatie/Shield اعمال می‌شود؛ فیلد متنی قدیمی role در پنل قابل ویرایش نیست.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('password')
                            ->label('رمز عبور')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255)
                            ->helperText('برای مدیر موجود خالی بگذارید تا رمز فعلی تغییر نکند.'),
                        Forms\Components\DateTimePicker::make('email_verified_at')->label('زمان تأیید ایمیل')->disabled()->dehydrated(false),
                        Forms\Components\DateTimePicker::make('two_factor_confirmed_at')->label('فعال‌سازی ورود دومرحله‌ای')->disabled()->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('نقش‌ها')->badge(),
                Tables\Columns\TextColumn::make('phone')->label('تماس')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('company')->label('مجموعه')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('two_factor_confirmed_at')->label('ورود دومرحله‌ای')->dateTime('Y/m/d H:i')->placeholder('فعال نشده')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('ایجاد')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('ویرایش')])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
