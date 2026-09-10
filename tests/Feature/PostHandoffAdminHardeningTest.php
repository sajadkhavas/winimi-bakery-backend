<?php

namespace Tests\Feature;

use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostHandoffAdminHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_recipient_scope_requires_active_explicit_opt_in(): void
    {
        $eligible = $this->createSubscription('eligible', marketing: true);
        $this->createSubscription('no-consent', marketing: false);
        $this->createSubscription('revoked', marketing: true, revoked: true);

        $recipients = WebPushSubscription::query()->marketingRecipients()->get();

        $this->assertCount(1, $recipients);
        $this->assertTrue($recipients->first()->is($eligible));
    }

    public function test_manual_broadcast_and_review_form_lock_the_audited_integrity_boundaries(): void
    {
        $broadcast = file_get_contents(
            app_path('Filament/Resources/NotificationOutboxResource/Pages/ManageNotificationOutbox.php'),
        );
        $reviews = file_get_contents(app_path('Filament/Resources/ProductReviewResource.php'));

        $this->assertIsString($broadcast);
        $this->assertStringContainsString('->marketingRecipients()', $broadcast);
        $this->assertStringContainsString('رضایت اعلان‌های بازاریابی', $broadcast);
        $this->assertStringContainsString('marketing.manual', $broadcast);

        $this->assertIsString($reviews);
        $this->assertMatchesRegularExpression(
            "/Select::make\('status'\).*?->disabled\(\).*?->dehydrated\(false\)/s",
            $reviews,
        );
        $this->assertMatchesRegularExpression(
            "/DateTimePicker::make\('published_at'\).*?->disabled\(\).*?->dehydrated\(false\)/s",
            $reviews,
        );
        $this->assertStringContainsString("Action::make('approve')", $reviews);
        $this->assertStringContainsString("Action::make('reject')", $reviews);
    }

    private function createSubscription(string $key, bool $marketing, bool $revoked = false): WebPushSubscription
    {
        return WebPushSubscription::query()->create([
            'guest_token_hash' => hash('sha256', 'guest-'.$key),
            'endpoint_hash' => hash('sha256', 'https://push.example.test/'.$key),
            'endpoint' => 'https://push.example.test/'.$key,
            'public_key' => 'public-'.$key,
            'auth_token' => 'auth-'.$key,
            'content_encoding' => 'aes128gcm',
            'transactional_enabled' => false,
            'marketing_enabled' => $marketing,
            'last_seen_at' => now(),
            'revoked_at' => $revoked ? now() : null,
        ]);
    }
}
