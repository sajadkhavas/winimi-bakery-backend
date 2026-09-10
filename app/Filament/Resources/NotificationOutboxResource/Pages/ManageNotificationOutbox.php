<?php

namespace App\Filament\Resources\NotificationOutboxResource\Pages;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Filament\Resources\NotificationOutboxResource;
use App\Models\NotificationOutbox;
use App\Models\WebPushSubscription;
use Filament\Actions;
use Filament\Forms;
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
                ->modalDescription('برای تمام دستگاه‌هایی ارسال می‌شود که کاربر روی همان دستگاه اعلان‌های وینیمی را فعال کرده است.')
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان اعلان')
                        ->required()
                        ->maxLength(80),
                    Forms\Components\Textarea::make('body')
                        ->label('متن اعلان')
                        ->required()
                        ->rows(4)
                        ->maxLength(240),
                    Forms\Components\TextInput::make('url')
                        ->label('مسیر بعد از لمس اعلان')
                        ->default('/products')
                        ->required()
                        ->rules(['regex:/^\/(?!\/)[^\s]*$/'])
                        ->helperText('فقط مسیر داخلی امن؛ مانند /products'),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $count = DB::transaction(function () use ($data): int {
                        $subscriptions = WebPushSubscription::query()
                            ->active()
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
                        ->title("{$count} اعلان در صف امن ارسال قرار گرفت")
                        ->success()
                        ->send();
                }),
        ];
    }
}
