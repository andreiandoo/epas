<?php

namespace App\Services\Tracking;

use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\PersonTag;
use App\Models\Tracking\PersonTagAssignment;
use App\Models\Tracking\PersonTagLog;
use App\Models\Tracking\PersonTagRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonTaggingService
{
    /**
     * Apply a tag to a person.
     */
    public function applyTag(
        int $tenantId,
        int $personId,
        int|string $tag, // ID or slug
        string $source = 'manual',
        ?int $sourceId = null,
        ?float $confidence = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $assignedBy = null,
        ?array $metadata = null
    ): ?PersonTagAssignment {
        // Resolve tag if slug provided
        if (is_string($tag)) {
            $tagModel = PersonTag::findBySlug($tenantId, $tag);
            if (!$tagModel) {
                return null;
            }
            $tagId = $tagModel->id;
        } else {
            $tagId = $tag;
        }

        return PersonTagAssignment::assignTag(
            $tenantId,
            $personId,
            $tagId,
            $source,
            $sourceId,
            $confidence,
            $expiresAt,
            $assignedBy,
            $metadata
        );
    }

    /**
     * Remove a tag from a person.
     */
    public function removeTag(
        int $personId,
        int|string $tag, // ID or slug
        string $source = 'manual',
        ?int $performedBy = null
    ): bool {
        if (is_string($tag)) {
            // Need tenant to resolve slug
            $assignment = PersonTagAssignment::where('person_id', $personId)
                ->whereHas('tag', fn($q) => $q->where('slug', $tag))
                ->first();

            if (!$assignment) {
                return false;
            }

            $tagId = $assignment->tag_id;
        } else {
            $tagId = $tag;
        }

        return PersonTagAssignment::removeTag($personId, $tagId, $source, $performedBy);
    }

    /**
     * Apply multiple tags to a person.
     */
    public function applyTags(
        int $tenantId,
        int $personId,
        array $tagIds,
        string $source = 'manual',
        ?int $sourceId = null,
        ?int $assignedBy = null
    ): array {
        $results = [];

        foreach ($tagIds as $tagId) {
            $results[$tagId] = $this->applyTag(
                $tenantId,
                $personId,
                $tagId,
                $source,
                $sourceId,
                assignedBy: $assignedBy
            );
        }

        return $results;
    }

    /**
     * Replace all tags for a person (sync).
     */
    public function syncTags(
        int $tenantId,
        int $personId,
        array $tagIds,
        string $source = 'manual',
        ?int $assignedBy = null
    ): void {
        // Get current tags
        $currentTagIds = PersonTagAssignment::forTenant($tenantId)
            ->forPerson($personId)
            ->pluck('tag_id')
            ->toArray();

        // Remove tags not in new list
        $toRemove = array_diff($currentTagIds, $tagIds);
        foreach ($toRemove as $tagId) {
            $this->removeTag($personId, $tagId, $source, $assignedBy);
        }

        // Add new tags
        $toAdd = array_diff($tagIds, $currentTagIds);
        foreach ($toAdd as $tagId) {
            $this->applyTag($tenantId, $personId, $tagId, $source, assignedBy: $assignedBy);
        }
    }

    /**
     * Run all active auto-tagging rules for a tenant.
     */
    public function runAutoTaggingRules(?int $tenantId = null): array
    {
        $query = PersonTagRule::active()->ordered();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        $rules = $query->with('tag')->get();
        $results = [];

        foreach ($rules as $rule) {
            try {
                $count = $this->executeRule($rule);
                $rule->recordRun($count);
                $results[$rule->id] = [
                    'name' => $rule->name,
                    'tag' => $rule->tag->name,
                    'count' => $count,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                Log::error("Auto-tagging rule {$rule->id} failed: {$e->getMessage()}");
                $results[$rule->id] = [
                    'name' => $rule->name,
                    'tag' => $rule->tag->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Execute a single auto-tagging rule.
     */
    public function executeRule(PersonTagRule $rule): int
    {
        $tenantId = $rule->tenant_id;
        $tagId = $rule->tag_id;

        // Build query from conditions
        $matchingPersonIds = $this->buildConditionQuery($tenantId, $rule->conditions, $rule->match_type);

        $assignedCount = 0;

        // Apply tags to matching persons
        foreach ($matchingPersonIds as $personId) {
            $existing = PersonTagAssignment::where('person_id', $personId)
                ->where('tag_id', $tagId)
                ->exists();

            if (!$existing) {
                PersonTagAssignment::assignTag(
                    $tenantId,
                    $personId,
                    $tagId,
                    'auto_rule',
                    $rule->id
                );
                $assignedCount++;
            }
        }

        // Handle remove_when_unmet
        if ($rule->remove_when_unmet) {
            $toRemove = PersonTagAssignment::forTenant($tenantId)
                ->forTag($tagId)
                ->fromSource('auto_rule')
                ->where('source_id', $rule->id)
                ->whereNotIn('person_id', $matchingPersonIds)
                ->pluck('person_id');

            foreach ($toRemove as $personId) {
                PersonTagAssignment::removeTag($personId, $tagId, 'auto_rule');
            }
        }

        return $assignedCount;
    }

    /**
     * Build query based on conditions.
     */
    protected function buildConditionQuery(int $tenantId, array $conditions, string $matchType = 'all'): array
    {
        $query = CoreCustomer::fromTenant($tenantId)
            ->notMerged()
            ->notAnonymized();

        $method = $matchType === 'all' ? 'where' : 'orWhere';

        if ($matchType === 'any') {
            $query->where(function ($q) use ($conditions) {
                foreach ($conditions as $condition) {
                    $this->applyCondition($q, $condition, 'orWhere');
                }
            });
        } else {
            foreach ($conditions as $condition) {
                $this->applyCondition($query, $condition, 'where');
            }
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Apply a single condition to query.
     */
    protected function applyCondition($query, array $condition, string $method = 'where'): void
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return;
        }

        // Handle special computed fields
        if ($field === 'days_since_last_seen') {
            $query->{$method}(
                DB::raw('COALESCE(EXTRACT(DAY FROM NOW() - last_seen_at), 9999)'),
                $operator,
                $value
            );
            return;
        }

        if ($field === 'days_since_last_purchase') {
            $query->{$method}(
                DB::raw('COALESCE(EXTRACT(DAY FROM NOW() - last_purchase_at), 9999)'),
                $operator,
                $value
            );
            return;
        }

        // Handle array operators
        if ($operator === 'in') {
            $query->{$method . 'In'}($field, (array) $value);
            return;
        }

        if ($operator === 'not_in') {
            $query->{$method . 'NotIn'}($field, (array) $value);
            return;
        }

        // Standard operators
        $query->{$method}($field, $operator, $value);
    }

    /**
     * Process expired tags.
     */
    public function processExpiredTags(): int
    {
        $expired = PersonTagAssignment::expired()->get();
        $count = 0;

        foreach ($expired as $assignment) {
            PersonTagLog::create([
                'tenant_id' => $assignment->tenant_id,
                'person_id' => $assignment->person_id,
                'tag_id' => $assignment->tag_id,
                'action' => 'expired',
                'source' => 'system',
                'created_at' => now(),
            ]);

            $assignment->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Apply behavioral tags based on recent activity.
     */
    public function applyBehavioralTags(int $tenantId, int $personId): void
    {
        $person = CoreCustomer::find($personId);
        if (!$person) {
            return;
        }

        $tagsToApply = [];
        $tagsToRemove = [];

        // Cart Abandoner
        if ($person->has_cart_abandoned) {
            $tagsToApply[] = 'cart-abandoner';
        } else {
            $tagsToRemove[] = 'cart-abandoner';
        }

        // Lifecycle tags
        if ($person->total_orders === 0) {
            if ($person->total_visits > 1) {
                $tagsToApply[] = 'returning-visitor';
                $tagsToRemove[] = 'new-visitor';
            } else {
                $tagsToApply[] = 'new-visitor';
                $tagsToRemove[] = 'returning-visitor';
            }
        } elseif ($person->total_orders === 1) {
            $tagsToApply[] = 'first-time-buyer';
            $tagsToRemove[] = ['new-visitor', 'returning-visitor'];
        } elseif ($person->total_orders >= 2) {
            $tagsToApply[] = 'repeat-buyer';
            $tagsToRemove[] = 'first-time-buyer';
        }

        // VIP
        if ($person->total_orders >= 5 || $person->total_spent >= 500) {
            $tagsToApply[] = 'vip';
        }

        // At Risk / Churned
        $daysSincePurchase = $person->last_purchase_at
            ? $person->last_purchase_at->diffInDays(now())
            : null;

        if ($daysSincePurchase !== null) {
            if ($daysSincePurchase > 180) {
                $tagsToApply[] = 'churned';
                $tagsToRemove[] = 'at-risk';
            } elseif ($daysSincePurchase > 90) {
                $tagsToApply[] = 'at-risk';
                $tagsToRemove[] = 'churned';
            } else {
                $tagsToRemove[] = ['at-risk', 'churned'];
            }
        }

        // Engagement
        if ($person->engagement_score >= 70) {
            $tagsToApply[] = 'high-engagement';
            $tagsToRemove[] = 'low-engagement';
        } elseif ($person->engagement_score !== null && $person->engagement_score < 30) {
            $tagsToApply[] = 'low-engagement';
            $tagsToRemove[] = 'high-engagement';
        }

        // Email behavior
        if ($person->email_open_rate >= 0.5) {
            $tagsToApply[] = 'email-opener';
        }
        if ($person->email_click_rate >= 0.1) {
            $tagsToApply[] = 'email-clicker';
        }

        // Device
        if ($person->primary_device === 'mobile') {
            $tagsToApply[] = 'mobile-user';
            $tagsToRemove[] = 'desktop-user';
        } elseif ($person->primary_device === 'desktop') {
            $tagsToApply[] = 'desktop-user';
            $tagsToRemove[] = 'mobile-user';
        }

        // Frequent visitor
        if ($person->total_visits >= 10) {
            $tagsToApply[] = 'frequent-visitor';
        }

        // Newsletter
        if ($person->email_subscribed) {
            $tagsToApply[] = 'newsletter-subscriber';
        } else {
            $tagsToRemove[] = 'newsletter-subscriber';
        }

        // Event attendee
        if ($person->total_events_attended > 0) {
            $tagsToApply[] = 'event-attendee';
        }

        // Price preference
        $priceBand = $person->price_sensitivity['dominant_band'] ?? null;
        if ($priceBand === 'premium') {
            $tagsToApply[] = 'premium-buyer';
            $tagsToRemove[] = 'price-sensitive';
        } elseif ($priceBand === 'low') {
            $tagsToApply[] = 'price-sensitive';
            $tagsToRemove[] = 'premium-buyer';
        }

        // Apply and remove tags
        foreach (array_flatten((array) $tagsToApply) as $slug) {
            $this->applyTag($tenantId, $personId, $slug, 'event');
        }

        foreach (array_flatten((array) $tagsToRemove) as $slug) {
            $this->removeTag($personId, $slug, 'event');
        }
    }

    /**
     * Get tag statistics for a tenant.
     */
    public function getTagStats(int $tenantId): array
    {
        return PersonTag::forTenant($tenantId)
            ->withCount('assignments')
            ->ordered()
            ->get()
            ->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'category' => $tag->category,
                'color' => $tag->color,
                'is_system' => $tag->is_system,
                'is_auto' => $tag->is_auto,
                'person_count' => $tag->assignments_count,
            ])
            ->toArray();
    }
}

/**
 * Helper function to flatten arrays.
 */
if (!function_exists('array_flatten')) {
    function array_flatten(array $array): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $result = array_merge($result, array_flatten($item));
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }
}
