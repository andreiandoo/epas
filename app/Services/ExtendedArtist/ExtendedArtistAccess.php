<?php

namespace App\Services\ExtendedArtist;

use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceArtistAccountMicroservice;
use App\Models\Microservice;
use Illuminate\Support\Facades\DB;

/**
 * Sursa unica de adevar pentru "are artistul X acces la Extended Artist?".
 *
 * Acoperă toate cele 3 căi de activare:
 *  - admin_override (manual, fără expirare)
 *  - self_purchase (plătit, recurring; acces până la expires_at)
 *  - trial (gratuit 30 zile; acces până la trial_ends_at)
 *
 * Folosit de:
 *  - middleware-ul RequireExtendedArtist (gate API-uri module)
 *  - controllers (afisare status in portal artist)
 *  - Filament resource (UI status pe pagina /marketplace/artist-accounts/{id})
 */
class ExtendedArtistAccess
{
    public const SLUG = 'extended-artist';
    public const TRIAL_DAYS_DEFAULT = 30;

    public const MODULE_FAN_CRM = 'fan_crm';
    public const MODULE_BOOKING = 'booking_marketplace';
    public const MODULE_EPK = 'smart_epk';
    public const MODULE_TOUR = 'tour_optimizer';

    public const ALL_MODULES = [
        self::MODULE_FAN_CRM,
        self::MODULE_BOOKING,
        self::MODULE_EPK,
        self::MODULE_TOUR,
    ];

    public function isEnabledFor(MarketplaceArtistAccount $account): bool
    {
        $row = $account->extendedArtistActivation();
        return $row !== null && $row->isAccessGranted();
    }

    /**
     * Verifica daca microserviciul Extended Artist este activat la nivel
     * marketplace_client. Daca nu e activ pentru marketplace, niciun cont
     * artist din acel marketplace nu poate primi serviciul (UI-ul de pe
     * artist-account nu va arata sectiunea, iar self-purchase va esua).
     */
    public function isAvailableForMarketplace(?int $marketplaceClientId): bool
    {
        if (!$marketplaceClientId) {
            return false;
        }

        return DB::table('marketplace_client_microservices as mcm')
            ->join('microservices as m', 'm.id', '=', 'mcm.microservice_id')
            ->where('mcm.marketplace_client_id', $marketplaceClientId)
            ->where('m.slug', self::SLUG)
            ->where('mcm.status', 'active')
            ->exists();
    }

    /**
     * Stare detaliata pentru UI:
     * [
     *   'enabled' => bool,
     *   'status' => string|null,
     *   'granted_by' => string|null,
     *   'trial_ends_at' => ISO|null,
     *   'expires_at' => ISO|null,
     *   'can_start_trial' => bool,
     *   'modules' => string[],   // module accesibile
     * ]
     */
    public function statusFor(MarketplaceArtistAccount $account): array
    {
        $row = $account->extendedArtistActivation();
        $enabled = $row && $row->isAccessGranted();

        return [
            'enabled' => $enabled,
            'status' => $row?->status,
            'granted_by' => $row?->granted_by,
            'trial_ends_at' => $row?->trial_ends_at?->toIso8601String(),
            'expires_at' => $row?->expires_at?->toIso8601String(),
            'cancelled_at' => $row?->cancelled_at?->toIso8601String(),
            'can_start_trial' => $this->canStartTrial($account),
            'modules' => $enabled ? self::ALL_MODULES : [],
        ];
    }

    public function modulesFor(MarketplaceArtistAccount $account): array
    {
        return $this->isEnabledFor($account) ? self::ALL_MODULES : [];
    }

    /**
     * Trial-ul se poate porni o singura data per cont. Verificam absenta
     * unui rand cu granted_by=trial in pivot (indiferent de status curent).
     */
    public function canStartTrial(MarketplaceArtistAccount $account): bool
    {
        if (!$this->isAvailableForMarketplace($account->marketplace_client_id)) {
            return false;
        }

        $microservice = $this->microservice();
        if (!$microservice) {
            return false;
        }

        // Daca exista deja un rand pivot cu acest microserviciu — fie e activ
        // (deci n-are sens trial), fie a folosit deja trial-ul.
        $existing = MarketplaceArtistAccountMicroservice::query()
            ->where('marketplace_artist_account_id', $account->id)
            ->where('microservice_id', $microservice->id)
            ->first();

        return $existing === null;
    }

    public function microservice(): ?Microservice
    {
        return Microservice::where('slug', self::SLUG)->first();
    }

    public function trialDays(): int
    {
        $microservice = $this->microservice();
        $configured = $microservice?->metadata['trial_days'] ?? null;
        return is_int($configured) && $configured > 0 ? $configured : self::TRIAL_DAYS_DEFAULT;
    }
}
