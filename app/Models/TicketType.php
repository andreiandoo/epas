<?php

namespace App\Models;

use App\Models\Seating\SeatingSection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TicketType extends Model
{
    use LogsActivity;
    protected $fillable = [
        'event_id',
        'name',
        'sku',
        'description',
        'currency',
        'quota_sold',
        'is_independent_stock',
        // Order quantity limits
        'min_per_order',
        'max_per_order',
        // Per-ticket commission settings (override organizer/marketplace defaults)
        'commission_type',   // null (inherit), 'percentage', 'fixed', or 'both'
        'commission_rate',   // Percentage rate (0-100)
        'commission_fixed',  // Fixed amount per ticket
        'commission_mode',   // null (inherit), 'included', or 'added_on_top'
        'bulk_discounts',
        'meta',
        // Real database columns
        'sales_start_at',
        'sales_end_at',
        'scheduled_at',
        'autostart_when_previous_sold_out',
        // Sale stock - limit tickets sold at sale price
        'sale_stock',
        'sale_stock_sold',
        // Active until - auto mark as soldout when date is reached
        'active_until',
        // Virtual fields (handled by mutators)
        'price_max',
        'price',
        'capacity',
        'is_active',
        // Refund eligibility
        'is_refundable',
        // Entry ticket flag (for mobile POS filtering)
        'is_entry_ticket',
        // Admin internal notes
        'admin_notes',
        // Declarable flag - included in cerere avizare documents
        'is_declarable',
        // Subscription flag - true if this ticket type is an abonament
        'is_subscription',
        // Multiplier for quantity increment/decrement step
        'multiplier',
        // Series fields for ticket numbering
        'series_start',
        'series_end',
        'event_series',
        // Sort order for drag & drop reordering
        'sort_order',
        // Color for seating map visualization
        'color',
        // Single-day ticket: valid only on this date (for range events)
        'valid_date',
        // Ticket grouping (e.g. "Bilete Acces", "Camping", "Parcari")
        'ticket_group',
        // Perks/conditions list (JSON array of strings)
        'perks',
        // Leisure venue fields
        'daily_capacity',
        'is_parking',
        'requires_vehicle_info',
        // Leisure venue: care societate a organizatorului emite factura — 'primary' | 'secondary' (NULL = primary)
        'issuing_company',
        // Leisure venue: categorie serviciu — access | parking | rental | activity | extra (NULL = legacy, tratat ca 'access')
        'service_category',
        // Leisure venue: durata serviciu in minute (relevant pentru parking, rental)
        'service_duration_minutes',
        // Leisure venue: descriere produs WYSIWYG (HTML) si conditii utilizare
        'product_description',
        'usage_terms',
        // Leisure venue: serviciul poate fi cumparat doar cu un bilet acces valid pe aceeasi zi
        'requires_access_ticket',
        // Leisure tenant (E1): variante durata pentru rentals + pricing rules pe zi + sezoane
        'leisure_duration_variants',
        'leisure_pricing_rules',
        'leisure_seasons',
        'leisure_is_overtime_chargeable',
        'leisure_overtime_surcharge_cents',
        'leisure_overtime_interval_minutes',
        // Leisure tenant (E5): emitent fiscal per-produs (multi-society)
        'tenant_tax_registry_id',
        // Leisure tenant (E6): prețuri per canal de vânzare
        'channel_pricing',
        // Leisure tenant: capacity & schedule defaults (E2 refactor)
        'leisure_default_daily_capacity',
        'leisure_schedule_open_time',
        'leisure_schedule_close_time',
        'leisure_schedule_days',
        'leisure_slot_duration_minutes',
    ];

    protected $casts = [
        'meta'           => 'array',
        'bulk_discounts' => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
        'scheduled_at'   => 'datetime',
        'active_until'   => 'datetime',
        'autostart_when_previous_sold_out' => 'boolean',
        'is_refundable'  => 'boolean',
        'is_entry_ticket' => 'boolean',
        'is_declarable' => 'boolean',
        'is_subscription' => 'boolean',
        'is_parking' => 'boolean',
        'requires_vehicle_info' => 'boolean',
        'is_independent_stock' => 'boolean',
        'requires_access_ticket' => 'boolean',
        'service_duration_minutes' => 'integer',
        'valid_date'     => 'date',
        'min_per_order'    => 'integer',
        'max_per_order'    => 'integer',
        'commission_rate'  => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'perks' => 'array',
        // Leisure tenant (E1)
        'leisure_duration_variants' => 'array',
        'leisure_pricing_rules' => 'array',
        'leisure_seasons' => 'array',
        'leisure_is_overtime_chargeable' => 'boolean',
        'leisure_overtime_surcharge_cents' => 'integer',
        'leisure_overtime_interval_minutes' => 'integer',
        // Leisure tenant (E6) — { online: 1000, pos_fixed: 1200, pos_mobile: 1100 }
        'channel_pricing' => 'array',
        'leisure_default_daily_capacity' => 'integer',
        'leisure_schedule_open_time' => 'datetime:H:i:s',
        'leisure_schedule_close_time' => 'datetime:H:i:s',
        'leisure_schedule_days' => 'array',
        'leisure_slot_duration_minutes' => 'integer',
    ];

    protected $appends = [
        'available_quantity',
        'price_max',
        'price',
        'capacity',
        'is_active',
        'display_price',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeByServiceCategory($query, string $category)
    {
        if ($category === 'access') {
            return $query->where(function ($q) {
                $q->whereNull('service_category')->orWhere('service_category', 'access');
            });
        }

        return $query->where('service_category', $category);
    }

    public function getEffectiveServiceCategoryAttribute(): string
    {
        return $this->service_category ?: 'access';
    }

    /**
     * Care societate a organizatorului emite facturi pentru biletele de acest tip.
     * 'primary' = societatea principala a organizatorului (default).
     * 'secondary' = a doua societate (doar daca organizer.has_secondary_issuer = true).
     */
    public function getEffectiveIssuingCompanyAttribute(): string
    {
        return $this->issuing_company ?: 'primary';
    }

    /**
     * Seating sections assigned to this ticket type
     */
    public function seatingSections(): BelongsToMany
    {
        return $this->belongsToMany(
            SeatingSection::class,
            'ticket_type_seating_sections',
            'ticket_type_id',
            'seating_section_id'
        )->withTimestamps();
    }

    public function seatingRows(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Seating\SeatingRow::class,
            'ticket_type_seating_rows',
            'ticket_type_id',
            'seating_row_id'
        )->withTimestamps();
    }

    /**
     * Get total capacity from assigned seating sections
     */
    public function getSeatingCapacityAttribute(): int
    {
        return $this->seatingSections->sum(function ($section) {
            return $section->total_seats;
        });
    }

    /**
     * Check if this ticket type has seating assigned (rows or sections)
     */
    public function hasSeatingAssigned(): bool
    {
        return $this->seatingRows()->exists() || $this->seatingSections()->exists();
    }

    /**
     * Boot the model and add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate SKU if empty
        static::saving(function ($model) {
            if (empty($model->sku) && !empty($model->name)) {
                $model->sku = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($model->name, '-'));
            }
        });

        // Refuse deletion if any order_item or ticket still references this type.
        // The DB also enforces this via the FK RESTRICT constraint, but raising a
        // readable exception here surfaces a clear admin-facing message before
        // Postgres returns a "foreign key violation" that Filament would render
        // as a generic error.
        static::deleting(function ($model) {
            $orderItemsCount = \DB::table('order_items')->where('ticket_type_id', $model->id)->count();
            $ticketsCount = \DB::table('tickets')->where('ticket_type_id', $model->id)->count();

            if ($orderItemsCount === 0 && $ticketsCount === 0) {
                return;
            }

            $name = is_array($model->name)
                ? ($model->name['ro'] ?? $model->name['en'] ?? reset($model->name) ?? '')
                : ($model->name ?? '');

            $comenzi = $orderItemsCount === 1 ? '1 comandă' : $orderItemsCount . ' comenzi';
            $bilete = $ticketsCount === 1 ? '1 bilet vândut' : $ticketsCount . ' bilete vândute';

            throw new \RuntimeException(sprintf(
                'Nu poți șterge tipul de bilet „%s" — există %s și %s pe acest tip. Anulează sau rambursează mai întâi comenzile asociate.',
                $name,
                $comenzi,
                $bilete
            ));
        });

        // Normalize series_start/series_end to use the ticket type id in
        // the identifier slot. Runs on every save (create + update) so it
        // catches:
        //   - NEW rows where the Filament form generated series with the
        //     SKU fallback because $get('id') was null during repeater
        //     interaction (id only becomes known after the model is saved).
        //   - Legacy rows that pre-date the id-first policy (e.g.
        //     AMB-4402-ACCES-00001 → AMB-4402-{id}-00001).
        // The save listener is also what fills empty values on first save.
        // Uses saveQuietly to avoid re-entry through the saved event.
        static::saved(function ($model) {
            $event = $model->event;
            $eventSeries = $event?->event_series;
            if (!$eventSeries || empty($model->id)) {
                return;
            }

            $prefix = $eventSeries . '-';
            $idStr = (string) $model->id;
            $capacity = (int) ($model->quota_total ?? 0);
            if ($capacity === -1) {
                // -1 = unlimited per the rest of the codebase; fall back to a
                // generous default for the series_end so the value is filled.
                $capacity = 1000;
            }

            $patched = [];
            foreach (['series_start', 'series_end'] as $field) {
                $value = $model->{$field};

                if (empty($value)) {
                    // Fill missing — only if we know the capacity for end.
                    if ($field === 'series_start') {
                        $patched[$field] = $prefix . $idStr . '-00001';
                    } elseif ($capacity > 0) {
                        $patched[$field] = $prefix . $idStr . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT);
                    }
                    continue;
                }

                // Existing — only touch if it matches our generated pattern
                // ({event_series}-{identifier}-{NNNNN}) AND the identifier
                // slot isn't already the id. Custom-formatted series the
                // operator entered manually are left alone.
                if (!str_starts_with($value, $prefix)) {
                    continue;
                }
                $tail = substr($value, strlen($prefix));
                if (!preg_match('/^(.+)-(\d+)$/', $tail, $m)) {
                    continue;
                }
                if ($m[1] === $idStr) {
                    continue; // already normalized
                }
                $patched[$field] = $prefix . $idStr . '-' . $m[2];
            }

            if (!empty($patched)) {
                foreach ($patched as $f => $v) {
                    $model->{$f} = $v;
                }
                $model->saveQuietly();
            }
        });
    }

    /**
     * Scope: only ticket types that are currently active AND not past their active_until date
     */
    public function scopeEffectivelyActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('active_until')
                  ->orWhere('active_until', '>', now('Europe/Bucharest'));
            });
    }

    // Getters
    public function getPriceMaxAttribute()
    {
        return $this->price_cents ? $this->price_cents / 100 : 0;
    }

    public function getPriceAttribute()
    {
        // Return sale_price if set, otherwise null
        return $this->sale_price_cents ? $this->sale_price_cents / 100 : null;
    }

    public function getCapacityAttribute()
    {
        return $this->quota_total;
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    /**
     * Accessor: intercept sale_price_cents reads to enforce sales_start_at / sales_end_at.
     * If the sale window hasn't opened yet or has closed, returns null.
     * All code that reads $tt->sale_price_cents will automatically respect sale dates.
     */
    public function getSalePriceCentsAttribute($value): ?int
    {
        if (! $value || $value <= 0) {
            return null;
        }

        $now = now('Europe/Bucharest');

        if ($this->attributes['sales_start_at'] ?? null) {
            $start = \Carbon\Carbon::parse($this->attributes['sales_start_at']);
            if ($now->lt($start)) {
                return null;
            }
        }

        if ($this->attributes['sales_end_at'] ?? null) {
            $end = \Carbon\Carbon::parse($this->attributes['sales_end_at']);
            if ($now->gt($end)) {
                return null;
            }
        }

        return (int) $value;
    }

    /**
     * Get raw sale_price_cents from DB without date enforcement (for admin forms).
     */
    public function getRawSalePriceCents(): ?int
    {
        return $this->attributes['sale_price_cents'] ?? null;
    }

    /**
     * Get the effective sale price in cents (alias for accessor, explicit call).
     * Returns null if no active sale.
     */
    public function getEffectiveSalePriceCents(): ?int
    {
        if (! $this->sale_price_cents || $this->sale_price_cents <= 0) {
            return null;
        }

        $now = now('Europe/Bucharest');

        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return null;
        }

        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return null;
        }

        return (int) $this->sale_price_cents;
    }

    /**
     * Get the effective display price (sale price if active, otherwise gross price)
     * This is the price that should be shown to customers
     */
    public function getDisplayPriceAttribute()
    {
        $effectiveSale = $this->getEffectiveSalePriceCents();
        if ($effectiveSale) {
            return $effectiveSale / 100;
        }
        return $this->price_cents ? $this->price_cents / 100 : 0;
    }

    /**
     * Get effective commission settings for this ticket type.
     * Falls back to event > organizer > marketplace defaults if not set.
     *
     * @param float|null $defaultRate Default percentage rate from organizer/marketplace
     * @param string|null $defaultMode Default commission mode from organizer/marketplace
     * @return array{type: string, rate: float, fixed: float, mode: string}
     */
    public function getEffectiveCommission(?float $defaultRate = 5.0, ?string $defaultMode = 'included'): array
    {
        // If ticket type has its own commission settings, use them
        if ($this->commission_type) {
            return [
                'type' => $this->commission_type,
                'rate' => (float) ($this->commission_rate ?? 0),
                'fixed' => (float) ($this->commission_fixed ?? 0),
                'mode' => $this->commission_mode ?? $defaultMode ?? 'included',
            ];
        }

        // Fall back to defaults (percentage type)
        return [
            'type' => 'percentage',
            'rate' => $defaultRate ?? 5.0,
            'fixed' => 0,
            'mode' => $defaultMode ?? 'included',
        ];
    }

    /**
     * Calculate commission amount for a given base price
     *
     * @param float $basePrice The ticket base price
     * @param float|null $defaultRate Default percentage rate
     * @param string|null $defaultMode Default commission mode
     * @return float Commission amount
     */
    public function calculateCommission(float $basePrice, ?float $defaultRate = 5.0, ?string $defaultMode = 'included'): float
    {
        $commission = $this->getEffectiveCommission($defaultRate, $defaultMode);
        $amount = 0;

        switch ($commission['type']) {
            case 'percentage':
                $amount = $basePrice * ($commission['rate'] / 100);
                break;
            case 'fixed':
                $amount = $commission['fixed'];
                break;
            case 'both':
                $amount = ($basePrice * ($commission['rate'] / 100)) + $commission['fixed'];
                break;
        }

        return round($amount, 2);
    }

    // Setters
    public function setPriceMaxAttribute($value)
    {
        $this->attributes['price_cents'] = $value ? (int)($value * 100) : 0;
    }

    public function setPriceAttribute($value)
    {
        // Save to sale_price_cents (can be null for no sale)
        $this->attributes['sale_price_cents'] = $value ? (int)($value * 100) : null;
    }

    public function setCapacityAttribute($value)
    {
        $this->attributes['quota_total'] = ($value === null || $value === '') ? -1 : (int) $value;
    }

    public function setIsActiveAttribute($value)
    {
        $this->attributes['status'] = $value ? 'active' : 'hidden';
    }

    // Accessor for available_quantity (computed from quota_total - quota_sold)
    // quota_total = -1 means unlimited
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quota_total < 0 ? PHP_INT_MAX : max(0, $this->quota_total - ($this->quota_sold ?? 0))
        );
    }

    /**
     * Check if this ticket type has unlimited stock (-1 = unlimited)
     */
    public function isUnlimited(): bool
    {
        return $this->quota_total < 0;
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price_cents', 'quota_total', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Ticket Type {$eventName}")
            ->useLogName('tenant');
    }

    /**
     * Add tenant_id to activity properties for scoping (via event relationship)
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $tenantId = $this->event?->tenant_id;
        if ($tenantId) {
            $activity->properties = $activity->properties->put('tenant_id', $tenantId);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Leisure tenant helpers (E1)                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Does this ticket type behave as a rental — has duration variants
     * or is categorized as a rental/activity service?
     */
    public function isLeisureRental(): bool
    {
        return in_array($this->service_category, ['rental', 'activity'], true)
            && filled($this->leisure_duration_variants);
    }

    /**
     * Normalized duration variants collection. Each entry has:
     *   duration_minutes (int|null), label (string|null), price_multiplier (float)
     * Empty array if none configured.
     */
    public function getDurationVariantsCollection(): \Illuminate\Support\Collection
    {
        return collect($this->leisure_duration_variants ?? [])
            ->map(fn ($v) => (object) array_merge([
                'duration_minutes' => null,
                'label' => null,
                'price_multiplier' => 1.0,
            ], (array) $v));
    }

    /**
     * Default duration in minutes for this rental ticket.
     * Falls back to service_duration_minutes if no variants exist.
     */
    public function getDefaultDurationMinutes(): ?int
    {
        $variants = $this->getDurationVariantsCollection();
        if ($variants->isNotEmpty()) {
            return $variants->first()->duration_minutes;
        }
        return $this->service_duration_minutes;
    }
}
