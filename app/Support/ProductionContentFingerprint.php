<?php

namespace App\Support;

use App\Models\BakeryCategory;
use App\Models\BakeryCategoryLanding;
use App\Models\BakeryCityPage;
use App\Models\BakeryContentPage;
use App\Models\BakeryFaq;
use App\Models\BakeryGalleryItem;
use App\Models\BakeryMediaAsset;
use App\Models\BakeryPost;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use App\Models\DeliveryZone;
use App\Models\NavigationItem;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ProductionContentFingerprint
{
    /**
     * Admin-managed storefront/catalog data that a code-only Production deploy
     * must never rewrite. Transactional/customer tables are intentionally
     * excluded because they may legitimately change while the store is live.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'bakery_categories' => BakeryCategory::class,
        'bakery_category_landings' => BakeryCategoryLanding::class,
        'bakery_city_pages' => BakeryCityPage::class,
        'bakery_content_pages' => BakeryContentPage::class,
        'bakery_faqs' => BakeryFaq::class,
        'bakery_gallery_items' => BakeryGalleryItem::class,
        'bakery_media_assets' => BakeryMediaAsset::class,
        'bakery_posts' => BakeryPost::class,
        'bakery_products' => BakeryProduct::class,
        'bakery_product_variants' => BakeryProductVariant::class,
        'delivery_zones' => DeliveryZone::class,
        'navigation_items' => NavigationItem::class,
        'store_settings' => StoreSetting::class,
        'spatie_media' => Media::class,
    ];

    /**
     * @return array{aggregate:string,tables:array<string,array{table:string,count:int,sha256:string}>}
     */
    public static function capture(): array
    {
        $tables = [];

        foreach (self::MODELS as $label => $modelClass) {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                $tables[$label] = [
                    'table' => $table,
                    'count' => 0,
                    'sha256' => hash('sha256', 'TABLE_ABSENT'),
                ];

                continue;
            }

            $key = $model->getKeyName();
            $query = DB::table($table);
            if (Schema::hasColumn($table, $key)) {
                $query->orderBy($key);
            }

            $rows = $query->get()->map(static function (object $row): array {
                $values = (array) $row;
                ksort($values, SORT_STRING);

                return $values;
            })->values()->all();

            $json = json_encode(
                $rows,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION,
            );

            $tables[$label] = [
                'table' => $table,
                'count' => count($rows),
                'sha256' => hash('sha256', $json),
            ];
        }

        $aggregatePayload = json_encode(
            $tables,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        );

        return [
            'aggregate' => hash('sha256', $aggregatePayload),
            'tables' => $tables,
        ];
    }
}
