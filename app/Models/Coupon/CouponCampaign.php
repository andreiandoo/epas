<?php

namespace App\Models\Coupon;

use App\Models\MarketplaceClient;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponCampaign extends Model
{
    use HasUuids, Translatable;

    protected $table = 'coupon_campaigns';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'name',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'budget_limit',
        'budget_used',
        'redemption_limit',
        'redemption_count',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'budget_limit' => 'decimal:2',
        'budget_used' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(CouponCode::class, 'campaign_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isBudgetExhausted(): bool
    {
        if (!$this->budget_limit) {
            return false;
        }

        return $this->budget_used >= $this->budget_limit;
    }

    public function isRedemptionLimitReached(): bool
    {
        if (!$this->redemption_limit) {
            return false;
        }

        return $this->redemption_count >= $this->redemption_limit;
    }

    public function addBudgetUsage(float $amount): void
    {
        $this->increment('budget_used', $amount);
        $this->increment('redemption_count');

        // Check if budget or redemption limit reached
        if ($this->isBudgetExhausted() || $this->isRedemptionLimitReached()) {
            $this->update(['status' => 'expired']);
        }
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
