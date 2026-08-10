<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A viewer report against a short — the entry point of the takedown queue (§14, D7).
 */
class ShortReport extends Model
{
    public const REASONS = [
        'inappropriate',
        'copyright',
        'misleading',
        'spam',
        'other',
    ];

    protected $fillable = [
        'short_id',
        'marketplace_customer_id',
        'reason',
        'detail',
        'status',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
