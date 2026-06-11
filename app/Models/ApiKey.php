<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'key',
        'key_hash',
        'secret_key',
        'description',
        'permissions',
        'is_active',
        'require_signature',
        'last_used_at',
        'last_used_ip',
        'total_requests',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'require_signature' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'secret_key' => 'encrypted',
        'total_requests' => 'integer',
    ];

    protected $hidden = [
        'key',
        'secret_key',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate key_hash when creating
        static::creating(function ($model) {
            if ($model->key && !$model->key_hash) {
                $model->key_hash = hash('sha256', $model->key);
            }
        });

        // Update key_hash when key changes
        static::updating(function ($model) {
            if ($model->isDirty('key') && $model->key) {
                $model->key_hash = hash('sha256', $model->key);
            }
        });
    }

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Find API key by its value (using hash for security)
     */
    public static function findByKey(string $key): ?self
    {
        $hash = hash('sha256', $key);

        // First try to find by hash (preferred)
        $apiKey = self::where('key_hash', $hash)->first();

        // Fallback to plaintext comparison for backwards compatibility
        // This should be removed after all keys have been migrated
        if (!$apiKey) {
            $apiKey = self::where('key', $key)->first();

            // If found by plaintext, update the hash
            if ($apiKey && !$apiKey->key_hash) {
                $apiKey->update(['key_hash' => $hash]);
            }
        }

        return $apiKey;
    }

    /**
     * Generate a new API key
     */
    public static function generate(
        string $name,
        ?string $description = null,
        ?array $permissions = null,
        bool $requireSignature = false,
        ?int $tenantId = null
    ): self {
        $key = 'pk_' . Str::random(61);

        return self::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key' => $key,
            'key_hash' => hash('sha256', $key),
            'secret_key' => 'sk_' . Str::random(61),
            'description' => $description,
            'permissions' => $permissions,
            'require_signature' => $requireSignature,
        ]);
    }

    /**
     * Verify HMAC signature
     */
    public function verifySignature(string $signature, int $timestamp, string $path): bool
    {
        // Check timestamp is within 5 minutes
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        // Generate expected signature
        $payload = $timestamp . $path;
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret_key);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Regenerate secret key
     */
    public function regenerateSecretKey(): string
    {
        $newSecret = 'sk_' . Str::random(61);
        $this->update(['secret_key' => $newSecret]);
        return $newSecret;
    }

    /**
     * Check if the API key is valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record API key usage with IP tracking
     */
    public function recordUsage(?string $ip = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'total_requests' => $this->total_requests + 1,
        ]);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get active keys only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
