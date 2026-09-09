<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\WebPushSubscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    public function capabilities(): JsonResponse
    {
        $enabled = (bool) config('winimi.push.enabled', false);
        $publicKey = trim((string) config('winimi.push.vapid_public_key'));

        return ApiResponse::success([
            'supported' => $enabled && $publicKey !== '',
            'publicKey' => $enabled && $publicKey !== '' ? $publicKey : null,
            'marketingDefault' => false,
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return ApiResponse::success([
            'push' => [
                'supported' => $this->isConfigured(),
                'subscribed' => $customer->webPushSubscriptions()->active()->exists(),
                'transactionalEnabled' => $customer->webPushSubscriptions()->active()
                    ->where('transactional_enabled', true)->exists(),
                'marketingEnabled' => $customer->marketing_consent
                    && $customer->webPushSubscriptions()
                        ->active()
                        ->where('marketing_enabled', true)
                        ->exists(),
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        abort_unless($this->isConfigured(), 503, 'Push notifications are not configured.');

        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
            'transactionalEnabled' => ['nullable', 'boolean'],
            'marketingEnabled' => ['nullable', 'boolean'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $marketing = (bool) ($validated['marketingEnabled'] ?? false);

        $subscription = WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'customer_id' => $customer->getKey(),
                'guest_token_hash' => null,
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'transactional_enabled' => (bool) ($validated['transactionalEnabled'] ?? true),
                'marketing_enabled' => $marketing,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        if ($marketing && ! $customer->marketing_consent) {
            $customer->update(['marketing_consent' => true]);
        }

        return ApiResponse::success(
            ['subscribed' => true],
            status: $subscription->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function subscribeGuest(Request $request): JsonResponse
    {
        abort_unless($this->isConfigured(), 503, 'Push notifications are not configured.');
        $validated = $request->validate([
            'guestToken' => ['required', 'string', 'min:43', 'max:128'],
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
            'marketingEnabled' => ['accepted'],
        ]);

        $subscription = WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'customer_id' => null,
                'guest_token_hash' => hash('sha256', $validated['guestToken']),
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'transactional_enabled' => false,
                'marketing_enabled' => true,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return ApiResponse::success(['subscribed' => true], status: $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function unsubscribeGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guestToken' => ['required', 'string', 'min:43', 'max:128'],
            'endpoint' => ['required', 'url:https', 'max:2048'],
        ]);

        WebPushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->where('guest_token_hash', hash('sha256', $validated['guestToken']))
            ->update(['revoked_at' => now(), 'marketing_enabled' => false]);

        return ApiResponse::success(['subscribed' => false]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transactionalEnabled' => ['required', 'boolean'],
            'marketingEnabled' => ['required', 'boolean'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $customer->webPushSubscriptions()->active()->update([
            'transactional_enabled' => $validated['transactionalEnabled'],
            'marketing_enabled' => $validated['marketingEnabled'],
        ]);
        $customer->update(['marketing_consent' => $validated['marketingEnabled']]);

        return $this->preferences($request);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
        ]);
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $customer->webPushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->update([
                'revoked_at' => now(),
                'transactional_enabled' => false,
                'marketing_enabled' => false,
            ]);

        return ApiResponse::success(['subscribed' => false]);
    }

    private function isConfigured(): bool
    {
        return (bool) config('winimi.push.enabled', false)
            && trim((string) config('winimi.push.vapid_public_key')) !== ''
            && trim((string) config('winimi.push.vapid_private_key')) !== '';
    }
}
