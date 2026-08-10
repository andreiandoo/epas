<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One cover under test (B10).
 */
class ShortPosterVariant extends Model
{
    protected $fillable = [
        'short_id',
        'poster_path',
        'label',
        'is_winner',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'is_winner' => 'boolean',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->poster_path) {
            return null;
        }

        return Storage::disk('public')->url($this->poster_path);
    }

    public function ctr(): float
    {
        return $this->impressions > 0 ? $this->clicks / $this->impressions : 0.0;
    }
}
