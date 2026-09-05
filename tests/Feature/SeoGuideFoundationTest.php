<?php

namespace Tests\Feature;

use App\Models\BakeryPost;
use App\Support\Content\SeoGuideManifest;
use Database\Seeders\SeoGuideStagingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeoGuideFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_contains_the_five_canonical_guides(): void
    {
        $manifest = SeoGuideManifest::load();

        $this->assertSame(SeoGuideManifest::VERSION, $manifest['version']);
        $this->assertSame(SeoGuideManifest::TOPIC, $manifest['topic']);
        $this->assertCount(SeoGuideManifest::GUIDE_COUNT, $manifest['guides']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['sha256']);
        $this->assertSame([
            'choose-food-gift-box',
            'cookies-per-guest-guide',
            'cookie-storage-guide',
            'cheesecake-cold-storage',
            'cold-delivery-guide',
        ], array_column($manifest['guides'], 'slug'));
    }

    public function test_sync_command_is_dry_run_by_default(): void
    {
        $this->artisan('content:sync-seo-guides')
            ->expectsOutputToContain('MODE=DRY_RUN')
            ->expectsOutputToContain('VALID_GUIDES=5')
            ->expectsOutputToContain('DATABASE_MUTATIONS=0')
            ->assertSuccessful();

        $this->assertDatabaseCount('bakery_posts', 0);
    }

    public function test_apply_requires_confirmation_and_exact_manifest_hash(): void
    {
        $manifest = SeoGuideManifest::load();

        $this->artisan('content:sync-seo-guides', [
            '--apply' => true,
            '--expected-sha256' => $manifest['sha256'],
        ])->assertFailed();
        $this->assertDatabaseCount('bakery_posts', 0);

        $this->artisan('content:sync-seo-guides', [
            '--apply' => true,
            '--confirm' => 'SYNC_SEO_GUIDES',
            '--expected-sha256' => str_repeat('0', 64),
        ])->assertFailed();
        $this->assertDatabaseCount('bakery_posts', 0);
    }

    public function test_apply_creates_drafts_and_publish_requires_explicit_flag(): void
    {
        $manifest = SeoGuideManifest::load();

        $this->artisan('content:sync-seo-guides', [
            '--apply' => true,
            '--confirm' => 'SYNC_SEO_GUIDES',
            '--expected-sha256' => $manifest['sha256'],
        ])->assertSuccessful();

        $this->assertSame(5, BakeryPost::query()->count());
        $this->assertSame(5, BakeryPost::query()->where('status', 'draft')->count());
        $this->assertSame(0, BakeryPost::query()->whereNotNull('published_at')->count());

        $this->artisan('content:sync-seo-guides', [
            '--apply' => true,
            '--publish' => true,
            '--confirm' => 'SYNC_SEO_GUIDES',
            '--expected-sha256' => $manifest['sha256'],
        ])->assertSuccessful();

        $this->assertSame(5, BakeryPost::query()->published()->count());
    }

    public function test_sync_is_idempotent_and_does_not_duplicate_guides(): void
    {
        $manifest = SeoGuideManifest::load();
        $arguments = [
            '--apply' => true,
            '--publish' => true,
            '--confirm' => 'SYNC_SEO_GUIDES',
            '--expected-sha256' => $manifest['sha256'],
        ];

        $this->artisan('content:sync-seo-guides', $arguments)->assertSuccessful();
        $ids = BakeryPost::query()->orderBy('slug')->pluck('id', 'slug')->all();

        $this->artisan('content:sync-seo-guides', $arguments)->assertSuccessful();

        $this->assertSame(5, BakeryPost::query()->count());
        $this->assertSame($ids, BakeryPost::query()->orderBy('slug')->pluck('id', 'slug')->all());
    }

    public function test_staging_seeder_publishes_manifest_guides_through_public_api(): void
    {
        $this->seed(SeoGuideStagingSeeder::class);

        foreach (SeoGuideManifest::load()['guides'] as $guide) {
            $this->getJson('/api/store/posts/'.$guide['slug'])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.post.slug', $guide['slug'])
                ->assertJsonPath('data.post.title', $guide['title'])
                ->assertJsonPath('data.post.category', SeoGuideManifest::TOPIC);
        }
    }

    public function test_seo_guide_staging_seeder_is_blocked_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('SEO guide staging data must never be seeded in production.');

            (new SeoGuideStagingSeeder)->run();
        } finally {
            app()->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }
}
