<?php

namespace App\Models\Coupon;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot de coduri generate în masă — vezi BulkCouponCodeGenerator.
 */
class CouponCodeBatch extends Model
{
    protected $table = 'coupon_code_batches';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'ticket_type_id',
        'campaign_id',
        'quantity',
        'code_length',
        'prefix',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /** Tabelul există? (codul poate ajunge pe server înaintea migrării) */
    public static function enabled(): bool
    {
        static $enabled = null;

        return $enabled ??= \Illuminate\Support\Facades\Schema::hasTable('coupon_code_batches');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(CouponCode::class, 'batch_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    public function getLabelAttribute(): string
    {
        return static::keyFor((int) $this->id);
    }

    /**
     * Cheia fiscală a lotului: discount_code al seriei unice a lotului
     * (EventTicketTypePromoSeries) și „codul" sub care se grupează vânzările
     * făcute cu oricare dintre codurile lotului în documente și decont.
     */
    public static function keyFor(int $batchId): string
    {
        return 'LOT-' . $batchId;
    }

    /**
     * Cod de reducere → cheia lotului, pentru codurile care fac parte dintr-un
     * lot. Codurile care nu sunt în lot lipsesc din rezultat. Chei uppercase.
     *
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    public static function mapCodesToBatchKeys(?int $marketplaceClientId, array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $codes))));
        if (empty($codes) || !static::enabled()) {
            return [];
        }

        $map = [];
        foreach (array_chunk($codes, 1000) as $chunk) {
            CouponCode::query()
                ->when($marketplaceClientId, fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId))
                ->whereNotNull('batch_id')
                ->whereIn('code', $chunk)
                ->get(['code', 'batch_id'])
                ->each(function ($c) use (&$map) {
                    $map[strtoupper((string) $c->code)] = static::keyFor((int) $c->batch_id);
                });
        }

        return $map;
    }
}
