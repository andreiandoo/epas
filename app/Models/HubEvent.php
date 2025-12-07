<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubEvent extends Model
{
    use HasUuids;

    protected $table = 'hub_events';

    protected $fillable = [
        'connection_id',
        'tenant_id',
        'direction',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_attempt_at',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HubConnection::class, 'connection_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markAsSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => $this->attempts >= 3 ? 'failed' : 'retrying',
            'error_message' => $error,
        ]);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'retrying'])
            ->where('attempts', '<', 3);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
