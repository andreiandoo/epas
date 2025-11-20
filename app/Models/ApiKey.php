<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'permissions',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    public static function generate(string $name, ?string $description = null, ?array $permissions = null): self
    {
        return self::create([
            'name' => $name,
            'key' => Str::random(64),
            'description' => $description,
            'permissions' => $permissions,
        ]);
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
