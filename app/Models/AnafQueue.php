<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnafQueue extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'anaf_queue';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payload_ref',
        'status',
        'error_message',
        'anaf_ids',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'response_data',
        'xml_hash',
        'submitted_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'anaf_ids' => 'array',
        'response_data' => 'array',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_QUEUED = 'queued';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ERROR = 'error';

    /**
     * Check if entry can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts
            && in_array($this->status, [self::STATUS_QUEUED, self::STATUS_ERROR]);
    }

    /**
     * Check if entry is in final state
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED]);
    }

    /**
     * Mark as submitted to ANAF
     */
    public function markAsSubmitted(string $remoteId, ?array $responseData = null): void
    {
        $anafIds = $this->anaf_ids ?? [];
        $anafIds['remote_id'] = $remoteId;

        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'anaf_ids' => $anafIds,
            'response_data' => $responseData,
            'submitted_at' => now(),
            'last_attempt_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark as accepted by ANAF
     */
    public function markAsAccepted(?array $responseData = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'response_data' => $responseData,
            'accepted_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as rejected by ANAF
     */
    public function markAsRejected(string $reason, ?array $responseData = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'error_message' => $reason,
            'response_data' => $responseData,
            'rejected_at' => now(),
        ]);
    }

    /**
     * Mark as error (technical failure, not ANAF rejection)
     */
    public function markAsError(string $error): void
    {
        $nextRetry = null;
        if ($this->canRetry()) {
            // Exponential backoff: 5min, 15min, 30min, 1h, 2h
            $delays = [5, 15, 30, 60, 120];
            $delayMinutes = $delays[$this->attempts] ?? 120;
            $nextRetry = now()->addMinutes($delayMinutes);
        }

        $this->update([
            'status' => self::STATUS_ERROR,
            'error_message' => $error,
            'last_attempt_at' => now(),
            'attempts' => $this->attempts + 1,
            'next_retry_at' => $nextRetry,
        ]);
    }

    /**
     * Reset for manual retry
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => self::STATUS_QUEUED,
            'error_message' => null,
            'next_retry_at' => now(),
        ]);
    }

    /**
     * Store ANAF response artifacts
     */
    public function storeAnafArtifacts(array $artifacts): void
    {
        $anafIds = $this->anaf_ids ?? [];
        $anafIds = array_merge($anafIds, $artifacts);

        $this->update([
            'anaf_ids' => $anafIds,
        ]);
    }

    /**
     * Scope: Ready for processing (queued or error with retry time passed)
     */
    public function scopeReadyForProcessing($query)
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_ERROR])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->where('attempts', '<', \DB::raw('max_attempts'))
            ->orderBy('created_at', 'asc');
    }

    /**
     * Scope: Submitted and awaiting poll
     */
    public function scopeAwaitingPoll($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED)
            ->orderBy('submitted_at', 'asc');
    }

    /**
     * Scope: By tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get remote ID from anaf_ids
     */
    public function getRemoteId(): ?string
    {
        return $this->anaf_ids['remote_id'] ?? null;
    }
}
