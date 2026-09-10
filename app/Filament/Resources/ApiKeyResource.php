<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'کلیدهای API';

    protected static ?string $modelLabel = 'کلید API';

    protected static ?string $pluralModelLabel = 'کلیدهای API';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('اطلاعات کلید')->schema([
                TextInput::make('name')->label('نام')->required(),
                TextInput::make('key')
                    ->label('کلید API')
                    ->default('tk_'.Str::random(40))
                    ->readOnly(),
                TextInput::make('rate_limit')
                    ->label('حد درخواست در دقیقه')
                    ->numeric()
                    ->minValue(1)
                    ->default(60),
                DateTimePicker::make('expires_at')
                    ->label('تاریخ انقضا')
                    ->nullable(),
                Toggle::make('is_active')->label('فعال')->default(true),
            ])->columns(2),
            Section::make('دسترسی‌ها')
                ->description('این بخش فنی است و فقط مدیر ارشد باید آن را تغییر دهد.')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('Endpointهای مجاز')
                        ->options([
                            'products' => 'محصولات',
                            'categories' => 'دسته‌بندی‌ها',
                            'brands' => 'برندها',
                            'blog' => 'وبلاگ',
                            'search' => 'جست‌وجو',
                            'settings' => 'تنظیمات',
                            'rfq' => 'درخواست قیمت',
                            'contact' => 'تماس',
                            'seo' => 'سئو',
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('کلید')
                    ->formatStateUsing(fn ($state): string => substr((string) $state, 0, 10).'…'),
                Tables\Columns\TextColumn::make('rate_limit')->label('حد درخواست')->suffix(' / دقیقه'),
                Tables\Columns\TextColumn::make('usage_count')->label('تعداد استفاده')->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')->label('آخرین استفاده')->dateTime('Y/m/d H:i')->default('—'),
                Tables\Columns\TextColumn::make('expires_at')->label('انقضا')->dateTime('Y/m/d')->default('بدون انقضا'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()->label('حذف')->requiresConfirmation(),
            ])
            ->bulkActions([]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
