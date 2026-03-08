<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repertoire extends Model
{
    use Translatable;

    protected $table = 'repertoire';

    public array $translatable = ['title', 'description', 'short_description'];

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'description',
        'short_description',
        'duration_minutes',
        'genre',
        'age_rating',
        'is_premiere',
        'premiere_date',
        'director',
        'choreographer',
        'conductor',
        'set_designer',
        'costume_designer',
        'lighting_designer',
        'librettist',
        'composer',
        'program_pieces',
        'poster_url',
        'hero_image_url',
        'gallery',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'program_pieces' => 'array',
        'gallery' => 'array',
        'meta' => 'array',
        'is_premiere' => 'boolean',
        'is_active' => 'boolean',
        'premiere_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Events (representations) of this repertoire piece.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'repertoire_id');
    }

    /**
     * Count total performances across all events for this piece.
     */
    public function getTotalPerformancesCountAttribute(): int
    {
        return $this->events()->count();
    }

    /**
     * Get display name from translatable title.
     */
    public function getDisplayTitleAttribute(): string
    {
        $title = $this->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? reset($title) ?? '';
        }
        return (string) ($title ?? '');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
