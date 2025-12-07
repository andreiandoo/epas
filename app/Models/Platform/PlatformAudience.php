<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAudience extends Model
{
    protected $fillable = [
        'platform_ad_account_id',
        'platform_audience_id',
        'name',
        'description',
        'audience_type',
        'lookalike_source_type',
        'lookalike_source_audience_id',
        'lookalike_percentage',
        'lookalike_country',
        'segment_rules',
        'member_count',
        'matched_count',
        'is_auto_sync',
        'sync_frequency',
        'last_synced_at',
        'status',
        'platform_status',
        'error_message',
    ];

    protected $casts = [
        'segment_rules' => 'array',
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
        'member_count' => 'integer',
        'matched_count' => 'integer',
        'lookalike_percentage' => 'integer',
    ];

    // Audience types
    const TYPE_ALL_CUSTOMERS = 'all_customers';
    const TYPE_PURCHASERS = 'purchasers';
    const TYPE_HIGH_VALUE = 'high_value';
    const TYPE_RECENT_VISITORS = 'recent_visitors';
    const TYPE_CART_ABANDONERS = 'cart_abandoners';
    const TYPE_ENGAGED_USERS = 'engaged_users';
    const TYPE_INACTIVE = 'inactive';
    const TYPE_CUSTOM = 'custom';
    const TYPE_LOOKALIKE = 'lookalike';

    const AUDIENCE_TYPES = [
        self::TYPE_ALL_CUSTOMERS => 'All Customers',
        self::TYPE_PURCHASERS => 'All Purchasers',
        self::TYPE_HIGH_VALUE => 'High-Value Customers',
        self::TYPE_RECENT_VISITORS => 'Recent Visitors',
        self::TYPE_CART_ABANDONERS => 'Cart Abandoners',
        self::TYPE_ENGAGED_USERS => 'Engaged Users',
        self::TYPE_INACTIVE => 'Inactive Customers',
        self::TYPE_CUSTOM => 'Custom Segment',
        self::TYPE_LOOKALIKE => 'Lookalike Audience',
    ];

    // Lookalike source types
    const LOOKALIKE_SOURCE_AUDIENCE = 'audience';
    const LOOKALIKE_SOURCE_PURCHASERS = 'purchasers';
    const LOOKALIKE_SOURCE_HIGH_VALUE = 'high_value';
    const LOOKALIKE_SOURCE_TOP_SPENDERS = 'top_spenders';
    const LOOKALIKE_SOURCE_ENGAGED = 'engaged';

    const LOOKALIKE_SOURCE_TYPES = [
        self::LOOKALIKE_SOURCE_AUDIENCE => 'Existing Audience',
        self::LOOKALIKE_SOURCE_PURCHASERS => 'All Purchasers',
        self::LOOKALIKE_SOURCE_HIGH_VALUE => 'High-Value Customers (RFM 12+)',
        self::LOOKALIKE_SOURCE_TOP_SPENDERS => 'Top 10% Spenders',
        self::LOOKALIKE_SOURCE_ENGAGED => 'Highly Engaged (50+ score)',
    ];

    // Sync frequencies
    const SYNC_HOURLY = 'hourly';
    const SYNC_DAILY = 'daily';
    const SYNC_WEEKLY = 'weekly';
    const SYNC_MANUAL = 'manual';

    // Statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_SYNCING = 'syncing';
    const STATUS_ERROR = 'error';
    const STATUS_PAUSED = 'paused';

    public function platformAdAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAdAccount::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(PlatformAudienceMember::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAutoSync($query)
    {
        return $query->where('is_auto_sync', true);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('is_auto_sync', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere(function ($q2) {
                      $q2->where('sync_frequency', self::SYNC_HOURLY)
                         ->where('last_synced_at', '<', now()->subHour());
                  })
                  ->orWhere(function ($q2) {
                      $q2->where('sync_frequency', self::SYNC_DAILY)
                         ->where('last_synced_at', '<', now()->subDay());
                  })
                  ->orWhere(function ($q2) {
                      $q2->where('sync_frequency', self::SYNC_WEEKLY)
                         ->where('last_synced_at', '<', now()->subWeek());
                  });
            });
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('platform_ad_account_id', $accountId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('audience_type', $type);
    }

    // Status management
    public function markSyncing(): void
    {
        $this->update(['status' => self::STATUS_SYNCING]);
    }

    public function markSynced(int $memberCount, int $matchedCount): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'member_count' => $memberCount,
            'matched_count' => $matchedCount,
            'last_synced_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markError(string $error): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'error_message' => $error,
        ]);
    }

    // Query builders for segment rules
    public function getCustomersQuery()
    {
        $query = CoreCustomer::query();

        switch ($this->audience_type) {
            case self::TYPE_PURCHASERS:
                $query->where('total_orders', '>', 0);
                break;

            case self::TYPE_HIGH_VALUE:
                $query->where('total_spent', '>=', 500)
                      ->orWhere('rfm_score', '>=', 12);
                break;

            case self::TYPE_RECENT_VISITORS:
                $query->where('last_seen_at', '>=', now()->subDays(30));
                break;

            case self::TYPE_CART_ABANDONERS:
                $query->where('has_cart_abandoned', true)
                      ->where('last_cart_abandoned_at', '>=', now()->subDays(30));
                break;

            case self::TYPE_ENGAGED_USERS:
                $query->where('engagement_score', '>=', 50)
                      ->orWhere('total_pageviews', '>=', 10);
                break;

            case self::TYPE_INACTIVE:
                $query->where('last_seen_at', '<', now()->subDays(90));
                break;

            case self::TYPE_CUSTOM:
                $query = $this->applyCustomRules($query);
                break;
        }

        // Only include customers with hashable data
        $query->where(function ($q) {
            $q->whereNotNull('email_hash')
              ->orWhereNotNull('phone_hash');
        });

        return $query;
    }

    protected function applyCustomRules($query)
    {
        if (!$this->segment_rules) {
            return $query;
        }

        foreach ($this->segment_rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? '=';
            $value = $rule['value'] ?? null;

            if (!$field) continue;

            switch ($operator) {
                case '=':
                case '==':
                    $query->where($field, $value);
                    break;
                case '!=':
                case '<>':
                    $query->where($field, '!=', $value);
                    break;
                case '>':
                    $query->where($field, '>', $value);
                    break;
                case '>=':
                    $query->where($field, '>=', $value);
                    break;
                case '<':
                    $query->where($field, '<', $value);
                    break;
                case '<=':
                    $query->where($field, '<=', $value);
                    break;
                case 'contains':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
                case 'starts_with':
                    $query->where($field, 'like', $value . '%');
                    break;
                case 'ends_with':
                    $query->where($field, 'like', '%' . $value);
                    break;
                case 'in':
                    $query->whereIn($field, (array) $value);
                    break;
                case 'not_in':
                    $query->whereNotIn($field, (array) $value);
                    break;
                case 'is_null':
                    $query->whereNull($field);
                    break;
                case 'is_not_null':
                    $query->whereNotNull($field);
                    break;
                case 'between':
                    if (is_array($value) && count($value) >= 2) {
                        $query->whereBetween($field, [$value[0], $value[1]]);
                    }
                    break;
                case 'days_ago':
                    $query->where($field, '>=', now()->subDays((int) $value));
                    break;
            }
        }

        return $query;
    }

    // Helpers
    public function getTypeLabel(): string
    {
        return self::AUDIENCE_TYPES[$this->audience_type] ?? ucfirst($this->audience_type);
    }

    public function getPlatformName(): string
    {
        return $this->platformAdAccount?->getPlatformLabel() ?? 'Unknown';
    }

    public function getMatchRate(): float
    {
        if ($this->member_count === 0) {
            return 0;
        }
        return round(($this->matched_count / $this->member_count) * 100, 1);
    }

    public function needsSync(): bool
    {
        if (!$this->is_auto_sync) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        switch ($this->sync_frequency) {
            case self::SYNC_HOURLY:
                return $this->last_synced_at < now()->subHour();
            case self::SYNC_DAILY:
                return $this->last_synced_at < now()->subDay();
            case self::SYNC_WEEKLY:
                return $this->last_synced_at < now()->subWeek();
            default:
                return false;
        }
    }

    // Lookalike audience methods

    /**
     * Get the source audience for a lookalike audience
     */
    public function sourceAudience(): BelongsTo
    {
        return $this->belongsTo(self::class, 'lookalike_source_audience_id');
    }

    /**
     * Get lookalike audiences created from this audience
     */
    public function derivedLookalikes(): HasMany
    {
        return $this->hasMany(self::class, 'lookalike_source_audience_id');
    }

    /**
     * Check if this is a lookalike audience
     */
    public function isLookalike(): bool
    {
        return $this->audience_type === self::TYPE_LOOKALIKE;
    }

    /**
     * Get the seed customers query for lookalike audience creation
     */
    public function getLookalikeSeedQuery()
    {
        $query = CoreCustomer::query();

        switch ($this->lookalike_source_type) {
            case self::LOOKALIKE_SOURCE_AUDIENCE:
                // Use the source audience's customer query
                if ($this->sourceAudience) {
                    return $this->sourceAudience->getCustomersQuery();
                }
                break;

            case self::LOOKALIKE_SOURCE_PURCHASERS:
                $query->where('total_orders', '>', 0);
                break;

            case self::LOOKALIKE_SOURCE_HIGH_VALUE:
                $query->where('rfm_score', '>=', 12);
                break;

            case self::LOOKALIKE_SOURCE_TOP_SPENDERS:
                // Get top 10% by total spent
                $threshold = CoreCustomer::where('total_spent', '>', 0)
                    ->orderByDesc('total_spent')
                    ->limit(1)
                    ->offset((int) (CoreCustomer::where('total_spent', '>', 0)->count() * 0.1))
                    ->value('total_spent') ?? 1000;
                $query->where('total_spent', '>=', $threshold);
                break;

            case self::LOOKALIKE_SOURCE_ENGAGED:
                $query->where('engagement_score', '>=', 50);
                break;
        }

        // Only include customers with hashable data for matching
        $query->where(function ($q) {
            $q->whereNotNull('email_hash')
              ->orWhereNotNull('phone_hash');
        });

        return $query;
    }

    /**
     * Get seed customer count for lookalike preview
     */
    public function getLookalikeSeedCount(): int
    {
        return $this->getLookalikeSeedQuery()?->count() ?? 0;
    }

    /**
     * Build the lookalike configuration for platform API
     */
    public function buildLookalikeConfig(): array
    {
        return [
            'source_type' => $this->lookalike_source_type,
            'source_audience_id' => $this->lookalike_source_audience_id,
            'percentage' => $this->lookalike_percentage ?? 1,
            'country' => $this->lookalike_country,
            'seed_count' => $this->getLookalikeSeedCount(),
            'seed_customers' => $this->getLookalikeSeedQuery()
                ->select('email_hash', 'phone_hash', 'country_code')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Get lookalike percentage options based on platform
     */
    public static function getLookalikePercentageOptions(string $platform = null): array
    {
        // Facebook/Meta supports 1-10%
        // Google supports 1-10% (similar audiences)
        // TikTok supports 1-10%
        // LinkedIn supports narrow, balanced, broad

        return [
            1 => '1% (Most Similar)',
            2 => '2%',
            3 => '3%',
            4 => '4%',
            5 => '5% (Balanced)',
            6 => '6%',
            7 => '7%',
            8 => '8%',
            9 => '9%',
            10 => '10% (Broadest Reach)',
        ];
    }

    /**
     * Get available countries for lookalike targeting
     */
    public static function getLookalikeCountryOptions(): array
    {
        return [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'IN' => 'India',
            'SG' => 'Singapore',
        ];
    }

    /**
     * Get the display label for lookalike source
     */
    public function getLookalikeSourceLabel(): string
    {
        if ($this->lookalike_source_type === self::LOOKALIKE_SOURCE_AUDIENCE && $this->sourceAudience) {
            return $this->sourceAudience->name;
        }

        return self::LOOKALIKE_SOURCE_TYPES[$this->lookalike_source_type] ?? 'Unknown';
    }

    /**
     * Estimate lookalike audience reach
     */
    public function estimateLookalikeReach(): array
    {
        $seedCount = $this->getLookalikeSeedCount();
        $percentage = $this->lookalike_percentage ?? 1;

        // Rough estimates based on typical platform data
        $countryPopulations = [
            'US' => 330_000_000,
            'GB' => 67_000_000,
            'CA' => 38_000_000,
            'AU' => 26_000_000,
            'DE' => 84_000_000,
            'FR' => 67_000_000,
            'BR' => 214_000_000,
            'IN' => 1_400_000_000,
        ];

        $country = $this->lookalike_country ?? 'US';
        $population = $countryPopulations[$country] ?? 100_000_000;

        // Estimate ~70% internet penetration, ~60% social media usage
        $addressableMarket = (int) ($population * 0.7 * 0.6);
        $estimatedReach = (int) ($addressableMarket * ($percentage / 100));

        return [
            'seed_count' => $seedCount,
            'percentage' => $percentage,
            'country' => $country,
            'estimated_reach_min' => (int) ($estimatedReach * 0.8),
            'estimated_reach_max' => (int) ($estimatedReach * 1.2),
            'quality_indicator' => $seedCount >= 1000 ? 'high' : ($seedCount >= 100 ? 'medium' : 'low'),
            'recommendation' => $seedCount < 100
                ? 'Seed audience is small. Consider using a broader source for better results.'
                : ($seedCount >= 1000 ? 'Excellent seed size for quality matching.' : 'Good seed size.'),
        ];
    }

    /**
     * Scope for lookalike audiences
     */
    public function scopeLookalike($query)
    {
        return $query->where('audience_type', self::TYPE_LOOKALIKE);
    }

    /**
     * Scope for audiences that can be used as lookalike seeds
     */
    public function scopeCanBeSeed($query)
    {
        return $query->where('audience_type', '!=', self::TYPE_LOOKALIKE)
            ->where('status', self::STATUS_ACTIVE)
            ->where('member_count', '>=', 100);
    }

    /**
     * Create a lookalike audience from this audience
     */
    public function createLookalike(
        PlatformAdAccount $account,
        int $percentage = 1,
        string $country = 'US',
        ?string $name = null
    ): self {
        return self::create([
            'platform_ad_account_id' => $account->id,
            'name' => $name ?? "{$this->name} - {$percentage}% Lookalike ({$country})",
            'description' => "Lookalike audience based on: {$this->name}",
            'audience_type' => self::TYPE_LOOKALIKE,
            'lookalike_source_type' => self::LOOKALIKE_SOURCE_AUDIENCE,
            'lookalike_source_audience_id' => $this->id,
            'lookalike_percentage' => $percentage,
            'lookalike_country' => $country,
            'status' => self::STATUS_DRAFT,
            'is_auto_sync' => false,
        ]);
    }
}
