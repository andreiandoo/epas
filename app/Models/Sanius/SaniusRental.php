<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaniusRental extends Model
{
    public const DAMAGE_LEVELS = ['small', 'medium', 'large'];

    protected $table = 'sanius_rentals';

    protected $fillable = [
        'event_id', 'tab_id', 'sled_id', 'status', 'started_at', 'started_by_member_id', 'returned_at',
        'returned_by_member_id', 'closed_at', 'condition', 'damage_level', 'damage_photo_path',
        'tows_count', 'tows_included', 'tows_paid', 'amounts', 'is_test',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'returned_at' => 'datetime',
        'closed_at' => 'datetime',
        'tows_count' => 'integer',
        'tows_included' => 'integer',
        'tows_paid' => 'integer',
        'amounts' => 'array',
        'is_test' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SaniusTab::class, 'tab_id');
    }

    public function sled(): BelongsTo
    {
        return $this->belongsTo(SaniusSled::class, 'sled_id');
    }

    public function tows(): HasMany
    {
        return $this->hasMany(SaniusTow::class, 'rental_id');
    }
}
