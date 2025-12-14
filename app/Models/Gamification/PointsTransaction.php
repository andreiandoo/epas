<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

class PointsTransaction extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['description'];

    // Transaction types
    public const TYPE_EARNED = 'earned';
    public const TYPE_SPENT = 'spent';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_ADJUSTED = 'adjusted';
    public const TYPE_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'type',
        'points',
        'balance_after',
        'action_type',
        'reference_type',
        'reference_id',
        'description',
        'admin_note',
        'metadata',
        'expires_at',
        'is_expired',
        'reversed_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'description' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerPoints(): BelongsTo
    {
        return $this->belongsTo(CustomerPoints::class, 'customer_id', 'customer_id')
            ->where('tenant_id', $this->tenant_id);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_transaction_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeEarned($query)
    {
        return $query->where('type', self::TYPE_EARNED);
    }

    public function scopeSpent($query)
    {
        return $query->where('type', self::TYPE_SPENT);
    }

    public function scopeExpiring($query)
    {
        return $query->where('type', self::TYPE_EARNED)
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }

    public function scopeExpired($query)
    {
        return $query->where('type', self::TYPE_EARNED)
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get display type
     */
    public function getDisplayType(): string
    {
        return match ($this->type) {
            self::TYPE_EARNED => 'Castigat',
            self::TYPE_SPENT => 'Folosit',
            self::TYPE_EXPIRED => 'Expirat',
            self::TYPE_ADJUSTED => 'Ajustat',
            self::TYPE_REFUNDED => 'Returnat',
            default => $this->type,
        };
    }

    /**
     * Get formatted points (with + or -)
     */
    public function getFormattedPoints(): string
    {
        $sign = $this->points > 0 ? '+' : '';
        return $sign . number_format($this->points);
    }

    /**
     * Check if this transaction can be reversed
     */
    public function canBeReversed(): bool
    {
        // Can only reverse spent transactions
        if ($this->type !== self::TYPE_SPENT) {
            return false;
        }

        // Check if already reversed
        $alreadyReversed = self::where('reversed_transaction_id', $this->id)->exists();

        return !$alreadyReversed;
    }

    /**
     * Get action type label
     */
    public function getActionTypeLabel(): string
    {
        $types = GamificationAction::getActionTypes();

        if (isset($types[$this->action_type])) {
            $locale = app()->getLocale();
            return $types[$this->action_type][$locale] ?? $types[$this->action_type]['en'] ?? $this->action_type;
        }

        return match ($this->action_type) {
            'redemption' => app()->getLocale() === 'ro' ? 'Rascumparare' : 'Redemption',
            'refund' => app()->getLocale() === 'ro' ? 'Rambursare' : 'Refund',
            'expiration' => app()->getLocale() === 'ro' ? 'Expirare' : 'Expiration',
            'manual_adjustment' => app()->getLocale() === 'ro' ? 'Ajustare manuala' : 'Manual adjustment',
            default => $this->action_type ?? 'Unknown',
        };
    }

    /**
     * Get CSS class for type badge
     */
    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_EARNED, self::TYPE_REFUNDED => 'bg-green-100 text-green-800',
            self::TYPE_SPENT => 'bg-blue-100 text-blue-800',
            self::TYPE_EXPIRED => 'bg-gray-100 text-gray-800',
            self::TYPE_ADJUSTED => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
