<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BakeryCityPage;
use App\Models\BakeryContentPage;
use App\Models\BakeryFaq;
use App\Models\BakeryGalleryItem;
use App\Models\BakeryPost;
use App\Models\StoreSetting;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreContentController extends Controller
{
    public function settings(): JsonResponse
    {
        $settings = StoreSetting::query()
            ->public()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (StoreSetting $setting): array => [$setting->key => $setting->typedValue()])
            ->all();
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
        $category = trim((string) $request->query('category'));
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
        $perPage = min(30, max(1, (int) $request->integer('perPage', 12)));
        $category = trim((string) $request->query('category'));
        $search = trim((string) $request->query('search'));
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
            ->paginate($perPage);

        return ApiResponse::success(
            $posts->getCollection()->map(fn (BakeryPost $post): array => $this->postSummary($post))->all(),
            meta: [
                'pagination' => [
                    'page' => $posts->currentPage(),
                    'perPage' => $posts->perPage(),
                    'total' => $posts->total(),
                    'totalPages' => $posts->lastPage(),
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
