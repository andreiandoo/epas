<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceContactList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'description',
        'list_type',
        'rules',
        'last_synced_at',
        'is_active',
        'is_default',
        'subscriber_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'rules' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Available rule types for dynamic lists
     */
    public const RULE_TYPES = [
        'newsletter_subscribed' => 'Subscribed to newsletter',
        'newsletter_unsubscribed' => 'Unsubscribed from newsletter',
        'has_purchases' => 'Has made at least one purchase',
        'purchase_count' => 'Has made X purchases',
        'purchased_category' => 'Purchased from category',
        'purchased_genre' => 'Purchased from genre',
        'has_refund_request' => 'Has requested refund',
        'city' => 'Lives in city',
        'state' => 'Lives in state/region',
        'age_less_than' => 'Age less than',
        'age_equals' => 'Age equals',
        'age_greater_than' => 'Age greater than',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceCustomer::class,
            'marketplace_contact_list_members',
            'list_id',
            'marketplace_customer_id'
        )
            ->withPivot(['status', 'subscribed_at', 'unsubscribed_at'])
            ->withTimestamps();
    }

    public function activeSubscribers(): BelongsToMany
    {
        return $this->subscribers()->wherePivot('status', 'subscribed');
    }

    /**
     * Check if list is dynamic (rule-based)
     */
    public function isDynamic(): bool
    {
        return $this->list_type === 'dynamic';
    }

    /**
     * Check if list is manual
     */
    public function isManual(): bool
    {
        return $this->list_type === 'manual';
    }

    /**
     * Get rules array
     */
    public function getRules(): array
    {
        return $this->rules ?? [];
    }

    /**
     * Build query for customers matching the rules
     */
    public function buildMatchingCustomersQuery(): Builder
    {
        $query = MarketplaceCustomer::query()
            ->where('marketplace_client_id', $this->marketplace_client_id)
            ->where('status', 'active');

        $rules = $this->getRules();

        if (empty($rules)) {
            // Return empty results if no rules
            return $query->whereRaw('1 = 0');
        }

        foreach ($rules as $rule) {
            $this->applyRule($query, $rule);
        }

        return $query;
    }

    /**
     * Apply a single rule to the query
     */
    protected function applyRule(Builder $query, array $rule): void
    {
        $type = $rule['type'] ?? null;
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;

        switch ($type) {
            case 'newsletter_subscribed':
                $query->where('accepts_marketing', true);
                break;

            case 'newsletter_unsubscribed':
                $query->where('accepts_marketing', false);
                break;

            case 'has_purchases':
                $query->where('total_orders', '>', 0);
                break;

            case 'purchase_count':
                $this->applyNumericOperator($query, 'total_orders', $operator, (int) $value);
                break;

            case 'purchased_category':
                $query->whereHas('orders', function ($q) use ($value) {
                    $q->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($value) {
                          $eq->where('marketplace_event_category_id', $value);
                      });
                });
                break;

            case 'purchased_genre':
                // Genre is typically on the event or through artists
                $query->whereHas('orders', function ($q) use ($value) {
                    $q->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($value) {
                          $eq->whereJsonContains('tags', $value);
                      });
                });
                break;

            case 'has_refund_request':
                $query->whereHas('refundRequests');
                break;

            case 'city':
                $query->where('city', 'like', "%{$value}%");
                break;

            case 'state':
                $query->where('state', 'like', "%{$value}%");
                break;

            case 'age_less_than':
                $query->whereNotNull('birth_date')
                    ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < ?', [(int) $value]);
                break;

            case 'age_equals':
                $query->whereNotNull('birth_date')
                    ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) = ?', [(int) $value]);
                break;

            case 'age_greater_than':
                $query->whereNotNull('birth_date')
                    ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) > ?', [(int) $value]);
                break;
        }
    }

    /**
     * Apply numeric operator to query
     */
    protected function applyNumericOperator(Builder $query, string $column, string $operator, $value): void
    {
        switch ($operator) {
            case 'equals':
                $query->where($column, '=', $value);
                break;
            case 'greater_than':
                $query->where($column, '>', $value);
                break;
            case 'less_than':
                $query->where($column, '<', $value);
                break;
            case 'greater_or_equal':
                $query->where($column, '>=', $value);
                break;
            case 'less_or_equal':
                $query->where($column, '<=', $value);
                break;
        }
    }

    /**
     * Sync subscribers based on rules (for dynamic lists)
     */
    public function syncSubscribers(): int
    {
        if (!$this->isDynamic()) {
            return 0;
        }

        $matchingCustomerIds = $this->buildMatchingCustomersQuery()->pluck('id');

        // Get current subscribers
        $currentSubscriberIds = $this->activeSubscribers()->pluck('marketplace_customers.id');

        // Customers to add
        $toAdd = $matchingCustomerIds->diff($currentSubscriberIds);

        // Add new matching customers
        foreach ($toAdd as $customerId) {
            $this->subscribers()->syncWithoutDetaching([
                $customerId => [
                    'status' => 'subscribed',
                    'subscribed_at' => now(),
                ],
            ]);
        }

        // Update sync timestamp
        $this->update([
            'last_synced_at' => now(),
            'subscriber_count' => $this->activeSubscribers()->count(),
        ]);

        return $toAdd->count();
    }

    /**
     * Add subscriber to list
     */
    public function addSubscriber(int|MarketplaceCustomer $customer): void
    {
        $customerId = $customer instanceof MarketplaceCustomer ? $customer->id : $customer;

        $this->subscribers()->syncWithoutDetaching([
            $customerId => [
                'status' => 'subscribed',
                'subscribed_at' => now(),
            ],
        ]);

        $this->updateSubscriberCount();
    }

    /**
     * Remove subscriber from list
     */
    public function removeSubscriber(int|MarketplaceCustomer $customer): void
    {
        $customerId = $customer instanceof MarketplaceCustomer ? $customer->id : $customer;

        $this->subscribers()->updateExistingPivot($customerId, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        $this->updateSubscriberCount();
    }

    /**
     * Update subscriber count
     */
    public function updateSubscriberCount(): void
    {
        $this->update([
            'subscriber_count' => $this->activeSubscribers()->count(),
        ]);
    }

    /**
     * Get matching customers count (preview for dynamic lists)
     */
    public function getMatchingCustomersCount(): int
    {
        if (!$this->isDynamic() || empty($this->getRules())) {
            return 0;
        }

        return $this->buildMatchingCustomersQuery()->count();
    }
}
