<?php

namespace App\Filament\Resources;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Filament\Resources\InquiryResource\Pages;
use App\Models\Inquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'درخواست‌های مشتریان';

    protected static ?string $modelLabel = 'درخواست';

    protected static ?string $pluralModelLabel = 'درخواست‌های مشتریان';

    protected static ?string $navigationGroup = 'ارتباطات';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('پیام مشتری')
                ->schema([
                    Forms\Components\TextInput::make('public_id')->label('شناسه')->disabled(),
                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options(collect(InquiryType::cases())->mapWithKeys(
                            fn (InquiryType $type): array => [$type->value => $type->label()],
                        ))
                        ->disabled(),
                    Forms\Components\TextInput::make('full_name')->label('نام')->disabled(),
                    Forms\Components\TextInput::make('mobile')->label('موبایل')->disabled(),
                    Forms\Components\TextInput::make('email')->label('ایمیل')->disabled(),
                    Forms\Components\TextInput::make('subject')->label('موضوع')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('message')->label('پیام')->disabled()->rows(7)->columnSpanFull(),
                    Forms\Components\KeyValue::make('metadata')
                        ->label('جزئیات ثبت‌شده')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('پیگیری داخلی')
                ->description('این اطلاعات داخلی است و به مشتری نمایش داده نمی‌شود.')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت پیگیری')
                        ->options(collect(InquiryStatus::cases())->mapWithKeys(
                            fn (InquiryStatus $status): array => [$status->value => $status->label()],
                        ))
                        ->required(),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('مسئول پیگیری')
                        ->relationship('assignedUser', 'name', modifyQueryUsing: fn ($query) => $query->role(['super_admin', 'panel_user']))
                        ->searchable(['name', 'email'])
                        ->preload()
                        ->nullable(),
                    Forms\Components\Textarea::make('internal_note')
                        ->label('یادداشت داخلی')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('last_action_at')
                        ->label('آخرین اقدام')
                        ->disabled()
                        ->dehydrated(false)
                        ->seconds(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')->label('شناسه')->copyable()->limit(12),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (InquiryType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('full_name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable(),
                Tables\Columns\TextColumn::make('subject')->label('موضوع')->limit(45),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (InquiryStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('مسئول')
                    ->placeholder('تخصیص‌نیافته')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_action_at')
                    ->label('آخرین اقدام')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('هنوز اقدامی نشده')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('ثبت')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع')
                    ->options(collect(InquiryType::cases())->mapWithKeys(
                        fn (InquiryType $type): array => [$type->value => $type->label()],
                    )),
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(collect(InquiryStatus::cases())->mapWithKeys(
                        fn (InquiryStatus $status): array => [$status->value => $status->label()],
                    )),
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('مسئول پیگیری')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('بررسی')])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageInquiries::route('/')];
    }
}
