<?php

namespace App\Http\Resources;

use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class BakeryProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Collection<int, BakeryProductVariant> $variants */
        $variants = $this->resource->relationLoaded('activeVariants')
            ? $this->activeVariants
            : collect();

        $defaultVariant = $variants->firstWhere('is_default', true) ?? $variants->first();
        $priceToman = $variants->min(fn ($variant): int => $variant->current_price_toman);
        $stock = $variants->sum('stock_quantity');
        $images = $this->catalogImages();
        $contentVerified = (bool) $this->content_verified;

        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'productCode' => $this->product_code,
            'shortDescription' => $this->short_description,
            'longDescription' => $contentVerified
                ? $this->publicPlainText($this->description)
                : null,
            'category' => $this->category?->name,
            'categorySlug' => $this->category?->slug,
            'categoryData' => $this->whenLoaded(
                'category',
                fn (): BakeryCategoryResource => new BakeryCategoryResource($this->category),
            ),
            'priceToman' => $priceToman,
            'regularPriceToman' => $defaultVariant?->regular_price_toman,
            'salePriceToman' => $defaultVariant?->hasValidSalePrice()
                ? $defaultVariant->sale_price_toman
                : null,
            'weightGrams' => $defaultVariant?->weight_grams,
            'weight' => $defaultVariant?->weight_grams
                ? number_format($defaultVariant->weight_grams).' گرم'
                : null,
            'stock' => $stock,
            'available' => $stock > 0,
            'requiresCooling' => (bool) $this->requires_cooling,
            'shippingScope' => $this->requires_cooling ? 'tehran-karaj' : 'nationwide',
            'shippingNote' => $this->requires_cooling
                ? 'این محصول نیازمند روش تحویل سرد است و محدوده نهایی در Checkout تأیید می‌شود.'
                : 'روش تحویل نهایی براساس مقصد و تنظیمات Checkout محاسبه می‌شود.',
            'ingredients' => $contentVerified
                ? BakeryProduct::normalizeTagList($this->ingredients)
                : [],
            'allergens' => $contentVerified
                ? BakeryProduct::normalizeTagList($this->allergens)
                : [],
            'shelfLife' => $contentVerified ? $this->shelf_life : null,
            'storageTips' => $contentVerified ? $this->storage_instructions : null,
            'preparationTimeDays' => $this->preparation_time_days,
            'badges' => array_values(array_filter([
                $this->requires_cooling ? 'نیازمند نگهداری سرد' : null,
                $this->is_featured ? 'پیشنهاد وینیمی' : null,
            ])),
            'images' => $images,
            'isFeatured' => (bool) $this->is_featured,
            'contentVerified' => $contentVerified,
            'mediaVerified' => (bool) $this->media_verified,
            'inventoryVerified' => true,
            'variants' => BakeryVariantResource::collection($variants),
            'seo' => [
                'title' => $this->meta_title ?: $this->name,
                'description' => $this->meta_description ?: $this->short_description,
            ],
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function publicPlainText(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $withoutExecutableBlocks = preg_replace(
            '/<(script|style)\b[^>]*>.*?<\/\1>/isu',
            '',
            $value,
        ) ?? $value;

        $withReadableBreaks = preg_replace(
            '/<(?:br\s*\/?|\/p|\/div|\/li|\/h[1-6])>/iu',
            "\n",
            $withoutExecutableBlocks,
        ) ?? $withoutExecutableBlocks;

        $plainText = html_entity_decode(
            strip_tags($withReadableBreaks),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $plainText = preg_replace('/[\p{Z}\s]+/u', ' ', $plainText) ?? $plainText;
        $plainText = trim($plainText);

        return $plainText !== '' ? $plainText : null;
    }

    private function catalogImages(): array
    {
        return $this->getMedia('catalog-main')
            ->concat($this->getMedia('catalog-gallery'))
            ->map(fn ($media): array => [
                'url' => $media->getFullUrl(),
                'alt' => $media->getCustomProperty('alt', $this->name),
                'verified' => (bool) $this->media_verified,
            ])
            ->values()
            ->all();
    }
}
