<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletPass extends Model
{
    use HasFactory;

    protected $table = 'wallet_passes';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'order_id',
        'platform',
        'pass_identifier',
        'serial_number',
        'auth_token',
        'push_token',
        'last_updated_at',
        'voided_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    // Constants
    const PLATFORM_APPLE = 'apple';
    const PLATFORM_GOOGLE = 'google';

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pushRegistrations(): HasMany
    {
        return $this->hasMany(WalletPushRegistration::class, 'pass_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WalletPassUpdate::class, 'pass_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function scopeVoided($query)
    {
        return $query->whereNotNull('voided_at');
    }

    // Helper methods
    public function isApple(): bool
    {
        return $this->platform === self::PLATFORM_APPLE;
    }

    public function isGoogle(): bool
    {
        return $this->platform === self::PLATFORM_GOOGLE;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function void(): void
    {
        $this->update(['voided_at' => now()]);
    }

    public function markUpdated(): void
    {
        $this->update(['last_updated_at' => now()]);
    }

    /**
     * Check if pass exists for ticket and platform
     */
    public static function existsForTicket(int $ticketId, string $platform): bool
    {
        return static::where('ticket_id', $ticketId)
            ->where('platform', $platform)
            ->whereNull('voided_at')
            ->exists();
    }
}
