<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookieConsentHistory extends Model
{
    /**
     * Change types
     */
    public const TYPE_INITIAL = 'initial';
    public const TYPE_UPDATE = 'update';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_RENEWAL = 'renewal';

    /**
     * Disable default timestamps as we use changed_at
     */
    public $timestamps = false;

    protected $table = 'cookie_consent_history';

    protected $fillable = [
        'cookie_consent_id',
        'previous_analytics',
        'previous_marketing',
        'previous_preferences',
        'new_analytics',
        'new_marketing',
        'new_preferences',
        'change_type',
        'ip_address',
        'user_agent',
        'change_source',
        'changed_at',
    ];

    protected $casts = [
        'previous_analytics' => 'boolean',
        'previous_marketing' => 'boolean',
        'previous_preferences' => 'boolean',
        'new_analytics' => 'boolean',
        'new_marketing' => 'boolean',
        'new_preferences' => 'boolean',
        'changed_at' => 'datetime',
    ];

    /**
     * Parent consent record
     */
    public function consent(): BelongsTo
    {
        return $this->belongsTo(CookieConsent::class, 'cookie_consent_id');
    }

    /**
     * Get changes as array
     */
    public function getChangesArray(): array
    {
        $changes = [];

        if ($this->previous_analytics !== $this->new_analytics) {
            $changes['analytics'] = [
                'from' => $this->previous_analytics,
                'to' => $this->new_analytics,
            ];
        }

        if ($this->previous_marketing !== $this->new_marketing) {
            $changes['marketing'] = [
                'from' => $this->previous_marketing,
                'to' => $this->new_marketing,
            ];
        }

        if ($this->previous_preferences !== $this->new_preferences) {
            $changes['preferences'] = [
                'from' => $this->previous_preferences,
                'to' => $this->new_preferences,
            ];
        }

        return $changes;
    }

    /**
     * Check if this was an opt-in action
     */
    public function isOptIn(): bool
    {
        return ($this->new_analytics && !$this->previous_analytics)
            || ($this->new_marketing && !$this->previous_marketing)
            || ($this->new_preferences && !$this->previous_preferences);
    }

    /**
     * Check if this was an opt-out action
     */
    public function isOptOut(): bool
    {
        return (!$this->new_analytics && $this->previous_analytics)
            || (!$this->new_marketing && $this->previous_marketing)
            || (!$this->new_preferences && $this->previous_preferences);
    }
}
