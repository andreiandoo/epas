<?php

namespace App\Models\Integrations\GoogleSheets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetsSyncJob extends Model
{
    protected $fillable = [
        'spreadsheet_id',
        'sync_type',
        'data_type',
        'status',
        'rows_processed',
        'rows_created',
        'rows_updated',
        'rows_failed',
        'filters',
        'started_at',
        'completed_at',
        'error_log',
        'triggered_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'error_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsSpreadsheet::class, 'spreadsheet_id');
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

    public function incrementProcessed(int $created = 0, int $updated = 0, int $failed = 0): void
    {
        $this->increment('rows_processed', $created + $updated + $failed);
        if ($created > 0) $this->increment('rows_created', $created);
        if ($updated > 0) $this->increment('rows_updated', $updated);
        if ($failed > 0) $this->increment('rows_failed', $failed);
    }
}
