<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonChannelAffinity extends Model
{
    protected $table = 'fs_person_channel_affinity';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'channel',
        'interaction_count',
        'conversion_count',
        'conversion_rate',
        'revenue_attributed',
        'last_interaction_at',
    ];

    protected $casts = [
        'interaction_count' => 'integer',
        'conversion_count' => 'integer',
        'conversion_rate' => 'float',
        'revenue_attributed' => 'decimal:2',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Known channels.
     */
    public const CHANNELS = [
        'direct' => 'Direct',
        'organic' => 'Organic Search',
        'paid_search' => 'Paid Search',
        'paid_social' => 'Paid Social',
        'email' => 'Email',
        'referral' => 'Referral',
        'social' => 'Social (Organic)',
        'affiliate' => 'Affiliate',
        'display' => 'Display Ads',
        'other' => 'Other',
    ];

    /**
     * Map UTM sources to channels.
     */
    public const SOURCE_CHANNEL_MAP = [
        // Email
        'newsletter' => 'email',
        'mailchimp' => 'email',
        'sendgrid' => 'email',
        'email' => 'email',

        // Paid Social
        'facebook' => 'paid_social',
        'fb' => 'paid_social',
        'instagram' => 'paid_social',
        'ig' => 'paid_social',
        'tiktok' => 'paid_social',
        'linkedin' => 'paid_social',
        'twitter' => 'paid_social',
        'x' => 'paid_social',

        // Paid Search
        'google' => 'paid_search',
        'bing' => 'paid_search',
        'yahoo' => 'paid_search',

        // Affiliate
        'affiliate' => 'affiliate',
        'partner' => 'affiliate',
    ];

    /**
     * Map UTM mediums to channels.
     */
    public const MEDIUM_CHANNEL_MAP = [
        'cpc' => 'paid_search',
        'ppc' => 'paid_search',
        'paidsearch' => 'paid_search',
        'paid-search' => 'paid_search',
        'cpm' => 'display',
        'display' => 'display',
        'banner' => 'display',
        'email' => 'email',
        'social' => 'social',
        'organic' => 'organic',
        'referral' => 'referral',
        'affiliate' => 'affiliate',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeWithConversions($query)
    {
        return $query->where('conversion_count', '>', 0);
    }

    public function scopeHighConverting($query, float $minRate = 0.05)
    {
        return $query->where('conversion_rate', '>=', $minRate);
    }

    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('last_interaction_at', '>=', now()->subDays($days));
    }

    // Static helpers

    /**
     * Determine channel from UTM parameters.
     */
    public static function determineChannel(?string $source, ?string $medium, ?string $referrer = null): string
    {
        $source = strtolower($source ?? '');
        $medium = strtolower($medium ?? '');

        // Check medium first (more specific)
        if (isset(self::MEDIUM_CHANNEL_MAP[$medium])) {
            return self::MEDIUM_CHANNEL_MAP[$medium];
        }

        // Check source
        foreach (self::SOURCE_CHANNEL_MAP as $pattern => $channel) {
            if (str_contains($source, $pattern)) {
                return $channel;
            }
        }

        // Check referrer if available
        if ($referrer) {
            $referrer = strtolower($referrer);
            if (str_contains($referrer, 'google') || str_contains($referrer, 'bing')) {
                return 'organic';
            }
            if (str_contains($referrer, 'facebook') || str_contains($referrer, 'instagram') ||
                str_contains($referrer, 'twitter') || str_contains($referrer, 'linkedin')) {
                return 'social';
            }
            if (!empty($referrer)) {
                return 'referral';
            }
        }

        // No source/medium/referrer = direct
        if (empty($source) && empty($medium)) {
            return 'direct';
        }

        return 'other';
    }

    /**
     * Get channel profile for a person.
     */
    public static function getProfile(int $tenantId, int $personId): array
    {
        $channels = static::forTenant($tenantId)
            ->forPerson($personId)
            ->orderByDesc('conversion_count')
            ->get();

        if ($channels->isEmpty()) {
            return ['primary_channel' => null, 'channels' => []];
        }

        $totalInteractions = $channels->sum('interaction_count');
        $totalConversions = $channels->sum('conversion_count');
        $totalRevenue = $channels->sum('revenue_attributed');

        return [
            'primary_channel' => $channels->first()->channel,
            'primary_channel_label' => self::CHANNELS[$channels->first()->channel] ?? $channels->first()->channel,
            'total_interactions' => $totalInteractions,
            'total_conversions' => $totalConversions,
            'total_revenue' => $totalRevenue,
            'channels' => $channels->map(fn($c) => [
                'channel' => $c->channel,
                'label' => self::CHANNELS[$c->channel] ?? $c->channel,
                'interactions' => $c->interaction_count,
                'conversions' => $c->conversion_count,
                'conversion_rate' => round($c->conversion_rate * 100, 2) . '%',
                'revenue' => $c->revenue_attributed,
                'share_of_interactions' => $totalInteractions > 0
                    ? round($c->interaction_count / $totalInteractions * 100, 1) . '%'
                    : '0%',
                'last_interaction' => $c->last_interaction_at?->diffForHumans(),
            ])->toArray(),
        ];
    }

    /**
     * Find users by primary channel.
     */
    public static function findByPrimaryChannel(int $tenantId, string $channel): array
    {
        // Get persons where the specified channel has the highest conversion count
        return static::forTenant($tenantId)
            ->forChannel($channel)
            ->whereIn('person_id', function ($query) use ($tenantId) {
                $query->select('person_id')
                    ->from('fs_person_channel_affinity')
                    ->where('tenant_id', $tenantId)
                    ->groupBy('person_id')
                    ->havingRaw('channel = (
                        SELECT channel FROM fs_person_channel_affinity c2
                        WHERE c2.tenant_id = ? AND c2.person_id = fs_person_channel_affinity.person_id
                        ORDER BY conversion_count DESC, interaction_count DESC
                        LIMIT 1
                    )', [$tenantId]);
            })
            ->pluck('person_id')
            ->toArray();
    }

    /**
     * Get best performing channel for audience.
     */
    public static function getBestChannelForAudience(int $tenantId, array $personIds): ?string
    {
        if (empty($personIds)) {
            return null;
        }

        return static::forTenant($tenantId)
            ->whereIn('person_id', $personIds)
            ->selectRaw('channel, SUM(conversion_count) as total_conversions, AVG(conversion_rate) as avg_rate')
            ->groupBy('channel')
            ->orderByDesc('total_conversions')
            ->value('channel');
    }
}
