<?php

namespace App\Console\Commands;

use App\Models\BakeryPost;
use App\Support\Content\SeoGuideManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncSeoGuides extends Command
{
    protected $signature = 'content:sync-seo-guides
        {manifest=database/content/winimi-seo-guides-v1.json : Manifest path; relative paths resolve from the Laravel root}
        {--apply : Apply the audited guide content changes}
        {--publish : Publish all controlled guides after applying them}
        {--expected-sha256= : Required SHA-256 pin when --apply is used}
        {--confirm= : Must equal SYNC_SEO_GUIDES when --apply is used}';

    protected $description = 'Audit or synchronize the controlled Winimi F29S SEO guide manifest';

    public function handle(): int
    {
        try {
            $manifest = SeoGuideManifest::load((string) $this->argument('manifest'));
        } catch (Throwable $exception) {
            $this->error('SEO_GUIDE_MANIFEST_INVALID='.$exception->getMessage());

            return self::FAILURE;
        }

        $guides = $manifest['guides'];
        $actualHash = (string) $manifest['sha256'];
        $publish = (bool) $this->option('publish');
        $apply = (bool) $this->option('apply');

        if ($publish && ! $apply) {
            $this->error('PUBLISH_REQUIRES_APPLY=YES');

            return self::FAILURE;
        }

        $existing = BakeryPost::query()
            ->whereIn('slug', array_column($guides, 'slug'))
            ->get()
            ->keyBy('slug');

        $changed = 0;
        $new = 0;
        foreach ($guides as $guide) {
            /** @var BakeryPost|null $post */
            $post = $existing->get($guide['slug']);
            if (! $post) {
                $new++;
                $changed++;
                continue;
            }

            if ($this->postDiffers($post, $guide) || ($publish && ! $post->published()->exists())) {
                $changed++;
            }
        }

        $this->newLine();
        $this->line('MODE='.($apply ? 'APPLY' : 'DRY_RUN'));
        $this->line('PUBLISH='.($publish ? 'YES' : 'NO'));
        $this->line('SEO_GUIDE_MANIFEST_STATUS=ready');
        $this->line('MANIFEST_VERSION='.$manifest['version']);
        $this->line('MANIFEST_SHA256='.$actualHash);
        $this->line('MANIFEST='.$manifest['path']);
        $this->line('TOPIC='.$manifest['topic']);
        $this->line('VALID_GUIDES='.count($guides));
        $this->line('EXISTING_GUIDES='.$existing->count());
        $this->line("NEW_GUIDES={$new}");
        $this->line("GUIDES_WITH_CHANGES={$changed}");

        foreach ($guides as $guide) {
            /** @var BakeryPost|null $post */
            $post = $existing->get($guide['slug']);
            $state = ! $post
                ? 'new'
                : ($this->postDiffers($post, $guide) ? 'changed' : 'unchanged');
            $this->line("GUIDE={$guide['slug']}|STATE={$state}|STATUS=".($post?->status ?? 'missing'));
        }

        if (! $apply) {
            $this->line('DATABASE_MUTATIONS=0');
            $this->line('NEXT=REVIEW_THEN_APPLY_WITH_HASH_AND_CONFIRMATION');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'SYNC_SEO_GUIDES') {
            $this->error('CONFIRMATION_REQUIRED=Use --confirm=SYNC_SEO_GUIDES');

            return self::FAILURE;
        }

        $expectedHash = strtolower(trim((string) $this->option('expected-sha256')));
        if ($expectedHash === '' || ! hash_equals($expectedHash, strtolower($actualHash))) {
            $this->error("MANIFEST_HASH_MISMATCH=actual:{$actualHash}");

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($guides, $publish): void {
                foreach ($guides as $guide) {
                    $post = BakeryPost::query()
                        ->where('slug', $guide['slug'])
                        ->lockForUpdate()
                        ->first();

                    if (! $post) {
                        $post = new BakeryPost(['slug' => $guide['slug']]);
                        $post->status = 'draft';
                        $post->published_at = null;
                        $post->view_count = 0;
                    }

                    $post->fill([
                        'title' => $guide['title'],
                        'excerpt' => $guide['excerpt'],
                        'content' => $guide['content'],
                        'category' => $guide['category'],
                        'tags' => $guide['tags'],
                        'author' => $guide['author'],
                    ]);

                    if ($publish) {
                        $post->status = 'published';
                        $post->published_at ??= now();
                    }

                    $post->save();
                }
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('SEO_GUIDE_SYNC_FAILED='.$exception->getMessage());

            return self::FAILURE;
        }

        $failures = 0;
        foreach ($guides as $guide) {
            $post = BakeryPost::query()->where('slug', $guide['slug'])->first();
            if (! $post || $this->postDiffers($post, $guide)) {
                $failures++;
                continue;
            }
            if ($publish && ($post->status !== 'published' || $post->published_at === null)) {
                $failures++;
            }
        }

        $this->newLine();
        $this->line('SEO_GUIDE_SYNC_STATUS='.($failures === 0 ? 'completed' : 'verification-failed'));
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line('GUIDES_SYNCHRONIZED='.count($guides));
        $this->line('GUIDES_PUBLISHED='.($publish ? count($guides) : 0));
        $this->line("VERIFICATION_FAILURES={$failures}");
        $this->line('DATABASE_MUTATIONS='.count($guides));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $guide */
    private function postDiffers(BakeryPost $post, array $guide): bool
    {
        return $post->title !== $guide['title']
            || $post->excerpt !== $guide['excerpt']
            || $post->content !== $guide['content']
            || $post->category !== $guide['category']
            || array_values($post->tags ?? []) !== array_values($guide['tags'])
            || $post->author !== $guide['author'];
    }
}
