<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOAuthIdentity extends Model
{
    use HasFactory;

    protected $table = 'customer_oauth_identities';

    protected $fillable = [
        'customer_id',
        'provider',
        'provider_user_id',
        'email',
        'email_verified',
    ];

    protected $hidden = [
        'id',
        'customer_id',
        'provider_user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
