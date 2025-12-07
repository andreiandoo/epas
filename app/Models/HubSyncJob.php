<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubSyncJob extends Model
{
    use HasUuids;

    protected $table = 'hub_sync_jobs';

    protected $fillable = [
        'connection_id',
        'tenant_id',
        'job_type',
        'status',
        'started_at',
        'completed_at',
        'records_processed',
        'records_failed',
        'error_log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HubConnection::class, 'connection_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(int $processed, int $failed = 0): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'records_processed' => $processed,
            'records_failed' => $failed,
        ]);
    }

    public function fail(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_log' => $error,
        ]);
    }

    public function scopeForConnection($query, $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
