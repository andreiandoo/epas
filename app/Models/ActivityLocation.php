<?php

namespace App\Models;

use App\Support\Translatable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An operator's location in the activities module (bilete.online), e.g.
 * "Rezervația naturală Sfânta Ana". Its products (activities.location_id) are
 * access tickets, experiences and packages.
 *
 * Kept apart from the shared `venues` table on purpose: venues are shared by
 * every marketplace, a location belongs to one marketplace and one operator.
 */
class ActivityLocation extends Model
{
    use SoftDeletes, Translatable;

    public const REVIEW_DRAFT    = 'draft';
    public const REVIEW_PENDING  = 'pending';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    public const DAY_KEYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    public array $translatable = ['name', 'subtitle', 'short_description', 'description', 'rules'];

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'venue_id',
        'marketplace_city_id',
        'marketplace_category_id',
        'slug',
        'name',
        'subtitle',
        'short_description',
        'description',
        'address',
        'latitude',
        'longitude',
        'google_maps_url',
        'phone',
        'email',
        'website_url',
        'cover_image_url',
        'gallery',
        'facilities',
        'rules',
        'seasons',
        'closed_dates',
        'max_advance_days',
        'display_categories',
        'lodging',
        'faqs',
        'seo',
        'review_status',
        'is_published',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected $casts = [
        'name' => 'array',
        'subtitle' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'rules' => 'array',
        'gallery' => 'array',
        'facilities' => 'array',
        'seasons' => 'array',
        'closed_dates' => 'array',
        'display_categories' => 'array',
        'lodging' => 'array',
        'faqs' => 'array',
        'seo' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'max_advance_days' => 'integer',
        'is_published' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCity::class, 'marketplace_city_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** Access tickets, experiences and packages sold at this location. */
    public function products(): HasMany
    {
        return $this->hasMany(Activity::class, 'location_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_published', true)->where('review_status', self::REVIEW_APPROVED);
    }

    public function isClosedOn(CarbonInterface $date): bool
    {
        return in_array($date->format('Y-m-d'), (array) ($this->closed_dates ?? []), true);
    }

    /**
     * The season covering a date. Seasons are "MM-DD" ranges repeated every
     * year; one may wrap the new year (11-01 → 03-31).
     */
    public function seasonFor(CarbonInterface $date): ?array
    {
        $md = $date->format('m-d');
        foreach ((array) ($this->seasons ?? []) as $season) {
            $start = $season['start'] ?? null;
            $end   = $season['end'] ?? null;
            if (!$start || !$end) {
                continue;
            }
            $inside = $start <= $end
                ? ($md >= $start && $md <= $end)
                : ($md >= $start || $md <= $end);
            if ($inside) {
                return $season;
            }
        }
        return null;
    }

    /**
     * Opening hours on a date from the location's seasons:
     * ['open' => 'H:i:s', 'close' => 'H:i:s', 'last_entry' => 'H:i:s'|null],
     * or null when closed. No seasons at all = no hours defined (null).
     */
    public function hoursOn(CarbonInterface $date): ?array
    {
        if ($this->isClosedOn($date)) {
            return null;
        }
        $season = $this->seasonFor($date);
        if (!$season) {
            return null;
        }
        $day = ($season['schedule'] ?? [])[self::DAY_KEYS[(int) $date->dayOfWeekIso]] ?? null;
        if (!is_array($day) || empty($day['open']) || empty($day['close'])) {
            return null;
        }
        return [
            'open'       => self::time($day['open']),
            'close'      => self::time($day['close']),
            'last_entry' => !empty($season['last_entry']) ? self::time($season['last_entry']) : null,
            'season'     => $season['name'] ?? null,
        ];
    }

    public static function time(string $value): string
    {
        return strlen($value) === 5 ? $value . ':00' : substr($value, 0, 8);
    }

    public static function uniqueSlug(string $base, int $marketplaceClientId, ?int $ignoreId = null): string
    {
        $root = Str::slug($base) ?: 'locatie';
        $slug = $root;
        $n = 2;
        while (static::withTrashed()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = $root . '-' . $n++;
        }
        return $slug;
    }
}
