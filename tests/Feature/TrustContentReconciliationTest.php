<?php

namespace Tests\Feature;

use App\Models\BakeryContentPage;
use App\Support\Content\TrustReconciliationManifest;
use Database\Seeders\TrustContentStagingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TrustContentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_controls_only_trust_pages(): void
    {
        $manifest = TrustReconciliationManifest::load();

        $this->assertSame(TrustReconciliationManifest::VERSION, $manifest['version']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['sha256']);
        $this->assertSame(['shipping', 'terms', 'privacy'], array_column($manifest['pages'], 'slug'));
    }

    public function test_dry_run_reports_pending_copy_without_mutation(): void
    {
        $this->seed(TrustContentStagingSeeder::class);
        $before = BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all();

        $this->artisan('content:reconcile-trust-pages')
            ->expectsOutputToContain('MODE=DRY_RUN')
            ->expectsOutputToContain('CONTROLLED_PAGES=3')
            ->expectsOutputToContain('AUDIT_FAILURES=0')
            ->expectsOutputToContain('DATABASE_MUTATIONS=0')
            ->assertSuccessful();

        $this->assertSame($before, BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all());
    }

    public function test_apply_requires_confirmation_and_exact_hash(): void
    {
        $this->seed(TrustContentStagingSeeder::class);
        $manifest = TrustReconciliationManifest::load();
        $before = BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all();

        $this->artisan('content:reconcile-trust-pages', [
            '--apply' => true,
            '--expected-sha256' => $manifest['sha256'],
        ])->assertFailed();

        $this->artisan('content:reconcile-trust-pages', [
            '--apply' => true,
            '--confirm' => 'RECONCILE_TRUST_PAGES',
            '--expected-sha256' => str_repeat('0', 64),
        ])->assertFailed();

        $this->assertSame($before, BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all());
    }

    public function test_apply_reconciles_copy_preserves_publication_state_and_is_idempotent(): void
    {
        $this->seed(TrustContentStagingSeeder::class);
        $manifest = TrustReconciliationManifest::load();
        $before = BakeryContentPage::query()->get()->keyBy('slug');
        $arguments = [
            '--apply' => true,
            '--confirm' => 'RECONCILE_TRUST_PAGES',
            '--expected-sha256' => $manifest['sha256'],
        ];

        $this->artisan('content:reconcile-trust-pages', $arguments)
            ->expectsOutputToContain('TRUST_RECONCILIATION_STATUS=completed')
            ->expectsOutputToContain('REMAINING_STALE_REPLACEMENTS=0')
            ->assertSuccessful();

        foreach ($manifest['pages'] as $definition) {
            $page = BakeryContentPage::query()->where('slug', $definition['slug'])->firstOrFail();
            $original = $before->get($definition['slug']);

            $this->assertSame($original->status, $page->status);
            $this->assertSame($original->published_at?->toISOString(), $page->published_at?->toISOString());

            foreach ($definition['replacements'] as $replacement) {
                $this->assertStringNotContainsString($replacement['from'], (string) $page->content);
                $this->assertStringContainsString($replacement['to'], (string) $page->content);
            }
        }

        $ids = BakeryContentPage::query()->orderBy('slug')->pluck('id', 'slug')->all();
        $this->artisan('content:reconcile-trust-pages', $arguments)
            ->expectsOutputToContain('DATABASE_MUTATIONS=0')
            ->assertSuccessful();
        $this->assertSame($ids, BakeryContentPage::query()->orderBy('slug')->pluck('id', 'slug')->all());
    }

    public function test_source_drift_fails_closed_without_mutation(): void
    {
        $this->seed(TrustContentStagingSeeder::class);
        $shipping = BakeryContentPage::query()->where('slug', 'shipping')->firstOrFail();
        $shipping->content = 'متن خارج از قرارداد کنترل‌شده';
        $shipping->save();
        $before = BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all();

        $this->artisan('content:reconcile-trust-pages')
            ->expectsOutputToContain('TRUST_PAGE_SOURCE_DRIFT=YES')
            ->assertFailed();

        $this->assertSame($before, BakeryContentPage::query()->orderBy('slug')->pluck('content', 'slug')->all());
    }

    public function test_public_page_api_returns_reconciled_content(): void
    {
        $this->seed(TrustContentStagingSeeder::class);
        $manifest = TrustReconciliationManifest::load();

        $this->artisan('content:reconcile-trust-pages', [
            '--apply' => true,
            '--confirm' => 'RECONCILE_TRUST_PAGES',
            '--expected-sha256' => $manifest['sha256'],
        ])->assertSuccessful();

        foreach ($manifest['pages'] as $definition) {
            $response = $this->getJson('/api/store/pages/'.$definition['slug'])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.page.slug', $definition['slug']);

            $content = (string) $response->json('data.page.content');
            $this->assertStringNotContainsString('پرداخت آنلاین در مرحله فعلی', $content);
            $this->assertStringNotContainsString('ارسال سراسری', $content);
        }
    }

    public function test_staging_seeder_is_blocked_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Trust content staging data must never be seeded in production.');
            (new TrustContentStagingSeeder)->run();
        } finally {
            app()->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }
}
