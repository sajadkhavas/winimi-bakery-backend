<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    /**
     * Canonical delivery method for all new storefront orders.
     *
     * The merchant arranges the courier. Courier cost is not part
     * of the online order total and is paid by the customer directly
     * to the courier at delivery.
     */
    case Standard = 'standard';

    /**
     * Legacy values are retained for historical order compatibility.
     * They are not offered for new storefront checkout.
     */
    case Chilled = 'chilled';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'ارسال توسط فروشگاه',
            self::Chilled => 'ارسال سرد',
            self::Pickup => 'تحویل حضوری',
        };
    }

    public function requiresAddress(): bool
    {
        return $this !== self::Pickup;
    }
}
