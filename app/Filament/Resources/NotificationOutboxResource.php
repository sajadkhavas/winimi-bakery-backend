<?php

namespace App\Filament\Resources;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Filament\Resources\NotificationOutboxResource\Pages;
use App\Models\NotificationOutbox;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationOutboxResource extends Resource
{
    protected static ?string $model = NotificationOutbox::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'اعلان‌ها';

    protected static ?string $modelLabel = 'اعلان';

    protected static ?string $pluralModelLabel = 'اعلان‌ها و پیام‌ها';

    protected static ?string $navigationGroup = 'ارتباطات';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('خلاصه اعلان')
                ->schema([
                    Forms\Components\TextInput::make('order.order_number')->label('سفارش')->disabled(),
                    Forms\Components\Select::make('channel')
                        ->label('نوع ارسال')
                        ->options(collect(NotificationChannel::cases())->mapWithKeys(
                            fn (NotificationChannel $channel): array => [$channel->value => $channel->label()],
                        ))
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(collect(NotificationStatus::cases())->mapWithKeys(
                            fn (NotificationStatus $status): array => [$status->value => $status->label()],
                        ))
                        ->disabled(),
                    Forms\Components\TextInput::make('template_key')
                        ->label('نوع پیام')
                        ->formatStateUsing(fn (?string $state): string => self::templateLabel($state))
                        ->disabled(),
                    Forms\Components\TextInput::make('provider')
                        ->label('مسیر ارسال')
                        ->formatStateUsing(fn (?string $state): string => self::providerLabel($state))
                        ->disabled(),
                    Forms\Components\TextInput::make('attempts')->label('تعداد تلاش')->disabled(),
                ])
                ->columns(3),
            Forms\Components\Section::make('جزئیات فنی و پشتیبانی')
                ->description('اطلاعات Read-only برای پیگیری خطا؛ در کار روزمره نیازی به باز کردن این بخش نیست.')
                ->schema([
                    Forms\Components\TextInput::make('public_id')->label('شناسه داخلی اعلان')->disabled(),
                    Forms\Components\TextInput::make('provider_message_id')->label('شناسه پیام در سرویس ارسال')->disabled()->columnSpanFull(),
                    Forms\Components\KeyValue::make('payload')->label('داده قالب')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('last_error')->label('آخرین خطا')->disabled()->rows(4)->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')->label('سفارش')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('destination')
                    ->label('مقصد')
                    ->formatStateUsing(fn (?string $state): string => self::maskDestination($state)),
                Tables\Columns\TextColumn::make('channel')
                    ->label('نوع ارسال')
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannel $state): string => $state->label()),
                Tables\Columns\TextColumn::make('template_key')
                    ->label('نوع پیام')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::templateLabel($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (NotificationStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('provider')
                    ->label('مسیر ارسال')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::providerLabel($state)),
                Tables\Columns\TextColumn::make('attempts')->label('تلاش')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('ایجاد')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(collect(NotificationStatus::cases())->mapWithKeys(
                        fn (NotificationStatus $status): array => [$status->value => $status->label()],
                    )),
                Tables\Filters\SelectFilter::make('channel')
                    ->label('نوع ارسال')
                    ->options(collect(NotificationChannel::cases())->mapWithKeys(
                        fn (NotificationChannel $channel): array => [$channel->value => $channel->label()],
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('ارسال مجدد')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('ارسال مجدد اعلان')
                    ->modalDescription('این اعلان دوباره وارد صف ارسال می‌شود. اطلاعات مقصد یا محتوای پیام تغییر نمی‌کند.')
                    ->visible(fn (NotificationOutbox $record): bool => in_array($record->status, [
                        NotificationStatus::Failed,
                        NotificationStatus::Cancelled,
                    ], true))
                    ->action(fn (NotificationOutbox $record): bool => $record->update([
                        'status' => NotificationStatus::Pending,
                        'available_at' => now(),
                        'failed_at' => null,
                        'last_error' => null,
                    ])),
                Tables\Actions\EditAction::make()->label('جزئیات'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotificationOutbox::route('/')];
    }

    public static function providerLabel(?string $provider): string
    {
        return match (strtolower(trim((string) $provider))) {
            'web-push-vapid' => 'اعلان مرورگر وینیمی',
            'kavenegar' => 'پیامک کاوه‌نگار',
            'disabled', '' => 'ارسال غیرفعال',
            'testing' => 'ارسال آزمایشی',
            default => 'سرویس ارسال دیگر',
        };
    }

    public static function templateLabel(?string $template): string
    {
        return match (trim((string) $template)) {
            'marketing.manual' => 'اعلان عمومی بازاریابی',
            'order.paid' => 'تأیید پرداخت سفارش',
            'order.preparing' => 'شروع آماده‌سازی سفارش',
            'order.ready' => 'آماده‌شدن سفارش',
            'order.dispatched' => 'ارسال سفارش',
            'order.delivered' => 'تحویل سفارش',
            'order.cancelled' => 'لغو سفارش',
            '' => 'بدون قالب',
            default => 'اعلان سیستمی',
        };
    }

    private static function maskDestination(?string $destination): string
    {
        $destination = (string) $destination;
        if (mb_strlen($destination) <= 4) {
            return '****';
        }

        return mb_substr($destination, 0, 3).'****'.mb_substr($destination, -4);
    }
}
