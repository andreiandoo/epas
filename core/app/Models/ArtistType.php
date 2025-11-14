<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArtistType extends Model
{
    protected $table = 'artist_types';

    protected $fillable = ['name', 'slug', 'parent_id', 'description'];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'artist_artist_type',
            'artist_type_id',
            'artist_id'
        );
    }

    public function parent()
    {
        return $this->belongsTo(ArtistType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ArtistType::class, 'parent_id');
    }
}
