<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;

/**
 * Setarile circuitului Sanius pe un eveniment. Implicit oprit (mode = off):
 * fara un rand aici sau cu mode = off, niciun endpoint al circuitului nu raspunde.
 */
class SaniusSetting extends Model
{
    public const MODES = ['off', 'test', 'live'];

    public const ROLES = ['desk', 'tow', 'cashier', 'manager'];

    public const PRODUCT_KEYS = [
        'rent', 'tow', 'damage_small', 'damage_medium', 'damage_large', 'lost', 'parking_car', 'parking_bus',
    ];

    public const PARKING_DEFAULTS = [
        'free_minutes' => 10,
        'free_minutes_with_access' => 240,
        'unit_minutes' => 30,
    ];

    protected $table = 'sanius_settings';

    protected $fillable = [
        'event_id', 'marketplace_organizer_id', 'mode', 'members', 'included_tows',
        'products', 'parking', 'ping_timeout_seconds',
    ];

    protected $casts = [
        'members' => 'array',
        'products' => 'array',
        'parking' => 'array',
        'included_tows' => 'integer',
        'ping_timeout_seconds' => 'integer',
    ];

    public function isOff(): bool
    {
        return ! in_array($this->mode, ['test', 'live'], true);
    }

    public function isTestMode(): bool
    {
        return $this->mode === 'test';
    }

    /** Rolul unui membru in circuit (null = nu are rol). */
    public function roleOf(?int $memberId): ?string
    {
        if (! $memberId) {
            return null;
        }
        foreach ((array) $this->members as $m) {
            if ((int) ($m['member_id'] ?? 0) === $memberId) {
                $role = $m['role'] ?? null;

                return in_array($role, self::ROLES, true) ? $role : null;
            }
        }

        return null;
    }

    public function productId(string $key): ?int
    {
        $id = $this->products[$key] ?? null;

        return $id ? (int) $id : null;
    }

    public function parkingRules(): array
    {
        $own = array_filter((array) $this->parking, fn ($v) => $v !== null && $v !== '');

        return array_map('intval', array_merge(self::PARKING_DEFAULTS, array_intersect_key($own, self::PARKING_DEFAULTS)));
    }
}
