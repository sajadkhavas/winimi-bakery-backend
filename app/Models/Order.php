<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'order_number',
        'idempotency_key',
        'request_hash',
        'status',
        'payment_status',
        'delivery_method',
        'requires_cooling',
        'subtotal_toman',
        'delivery_fee_toman',
        'packaging_fee_toman',
        'discount_total_toman',
        'grand_total_toman',
        'item_count',
        'preparation_time_days',
        'customer_name',
        'customer_mobile',
        'province',
        'city',
        'address',
        'postal_code',
        'notes',
        'reservation_expires_at',
        'placed_at',
        'paid_at',
        'cancelled_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'requires_cooling' => 'boolean',
            'subtotal_toman' => 'integer',
            'delivery_fee_toman' => 'integer',
            'packaging_fee_toman' => 'integer',
            'discount_total_toman' => 'integer',
            'grand_total_toman' => 'integer',
            'item_count' => 'integer',
            'preparation_time_days' => 'integer',
            'reservation_expires_at' => 'datetime',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function scopeOwnedBy(Builder $query, Customer $customer): Builder
    {
        return $query->where('customer_id', $customer->getKey());
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === OrderStatus::AwaitingPayment
            && $this->payment_status === PaymentStatus::Unpaid;
    }
}
