<?php

namespace Database\Seeders;

use App\Models\BakeryPost;
use App\Support\Content\SeoGuideManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeoGuideStagingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('SEO guide staging data must never be seeded in production.');
        }

        $manifest = SeoGuideManifest::load();
        $publishedAt = now();

        DB::transaction(function () use ($manifest, $publishedAt): void {
            foreach ($manifest['guides'] as $guide) {
                $post = BakeryPost::query()->firstOrNew(['slug' => $guide['slug']]);
                $post->fill([
                    'title' => $guide['title'],
                    'excerpt' => $guide['excerpt'],
                    'content' => $guide['content'],
                    'category' => $guide['category'],
                    'tags' => $guide['tags'],
                    'author' => $guide['author'],
                    'status' => 'published',
                    'published_at' => $post->published_at ?? $publishedAt,
                    'view_count' => $post->view_count ?? 0,
                ]);
                $post->save();
            }
        }, 3);

        $this->command?->line('SEO_GUIDE_STAGING_SEED_STATUS=completed');
        $this->command?->line('SEO_GUIDE_MANIFEST_SHA256='.$manifest['sha256']);
        $this->command?->line('SEO_GUIDES_PUBLISHED='.count($manifest['guides']));
    }
}
