<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Saved beneficiary / family member for a marketplace customer.
 *
 * Speeds up checkout (no retyping names) and feeds the recommendations
 * engine with extra signals (age bracket, declared interests).
 */
class MarketplaceCustomerBeneficiary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_customer_id',
        'name',
        'relation',
        'birth_date',
        'email',
        'phone',
        'interests',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'interests'  => 'array',
        'is_active'  => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Age in whole years at the time of access (null if no birth_date).
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }
}
