<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'secret_key',
        'description',
        'permissions',
        'is_active',
        'require_signature',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'require_signature' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'secret_key' => 'encrypted',
    ];

    protected $hidden = [
        'key',
        'secret_key',
    ];

    public static function generate(string $name, ?string $description = null, ?array $permissions = null, bool $requireSignature = false): self
    {
        return self::create([
            'name' => $name,
            'key' => 'pk_' . Str::random(61),
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

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
