<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArtistGenre extends Model
{
    protected $table = 'artist_genres';

    protected $fillable = ['name', 'slug', 'parent_id', 'description'];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'artist_artist_genre',
            'artist_genre_id',
            'artist_id'
        );
    }

    public function parent()
    {
        return $this->belongsTo(ArtistGenre::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ArtistGenre::class, 'parent_id');
    }
}
