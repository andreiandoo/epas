<?php

namespace App\Models\Platform;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        // Health Score fields
        'health_score',
        'health_score_breakdown',
        'health_score_calculated_at',
        // Cross-device & Merge fields
        'primary_device_id',
        'linked_device_ids',
        'linked_customer_ids',
        'is_merged',
        'merged_into_id',
        'merged_at',
        // Cohort fields
        'cohort_month',
        'cohort_week',
        // Additional fields
        'visitor_id',
        'has_cart_abandoned',
        'last_cart_abandoned_at',
        // GDPR anonymization fields
        'is_anonymized',
        'anonymized_at',
        // Computed RFM score
        'rfm_score',
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
        'health_score_breakdown' => 'array',
        'health_score_calculated_at' => 'datetime',
        'linked_device_ids' => 'array',
        'linked_customer_ids' => 'array',
        'is_merged' => 'boolean',
        'merged_at' => 'datetime',
        'has_cart_abandoned' => 'boolean',
        'last_cart_abandoned_at' => 'datetime',
        'is_anonymized' => 'boolean',
        'anonymized_at' => 'datetime',
        'rfm_score' => 'integer',
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

    public function txEvents(): HasMany
    {
        return $this->hasMany(\App\Models\Tracking\TxEvent::class, 'person_id');
    }

    public function identityLinks(): HasMany
    {
        return $this->hasMany(\App\Models\Tracking\TxIdentityLink::class, 'person_id');
    }

    public function personTags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Tracking\PersonTag::class,
            'person_tag_assignments',
            'person_id',
            'tag_id'
        )->withPivot(['source', 'confidence', 'assigned_at', 'expires_at'])
         ->wherePivot(function ($query) {
             $query->whereNull('expires_at')
                   ->orWhere('expires_at', '>', now());
         });
    }

    public function tagAssignments(): HasMany
    {
        return $this->hasMany(\App\Models\Tracking\PersonTagAssignment::class, 'person_id');
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

    public function scopeWithTag($query, int|string $tag)
    {
        return $query->whereHas('tagAssignments', function ($q) use ($tag) {
            $q->where(function ($inner) use ($tag) {
                $inner->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            });

            if (is_int($tag)) {
                $q->where('tag_id', $tag);
            } else {
                $q->whereHas('tag', fn($t) => $t->where('slug', $tag));
            }
        });
    }

    public function scopeWithAnyTag($query, array $tagIds)
    {
        return $query->whereHas('tagAssignments', function ($q) use ($tagIds) {
            $q->whereIn('tag_id', $tagIds)
              ->where(function ($inner) {
                  $inner->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
              });
        });
    }

    public function scopeWithAllTags($query, array $tagIds)
    {
        foreach ($tagIds as $tagId) {
            $query->withTag($tagId);
        }
        return $query;
    }

    public function scopeWithoutTag($query, int|string $tag)
    {
        return $query->whereDoesntHave('tagAssignments', function ($q) use ($tag) {
            $q->where(function ($inner) {
                $inner->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            });

            if (is_int($tag)) {
                $q->where('tag_id', $tag);
            } else {
                $q->whereHas('tag', fn($t) => $t->where('slug', $tag));
            }
        });
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

    // Display name helper
    public function getDisplayName(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        if ($this->email) {
            return explode('@', $this->email)[0];
        }

        return 'Customer #' . substr($this->uuid, 0, 8);
    }

    // Health Score helpers
    public function getHealthScoreLabel(): string
    {
        return match (true) {
            $this->health_score >= 80 => 'Excellent',
            $this->health_score >= 60 => 'Good',
            $this->health_score >= 40 => 'Fair',
            $this->health_score >= 20 => 'Poor',
            default => 'Critical',
        };
    }

    public function getHealthScoreColor(): string
    {
        return match (true) {
            $this->health_score >= 80 => 'success',
            $this->health_score >= 60 => 'info',
            $this->health_score >= 40 => 'warning',
            default => 'danger',
        };
    }

    // Cross-device linking
    public function linkDevice(string $deviceId): void
    {
        $linkedDevices = $this->linked_device_ids ?? [];

        if (!in_array($deviceId, $linkedDevices)) {
            $linkedDevices[] = $deviceId;
            $this->update([
                'linked_device_ids' => $linkedDevices,
                'primary_device_id' => $this->primary_device_id ?? $deviceId,
            ]);
        }
    }

    public function linkCustomer(int $customerId): void
    {
        $linkedCustomers = $this->linked_customer_ids ?? [];

        if (!in_array($customerId, $linkedCustomers)) {
            $linkedCustomers[] = $customerId;
            $this->update(['linked_customer_ids' => $linkedCustomers]);
        }
    }

    // Customer Merge functionality
    public function mergeInto(self $targetCustomer): void
    {
        // Prevent merging already merged customers
        if ($this->is_merged) {
            throw new \Exception('Cannot merge an already merged customer.');
        }

        if ($targetCustomer->is_merged) {
            throw new \Exception('Cannot merge into an already merged customer.');
        }

        DB::transaction(function () use ($targetCustomer) {
            // Transfer purchase data
            $targetCustomer->total_orders += $this->total_orders;
            $targetCustomer->total_spent += $this->total_spent;
            $targetCustomer->total_tickets += $this->total_tickets ?? 0;
            $targetCustomer->total_visits += $this->total_visits ?? 0;
            $targetCustomer->total_pageviews += $this->total_pageviews ?? 0;

            // Keep earliest dates
            if ($this->first_seen_at && (!$targetCustomer->first_seen_at || $this->first_seen_at < $targetCustomer->first_seen_at)) {
                $targetCustomer->first_seen_at = $this->first_seen_at;
                // Also update cohort based on earliest first_seen
                $targetCustomer->cohort_month = $this->first_seen_at->format('Y-m');
                $targetCustomer->cohort_week = $this->first_seen_at->format('Y-\WW');
            }
            if ($this->first_purchase_at && (!$targetCustomer->first_purchase_at || $this->first_purchase_at < $targetCustomer->first_purchase_at)) {
                $targetCustomer->first_purchase_at = $this->first_purchase_at;
            }

            // Keep latest dates
            if ($this->last_seen_at && (!$targetCustomer->last_seen_at || $this->last_seen_at > $targetCustomer->last_seen_at)) {
                $targetCustomer->last_seen_at = $this->last_seen_at;
            }
            if ($this->last_purchase_at && (!$targetCustomer->last_purchase_at || $this->last_purchase_at > $targetCustomer->last_purchase_at)) {
                $targetCustomer->last_purchase_at = $this->last_purchase_at;
            }

            // Keep higher scores
            if (($this->rfm_score ?? 0) > ($targetCustomer->rfm_score ?? 0)) {
                $targetCustomer->rfm_score = $this->rfm_score;
            }
            if (($this->health_score ?? 0) > ($targetCustomer->health_score ?? 0)) {
                $targetCustomer->health_score = $this->health_score;
                $targetCustomer->health_score_breakdown = $this->health_score_breakdown;
            }

            // Merge tenant IDs
            $mergedTenantIds = array_unique(array_merge(
                $targetCustomer->tenant_ids ?? [],
                $this->tenant_ids ?? []
            ));
            $targetCustomer->tenant_ids = array_values($mergedTenantIds);
            $targetCustomer->tenant_count = count($mergedTenantIds);

            // Merge device IDs
            $mergedDeviceIds = array_unique(array_merge(
                $targetCustomer->linked_device_ids ?? [],
                $this->linked_device_ids ?? [],
                $this->primary_device_id ? [$this->primary_device_id] : []
            ));
            $targetCustomer->linked_device_ids = array_values($mergedDeviceIds);

            // Link this customer's UUID to target
            $linkedCustomerIds = $targetCustomer->linked_customer_ids ?? [];
            if (!in_array($this->uuid, $linkedCustomerIds)) {
                $linkedCustomerIds[] = $this->uuid;
                $targetCustomer->linked_customer_ids = $linkedCustomerIds;
            }

            // Recalculate average order value
            if ($targetCustomer->total_orders > 0) {
                $targetCustomer->average_order_value = $targetCustomer->total_spent / $targetCustomer->total_orders;
            }

            $targetCustomer->save();

            // Update events to point to target customer
            CoreCustomerEvent::where('customer_id', $this->id)
                ->update(['customer_id' => $targetCustomer->id]);

            // Update sessions to point to target customer
            CoreSession::where('customer_id', $this->id)
                ->update(['customer_id' => $targetCustomer->id]);

            // Mark this customer as merged
            $this->update([
                'is_merged' => true,
                'merged_into_id' => $targetCustomer->id,
                'merged_at' => now(),
            ]);
        });
    }

    // Cohort assignment
    public function assignCohort(): void
    {
        if ($this->first_seen_at) {
            $this->cohort_month = $this->first_seen_at->format('Y-m');
            $this->cohort_week = $this->first_seen_at->format('Y-W');
            $this->save();
        }
    }

    // Scopes for merged customers
    public function scopeNotMerged($query)
    {
        return $query->where('is_merged', false);
    }

    public function scopeMerged($query)
    {
        return $query->where('is_merged', true);
    }

    // Scope for health score
    public function scopeHealthy($query, int $minScore = 60)
    {
        return $query->where('health_score', '>=', $minScore);
    }

    public function scopeAtRisk($query, int $maxScore = 40)
    {
        return $query->where('health_score', '<', $maxScore);
    }

    // Scope for cohort analysis
    public function scopeInCohort($query, string $cohortPeriod, string $type = 'month')
    {
        $field = $type === 'week' ? 'cohort_week' : 'cohort_month';
        return $query->where($field, $cohortPeriod);
    }

    // Get the customer this was merged into
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    // Get customers that were merged into this one
    public function mergedCustomers(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    // GDPR Data Export
    public function exportPersonalData(): array
    {
        return [
            'identity' => [
                'uuid' => $this->uuid,
                'email' => $this->email,
                'phone' => $this->phone,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
            ],
            'demographics' => [
                'country_code' => $this->country_code,
                'region' => $this->region,
                'city' => $this->city,
                'postal_code' => $this->postal_code,
                'language' => $this->language,
                'timezone' => $this->timezone,
                'gender' => $this->gender,
                'birth_date' => $this->birth_date?->toDateString(),
            ],
            'activity' => [
                'first_seen_at' => $this->first_seen_at?->toIso8601String(),
                'last_seen_at' => $this->last_seen_at?->toIso8601String(),
                'total_visits' => $this->total_visits,
                'total_pageviews' => $this->total_pageviews,
            ],
            'purchases' => [
                'first_purchase_at' => $this->first_purchase_at?->toIso8601String(),
                'last_purchase_at' => $this->last_purchase_at?->toIso8601String(),
                'total_orders' => $this->total_orders,
                'total_spent' => $this->total_spent,
            ],
            'marketing' => [
                'first_utm_source' => $this->first_utm_source,
                'first_utm_medium' => $this->first_utm_medium,
                'first_utm_campaign' => $this->first_utm_campaign,
                'email_subscribed' => $this->email_subscribed,
            ],
            'consent' => [
                'marketing_consent' => $this->marketing_consent,
                'analytics_consent' => $this->analytics_consent,
                'personalization_consent' => $this->personalization_consent,
            ],
            'events' => $this->events()
                ->orderByDesc('created_at')
                ->limit(1000)
                ->get()
                ->map(fn($e) => [
                    'type' => $e->event_type,
                    'page_url' => $e->page_url,
                    'created_at' => $e->created_at->toIso8601String(),
                ])
                ->toArray(),
        ];
    }

    // GDPR Data Deletion
    public function anonymizeForGdpr(): void
    {
        $this->update([
            'email' => null,
            'email_hash' => 'anonymized_' . Str::random(32),
            'phone' => null,
            'phone_hash' => null,
            'first_name' => null,
            'last_name' => null,
            'city' => null,
            'postal_code' => null,
            'stripe_customer_id' => null,
            'facebook_user_id' => null,
            'google_user_id' => null,
            'external_ids' => null,
            'custom_attributes' => null,
            'notes' => 'Data deleted per GDPR request at ' . now()->toIso8601String(),
            'is_anonymized' => true,
            'anonymized_at' => now(),
        ]);

        // Anonymize related events
        $this->events()->update([
            'ip_address' => null,
            'event_data' => null,
        ]);

        // Anonymize related sessions
        $this->sessions()->update([
            'ip_address' => null,
            'visitor_id' => 'anonymized_' . $this->id,
        ]);
    }

    // Scope for anonymized customers
    public function scopeAnonymized($query)
    {
        return $query->where('is_anonymized', true);
    }

    public function scopeNotAnonymized($query)
    {
        return $query->where('is_anonymized', false);
    }

    // Get computed RFM score
    public function getRfmTotalScore(): int
    {
        return ($this->rfm_recency_score ?? 0) +
               ($this->rfm_frequency_score ?? 0) +
               ($this->rfm_monetary_score ?? 0);
    }
}
