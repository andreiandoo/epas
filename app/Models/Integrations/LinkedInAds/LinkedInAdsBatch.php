<?php

namespace App\Models\Integrations\LinkedInAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedInAdsBatch extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_count',
        'status',
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
        return $this->belongsTo(LinkedInAdsConnection::class, 'connection_id');
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
}
