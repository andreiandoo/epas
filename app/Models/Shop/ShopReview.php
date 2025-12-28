<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShopReview extends Model
{
    use HasUuids;

    protected $table = 'shop_reviews';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'customer_id',
        'order_item_id',
        'rating',
        'title',
        'content',
        'status',
        'is_verified_purchase',
        'admin_response',
        'admin_responded_at',
        'helpful_count',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'admin_responded_at' => 'datetime',
        'helpful_count' => 'integer',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class, 'order_item_id');
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified_purchase', true);
    }

    // Methods

    public function approve(): void
    {
        $this->update(['status' => 'approved']);
        $this->product->updateReviewStats();
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
        $this->product->updateReviewStats();
    }

    public function addAdminResponse(string $response): void
    {
        $this->update([
            'admin_response' => $response,
            'admin_responded_at' => now(),
        ]);
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function getCustomerName(): string
    {
        if ($this->customer) {
            return $this->customer->first_name ?? 'Customer';
        }
        return 'Anonymous';
    }

    public function getRatingStars(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
