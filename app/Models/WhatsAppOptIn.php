<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppOptIn extends Model
{
    use HasFactory;

    protected $table = 'wa_optin';

    protected $fillable = [
        'tenant_id',
        'user_ref',
        'phone_e164',
        'status',
        'source',
        'consented_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'consented_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_OPT_IN = 'opt_in';
    const STATUS_OPT_OUT = 'opt_out';

    /**
     * Check if user has opted in
     */
    public function isOptedIn(): bool
    {
        return $this->status === self::STATUS_OPT_IN;
    }

    /**
     * Opt in the user
     */
    public function optIn(string $source, array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_OPT_IN,
            'source' => $source,
            'consented_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Opt out the user
     */
    public function optOut(): void
    {
        $this->update([
            'status' => self::STATUS_OPT_OUT,
        ]);
    }

    /**
     * Find or create opt-in record
     */
    public static function findOrCreateForPhone(string $tenantId, string $phoneE164, string $userRef = null): self
    {
        return static::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_e164' => $phoneE164,
            ],
            [
                'user_ref' => $userRef,
                'status' => self::STATUS_OPT_OUT, // Default to opt-out
            ]
        );
    }

    /**
     * Check if phone has opted in for tenant
     */
    public static function hasOptedIn(string $tenantId, string $phoneE164): bool
    {
        $record = static::where('tenant_id', $tenantId)
            ->where('phone_e164', $phoneE164)
            ->first();

        return $record && $record->isOptedIn();
    }

    /**
     * Scope: Opted-in users for tenant
     */
    public function scopeOptedIn($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_OPT_IN);
    }

    /**
     * Normalize phone to E.164 format
     */
    public static function normalizePhone(string $phone, string $defaultCountryCode = '+40'): string
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // If doesn't start with +, add default country code
        if (!str_starts_with($phone, '+')) {
            // If starts with 0, remove it (Romanian format)
            if (str_starts_with($phone, '0')) {
                $phone = substr($phone, 1);
            }
            $phone = $defaultCountryCode . $phone;
        }

        return $phone;
    }
}
