<?php

namespace App\Services\Store;

use App\Enums\DeliveryMethod;
use App\Models\DeliveryZone;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class DeliveryConfigurationService
{
    public const FEE_PAYMENT_MODE = 'pay_on_delivery_to_courier';

    public const CUSTOMER_NOTICE =
        'هزینه ارسال در مبلغ سفارش محاسبه نشده و هنگام تحویل مستقیماً به پیک پرداخت می‌شود.';

    /**
     * DeliveryZone pricing remains non-authoritative for new checkout.
     * Active zones with an explicit city and chilled_enabled=true are the
     * allow-list for temperature-sensitive deliveries only.
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
        $this->assertDestinationEligible($province, $city, $requiresCooling);

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
     * For dry products it is nationwide. For a chilled cart it is enabled only
     * when the exact destination city has an active chilled DeliveryZone.
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
            'enabled' => ! $requiresCooling || $this->resolveChilledZone($province, $city) !== null,
            'feeToman' => 0,
        ]];
    }

    /**
     * Retained for backward-compatible callers.
     *
     * Delivery zones do not determine new-checkout price or courier fee.
     */
    public function resolve(?string $province, ?string $city): ?DeliveryZone
    {
        return null;
    }

    private function assertDestinationEligible(
        ?string $province,
        ?string $city,
        bool $requiresCooling,
    ): void {
        if (! $requiresCooling) {
            return;
        }

        if ($this->resolveChilledZone($province, $city) !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'customer.city' => [
                'ارسال محصولات یخچالی برای این مقصد فعال نیست. لطفاً یک شهر تحت پوشش انتخاب کنید یا با پشتیبانی هماهنگ کنید.',
            ],
        ]);
    }

    private function resolveChilledZone(?string $province, ?string $city): ?DeliveryZone
    {
        $normalizedProvince = DeliveryZone::normalizeLocation($province);
        $normalizedCity = DeliveryZone::normalizeLocation($city);

        if ($normalizedCity === null) {
            return null;
        }

        return DeliveryZone::query()
            ->active()
            ->where('chilled_enabled', true)
            ->whereNotNull('city')
            ->where('city', $normalizedCity)
            ->where(function (Builder $query) use ($normalizedProvince): void {
                $query->whereNull('province');

                if ($normalizedProvince !== null) {
                    $query->orWhere('province', $normalizedProvince);
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
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
