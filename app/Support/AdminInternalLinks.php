<?php

namespace App\Support;

use App\Models\BakeryCategory;
use App\Models\BakeryContentPage;
use App\Models\BakeryPost;
use App\Models\BakeryProduct;

final class AdminInternalLinks
{
    /**
     * @return array<string, string>
     */
    public static function options(?string $current = null): array
    {
        $options = [
            '/' => 'صفحه اصلی',
            '/products' => 'فروشگاه — همه محصولات',
            '/blog' => 'محتوا — همه راهنماها',
            '/contact' => 'تماس با ما',
            '/about' => 'درباره ما',
        ];

        BakeryPost::query()
            ->published()
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->each(function (BakeryPost $post) use (&$options): void {
                $options['/blog/'.$post->slug] = 'مقاله — '.$post->title;
            });

        BakeryProduct::query()
            ->launchReady()
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->each(function (BakeryProduct $product) use (&$options): void {
                $options['/products/'.$product->slug] = 'محصول — '.$product->name;
            });

        BakeryCategory::query()
            ->active()
            ->ordered()
            ->get(['slug', 'name'])
            ->each(function (BakeryCategory $category) use (&$options): void {
                $options['/products/category/'.$category->slug] = 'دسته — '.$category->name;
            });

        BakeryContentPage::query()
            ->published()
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->each(function (BakeryContentPage $page) use (&$options): void {
                $options['/'.ltrim($page->slug, '/')] = 'صفحه — '.$page->title;
            });

        $current = trim((string) $current);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options = [$current => 'لینک داخلی فعلی (Legacy) — '.$current] + $options;
        }

        return $options;
    }

    public static function titleFor(string $href): string
    {
        $label = self::options($href)[$href] ?? $href;
        $parts = array_map('trim', explode('—', $label, 2));

        return $parts[count($parts) - 1] ?: $href;
    }
}
