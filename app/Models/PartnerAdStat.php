<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily impressions and clicks a partner reports for one ad.
 */
class PartnerAdStat extends Model
{
    protected $fillable = [
        'partner_ad_id',
        'date',
        'impressions',
        'clicks',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(PartnerAd::class, 'partner_ad_id');
    }
}
