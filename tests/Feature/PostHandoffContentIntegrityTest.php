<?php

namespace Tests\Feature;

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
}
