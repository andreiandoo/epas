<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessExchangeRate extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'from_currency', 'to_currency',
        'rate', 'markup_rate', 'valid_from', 'valid_until', 'source', 'meta',
    ];

    protected $casts = [
        'rate'        => 'decimal:6',
        'markup_rate' => 'decimal:6',
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
        'meta'        => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }

    /**
     * Get the current valid exchange rate for a currency pair.
     */
    public static function getCurrentRate(int $editionId, string $from, string $to): ?self
    {
        return static::where('festival_edition_id', $editionId)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Convert an amount using this rate (with markup).
     */
    public function convert(int $amountCents): int
    {
        return (int) round($amountCents * $this->markup_rate);
    }
}
