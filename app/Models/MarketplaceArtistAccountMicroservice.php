<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceArtistAccountMicroservice extends Model
{
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIAL = 'trial';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUSPENDED = 'suspended';

    public const GRANTED_ADMIN_OVERRIDE = 'admin_override';
    public const GRANTED_SELF_PURCHASE = 'self_purchase';
    public const GRANTED_TRIAL = 'trial';

    protected $fillable = [
        'marketplace_artist_account_id',
        'microservice_id',
        'status',
        'granted_by',
        'granted_by_user_id',
        'service_order_id',
        'activated_at',
        'trial_ends_at',
        'expires_at',
        'cancelled_at',
        'settings',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'settings' => 'array',
    ];

    public function artistAccount(): BelongsTo
    {
        return $this->belongsTo(MarketplaceArtistAccount::class, 'marketplace_artist_account_id');
    }

    public function microservice(): BelongsTo
    {
        return $this->belongsTo(Microservice::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * Admin user (panel /admin) care a făcut admin_override.
     * Pentru self_purchase / trial returnează null.
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * Considerăm serviciul activ dacă:
     *  - status active SAU trial SAU cancelled (cancelled păstrează acces până la expires_at)
     *  - expires_at e null sau în viitor
     *  - trial_ends_at e null sau în viitor (când suntem în trial)
     */
    public function isAccessGranted(): bool
    {
        if (!in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_TRIAL,
            self::STATUS_CANCELLED,
        ], true)) {
            return false;
        }

        if ($this->status === self::STATUS_TRIAL) {
            if ($this->trial_ends_at && $this->trial_ends_at->isPast()) {
                return false;
            }
            return true;
        }

        // active / cancelled — verificăm expires_at (null = nelimitat, ex admin_override)
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIAL, self::STATUS_CANCELLED])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
