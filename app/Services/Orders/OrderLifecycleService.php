<?php

namespace App\Services\Orders;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InventoryUnavailable;
use App\Models\BakeryProductVariant;
use App\Models\Customer;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderLifecycleService
{
    public function cancelByCustomer(Order $order, Customer $customer): Order
    {
        return DB::transaction(function () use ($order, $customer): Order {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->where('customer_id', $customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeCancelledByCustomer()) {
                throw ValidationException::withMessages([
                    'order' => ['این سفارش در وضعیت فعلی قابل لغو نیست.'],
                ]);
            }

            $this->releaseReservationsLocked(
                $locked,
                InventoryReservationStatus::Released,
                'customer_cancelled',
            );
            $this->transitionLocked(
                $locked,
                OrderStatus::Cancelled,
                'customer',
                $customer->getKey(),
                'سفارش پیش از پرداخت توسط مشتری لغو شد.',
            );
            $locked->forceFill(['cancelled_at' => now()])->save();

            return $locked->fresh(['items', 'reservations', 'paymentAttempts']);
        }, 3);
    }

    public function expireAwaitingPaymentOrders(): int
    {
        $orderIds = Order::query()
            ->where('status', OrderStatus::AwaitingPayment->value)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->pluck('id');

        $expired = 0;

        foreach ($orderIds as $orderId) {
            $didExpire = DB::transaction(function () use ($orderId): bool {
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                if (
                    ! $order
                    || $order->status !== OrderStatus::AwaitingPayment
                    || $order->reservation_expires_at?->isFuture()
                ) {
                    return false;
                }

                $this->releaseReservationsLocked(
                    $order,
                    InventoryReservationStatus::Expired,
                    'payment_timeout',
                );
                $this->transitionLocked(
                    $order,
                    OrderStatus::Expired,
                    'system',
                    null,
                    'مهلت پرداخت و رزرو موجودی پایان یافت.',
                );

                return true;
            }, 3);

            if ($didExpire) {
                $expired++;
            }
        }

        return $expired;
    }

    public function consumeReservations(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->consumeReservationsLocked($lockedOrder);
        }, 3);
    }

    /**
     * The caller must already hold a row lock on the order and be inside the
     * same database transaction as the verified payment-attempt update.
     */
    public function markPaidFromVerifiedPaymentLocked(Order $order): void
    {
        if ($order->status === OrderStatus::Paid && $order->payment_status === PaymentStatus::Paid) {
            return;
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages([
                'order' => ['این سفارش دیگر در وضعیت قابل پرداخت نیست.'],
            ]);
        }

        if (! $order->reservation_expires_at || $order->reservation_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'order' => ['مهلت رزرو موجودی سفارش پایان یافته است.'],
            ]);
        }

        $this->consumeReservationsLocked($order);
        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ])->save();
        $this->transitionLocked(
            $order,
            OrderStatus::Paid,
            'payment',
            null,
            'پرداخت توسط درگاه تأیید شد و رزرو موجودی به‌صورت اتمیک مصرف شد.',
        );
    }

    private function consumeReservationsLocked(Order $lockedOrder): void
    {
        $reservations = InventoryReservation::query()
            ->where('order_id', $lockedOrder->getKey())
            ->where('status', InventoryReservationStatus::Active->value)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        if (
            $reservations->isEmpty()
            || $reservations->contains(
                fn (InventoryReservation $reservation): bool => ! $reservation->isActive(),
            )
        ) {
            throw ValidationException::withMessages([
                'order' => ['رزرو موجودی سفارش معتبر نیست یا منقضی شده است.'],
            ]);
        }

        $variants = BakeryProductVariant::query()
            ->whereIn('id', $reservations->pluck('variant_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $reservation) {
            /** @var BakeryProductVariant|null $variant */
            $variant = $variants->get($reservation->variant_id);

            if (! $variant || $variant->stock_quantity < $reservation->quantity) {
                throw new InventoryUnavailable(
                    $variant?->public_id ?? 'deleted',
                    $variant?->name ?? 'نامشخص',
                    $reservation->quantity,
                    $variant?->stock_quantity ?? 0,
                );
            }

            $variant->decrement('stock_quantity', $reservation->quantity);
            $reservation->forceFill([
                'status' => InventoryReservationStatus::Consumed,
                'consumed_at' => now(),
            ])->save();
        }
    }

    private function releaseReservationsLocked(
        Order $order,
        InventoryReservationStatus $status,
        string $reason,
    ): void {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->where('status', InventoryReservationStatus::Active->value)
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->forceFill([
                'status' => $status,
                'released_at' => now(),
                'release_reason' => $reason,
            ])->save();
        }
    }

    private function transitionLocked(
        Order $order,
        OrderStatus $to,
        string $actorType,
        ?int $actorId,
        string $note,
    ): void {
        $from = $order->status;
        $order->forceFill(['status' => $to])->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}