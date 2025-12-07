<?php

namespace App\Models\Integrations\GoogleAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsUploadBatch extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_count',
        'status',
        'job_id',
        'successful_count',
        'failed_count',
        'errors',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'completed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConnection::class, 'connection_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->conversion_count === 0) {
            return 0;
        }
        return ($this->successful_count ?? 0) / $this->conversion_count * 100;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
