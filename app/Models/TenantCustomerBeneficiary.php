<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomerBeneficiary extends Model
{
    protected $fillable = ['tenant_id', 'customer_id', 'name', 'relation', 'email', 'birthdate', 'status', 'meta'];

    protected $casts = ['birthdate' => 'date', 'meta' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
