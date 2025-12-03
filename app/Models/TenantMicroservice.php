<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TenantMicroservice extends Model
{
    use LogsActivity;
    protected $fillable = [
        'tenant_id',
        'microservice_id',
        'status',
        'activated_at',
        'expires_at',
        'trial_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'settings',
        'usage_stats',
        'monthly_price',
        'last_billed_at',
        'next_billing_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_billed_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'settings' => 'array',
        'usage_stats' => 'array',
        'monthly_price' => 'decimal:2',
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
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function suspend(): void
    {
        $this->update([
            'status' => 'suspended',
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

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Microservice settings {$eventName}")
            ->useLogName('tenant');
    }

    /**
     * Add tenant_id to activity properties for scoping
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}
