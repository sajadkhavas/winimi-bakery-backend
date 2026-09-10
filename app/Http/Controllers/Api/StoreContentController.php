<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BakeryCityPage;
use App\Models\BakeryContentPage;
use App\Models\BakeryFaq;
use App\Models\BakeryGalleryItem;
use App\Models\BakeryPost;
use App\Models\NavigationItem;
use App\Models\StoreSetting;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StoreContentController extends Controller
{
    public function settings(): JsonResponse
    {
        $settings = [];
        StoreSetting::query()
            ->public()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->each(function (StoreSetting $setting) use (&$settings): void {
                Arr::set($settings, $setting->key, $setting->typedValue());
            });
        $enamadEnabled = (bool) StoreSetting::value('trust.enamad_enabled', false);
        $badgeCode = $enamadEnabled
            ? trim((string) StoreSetting::value('trust.enamad_badge_code', ''))
            : '';

        return ApiResponse::success([
            'settings' => $settings,
            'trust' => [
                'enamad' => [
                    'enabled' => $enamadEnabled && $badgeCode !== '',
                    'badgeCode' => $enamadEnabled && $badgeCode !== '' ? $badgeCode : null,
                ],
            ],
        ]);
    }

    public function navigation(Request $request): JsonResponse
    {
        $placement = $request->validate([
            'placement' => ['nullable', 'in:header,mobile,footer'],
        ])['placement'] ?? 'header';

        $items = NavigationItem::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereIn('placement', ['all', $placement])
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('placement', ['all', $placement])
                ->with(['linkedCategory' => fn ($query) => $query->withCount([
                    'products' => fn ($products) => $products->active(),
                ])])
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (NavigationItem $item): array => [
                'id' => $item->getKey(),
                'label' => $item->label,
                'href' => $item->href,
                'description' => $item->description,
                'icon' => $item->icon,
                'imageUrl' => $item->image_path ? asset('storage/'.$item->image_path) : null,
                'openInNewTab' => $item->open_in_new_tab,
                'children' => $item->children
                    ->reject(fn (NavigationItem $child): bool => $child->hide_when_empty
                        && $child->linked_category_id !== null
                        && (int) ($child->linkedCategory?->products_count ?? 0) === 0)
                    ->map(fn (NavigationItem $child): array => [
                        'id' => $child->getKey(),
                        'label' => $child->label,
                        'href' => $child->href,
                        'description' => $child->description,
                        'icon' => $child->icon,
                        'imageUrl' => $child->image_path ? asset('storage/'.$child->image_path) : null,
                        'openInNewTab' => $child->open_in_new_tab,
                    ])->values()->all(),
            ])->values()->all();

        return ApiResponse::success($items, meta: ['placement' => $placement]);
    }

    public function page(string $slug): JsonResponse
    {
        $page = BakeryContentPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return ApiResponse::success([
            'page' => [
                'id' => $page->public_id,
                'type' => $page->type,
                'slug' => $page->slug,
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'coverUrl' => $page->cover_url,
                'content' => $page->content,
                'seo' => [
                    'title' => $page->meta_title,
                    'description' => $page->meta_description,
                ],
                'publishedAt' => $page->published_at?->toIso8601String(),
            ],
        ]);
    }

    public function faqs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $category = trim((string) ($filters['category'] ?? ''));
        $faqs = BakeryFaq::query()
            ->active()
            ->when($category !== '', fn (Builder $query): Builder => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BakeryFaq $faq): array => [
                'id' => $faq->getKey(),
                'category' => $faq->category,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])->all();

        return ApiResponse::success($faqs);
    }

    public function gallery(): JsonResponse
    {
        $items = BakeryGalleryItem::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BakeryGalleryItem $item): array => [
                'id' => $item->getKey(),
                'title' => $item->title,
                'caption' => $item->caption,
                'imageUrl' => $item->image_url,
                'linkUrl' => $item->link_url,
            ])->all();

        return ApiResponse::success($items);
    }

    public function posts(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.config('winimi.policies.pagination.catalog_max', 48)],
        ]);
        $category = trim((string) ($filters['category'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $posts = BakeryPost::query()
            ->published()
            ->when($category !== '', fn (Builder $query): Builder => $query->where('category', $category))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->latest('published_at')
            ->paginate((int) ($filters['perPage'] ?? config(
                'winimi.policies.pagination.catalog_default',
                12,
            )));

        return ApiResponse::success(
            $posts->getCollection()->map(fn (BakeryPost $post): array => $this->postSummary($post))->all(),
            meta: [
                'pagination' => Pagination::meta($posts),
                'filters' => [
                    'category' => $category !== '' ? $category : null,
                    'search' => $search !== '' ? $search : null,
                ],
            ],
        );
    }

    public function post(string $slug): JsonResponse
    {
        $post = BakeryPost::query()->published()->where('slug', $slug)->firstOrFail();
        BakeryPost::withoutTimestamps(
            fn (): int => BakeryPost::query()->whereKey($post->getKey())->increment('view_count'),
        );

        return ApiResponse::success([
            'post' => [
                ...$this->postSummary($post),
                'content' => $post->content,
                'viewCount' => $post->view_count + 1,
            ],
        ]);
    }

    public function city(string $slug): JsonResponse
    {
        $page = BakeryCityPage::query()->active()->where('slug', $slug)->firstOrFail();

        return ApiResponse::success([
            'city' => [
                'id' => $page->public_id,
                'city' => $page->city,
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'content' => $page->content,
                'seo' => [
                    'title' => $page->meta_title,
                    'description' => $page->meta_description,
                ],
            ],
        ]);
    }

    private function postSummary(BakeryPost $post): array
    {
        return [
            'id' => $post->public_id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'category' => $post->category,
            'tags' => $post->tags ?? [],
            'coverUrl' => $post->cover_url,
            'author' => $post->author,
            'publishedAt' => $post->published_at?->toIso8601String(),
        ];
    }
}
