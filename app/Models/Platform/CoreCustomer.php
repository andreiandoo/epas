<?php

namespace App\Models\Platform;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class CoreCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'email',
        'email_hash',
        'phone',
        'phone_hash',
        'first_name',
        'last_name',
        'country_code',
        'region',
        'city',
        'postal_code',
        'language',
        'timezone',
        'gender',
        'birth_date',
        'age_range',
        'first_source',
        'first_medium',
        'first_campaign',
        'first_referrer',
        'first_landing_page',
        'first_utm_source',
        'first_utm_medium',
        'first_utm_campaign',
        'first_utm_term',
        'first_utm_content',
        'last_source',
        'last_medium',
        'last_campaign',
        'last_referrer',
        'last_utm_source',
        'last_utm_medium',
        'last_utm_campaign',
        'first_gclid',
        'first_fbclid',
        'first_ttclid',
        'first_li_fat_id',
        'last_gclid',
        'last_fbclid',
        'last_ttclid',
        'last_li_fat_id',
        'first_seen_at',
        'last_seen_at',
        'total_visits',
        'total_pageviews',
        'total_sessions',
        'total_time_spent_seconds',
        'avg_session_duration_seconds',
        'bounce_rate',
        'first_purchase_at',
        'last_purchase_at',
        'total_orders',
        'total_tickets',
        'total_spent',
        'average_order_value',
        'lifetime_value',
        'currency',
        'days_since_last_purchase',
        'purchase_frequency_days',
        'total_events_viewed',
        'total_events_attended',
        'favorite_categories',
        'favorite_event_types',
        'price_sensitivity',
        'first_tenant_id',
        'primary_tenant_id',
        'tenant_ids',
        'tenant_count',
        'devices',
        'browsers',
        'operating_systems',
        'primary_device',
        'primary_browser',
        'emails_sent',
        'emails_opened',
        'emails_clicked',
        'email_open_rate',
        'email_click_rate',
        'last_email_opened_at',
        'email_subscribed',
        'email_unsubscribed_at',
        'customer_segment',
        'engagement_score',
        'purchase_likelihood_score',
        'churn_risk_score',
        'predicted_ltv',
        'rfm_recency_score',
        'rfm_frequency_score',
        'rfm_monetary_score',
        'rfm_segment',
        'marketing_consent',
        'analytics_consent',
        'personalization_consent',
        'consent_updated_at',
        'consent_source',
        'consent_history',
        'stripe_customer_id',
        'facebook_user_id',
        'google_user_id',
        'external_ids',
        'custom_attributes',
        'tags',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'first_purchase_at' => 'datetime',
        'last_purchase_at' => 'datetime',
        'last_email_opened_at' => 'datetime',
        'email_unsubscribed_at' => 'datetime',
        'consent_updated_at' => 'datetime',
        'total_spent' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'lifetime_value' => 'decimal:2',
        'predicted_ltv' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'email_open_rate' => 'decimal:2',
        'email_click_rate' => 'decimal:2',
        'marketing_consent' => 'boolean',
        'analytics_consent' => 'boolean',
        'personalization_consent' => 'boolean',
        'email_subscribed' => 'boolean',
        'favorite_categories' => 'array',
        'favorite_event_types' => 'array',
        'price_sensitivity' => 'array',
        'tenant_ids' => 'array',
        'devices' => 'array',
        'browsers' => 'array',
        'operating_systems' => 'array',
        'consent_history' => 'array',
        'external_ids' => 'array',
        'custom_attributes' => 'array',
        'tags' => 'array',
    ];

    protected $hidden = [
        'email',
        'phone',
        'first_name',
        'last_name',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
        });
    }

    // Encrypted PII fields
    public function setEmailAttribute($value): void
    {
        if ($value) {
            $this->attributes['email'] = Crypt::encryptString(strtolower(trim($value)));
            $this->attributes['email_hash'] = hash('sha256', strtolower(trim($value)));
        } else {
            $this->attributes['email'] = null;
            $this->attributes['email_hash'] = null;
        }
    }

    public function getEmailAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setPhoneAttribute($value): void
    {
        if ($value) {
            $normalized = preg_replace('/[^0-9+]/', '', $value);
            $this->attributes['phone'] = Crypt::encryptString($normalized);
            $this->attributes['phone_hash'] = hash('sha256', $normalized);
        } else {
            $this->attributes['phone'] = null;
            $this->attributes['phone_hash'] = null;
        }
    }

    public function getPhoneAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getFirstNameAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getLastNameAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Relationships
    public function firstTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'first_tenant_id');
    }

    public function primaryTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'primary_tenant_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CoreCustomerEvent::class, 'customer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CoreSession::class, 'customer_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(PlatformConversion::class, 'customer_id');
    }

    // Computed attributes
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return !empty($parts) ? implode(' ', $parts) : null;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInDays(now()) < 30;
    }

    public function getIsReturningAttribute(): bool
    {
        return $this->total_visits > 1;
    }

    public function getHasPurchasedAttribute(): bool
    {
        return $this->total_orders > 0;
    }

    // Scopes
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email_hash');
    }

    public function scopeActive($query, int $days = 30)
    {
        return $query->where('last_seen_at', '>=', now()->subDays($days));
    }

    public function scopeInactive($query, int $days = 90)
    {
        return $query->where('last_seen_at', '<', now()->subDays($days));
    }

    public function scopePurchasers($query)
    {
        return $query->where('total_orders', '>', 0);
    }

    public function scopeHighValue($query, float $threshold = 500)
    {
        return $query->where('total_spent', '>=', $threshold);
    }

    public function scopeBySegment($query, string $segment)
    {
        return $query->where('customer_segment', $segment);
    }

    public function scopeByRfmSegment($query, string $segment)
    {
        return $query->where('rfm_segment', $segment);
    }

    public function scopeFromTenant($query, int $tenantId)
    {
        return $query->whereJsonContains('tenant_ids', $tenantId);
    }

    public function scopeWithConsent($query, string $type = 'marketing')
    {
        return $query->where("{$type}_consent", true);
    }

    // Lookup methods
    public static function findByEmail(string $email): ?self
    {
        $hash = hash('sha256', strtolower(trim($email)));
        return static::where('email_hash', $hash)->first();
    }

    public static function findByPhone(string $phone): ?self
    {
        $normalized = preg_replace('/[^0-9+]/', '', $phone);
        $hash = hash('sha256', $normalized);
        return static::where('phone_hash', $hash)->first();
    }

    public static function findOrCreateByEmail(string $email, array $attributes = []): self
    {
        $customer = static::findByEmail($email);

        if (!$customer) {
            $customer = static::create(array_merge(['email' => $email], $attributes));
        }

        return $customer;
    }

    // Update methods
    public function recordVisit(array $data = []): void
    {
        $now = now();

        $updates = [
            'last_seen_at' => $now,
            'total_visits' => $this->total_visits + 1,
        ];

        if (!$this->first_seen_at) {
            $updates['first_seen_at'] = $now;
        }

        // Update attribution if provided
        if (!empty($data['source']) && !$this->first_source) {
            $updates['first_source'] = $data['source'];
            $updates['first_medium'] = $data['medium'] ?? null;
            $updates['first_campaign'] = $data['campaign'] ?? null;
        }

        if (!empty($data['source'])) {
            $updates['last_source'] = $data['source'];
            $updates['last_medium'] = $data['medium'] ?? null;
            $updates['last_campaign'] = $data['campaign'] ?? null;
        }

        // Click IDs
        foreach (['gclid', 'fbclid', 'ttclid', 'li_fat_id'] as $clickId) {
            if (!empty($data[$clickId])) {
                if (!$this->{"first_{$clickId}"}) {
                    $updates["first_{$clickId}"] = $data[$clickId];
                }
                $updates["last_{$clickId}"] = $data[$clickId];
            }
        }

        $this->update($updates);
    }

    public function recordPurchase(float $value, int $tickets = 0, ?int $tenantId = null): void
    {
        $now = now();

        $this->total_orders += 1;
        $this->total_tickets += $tickets;
        $this->total_spent += $value;
        $this->average_order_value = $this->total_spent / $this->total_orders;
        $this->last_purchase_at = $now;

        if (!$this->first_purchase_at) {
            $this->first_purchase_at = $now;
        }

        // Calculate purchase frequency
        if ($this->total_orders > 1 && $this->first_purchase_at) {
            $daysSinceFirst = $this->first_purchase_at->diffInDays($now);
            $this->purchase_frequency_days = (int) ($daysSinceFirst / ($this->total_orders - 1));
        }

        $this->days_since_last_purchase = 0;

        // Add tenant if provided
        if ($tenantId) {
            $this->addTenant($tenantId);
        }

        // Update LTV (simple calculation - can be enhanced with ML)
        $this->lifetime_value = $this->total_spent;

        $this->save();
        $this->updateSegment();
    }

    public function addTenant(int $tenantId): void
    {
        $tenantIds = $this->tenant_ids ?? [];

        if (!in_array($tenantId, $tenantIds)) {
            $tenantIds[] = $tenantId;
            $this->tenant_ids = $tenantIds;
            $this->tenant_count = count($tenantIds);

            if (!$this->first_tenant_id) {
                $this->first_tenant_id = $tenantId;
            }

            $this->save();
        }
    }

    public function updateSegment(): void
    {
        // Simple segmentation logic - can be enhanced
        $segment = 'New';

        if ($this->total_orders === 0) {
            if ($this->total_visits > 5) {
                $segment = 'Engaged Non-Buyer';
            } else {
                $segment = 'New';
            }
        } elseif ($this->total_orders === 1) {
            $segment = 'First-Time Buyer';
        } elseif ($this->total_orders >= 5 || $this->total_spent >= 500) {
            if ($this->days_since_last_purchase > 180) {
                $segment = 'Lapsed VIP';
            } else {
                $segment = 'VIP';
            }
        } elseif ($this->total_orders >= 2) {
            if ($this->days_since_last_purchase > 90) {
                $segment = 'At Risk';
            } else {
                $segment = 'Repeat Buyer';
            }
        }

        $this->customer_segment = $segment;
        $this->save();
    }

    public function calculateRfmScores(): void
    {
        // Recency (days since last purchase) - lower is better
        $recency = $this->last_purchase_at
            ? $this->last_purchase_at->diffInDays(now())
            : 365;

        if ($recency <= 30) $this->rfm_recency_score = 5;
        elseif ($recency <= 60) $this->rfm_recency_score = 4;
        elseif ($recency <= 90) $this->rfm_recency_score = 3;
        elseif ($recency <= 180) $this->rfm_recency_score = 2;
        else $this->rfm_recency_score = 1;

        // Frequency (number of orders)
        if ($this->total_orders >= 10) $this->rfm_frequency_score = 5;
        elseif ($this->total_orders >= 5) $this->rfm_frequency_score = 4;
        elseif ($this->total_orders >= 3) $this->rfm_frequency_score = 3;
        elseif ($this->total_orders >= 2) $this->rfm_frequency_score = 2;
        else $this->rfm_frequency_score = 1;

        // Monetary (total spent)
        if ($this->total_spent >= 1000) $this->rfm_monetary_score = 5;
        elseif ($this->total_spent >= 500) $this->rfm_monetary_score = 4;
        elseif ($this->total_spent >= 200) $this->rfm_monetary_score = 3;
        elseif ($this->total_spent >= 50) $this->rfm_monetary_score = 2;
        else $this->rfm_monetary_score = 1;

        // RFM Segment
        $rfmScore = "{$this->rfm_recency_score}{$this->rfm_frequency_score}{$this->rfm_monetary_score}";

        $this->rfm_segment = match (true) {
            in_array($rfmScore, ['555', '554', '544', '545', '454', '455', '445']) => 'Champions',
            in_array($rfmScore, ['543', '444', '435', '355', '354', '345', '344', '335']) => 'Loyal',
            in_array($rfmScore, ['553', '551', '552', '541', '542', '533', '532', '531', '452', '451', '442', '441', '431', '453', '443']) => 'Potential Loyalist',
            in_array($rfmScore, ['512', '511', '422', '421', '412', '411', '311']) => 'New Customers',
            in_array($rfmScore, ['525', '524', '523', '522', '521', '515', '514', '513', '425', '424', '413', '414', '415', '315', '314', '313']) => 'Promising',
            in_array($rfmScore, ['535', '534', '443', '434', '343', '334', '325', '324']) => 'Need Attention',
            in_array($rfmScore, ['331', '321', '312', '221', '213', '231', '241', '251']) => 'About To Sleep',
            in_array($rfmScore, ['255', '254', '245', '244', '253', '252', '243', '242', '235', '234', '225', '224', '153', '152', '145', '143', '142', '135', '134', '133', '125', '124']) => 'At Risk',
            in_array($rfmScore, ['155', '154', '144', '214', '215', '115', '114', '113']) => 'Cannot Lose Them',
            in_array($rfmScore, ['332', '322', '231', '241', '251', '233', '232', '223', '222', '132', '123', '122', '212', '211']) => 'Hibernating',
            in_array($rfmScore, ['111', '112', '121', '131', '141', '151']) => 'Lost',
            default => 'Other',
        };

        $this->save();
    }

    // Get data for ad platforms (hashed)
    public function getHashedDataForAds(): array
    {
        return [
            'em' => $this->email_hash,
            'ph' => $this->phone_hash,
            'fn' => $this->first_name ? hash('sha256', strtolower($this->first_name)) : null,
            'ln' => $this->last_name ? hash('sha256', strtolower($this->last_name)) : null,
            'ct' => $this->city ? hash('sha256', strtolower($this->city)) : null,
            'st' => $this->region ? hash('sha256', strtolower($this->region)) : null,
            'zp' => $this->postal_code ? hash('sha256', $this->postal_code) : null,
            'country' => $this->country_code,
        ];
    }
}
