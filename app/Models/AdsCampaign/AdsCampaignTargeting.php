<?php

namespace App\Models\AdsCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCampaignTargeting extends Model
{
    protected $table = 'ads_campaign_targeting';

    protected $fillable = [
        'campaign_id',
        'age_min',
        'age_max',
        'genders',
        'languages',
        'locations',
        'excluded_locations',
        'location_type',
        'interests',
        'behaviors',
        'demographics_detailed',
        'custom_audience_ids',
        'lookalike_config',
        'excluded_audience_ids',
        'placements',
        'automatic_placements',
        'ad_schedule',
        'timezone',
        'devices',
        'operating_systems',
        'variant_label',
    ];

    protected $casts = [
        'genders' => 'array',
        'languages' => 'array',
        'locations' => 'array',
        'excluded_locations' => 'array',
        'interests' => 'array',
        'behaviors' => 'array',
        'demographics_detailed' => 'array',
        'custom_audience_ids' => 'array',
        'lookalike_config' => 'array',
        'excluded_audience_ids' => 'array',
        'placements' => 'array',
        'automatic_placements' => 'boolean',
        'ad_schedule' => 'array',
        'devices' => 'array',
        'operating_systems' => 'array',
    ];

    // Common event-related interests (pre-built for convenience)
    const EVENT_INTERESTS = [
        'concerts' => ['id' => '6003139266461', 'name' => 'Concerts'],
        'festivals' => ['id' => '6003384097498', 'name' => 'Music festivals'],
        'nightlife' => ['id' => '6003107902433', 'name' => 'Nightlife'],
        'theater' => ['id' => '6003017847883', 'name' => 'Theatre'],
        'sports_events' => ['id' => '6003107902433', 'name' => 'Sporting events'],
        'comedy' => ['id' => '6003139266461', 'name' => 'Comedy'],
        'live_music' => ['id' => '6003020834693', 'name' => 'Live music'],
        'electronic_music' => ['id' => '6003277229438', 'name' => 'Electronic music'],
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    /**
     * Build Facebook targeting spec from this model
     */
    public function toFacebookTargetingSpec(): array
    {
        $spec = [];

        // Age
        if ($this->age_min) $spec['age_min'] = $this->age_min;
        if ($this->age_max) $spec['age_max'] = $this->age_max;

        // Genders (Facebook: 0=all, 1=male, 2=female)
        if ($this->genders && !in_array('all', $this->genders)) {
            $spec['genders'] = array_map(fn ($g) => $g === 'male' ? 1 : 2, $this->genders);
        }

        // Locations
        if ($this->locations) {
            $geoLocations = ['location_types' => [$this->location_type ?? 'home']];
            foreach ($this->locations as $loc) {
                $type = $loc['type'] ?? 'country';
                if ($type === 'country') {
                    $geoLocations['countries'][] = $loc['id'];
                } elseif ($type === 'city') {
                    $geoLocations['cities'][] = [
                        'key' => $loc['id'],
                        'radius' => $loc['radius_km'] ?? 25,
                        'distance_unit' => 'kilometer',
                    ];
                } elseif ($type === 'region') {
                    $geoLocations['regions'][] = ['key' => $loc['id']];
                }
            }
            $spec['geo_locations'] = $geoLocations;
        }

        // Excluded locations
        if ($this->excluded_locations) {
            $excluded = [];
            foreach ($this->excluded_locations as $loc) {
                $type = $loc['type'] ?? 'country';
                if ($type === 'country') {
                    $excluded['countries'][] = $loc['id'];
                }
            }
            $spec['excluded_geo_locations'] = $excluded;
        }

        // Interests
        if ($this->interests) {
            $spec['flexible_spec'][] = [
                'interests' => array_map(fn ($i) => ['id' => $i['id'], 'name' => $i['name']], $this->interests),
            ];
        }

        // Behaviors
        if ($this->behaviors) {
            $spec['flexible_spec'][] = [
                'behaviors' => array_map(fn ($b) => ['id' => $b['id'], 'name' => $b['name']], $this->behaviors),
            ];
        }

        // Languages
        if ($this->languages) {
            $spec['locales'] = $this->languages;
        }

        // Custom audiences
        if ($this->custom_audience_ids) {
            $spec['custom_audiences'] = array_map(fn ($id) => ['id' => $id], $this->custom_audience_ids);
        }

        // Excluded audiences
        if ($this->excluded_audience_ids) {
            $spec['excluded_custom_audiences'] = array_map(fn ($id) => ['id' => $id], $this->excluded_audience_ids);
        }

        return $spec;
    }

    /**
     * Build Google Ads targeting criteria
     */
    public function toGoogleAdsTargeting(): array
    {
        $criteria = [];

        // Location targeting
        if ($this->locations) {
            foreach ($this->locations as $loc) {
                $criteria['location_targets'][] = [
                    'geo_target_constant' => $loc['id'],
                    'negative' => false,
                ];
            }
        }

        // Age range (Google uses predefined ranges)
        $criteria['age_range'] = $this->getGoogleAgeRange();

        // Gender
        if ($this->genders && !in_array('all', $this->genders)) {
            $criteria['genders'] = array_map(fn ($g) => strtoupper($g), $this->genders);
        }

        // Languages
        if ($this->languages) {
            $criteria['languages'] = $this->languages;
        }

        // Device targeting
        if ($this->devices) {
            $criteria['devices'] = $this->devices;
        }

        // Ad schedule
        if ($this->ad_schedule) {
            $criteria['ad_schedule'] = $this->ad_schedule;
        }

        return $criteria;
    }

    protected function getGoogleAgeRange(): array
    {
        $ranges = [];
        $googleRanges = [
            'AGE_RANGE_18_24' => [18, 24],
            'AGE_RANGE_25_34' => [25, 34],
            'AGE_RANGE_35_44' => [35, 44],
            'AGE_RANGE_45_54' => [45, 54],
            'AGE_RANGE_55_64' => [55, 64],
            'AGE_RANGE_65_UP' => [65, 100],
        ];

        foreach ($googleRanges as $range => [$min, $max]) {
            if ($this->age_min <= $max && $this->age_max >= $min) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    /**
     * Get estimated audience size description
     */
    public function getAudienceDescription(): string
    {
        $parts = [];

        if ($this->age_min || $this->age_max) {
            $parts[] = "Ages {$this->age_min}-{$this->age_max}";
        }

        if ($this->genders && !in_array('all', $this->genders)) {
            $parts[] = implode(' & ', array_map('ucfirst', $this->genders));
        }

        if ($this->locations) {
            $locationNames = array_map(fn ($l) => $l['name'] ?? $l['id'], $this->locations);
            $parts[] = implode(', ', array_slice($locationNames, 0, 3));
            if (count($locationNames) > 3) {
                $parts[] = '+' . (count($locationNames) - 3) . ' more';
            }
        }

        if ($this->interests) {
            $interestNames = array_map(fn ($i) => $i['name'], $this->interests);
            $parts[] = 'Interests: ' . implode(', ', array_slice($interestNames, 0, 3));
        }

        return implode(' | ', $parts) ?: 'Broad audience';
    }

    public function scopeForVariant($query, string $variant)
    {
        return $query->where('variant_label', $variant);
    }
}
