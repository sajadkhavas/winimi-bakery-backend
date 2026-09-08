<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\StoreSetting;

final class CookieBulkDiscountService
{
    public const ENABLED_KEY = 'pricing.cookie_bulk_discount.enabled';

    public const MIN_QUANTITY_KEY = 'pricing.cookie_bulk_discount.min_quantity';

    public const PERCENT_KEY = 'pricing.cookie_bulk_discount.percent';

    public const CATEGORY_SLUGS_KEY = 'pricing.cookie_bulk_discount.category_slugs';

    /**
     * Recalculate the order-level bulk discount from persisted, server-priced
     * order items. The resulting amount is snapshotted on the order and is not
     * recalculated after checkout unless order items themselves are created.
     */
    public function applyToOrder(Order $order): void
    {
        $enabled = (bool) StoreSetting::value(self::ENABLED_KEY, true);
        $minimumQuantity = max(1, (int) StoreSetting::value(self::MIN_QUANTITY_KEY, 100));
        $percent = min(100, max(0, (int) StoreSetting::value(self::PERCENT_KEY, 10)));
        $categorySlugs = $this->categorySlugs();

        $discount = 0;

        if ($enabled && $percent > 0 && $categorySlugs !== []) {
            $eligibleItems = $order->items()
                ->with('product.category')
                ->get()
                ->filter(function ($item) use ($categorySlugs): bool {
                    $slug = $item->product?->category?->slug;

                    return is_string($slug) && in_array($slug, $categorySlugs, true);
                });

            $eligibleQuantity = (int) $eligibleItems->sum('quantity');

            if ($eligibleQuantity >= $minimumQuantity) {
                $eligibleSubtotal = (int) $eligibleItems->sum('line_total_toman');
                $discount = intdiv($eligibleSubtotal * $percent, 100);
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
        $configured = StoreSetting::value(self::CATEGORY_SLUGS_KEY, [
            'kokyhay-khangy',
            'myny-koky',
        ]);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn ($slug): string => trim((string) $slug),
                $configured,
            ),
            static fn (string $slug): bool => $slug !== '',
        )));
    }
}
