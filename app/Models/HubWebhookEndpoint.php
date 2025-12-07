<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HubWebhookEndpoint extends Model
{
    use HasUuids;

    protected $table = 'hub_webhook_endpoints';

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'last_triggered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->secret) {
                $model->secret = Str::random(64);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSubscribedTo(string $eventType): bool
    {
        if (!$this->events || empty($this->events)) {
            return true; // Subscribe to all if not specified
        }

        return in_array($eventType, $this->events);
    }

    public function recordSuccess(): void
    {
        $this->update([
            'failure_count' => 0,
            'last_triggered_at' => now(),
        ]);
    }

    public function recordFailure(): void
    {
        $this->increment('failure_count');

        // Disable after too many failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
