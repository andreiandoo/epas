<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Support\Translatable;

class ExperienceTransaction extends Model
{
    use HasFactory;
    use Translatable;

    public array $translatable = ['description'];

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'customer_id',
        'marketplace_customer_id',
        'xp',
        'xp_balance_after',
        'level_after',
        'triggered_level_up',
        'old_level',
        'new_level',
        'old_level_group',
        'new_level_group',
        'action_type',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected $casts = [
        'xp' => 'integer',
        'xp_balance_after' => 'integer',
        'level_after' => 'integer',
        'triggered_level_up' => 'boolean',
        'old_level' => 'integer',
        'new_level' => 'integer',
        'description' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeLevelUps($query)
    {
        return $query->where('triggered_level_up', true);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get action type label
     */
    public function getActionTypeLabelAttribute(): string
    {
        return ExperienceAction::ACTION_TYPES[$this->action_type] ?? ucfirst(str_replace('_', ' ', $this->action_type));
    }

    /**
     * Check if this was positive XP
     */
    public function getIsPositiveAttribute(): bool
    {
        return $this->xp > 0;
    }

    /**
     * Get formatted XP (with + or -)
     */
    public function getFormattedXpAttribute(): string
    {
        $prefix = $this->xp >= 0 ? '+' : '';
        return $prefix . number_format($this->xp);
    }

    /**
     * Get level change summary
     */
    public function getLevelChangeSummaryAttribute(): ?string
    {
        if (!$this->triggered_level_up) {
            return null;
        }

        $summary = "Level {$this->old_level} → {$this->new_level}";

        if ($this->old_level_group !== $this->new_level_group && $this->new_level_group) {
            $summary .= " ({$this->new_level_group})";
        }

        return $summary;
    }

    /**
     * Get customer name
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->full_name ?? $this->customer?->email ?? 'Unknown Customer';
    }
}
