<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantVerificationCode extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'tenant_type',
        'code',
        'entity_name',
        'matched_entity_id',
        'matched_entity_type',
        'status',
        'verified_at',
        'verified_by',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the matched entity (polymorphic manual lookup).
     */
    public function matchedEntity(): ?Model
    {
        if (!$this->matched_entity_type || !$this->matched_entity_id) {
            return null;
        }

        return $this->matched_entity_type::find($this->matched_entity_id);
    }

    /**
     * Generate a unique verification code like TXV-A8K3M2
     */
    public static function generateCode(): string
    {
        do {
            $code = 'TXV-' . strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if the code is still valid (not expired and pending).
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    /**
     * Mark as verified.
     */
    public function markVerified(string $verifiedBy = 'manual'): void
    {
        $this->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);
    }

    /**
     * Scope for pending codes.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
