<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateDetectionService
{
    // Matching thresholds
    const THRESHOLD_HIGH = 0.9;      // High confidence match
    const THRESHOLD_MEDIUM = 0.7;    // Medium confidence match
    const THRESHOLD_LOW = 0.5;       // Low confidence match

    // Field weights for scoring
    protected array $fieldWeights = [
        'email_hash' => 50,          // Exact email match is very strong
        'phone_hash' => 40,          // Exact phone match is strong
        'name_similarity' => 25,     // Name similarity
        'address_similarity' => 15,  // Address/location similarity
        'device_overlap' => 10,      // Shared devices
        'behavioral_similarity' => 10, // Similar behavior patterns
    ];

    /**
     * Find potential duplicates for a specific customer
     */
    public function findDuplicatesFor(CoreCustomer $customer, float $threshold = self::THRESHOLD_MEDIUM): Collection
    {
        $candidates = collect();

        // Find exact email matches (different records)
        if ($customer->email_hash) {
            $emailMatches = CoreCustomer::where('email_hash', $customer->email_hash)
                ->where('id', '!=', $customer->id)
                ->notMerged()
                ->notAnonymized()
                ->get();

            foreach ($emailMatches as $match) {
                $candidates->push([
                    'customer' => $match,
                    'score' => 1.0,
                    'match_type' => 'exact_email',
                    'confidence' => 'high',
                ]);
            }
        }

        // Find exact phone matches
        if ($customer->phone_hash) {
            $phoneMatches = CoreCustomer::where('phone_hash', $customer->phone_hash)
                ->where('id', '!=', $customer->id)
                ->where('email_hash', '!=', $customer->email_hash ?? '')
                ->notMerged()
                ->notAnonymized()
                ->get();

            foreach ($phoneMatches as $match) {
                $score = $this->calculateMatchScore($customer, $match);
                if ($score >= $threshold) {
                    $candidates->push([
                        'customer' => $match,
                        'score' => $score,
                        'match_type' => 'exact_phone',
                        'confidence' => $this->getConfidenceLevel($score),
                    ]);
                }
            }
        }

        // Find fuzzy name + location matches
        $fuzzyMatches = $this->findFuzzyMatches($customer, $threshold);
        foreach ($fuzzyMatches as $match) {
            // Avoid adding duplicates
            if (!$candidates->contains(fn($c) => $c['customer']->id === $match['customer']->id)) {
                $candidates->push($match);
            }
        }

        // Find device overlap matches
        $deviceMatches = $this->findDeviceOverlapMatches($customer, $threshold);
        foreach ($deviceMatches as $match) {
            if (!$candidates->contains(fn($c) => $c['customer']->id === $match['customer']->id)) {
                $candidates->push($match);
            }
        }

        return $candidates->sortByDesc('score')->values();
    }

    /**
     * Find all potential duplicates across the database
     */
    public function findAllDuplicates(float $threshold = self::THRESHOLD_MEDIUM, int $limit = 100): Collection
    {
        $duplicateGroups = collect();

        // Find groups by email hash
        $emailGroups = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull('email_hash')
            ->select('email_hash')
            ->groupBy('email_hash')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get()
            ->pluck('email_hash');

        foreach ($emailGroups as $emailHash) {
            $customers = CoreCustomer::where('email_hash', $emailHash)
                ->notMerged()
                ->notAnonymized()
                ->orderByDesc('total_orders')
                ->get();

            if ($customers->count() > 1) {
                $duplicateGroups->push([
                    'type' => 'exact_email',
                    'confidence' => 'high',
                    'score' => 1.0,
                    'customers' => $customers,
                    'recommended_primary' => $customers->first(),
                ]);
            }
        }

        // Find groups by phone hash
        $phoneGroups = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull('phone_hash')
            ->select('phone_hash')
            ->groupBy('phone_hash')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get()
            ->pluck('phone_hash');

        foreach ($phoneGroups as $phoneHash) {
            $customers = CoreCustomer::where('phone_hash', $phoneHash)
                ->notMerged()
                ->notAnonymized()
                ->orderByDesc('total_orders')
                ->get();

            // Check if already in email groups
            $existingGroup = $duplicateGroups->first(function ($group) use ($customers) {
                return $group['customers']->pluck('id')->intersect($customers->pluck('id'))->isNotEmpty();
            });

            if (!$existingGroup && $customers->count() > 1) {
                $duplicateGroups->push([
                    'type' => 'exact_phone',
                    'confidence' => 'high',
                    'score' => 0.95,
                    'customers' => $customers,
                    'recommended_primary' => $customers->first(),
                ]);
            }
        }

        // Find fuzzy name + device matches
        $fuzzyGroups = $this->findFuzzyDuplicateGroups($threshold, $limit);
        foreach ($fuzzyGroups as $group) {
            $existingGroup = $duplicateGroups->first(function ($existing) use ($group) {
                return $existing['customers']->pluck('id')->intersect($group['customers']->pluck('id'))->isNotEmpty();
            });

            if (!$existingGroup) {
                $duplicateGroups->push($group);
            }
        }

        return $duplicateGroups->take($limit);
    }

    /**
     * Calculate match score between two customers
     */
    public function calculateMatchScore(CoreCustomer $customer1, CoreCustomer $customer2): float
    {
        $score = 0;
        $maxScore = array_sum($this->fieldWeights);

        // Email hash match
        if ($customer1->email_hash && $customer1->email_hash === $customer2->email_hash) {
            $score += $this->fieldWeights['email_hash'];
        }

        // Phone hash match
        if ($customer1->phone_hash && $customer1->phone_hash === $customer2->phone_hash) {
            $score += $this->fieldWeights['phone_hash'];
        }

        // Name similarity
        $nameSimilarity = $this->calculateNameSimilarity($customer1, $customer2);
        $score += $this->fieldWeights['name_similarity'] * $nameSimilarity;

        // Address similarity
        $addressSimilarity = $this->calculateAddressSimilarity($customer1, $customer2);
        $score += $this->fieldWeights['address_similarity'] * $addressSimilarity;

        // Device overlap
        $deviceOverlap = $this->calculateDeviceOverlap($customer1, $customer2);
        $score += $this->fieldWeights['device_overlap'] * $deviceOverlap;

        // Behavioral similarity
        $behaviorSimilarity = $this->calculateBehavioralSimilarity($customer1, $customer2);
        $score += $this->fieldWeights['behavioral_similarity'] * $behaviorSimilarity;

        return $maxScore > 0 ? $score / $maxScore : 0;
    }

    /**
     * Calculate name similarity using Levenshtein distance
     */
    protected function calculateNameSimilarity(CoreCustomer $customer1, CoreCustomer $customer2): float
    {
        $name1 = strtolower(trim(($customer1->first_name ?? '') . ' ' . ($customer1->last_name ?? '')));
        $name2 = strtolower(trim(($customer2->first_name ?? '') . ' ' . ($customer2->last_name ?? '')));

        if (empty($name1) || empty($name2)) {
            return 0;
        }

        // Exact match
        if ($name1 === $name2) {
            return 1.0;
        }

        // Levenshtein distance
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen === 0) return 0;

        $distance = levenshtein($name1, $name2);
        $similarity = 1 - ($distance / $maxLen);

        // Also check metaphone for phonetic similarity
        $metaphone1 = metaphone($name1);
        $metaphone2 = metaphone($name2);
        $phoneticMatch = ($metaphone1 === $metaphone2) ? 0.3 : 0;

        return min(1.0, $similarity + $phoneticMatch);
    }

    /**
     * Calculate address/location similarity
     */
    protected function calculateAddressSimilarity(CoreCustomer $customer1, CoreCustomer $customer2): float
    {
        $score = 0;
        $components = 0;

        // Country match
        if ($customer1->country_code && $customer2->country_code) {
            $components++;
            if ($customer1->country_code === $customer2->country_code) {
                $score += 0.3;
            }
        }

        // Region match
        if ($customer1->region && $customer2->region) {
            $components++;
            if (strtolower($customer1->region) === strtolower($customer2->region)) {
                $score += 0.3;
            }
        }

        // City match
        if ($customer1->city && $customer2->city) {
            $components++;
            if (strtolower($customer1->city) === strtolower($customer2->city)) {
                $score += 0.2;
            }
        }

        // Postal code match
        if ($customer1->postal_code && $customer2->postal_code) {
            $components++;
            if ($customer1->postal_code === $customer2->postal_code) {
                $score += 0.2;
            }
        }

        return $components > 0 ? $score : 0;
    }

    /**
     * Calculate device overlap
     */
    protected function calculateDeviceOverlap(CoreCustomer $customer1, CoreCustomer $customer2): float
    {
        $devices1 = array_merge(
            $customer1->linked_device_ids ?? [],
            $customer1->primary_device_id ? [$customer1->primary_device_id] : []
        );

        $devices2 = array_merge(
            $customer2->linked_device_ids ?? [],
            $customer2->primary_device_id ? [$customer2->primary_device_id] : []
        );

        if (empty($devices1) || empty($devices2)) {
            return 0;
        }

        $overlap = count(array_intersect($devices1, $devices2));
        $total = count(array_unique(array_merge($devices1, $devices2)));

        return $total > 0 ? $overlap / $total : 0;
    }

    /**
     * Calculate behavioral similarity
     */
    protected function calculateBehavioralSimilarity(CoreCustomer $customer1, CoreCustomer $customer2): float
    {
        $score = 0;

        // Similar purchase patterns
        if ($customer1->total_orders > 0 && $customer2->total_orders > 0) {
            $aovDiff = abs(($customer1->average_order_value ?? 0) - ($customer2->average_order_value ?? 0));
            $avgAov = (($customer1->average_order_value ?? 0) + ($customer2->average_order_value ?? 0)) / 2;
            if ($avgAov > 0) {
                $aovSimilarity = 1 - min(1, $aovDiff / $avgAov);
                $score += $aovSimilarity * 0.5;
            }
        }

        // Similar engagement patterns
        if ($customer1->engagement_score && $customer2->engagement_score) {
            $engagementDiff = abs($customer1->engagement_score - $customer2->engagement_score);
            $score += (1 - min(1, $engagementDiff / 100)) * 0.3;
        }

        // Similar RFM segments
        if ($customer1->rfm_segment && $customer1->rfm_segment === $customer2->rfm_segment) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Find fuzzy matches using name + location
     */
    protected function findFuzzyMatches(CoreCustomer $customer, float $threshold): Collection
    {
        $matches = collect();

        if (!$customer->first_name && !$customer->last_name) {
            return $matches;
        }

        // Find customers with similar names in same location
        $candidates = CoreCustomer::query()
            ->where('id', '!=', $customer->id)
            ->notMerged()
            ->notAnonymized()
            ->when($customer->country_code, fn($q) => $q->where('country_code', $customer->country_code))
            ->when($customer->city, fn($q) => $q->where('city', $customer->city))
            ->limit(100)
            ->get();

        foreach ($candidates as $candidate) {
            $score = $this->calculateMatchScore($customer, $candidate);
            if ($score >= $threshold) {
                $matches->push([
                    'customer' => $candidate,
                    'score' => $score,
                    'match_type' => 'fuzzy_name_location',
                    'confidence' => $this->getConfidenceLevel($score),
                ]);
            }
        }

        return $matches;
    }

    /**
     * Find device overlap matches
     */
    protected function findDeviceOverlapMatches(CoreCustomer $customer, float $threshold): Collection
    {
        $matches = collect();

        $devices = array_merge(
            $customer->linked_device_ids ?? [],
            $customer->primary_device_id ? [$customer->primary_device_id] : []
        );

        if (empty($devices)) {
            return $matches;
        }

        // Find customers with overlapping devices
        foreach ($devices as $deviceId) {
            $candidates = CoreCustomer::query()
                ->where('id', '!=', $customer->id)
                ->notMerged()
                ->notAnonymized()
                ->where(function ($q) use ($deviceId) {
                    $q->where('primary_device_id', $deviceId)
                      ->orWhereJsonContains('linked_device_ids', $deviceId);
                })
                ->get();

            foreach ($candidates as $candidate) {
                $score = $this->calculateMatchScore($customer, $candidate);
                if ($score >= $threshold && !$matches->contains(fn($m) => $m['customer']->id === $candidate->id)) {
                    $matches->push([
                        'customer' => $candidate,
                        'score' => $score,
                        'match_type' => 'device_overlap',
                        'confidence' => $this->getConfidenceLevel($score),
                    ]);
                }
            }
        }

        return $matches;
    }

    /**
     * Find fuzzy duplicate groups across database
     */
    protected function findFuzzyDuplicateGroups(float $threshold, int $limit): Collection
    {
        $groups = collect();

        // Find customers with same name + location but different email
        $nameClusters = DB::table('core_customers')
            ->select(DB::raw('LOWER(CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, ""))) as full_name'), 'city', 'country_code')
            ->whereNull('deleted_at')
            ->where('is_merged', false)
            ->where('is_anonymized', false)
            ->whereNotNull('first_name')
            ->groupBy('full_name', 'city', 'country_code')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get();

        foreach ($nameClusters as $cluster) {
            if (empty(trim($cluster->full_name))) continue;

            $customers = CoreCustomer::query()
                ->whereRaw('LOWER(CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, ""))) = ?', [$cluster->full_name])
                ->where('city', $cluster->city)
                ->where('country_code', $cluster->country_code)
                ->notMerged()
                ->notAnonymized()
                ->orderByDesc('total_orders')
                ->get();

            if ($customers->count() > 1) {
                // Calculate average match score
                $avgScore = 0;
                $comparisons = 0;
                for ($i = 0; $i < $customers->count() - 1; $i++) {
                    for ($j = $i + 1; $j < $customers->count(); $j++) {
                        $avgScore += $this->calculateMatchScore($customers[$i], $customers[$j]);
                        $comparisons++;
                    }
                }
                $avgScore = $comparisons > 0 ? $avgScore / $comparisons : 0;

                if ($avgScore >= $threshold) {
                    $groups->push([
                        'type' => 'fuzzy_name_location',
                        'confidence' => $this->getConfidenceLevel($avgScore),
                        'score' => $avgScore,
                        'customers' => $customers,
                        'recommended_primary' => $customers->first(),
                    ]);
                }
            }
        }

        return $groups;
    }

    /**
     * Get confidence level based on score
     */
    protected function getConfidenceLevel(float $score): string
    {
        if ($score >= self::THRESHOLD_HIGH) return 'high';
        if ($score >= self::THRESHOLD_MEDIUM) return 'medium';
        return 'low';
    }

    /**
     * Auto-merge high confidence duplicates
     */
    public function autoMergeHighConfidenceDuplicates(int $limit = 50): array
    {
        $results = [
            'merged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $duplicates = $this->findAllDuplicates(self::THRESHOLD_HIGH, $limit);

        foreach ($duplicates as $group) {
            if ($group['confidence'] !== 'high' || $group['customers']->count() < 2) {
                $results['skipped']++;
                continue;
            }

            try {
                $primary = $group['recommended_primary'];
                $others = $group['customers']->filter(fn($c) => $c->id !== $primary->id);

                foreach ($others as $duplicate) {
                    $duplicate->mergeInto($primary);
                    $results['merged']++;
                }
            } catch (\Exception $e) {
                Log::error('Auto-merge failed', [
                    'group_type' => $group['type'],
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Get duplicate detection statistics
     */
    public function getStatistics(): array
    {
        $totalCustomers = CoreCustomer::notMerged()->notAnonymized()->count();
        $duplicateGroups = $this->findAllDuplicates(self::THRESHOLD_MEDIUM, 1000);

        $highConfidence = $duplicateGroups->filter(fn($g) => $g['confidence'] === 'high');
        $mediumConfidence = $duplicateGroups->filter(fn($g) => $g['confidence'] === 'medium');
        $lowConfidence = $duplicateGroups->filter(fn($g) => $g['confidence'] === 'low');

        return [
            'total_customers' => $totalCustomers,
            'duplicate_groups' => $duplicateGroups->count(),
            'high_confidence' => $highConfidence->count(),
            'medium_confidence' => $mediumConfidence->count(),
            'low_confidence' => $lowConfidence->count(),
            'potential_duplicates' => $duplicateGroups->sum(fn($g) => $g['customers']->count() - 1),
            'estimated_savings' => $duplicateGroups->sum(fn($g) => $g['customers']->count() - 1),
        ];
    }
}
