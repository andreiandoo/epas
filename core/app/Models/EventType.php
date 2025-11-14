<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventType extends Model
{
    protected $table = 'event_types';

    protected $fillable = [
        'name', 'slug', 'parent_id', 'description',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            Event::class,
            'event_event_type',
            'event_type_id',
            'event_id'
        );
    }

    public function parent()
    {
        return $this->belongsTo(EventType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(EventType::class, 'parent_id');
    }

    public function allowedEventGenres()
    {
        return $this->belongsToMany(
            \App\Models\EventGenre::class,
            'event_type_event_genre',
            'event_type_id',
            'event_genre_id'
        );
    }
}
