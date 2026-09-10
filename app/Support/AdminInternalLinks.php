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
    public static function options(): array
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

        return $options;
    }
}
