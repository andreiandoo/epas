<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomerPaymentMethod extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'brand', 'last4', 'exp_month', 'exp_year',
        'holder', 'is_default', 'token', 'meta',
    ];

    protected $casts = ['is_default' => 'boolean', 'meta' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
