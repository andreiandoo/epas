<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A WebVTT subtitle track for one short, in one language (B6).
 */
class ShortCaption extends Model
{
    protected $fillable = [
        'short_id',
        'language',
        'vtt_path',
        'auto_generated',
    ];

    protected $casts = [
        'auto_generated' => 'boolean',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->vtt_path) {
            return null;
        }

        if (str_starts_with($this->vtt_path, 'http://') || str_starts_with($this->vtt_path, 'https://')) {
            return $this->vtt_path;
        }

        return Storage::disk('public')->url($this->vtt_path);
    }
}
