<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArtistGenre extends Model
{
    use Translatable;

    protected $table = 'artist_genres';

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'slug', 'parent_id', 'description'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

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
