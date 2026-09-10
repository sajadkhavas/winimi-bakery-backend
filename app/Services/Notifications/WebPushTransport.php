<?php

namespace App\Services\Notifications;

use App\Models\WebPushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class WebPushTransport
{
    public function ready(): bool
    {
        return (bool) config('winimi.push.enabled', false)
            && trim((string) config('winimi.push.vapid_subject')) !== ''
            && trim((string) config('winimi.push.vapid_public_key')) !== ''
            && trim((string) config('winimi.push.vapid_private_key')) !== '';
    }

    public function send(WebPushSubscription $stored, array $payload): string
    {
        if (! $this->ready()) {
            throw new RuntimeException('Web Push is not configured.');
        }

        $timeout = max(1, (int) config('winimi.notifications.timeout_seconds', 8));
        $transport = new WebPush([
            'VAPID' => [
                'subject' => config('winimi.push.vapid_subject'),
                'publicKey' => config('winimi.push.vapid_public_key'),
                'privateKey' => config('winimi.push.vapid_private_key'),
            ],
        ], [], new Client([
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
        ]));
        $subscription = Subscription::create([
            'endpoint' => $stored->endpoint,
            'publicKey' => $stored->public_key,
            'authToken' => $stored->auth_token,
            'contentEncoding' => $stored->content_encoding,
        ]);
        $transport->queueNotification(
            $subscription,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );

        foreach ($transport->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $stored->update([
                    'revoked_at' => now(),
                    'transactional_enabled' => false,
                    'marketing_enabled' => false,
                ]);
            }
            if (! $report->isSuccess()) {
                throw new RuntimeException($report->getReason());
            }
        }

        return 'web-push-'.now()->format('YmdHisv');
    }
}
