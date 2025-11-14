<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_active',
        'is_suspended',
        'is_primary',
        'activated_at',
        'suspended_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_suspended' => 'boolean',
        'is_primary' => 'boolean',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope pentru domenii active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_suspended', false);
    }

    /**
     * Scope pentru domeniul principal
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
