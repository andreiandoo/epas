<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateLink extends Model
{
    protected $fillable = [
        'affiliate_id',
        'slug',
        'code',
        'landing_url',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
