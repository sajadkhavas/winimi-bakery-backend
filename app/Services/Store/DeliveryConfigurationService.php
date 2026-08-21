<?php

namespace App\Services\Store;

use App\Enums\DeliveryMethod;
use App\Models\DeliveryZone;
use App\Models\StoreSetting;
use Illuminate\Validation\ValidationException;

final class DeliveryConfigurationService
{
    public const FEE_PAYMENT_MODE = 'pay_on_delivery_to_courier';

    public const CUSTOMER_NOTICE =
        'هزینه ارسال در مبلغ سفارش محاسبه نشده و هنگام تحویل مستقیماً به پیک پرداخت می‌شود.';

    /**
     * DeliveryZone pricing is intentionally non-authoritative for new checkout.
     *
     * @return array{
     *     zone: null,
     *     fee_toman: int,
     *     packaging_fee_toman: int,
     *     preparation_min_days: int,
     *     preparation_max_days: int
     * }
     */
    public function quote(
        DeliveryMethod $method,
        ?string $province,
        ?string $city,
        int $subtotalToman,
        bool $requiresCooling,
    ): array {
        $this->assertStoreCanAcceptOrder($subtotalToman);

        return [
            'zone' => null,
            'fee_toman' => 0,
            'packaging_fee_toman' => 0,
            'preparation_min_days' => 0,
            'preparation_max_days' => 0,
        ];
    }

    /**
     * Only the canonical merchant-arranged courier method is offered.
     *
     * @return array<int, array{
     *     method: string,
     *     label: string,
     *     enabled: bool,
     *     feeToman: int
     * }>
     */
    public function options(
        ?string $province,
        ?string $city,
        int $subtotalToman,
        bool $requiresCooling,
    ): array {
        return [[
            'method' => DeliveryMethod::Standard->value,
            'label' => DeliveryMethod::Standard->label(),
            'enabled' => true,
            'feeToman' => 0,
        ]];
    }

    /**
     * Retained for backward-compatible callers.
     *
     * Delivery zones no longer determine price or checkout eligibility.
     */
    public function resolve(?string $province, ?string $city): ?DeliveryZone
    {
        return null;
    }

    private function assertStoreCanAcceptOrder(int $subtotalToman): void
    {
        if (! StoreSetting::value('orders.accepting_orders', true)) {
            throw ValidationException::withMessages([
                'checkout' => ['پذیرش سفارش جدید موقتاً متوقف شده است.'],
            ]);
        }

        $minimum = max(
            0,
            (int) StoreSetting::value(
                'orders.minimum_total_toman',
                0,
            ),
        );

        if ($subtotalToman < $minimum) {
            throw ValidationException::withMessages([
                'items' => [
                    "حداقل مبلغ سفارش {$minimum} تومان است.",
                ],
            ]);
        }
    }
}
