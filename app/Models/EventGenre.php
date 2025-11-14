<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventGenre extends Model
{
    protected $fillable = ['name','slug','description','tenant_id','parent_id'];

    public function parent() { return $this->belongsTo(static::class, 'parent_id'); }
    public function children() { return $this->hasMany(static::class, 'parent_id'); }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_event_genre');
    }

    public function allowedEventTypes()
    {
        return $this->belongsToMany(
            \App\Models\EventType::class,
            'event_type_event_genre',
            'event_genre_id',
            'event_type_id'
        );
    }
}
