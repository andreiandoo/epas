<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalProgram extends Model
{
    use Translatable;

    public array $translatable = ['title', 'director_notes', 'dramaturg_notes', 'synopsis', 'program_content'];

    protected $fillable = [
        'tenant_id',
        'event_id',
        'repertoire_id',
        'season_id',
        'title',
        'director_notes',
        'dramaturg_notes',
        'synopsis',
        'program_content',
        'creative_team',
        'cast_list',
        'program_pieces',
        'intermission_count',
        'intermission_details',
        'sponsors',
        'has_surtitles',
        'surtitle_languages',
        'cover_image_url',
        'pdf_url',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'title' => 'array',
        'director_notes' => 'array',
        'dramaturg_notes' => 'array',
        'synopsis' => 'array',
        'program_content' => 'array',
        'creative_team' => 'array',
        'cast_list' => 'array',
        'program_pieces' => 'array',
        'intermission_details' => 'array',
        'sponsors' => 'array',
        'surtitle_languages' => 'array',
        'has_surtitles' => 'boolean',
        'is_published' => 'boolean',
        'intermission_count' => 'integer',
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function repertoire(): BelongsTo
    {
        return $this->belongsTo(Repertoire::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Publish the program.
     */
    public function publish(): bool
    {
        return $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
