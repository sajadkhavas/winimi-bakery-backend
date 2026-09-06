<?php

namespace Database\Seeders;

use App\Models\BakeryContentPage;
use App\Support\Content\TrustReconciliationManifest;
use Illuminate\Database\Seeder;
use RuntimeException;

class TrustContentStagingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Trust content staging data must never be seeded in production.');
        }

        foreach (TrustReconciliationManifest::load()['pages'] as $definition) {
            $content = collect($definition['replacements'])
                ->pluck('from')
                ->implode("\n\n");

            BakeryContentPage::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'type' => 'page',
                    'title' => match ($definition['slug']) {
                        'shipping' => 'ارسال و تحویل',
                        'terms' => 'شرایط استفاده و سفارش',
                        'privacy' => 'حریم خصوصی',
                    },
                    'excerpt' => 'داده کنترل‌شده ویژه آزمون پذیرش F29S-G.',
                    'content' => $content,
                    'meta_title' => null,
                    'meta_description' => null,
                    'status' => 'published',
                    'published_at' => now()->subMinute(),
                ],
            );
        }
    }
}
