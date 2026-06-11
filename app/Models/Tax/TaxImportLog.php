<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TaxImportLog extends Model
{
    protected $table = 'tax_import_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'filename',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // Helpers

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
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

    public function markAsFailed(array $errors = []): void
    {
        $this->update([
            'status' => 'failed',
            'errors' => array_merge($this->errors ?? [], $errors),
            'completed_at' => now(),
        ]);
    }

    public function incrementImported(int $count = 1): void
    {
        $this->increment('imported_rows', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('failed_rows', $count);
    }

    public function addError(int $row, string $message): void
    {
        $errors = $this->errors ?? [];
        $errors[] = ['row' => $row, 'message' => $message];
        $this->update(['errors' => $errors]);
    }

    public function getSuccessRate(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }
        return ($this->imported_rows / $this->total_rows) * 100;
    }

    public function getDuration(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $diff = $this->started_at->diff($end);

        if ($diff->h > 0) {
            return $diff->format('%h hr %i min %s sec');
        }
        if ($diff->i > 0) {
            return $diff->format('%i min %s sec');
        }
        return $diff->format('%s sec');
    }
}
