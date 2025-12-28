<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCost extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'amount',
        'currency',
        'billing_cycle',
        'start_date',
        'end_date',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Get the monthly equivalent cost
     */
    public function getMonthlyAmountAttribute(): float
    {
        return match ($this->billing_cycle) {
            'yearly' => $this->amount / 12,
            'one_time' => 0, // One-time costs are not recurring
            default => $this->amount,
        };
    }

    /**
     * Scope for active costs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope for recurring costs (monthly/yearly)
     */
    public function scopeRecurring($query)
    {
        return $query->whereIn('billing_cycle', ['monthly', 'yearly']);
    }
}
