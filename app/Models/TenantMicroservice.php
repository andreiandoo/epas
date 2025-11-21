<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMicroservice extends Model
{
    protected $fillable = [
        'tenant_id',
        'microservice_id',
        'is_active',
        'activated_at',
        'deactivated_at',
        'settings',
        'usage_stats',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'settings' => 'array',
        'usage_stats' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function microservice(): BelongsTo
    {
        return $this->belongsTo(Microservice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    public function getUsageStat(string $key, $default = 0)
    {
        return $this->usage_stats[$key] ?? $default;
    }

    public function incrementUsageStat(string $key, int $amount = 1): void
    {
        $stats = $this->usage_stats ?? [];
        $stats[$key] = ($stats[$key] ?? 0) + $amount;
        $this->update(['usage_stats' => $stats]);
    }
}
