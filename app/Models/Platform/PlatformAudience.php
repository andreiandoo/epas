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

    const AUDIENCE_TYPES = [
        self::TYPE_ALL_CUSTOMERS => 'All Customers',
        self::TYPE_PURCHASERS => 'All Purchasers',
        self::TYPE_HIGH_VALUE => 'High-Value Customers',
        self::TYPE_RECENT_VISITORS => 'Recent Visitors',
        self::TYPE_CART_ABANDONERS => 'Cart Abandoners',
        self::TYPE_ENGAGED_USERS => 'Engaged Users',
        self::TYPE_INACTIVE => 'Inactive Customers',
        self::TYPE_CUSTOM => 'Custom Segment',
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
}
