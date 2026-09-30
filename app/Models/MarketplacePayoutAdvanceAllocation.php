<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cât dintr-un avans (MarketplacePayout cu source = 'advance') acoperă un
 * decont pe eveniment. Vezi MarketplacePayout::allocateFromAdvances().
 */
class MarketplacePayoutAdvanceAllocation extends Model
{
    protected $fillable = [
        'advance_payout_id',
        'payout_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'advance_payout_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }
}
