<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistEpkRiderLead extends Model
{
    protected $fillable = [
        'artist_epk_variant_id',
        'name',
        'company',
        'email',
        'phone',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function artistEpkVariant(): BelongsTo
    {
        return $this->belongsTo(ArtistEpkVariant::class);
    }
}
