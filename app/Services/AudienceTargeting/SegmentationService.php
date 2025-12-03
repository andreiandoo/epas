<?php

namespace App\Services\AudienceTargeting;

use App\Models\AudienceSegment;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SegmentationService
{
    public function __construct(
        protected CustomerProfileService $profileService
    ) {}

    /**
     * Create a new segment
     */
    public function createSegment(
        Tenant $tenant,
        string $name,
        string $type = 'dynamic',
        ?array $criteria = null,
        ?string $description = null
    ): AudienceSegment {
        $segment = AudienceSegment::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'description' => $description,
            'segment_type' => $type,
            'criteria' => $criteria,
            'status' => AudienceSegment::STATUS_ACTIVE,
        ]);

        if ($type === AudienceSegment::TYPE_DYNAMIC && $criteria) {
            $this->refreshSegment($segment);
        }

        return $segment;
    }

    /**
     * Preview segment membership without persisting
     */
    public function previewSegment(Tenant $tenant, array $criteria): Collection
    {
        return $this->findMatchingCustomers($tenant, $criteria);
    }

    /**
     * Refresh a dynamic segment's membership
     */
    public function refreshSegment(AudienceSegment $segment): int
    {
        if (!$segment->isDynamic()) {
            return $segment->customer_count;
        }

        $criteria = $segment->criteria;
        if (!$criteria) {
            return 0;
        }

        $tenant = $segment->tenant;
        $matchingCustomers = $this->findMatchingCustomers($tenant, $criteria);

        // Sync customers with scores
        $syncData = [];
        foreach ($matchingCustomers as $match) {
            $syncData[$match['customer_id']] = [
                'score' => $match['score'],
                'source' => 'rule',
                'added_at' => now(),
            ];
        }

        $segment->customers()->sync($syncData);
        $segment->update([
            'customer_count' => count($syncData),
            'last_synced_at' => now(),
        ]);

        return count($syncData);
    }

    /**
     * Refresh all segments that need updating
     */
    public function refreshStaleSegments(): int
    {
        $segments = AudienceSegment::needsRefresh()->get();
        $count = 0;

        foreach ($segments as $segment) {
            $this->refreshSegment($segment);
            $count++;
        }

        return $count;
    }

    /**
     * Add customer manually to a static segment
     */
    public function addCustomerToSegment(
        AudienceSegment $segment,
        Customer $customer,
        int $score = 100
    ): void {
        $segment->customers()->syncWithoutDetaching([
            $customer->id => [
                'score' => $score,
                'source' => 'manual',
                'added_at' => now(),
            ],
        ]);

        $segment->increment('customer_count');
    }

    /**
     * Remove customer from segment
     */
    public function removeCustomerFromSegment(AudienceSegment $segment, Customer $customer): void
    {
        $segment->customers()->detach($customer->id);
        $segment->decrement('customer_count');
    }

    /**
     * Import customers to a segment from CSV/array
     */
    public function importCustomersToSegment(
        AudienceSegment $segment,
        array $customerIds,
        int $defaultScore = 100
    ): int {
        $validIds = Customer::whereIn('id', $customerIds)->pluck('id');

        $syncData = [];
        foreach ($validIds as $id) {
            $syncData[$id] = [
                'score' => $defaultScore,
                'source' => 'import',
                'added_at' => now(),
            ];
        }

        $segment->customers()->syncWithoutDetaching($syncData);
        $segment->refreshCustomerCount();

        return count($syncData);
    }

    /**
     * Find customers matching criteria
     */
    protected function findMatchingCustomers(Tenant $tenant, array $criteria): Collection
    {
        $matchMode = $criteria['match'] ?? 'all';
        $rules = $criteria['rules'] ?? [];

        if (empty($rules)) {
            return collect();
        }

        // Start with all profiles for this tenant
        $query = CustomerProfile::where('tenant_id', $tenant->id)
            ->with('customer');

        // Build the query based on rules
        if ($matchMode === 'all') {
            foreach ($rules as $rule) {
                $query = $this->applyRule($query, $rule);
            }
        } else {
            // 'any' mode - use orWhere
            $query->where(function ($q) use ($rules) {
                foreach ($rules as $index => $rule) {
                    if ($index === 0) {
                        $q = $this->applyRule($q, $rule);
                    } else {
                        $q->orWhere(function ($subQ) use ($rule) {
                            $this->applyRule($subQ, $rule);
                        });
                    }
                }
            });
        }

        $profiles = $query->get();

        // Calculate scores based on how many rules each customer matches
        return $profiles->map(function ($profile) use ($rules) {
            $matchedRules = 0;
            foreach ($rules as $rule) {
                if ($this->profileMatchesRule($profile, $rule)) {
                    $matchedRules++;
                }
            }

            $score = count($rules) > 0
                ? (int) round(($matchedRules / count($rules)) * 100)
                : 100;

            return [
                'customer_id' => $profile->customer_id,
                'profile' => $profile,
                'score' => $score,
            ];
        })->filter(fn ($m) => $m['score'] > 0);
    }

    /**
     * Apply a single rule to the query
     */
    protected function applyRule($query, array $rule)
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;

        if (!$field) {
            return $query;
        }

        return match ($field) {
            // Purchase metrics
            'total_spent' => $this->applyNumericRule($query, 'total_spent_cents', $operator, $value * 100),
            'purchase_count' => $this->applyNumericRule($query, 'purchase_count', $operator, $value),
            'avg_order' => $this->applyNumericRule($query, 'avg_order_cents', $operator, $value * 100),

            // Engagement metrics
            'engagement_score' => $this->applyNumericRule($query, 'engagement_score', $operator, $value),
            'churn_risk' => $this->applyNumericRule($query, 'churn_risk', $operator, $value),

            // Date-based
            'last_purchase' => $this->applyDateRule($query, 'last_purchase_at', $operator, $value),
            'first_purchase' => $this->applyDateRule($query, 'first_purchase_at', $operator, $value),

            // Preferences
            'genres' => $this->applyJsonArrayRule($query, 'preferred_genres', $operator, $value),
            'event_types' => $this->applyJsonArrayRule($query, 'preferred_event_types', $operator, $value),
            'attended_event' => $this->applyJsonContainsRule($query, 'attended_events', $value),

            // Location
            'city' => $this->applyLocationRule($query, 'city', $operator, $value),
            'country' => $this->applyLocationRule($query, 'country', $operator, $value),

            // Customer demographics (via join)
            'age' => $this->applyCustomerFieldRule($query, 'age', $operator, $value),

            default => $query,
        };
    }

    /**
     * Apply numeric comparison rule
     */
    protected function applyNumericRule($query, string $column, string $operator, $value)
    {
        return match ($operator) {
            '=', 'equals' => $query->where($column, $value),
            '!=', 'not_equals' => $query->where($column, '!=', $value),
            '>', 'greater_than' => $query->where($column, '>', $value),
            '>=', 'greater_or_equal' => $query->where($column, '>=', $value),
            '<', 'less_than' => $query->where($column, '<', $value),
            '<=', 'less_or_equal' => $query->where($column, '<=', $value),
            'between' => $query->whereBetween($column, $value),
            default => $query,
        };
    }

    /**
     * Apply date-based rule
     */
    protected function applyDateRule($query, string $column, string $operator, $value)
    {
        return match ($operator) {
            'within_days' => $query->where($column, '>=', now()->subDays($value)),
            'before_days' => $query->where($column, '<', now()->subDays($value)),
            'after' => $query->where($column, '>=', $value),
            'before' => $query->where($column, '<', $value),
            'is_null' => $query->whereNull($column),
            'not_null' => $query->whereNotNull($column),
            default => $query,
        };
    }

    /**
     * Apply JSON array rule (for preferences with weights)
     */
    protected function applyJsonArrayRule($query, string $column, string $operator, $value)
    {
        if ($operator === 'includes' || $operator === 'contains') {
            // Value can be a single slug or array of slugs
            $slugs = is_array($value) ? $value : [$value];

            return $query->where(function ($q) use ($column, $slugs) {
                foreach ($slugs as $slug) {
                    $q->orWhereRaw("JSON_SEARCH({$column}, 'one', ?) IS NOT NULL", [$slug]);
                }
            });
        }

        return $query;
    }

    /**
     * Apply JSON contains rule (for simple arrays)
     */
    protected function applyJsonContainsRule($query, string $column, $value)
    {
        return $query->whereJsonContains($column, $value);
    }

    /**
     * Apply location rule
     */
    protected function applyLocationRule($query, string $field, string $operator, $value)
    {
        $jsonPath = "location_data->{$field}";

        return match ($operator) {
            '=', 'equals', 'is' => $query->where($jsonPath, $value),
            'in' => $query->whereIn($jsonPath, (array) $value),
            'not_in' => $query->whereNotIn($jsonPath, (array) $value),
            default => $query,
        };
    }

    /**
     * Apply rule on customer table field (requires join)
     */
    protected function applyCustomerFieldRule($query, string $field, string $operator, $value)
    {
        $query->whereHas('customer', function ($q) use ($field, $operator, $value) {
            match ($operator) {
                '=', 'equals' => $q->where($field, $value),
                '>', 'greater_than' => $q->where($field, '>', $value),
                '>=', 'greater_or_equal' => $q->where($field, '>=', $value),
                '<', 'less_than' => $q->where($field, '<', $value),
                '<=', 'less_or_equal' => $q->where($field, '<=', $value),
                'between' => $q->whereBetween($field, $value),
                default => null,
            };
        });

        return $query;
    }

    /**
     * Check if a profile matches a specific rule
     */
    protected function profileMatchesRule(CustomerProfile $profile, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;

        if (!$field) {
            return false;
        }

        $profileValue = match ($field) {
            'total_spent' => $profile->total_spent_cents / 100,
            'purchase_count' => $profile->purchase_count,
            'avg_order' => $profile->avg_order_cents / 100,
            'engagement_score' => $profile->engagement_score,
            'churn_risk' => $profile->churn_risk,
            'last_purchase' => $profile->last_purchase_at,
            'first_purchase' => $profile->first_purchase_at,
            'genres' => collect($profile->preferred_genres)->pluck('slug')->toArray(),
            'event_types' => collect($profile->preferred_event_types)->pluck('slug')->toArray(),
            'attended_event' => $profile->attended_events ?? [],
            'city' => $profile->location_data['city'] ?? null,
            'country' => $profile->location_data['country'] ?? null,
            'age' => $profile->customer?->age,
            default => null,
        };

        return $this->compareValues($profileValue, $operator, $value);
    }

    /**
     * Compare values based on operator
     */
    protected function compareValues($actual, string $operator, $expected): bool
    {
        return match ($operator) {
            '=', 'equals', 'is' => $actual == $expected,
            '!=', 'not_equals' => $actual != $expected,
            '>', 'greater_than' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<', 'less_than' => $actual < $expected,
            '<=' => $actual <= $expected,
            'between' => is_array($expected) && $actual >= $expected[0] && $actual <= $expected[1],
            'in' => in_array($actual, (array) $expected),
            'not_in' => !in_array($actual, (array) $expected),
            'includes', 'contains' => is_array($actual) && !empty(array_intersect($actual, (array) $expected)),
            'within_days' => $actual && $actual->gte(now()->subDays($expected)),
            'before_days' => $actual && $actual->lt(now()->subDays($expected)),
            default => false,
        };
    }

    /**
     * Get available criteria fields with metadata
     */
    public function getAvailableCriteriaFields(): array
    {
        return [
            [
                'field' => 'total_spent',
                'label' => 'Total Spent (EUR)',
                'type' => 'number',
                'operators' => ['>=', '<=', '=', 'between'],
            ],
            [
                'field' => 'purchase_count',
                'label' => 'Number of Purchases',
                'type' => 'number',
                'operators' => ['>=', '<=', '=', 'between'],
            ],
            [
                'field' => 'avg_order',
                'label' => 'Average Order Value (EUR)',
                'type' => 'number',
                'operators' => ['>=', '<=', 'between'],
            ],
            [
                'field' => 'engagement_score',
                'label' => 'Engagement Score',
                'type' => 'number',
                'operators' => ['>=', '<=', 'between'],
            ],
            [
                'field' => 'churn_risk',
                'label' => 'Churn Risk',
                'type' => 'number',
                'operators' => ['>=', '<=', 'between'],
            ],
            [
                'field' => 'last_purchase',
                'label' => 'Last Purchase',
                'type' => 'date',
                'operators' => ['within_days', 'before_days'],
            ],
            [
                'field' => 'genres',
                'label' => 'Preferred Genres',
                'type' => 'multiselect',
                'operators' => ['includes'],
            ],
            [
                'field' => 'event_types',
                'label' => 'Preferred Event Types',
                'type' => 'multiselect',
                'operators' => ['includes'],
            ],
            [
                'field' => 'attended_event',
                'label' => 'Attended Event',
                'type' => 'select',
                'operators' => ['is'],
            ],
            [
                'field' => 'city',
                'label' => 'City',
                'type' => 'select',
                'operators' => ['is', 'in'],
            ],
            [
                'field' => 'country',
                'label' => 'Country',
                'type' => 'select',
                'operators' => ['is', 'in'],
            ],
            [
                'field' => 'age',
                'label' => 'Age',
                'type' => 'number',
                'operators' => ['>=', '<=', 'between'],
            ],
        ];
    }
}
