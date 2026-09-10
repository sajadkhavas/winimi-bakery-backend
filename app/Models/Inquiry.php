<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'full_name',
        'mobile',
        'email',
        'subject',
        'message',
        'metadata',
        'status',
        'assigned_user_id',
        'internal_note',
        'last_action_at',
        'ip_hash',
        'user_agent_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $inquiry): void {
            $inquiry->public_id ??= (string) Str::ulid();
        });

        static::saving(function (self $inquiry): void {
            if ($inquiry->exists && $inquiry->isDirty(['status', 'assigned_user_id', 'internal_note'])) {
                $inquiry->last_action_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => InquiryType::class,
            'status' => InquiryStatus::class,
            'metadata' => 'array',
            'last_action_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
