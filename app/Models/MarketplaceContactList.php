<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceContactList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'description',
        'is_default',
        'subscriber_count',
    ];

    protected $casts = [
        'is_default' => 'boolean',
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
     * Add subscriber to list
     */
    public function addSubscriber(MarketplaceCustomer $customer): void
    {
        $this->subscribers()->syncWithoutDetaching([
            $customer->id => [
                'status' => 'subscribed',
                'subscribed_at' => now(),
            ],
        ]);

        $this->updateSubscriberCount();
    }

    /**
     * Remove subscriber from list
     */
    public function removeSubscriber(MarketplaceCustomer $customer): void
    {
        $this->subscribers()->updateExistingPivot($customer->id, [
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
}
