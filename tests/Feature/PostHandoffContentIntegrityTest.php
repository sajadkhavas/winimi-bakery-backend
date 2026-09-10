<?php

namespace Tests\Feature;

use App\Models\BakeryContentPage;
use App\Models\BakeryPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostHandoffContentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_blog_post_gets_a_publish_timestamp_automatically(): void
    {
        $post = BakeryPost::query()->create([
            'slug' => 'publish-integrity',
            'title' => 'Publish integrity',
            'content' => '<p>Content</p>',
            'status' => 'published',
        ]);

        $this->assertNotNull($post->published_at);
        $this->assertTrue(BakeryPost::query()->published()->whereKey($post)->exists());
    }

    public function test_draft_cannot_keep_a_stale_publish_timestamp_and_scheduled_publish_is_preserved(): void
    {
        $post = BakeryPost::query()->create([
            'slug' => 'draft-integrity',
            'title' => 'Draft integrity',
            'content' => '<p>Content</p>',
            'status' => 'draft',
            'published_at' => now()->subDay(),
        ]);
        $this->assertNull($post->published_at);

        $scheduledAt = now()->addDay()->startOfSecond();
        $post->update([
            'status' => 'published',
            'published_at' => $scheduledAt,
        ]);

        $this->assertTrue($post->fresh()->published_at?->equalTo($scheduledAt));
        $this->assertFalse(BakeryPost::query()->published()->whereKey($post)->exists());
    }

    public function test_published_managed_page_exposes_its_cover_and_draft_pages_stay_private(): void
    {
        $page = BakeryContentPage::query()->create([
            'type' => 'legal',
            'slug' => 'covered-page',
            'title' => 'Covered page',
            'excerpt' => 'Managed excerpt',
            'cover_url' => 'https://cdn.example.test/managed-cover.webp',
            'content' => '<p>Managed content</p>',
            'status' => 'published',
        ]);

        $this->assertNotNull($page->published_at);
        $this->assertTrue(BakeryContentPage::query()->published()->whereKey($page)->exists());

        $this->getJson('/api/store/pages/covered-page')
            ->assertOk()
            ->assertJsonPath('data.page.coverUrl', 'https://cdn.example.test/managed-cover.webp')
            ->assertJsonPath('data.page.slug', 'covered-page');

        $draft = BakeryContentPage::query()->create([
            'type' => 'legal',
            'slug' => 'private-draft-page',
            'title' => 'Private draft',
            'cover_url' => 'https://cdn.example.test/private.webp',
            'content' => '<p>Private</p>',
            'status' => 'draft',
            'published_at' => now()->subDay(),
        ]);

        $this->assertNull($draft->published_at);
        $this->assertFalse(BakeryContentPage::query()->published()->whereKey($draft)->exists());
        $this->getJson('/api/store/pages/private-draft-page')->assertNotFound();
    }

    public function test_scheduled_managed_page_is_not_public_before_its_publish_time(): void
    {
        $scheduledAt = now()->addDay()->startOfSecond();
        $page = BakeryContentPage::query()->create([
            'type' => 'legal',
            'slug' => 'scheduled-managed-page',
            'title' => 'Scheduled page',
            'content' => '<p>Scheduled</p>',
            'status' => 'published',
            'published_at' => $scheduledAt,
        ]);

        $this->assertTrue($page->fresh()->published_at?->equalTo($scheduledAt));
        $this->assertFalse(BakeryContentPage::query()->published()->whereKey($page)->exists());
        $this->getJson('/api/store/pages/scheduled-managed-page')->assertNotFound();
    }
}
