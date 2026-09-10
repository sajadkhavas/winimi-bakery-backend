<?php

namespace App\Filament\Resources\NotificationOutboxResource\Pages;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Filament\Resources\NotificationOutboxResource;
use App\Models\NotificationOutbox;
use App\Models\WebPushSubscription;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageNotificationOutbox extends ManageRecords
{
    protected static string $resource = NotificationOutboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('broadcastPush')
                ->label('ارسال اعلان عمومی')
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->modalHeading('پیش‌نمایش و تأیید اعلان عمومی')
                ->modalDescription(fn (): string => sprintf(
                    'گیرندگان فعلی: %s دستگاه فعال با رضایت اعلان‌های بازاریابی. اعلان‌های تراکنشی سفارش مستقل هستند.',
                    number_format(WebPushSubscription::query()->marketingRecipients()->count()),
                ))
                ->modalSubmitActionLabel('تأیید نهایی و قرار دادن در صف')
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان اعلان')
                        ->required()
                        ->maxLength(80)
                        ->live(onBlur: true),
                    Forms\Components\Textarea::make('body')
                        ->label('متن اعلان')
                        ->required()
                        ->rows(4)
                        ->maxLength(240)
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('url')
                        ->label('مسیر بعد از لمس اعلان')
                        ->default('/products')
                        ->required()
                        ->rules(['regex:/^\/(?!\/)[^\s]*$/'])
                        ->helperText('فقط مسیر داخلی امن؛ مانند /products')
                        ->live(onBlur: true),
                    Forms\Components\Placeholder::make('broadcast_preview')
                        ->label('پیش‌نمایش نهایی')
                        ->content(function (Get $get): string {
                            $title = trim((string) $get('title')) ?: '—';
                            $body = trim((string) $get('body')) ?: '—';
                            $url = trim((string) $get('url')) ?: '—';
                            $eligible = number_format(WebPushSubscription::query()->marketingRecipients()->count());

                            return "نوع: اعلان عمومی بازاریابی\nگیرنده مجاز: {$eligible} دستگاه\nعنوان: {$title}\nمتن: {$body}\nمقصد کلیک: {$url}\nرضایت: فقط marketing_enabled فعال";
                        })
                        ->columnSpanFull(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $count = DB::transaction(function () use ($data): int {
                        $subscriptions = WebPushSubscription::query()
                            ->marketingRecipients()
                            ->lockForUpdate()
                            ->get();

                        foreach ($subscriptions as $subscription) {
                            NotificationOutbox::query()->create([
                                'customer_id' => $subscription->customer_id,
                                'channel' => NotificationChannel::WebPush,
                                'destination' => (string) $subscription->getKey(),
                                'template_key' => 'marketing.manual',
                                'payload' => [
                                    'title' => trim($data['title']),
                                    'body' => trim($data['body']),
                                    'url' => $data['url'],
                                    'tag' => 'marketing-'.now()->format('YmdHis'),
                                ],
                                'status' => NotificationStatus::Pending,
                                'provider' => 'web-push-vapid',
                                'available_at' => now(),
                            ]);
                        }

                        return $subscriptions->count();
                    }, 3);

                    Notification::make()
                        ->title("{$count} اعلان بازاریابی رضایت‌مند در صف امن ارسال قرار گرفت")
                        ->success()
                        ->send();
                }),
        ];
    }
}
