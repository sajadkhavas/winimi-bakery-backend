<?php

namespace App\Services\Orders;

use App\Models\BakeryProductVariant;
use App\Models\Order;
use App\Models\StoreSetting;

final class CookieBulkDiscountService
{
    public const ENABLED_KEY = 'pricing.cookie_bulk_discount.enabled';

    public const MIN_QUANTITY_KEY = 'pricing.cookie_bulk_discount.min_quantity';

    public const PERCENT_KEY = 'pricing.cookie_bulk_discount.percent';

    public const CATEGORY_SLUGS_KEY = 'pricing.cookie_bulk_discount.category_slugs';

    public const DEFAULT_MINIMUM_QUANTITY = 100;

    public const DEFAULT_PERCENT = 10;

    public const MAXIMUM_MINIMUM_QUANTITY = 1_000;

    public const MAXIMUM_CATEGORY_COUNT = 30;

    /**
     * @return array{
     *     enabled: bool,
     *     minimum_quantity: int,
     *     percent: int,
     *     category_slugs: list<string>
     * }
     */
    public function configuration(): array
    {
        return [
            'enabled' => (bool) StoreSetting::value(self::ENABLED_KEY, false),
            'minimum_quantity' => min(
                self::MAXIMUM_MINIMUM_QUANTITY,
                max(
                    1,
                    (int) StoreSetting::value(
                        self::MIN_QUANTITY_KEY,
                        self::DEFAULT_MINIMUM_QUANTITY,
                    ),
                ),
            ),
            'percent' => min(
                100,
                max(
                    0,
                    (int) StoreSetting::value(
                        self::PERCENT_KEY,
                        self::DEFAULT_PERCENT,
                    ),
                ),
            ),
            'category_slugs' => $this->categorySlugs(),
        ];
    }

    public function checkoutQuantityFloor(): int
    {
        $configuration = $this->configuration();

        return $configuration['enabled']
            && $configuration['percent'] > 0
            && $configuration['category_slugs'] !== []
            ? $configuration['minimum_quantity']
            : 1;
    }

    /**
     * Validate the ordinary checkout safety caps without making the bulk-cookie
     * promotion impossible. Only categories explicitly configured for cookie
     * bulk ordering are exempt from the generic per-line / total-unit caps;
     * their real upper bounds remain variant max_order_quantity and inventory.
     *
     * The exemption is independent from the discount enabled flag so an admin
     * can pause the promotion without accidentally disabling legitimate large
     * cookie orders.
     *
     * @param array<int, array{variantId?: mixed, quantity?: mixed}> $items
     *
     * @return list<string>
     */
    public function checkoutQuantityViolations(array $items): array
    {
        $groupedQuantities = [];

        foreach ($items as $item) {
            if (is_array($item) === false) {
                continue;
            }

            $variantId = trim((string) ($item['variantId'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId === '' || $quantity < 1) {
                continue;
            }

            $currentQuantity = (int) ($groupedQuantities[$variantId] ?? 0);
            $groupedQuantities[$variantId] = $currentQuantity + $quantity;
        }

        if ($groupedQuantities === []) {
            return [];
        }

        $variants = BakeryProductVariant::query()
            ->whereIn('public_id', array_keys($groupedQuantities))
            ->with('product.category')
            ->get()
            ->keyBy('public_id');

        $bulkCategorySlugs = $this->configuration()['category_slugs'];
        $maximumPerLine = max(
            1,
            (int) config('winimi.checkout.max_quantity_per_line', 20),
        );
        $maximumStandardUnits = max(
            1,
            (int) config('winimi.checkout.max_total_units', 50),
        );

        $standardUnits = 0;
        $violations = [];

        foreach ($groupedQuantities as $variantId => $quantity) {
            /** @var BakeryProductVariant|null $variant */
            $variant = $variants->get($variantId);
            if ($variant === null) {
                // Availability/launch validation remains authoritative in
                // CheckoutService. Do not reinterpret an unknown variant here.
                continue;
            }

            $categorySlug = $variant->product?->category?->slug;
            $isBulkCookie = is_string($categorySlug)
                && in_array($categorySlug, $bulkCategorySlugs, true);

            if ($isBulkCookie) {
                continue;
            }

            $standardUnits += $quantity;

            if ($quantity > $maximumPerLine) {
                $violations[] = "تعداد هر محصول در سفارش عادی نمی‌تواند بیشتر از {$maximumPerLine} عدد باشد.";
            }
        }

        if ($standardUnits > $maximumStandardUnits) {
            $violations[] = "تعداد کل محصولات غیرعمده در هر سفارش نمی‌تواند بیشتر از {$maximumStandardUnits} عدد باشد.";
        }

        return array_values(array_unique($violations));
    }

    /**
     * Recalculate the order-level bulk discount from persisted, server-priced
     * order items. The resulting amount is snapshotted on the order before a
     * payment attempt can be initiated.
     */
    public function applyToOrder(Order $order): void
    {
        $configuration = $this->configuration();
        $discount = 0;

        if (
            $configuration['enabled']
            && $configuration['percent'] > 0
            && $configuration['category_slugs'] !== []
        ) {
            $eligibleItems = $order->items()
                ->with('product.category')
                ->get()
                ->filter(function ($item) use ($configuration): bool {
                    $slug = $item->product?->category?->slug;

                    return is_string($slug)
                        && in_array($slug, $configuration['category_slugs'], true);
                });

            $eligibleQuantity = (int) $eligibleItems->sum('quantity');

            if ($eligibleQuantity >= $configuration['minimum_quantity']) {
                $eligibleSubtotal = (int) $eligibleItems->sum('line_total_toman');
                $discount = intdiv(
                    $eligibleSubtotal * $configuration['percent'],
                    100,
                );
            }
        }

        $grandTotal = max(
            0,
            (int) $order->subtotal_toman
                + (int) $order->delivery_fee_toman
                + (int) $order->packaging_fee_toman
                - $discount,
        );

        $order->forceFill([
            'discount_total_toman' => $discount,
            'grand_total_toman' => $grandTotal,
        ])->saveQuietly();
    }

    /** @return list<string> */
    private function categorySlugs(): array
    {
        $configured = StoreSetting::value(self::CATEGORY_SLUGS_KEY, []);

        if (! is_array($configured)) {
            return [];
        }

        $slugs = [];
        foreach (array_slice($configured, 0, self::MAXIMUM_CATEGORY_COUNT) as $candidate) {
            $slug = trim((string) $candidate);
            if (
                $slug === ''
                || strlen($slug) > 180
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
                || in_array($slug, $slugs, true)
            ) {
                continue;
            }

            $slugs[] = $slug;
        }

        return $slugs;
    }
}
