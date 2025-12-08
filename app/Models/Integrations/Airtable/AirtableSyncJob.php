<?php

namespace App\Models\Integrations\Airtable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtableSyncJob extends Model
{
    protected $fillable = [
        'table_sync_id',
        'sync_type',
        'direction',
        'status',
        'records_processed',
        'records_created',
        'records_updated',
        'records_failed',
        'error_log',
        'triggered_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tableSync(): BelongsTo
    {
        return $this->belongsTo(AirtableTableSync::class, 'table_sync_id');
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(array $errors): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_log' => $errors,
        ]);
    }
}
