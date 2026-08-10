<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One share act, plus what it brought back (clicks/installs/conversions).
 */
class ShortShare extends Model
{
    protected $fillable = [
        'short_id',
        'sharer_customer_id',
        'channel',
        'token',
        'referral_code',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'installs' => 'integer',
        'conversions' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $share) {
            $share->token ??= Str::lower(Str::random(16));
        });
    }

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'sharer_customer_id');
    }
}
