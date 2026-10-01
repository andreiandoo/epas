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
        return 'LOT-' . $this->id;
    }
}
