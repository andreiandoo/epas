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
        'cycle_weekday', 'cycle_weekend', 'tow', 'damage_small', 'damage_medium', 'damage_large', 'lost', 'parking_car', 'parking_bus',
    ];

    public const PARKING_DEFAULTS = [
        'free_minutes' => 10,
        'free_minutes_with_access' => 240,
        'unit_minutes' => 30,
    ];

    /** Chiria pe cicluri: durata ciclului, tractari incluse pe ciclu, toleranta la returnare. */
    public const RENTAL_DEFAULTS = [
        'cycle_minutes' => 60,
        'tows_per_cycle' => 1,
        'grace_minutes' => 10,
    ];

    public const CONTRACT_DEFAULT_TITLE = 'Contract de închiriere sănii și asumare a răspunderii';

    /** Text provizoriu, pana vine contractul tipizat folosit azi la Sf. Ana. */
    public const CONTRACT_DEFAULT_BODY = 'Subsemnatul/Subsemnata {nume}, act de identitate {act_tip} seria {act_serie} nr. {act_numar}, telefon {telefon}, '
        ."închiriez în data de {data}, ora {ora}, sănii de la {organizator}, pe tichetul de parcare nr. {tichet_parcare}.\n\n"
        ."Declar că am primit săniile în stare bună și că le voi folosi doar pe traseul amenajat, conform regulilor afișate la ghișeu.\n\n"
        .'Răspund pentru săniile primite până la predarea lor la ghișeu. Mă oblig să achit la casierie chiria, depășirile de timp, '
        .'tractările peste cele incluse și, dacă este cazul, contravaloarea avariilor sau a saniei pierdute, conform tarifelor afișate.';

    protected $table = 'sanius_settings';

    protected $fillable = [
        'event_id', 'marketplace_organizer_id', 'mode', 'members', 'included_tows',
        'products', 'parking', 'ping_timeout_seconds', 'rental', 'contract',
    ];

    protected $casts = [
        'members' => 'array',
        'products' => 'array',
        'parking' => 'array',
        'rental' => 'array',
        'contract' => 'array',
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

    /** @return array{cycle_minutes: int, tows_per_cycle: int, grace_minutes: int, weekend_extra_dates: string[]} */
    public function rentalRules(): array
    {
        $own = (array) $this->rental;
        $rules = self::RENTAL_DEFAULTS;
        foreach (array_keys(self::RENTAL_DEFAULTS) as $k) {
            if (isset($own[$k]) && $own[$k] !== '' && is_numeric($own[$k])) {
                $rules[$k] = (int) $own[$k];
            }
        }
        $rules['cycle_minutes'] = max(1, $rules['cycle_minutes']);
        $rules['tows_per_cycle'] = max(0, $rules['tows_per_cycle']);
        $rules['grace_minutes'] = max(0, $rules['grace_minutes']);
        $rules['weekend_extra_dates'] = self::cleanDates($own['weekend_extra_dates'] ?? []);

        return $rules;
    }

    /** @return string[] zile Y-m-d, unice, sortate */
    public static function cleanDates(mixed $dates): array
    {
        $out = [];
        foreach ((array) $dates as $d) {
            $d = substr(trim((string) $d), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
                $out[$d] = true;
            }
        }
        $out = array_keys($out);
        sort($out);

        return $out;
    }

    /** @return array{required: bool, title: string, body: string, version: int, stations: string[]} */
    public function contractRules(): array
    {
        $own = (array) $this->contract;
        $stations = array_values(array_filter(array_map(
            fn ($s) => mb_substr(trim((string) $s), 0, 40),
            (array) ($own['stations'] ?? [])
        ), fn ($s) => $s !== ''));

        return [
            'required' => (bool) ($own['required'] ?? false),
            'title' => trim((string) ($own['title'] ?? '')) ?: self::CONTRACT_DEFAULT_TITLE,
            'body' => trim((string) ($own['body'] ?? '')) ?: self::CONTRACT_DEFAULT_BODY,
            'version' => max(1, (int) ($own['version'] ?? 1)),
            'stations' => $stations ?: ['Casa 1'],
        ];
    }
}
