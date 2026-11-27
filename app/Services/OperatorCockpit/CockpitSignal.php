<?php

namespace App\Services\OperatorCockpit;

use App\Models\MarketplaceClient;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Semnal „s-a schimbat ceva” pe canalul privat cockpit.{marketplaceClientId}.
 * Nu duce date: browserul operatorului își recere starea prin /marketplace/cockpit/poll,
 * unde se aplică regulile de vizibilitate. Orice eroare de broadcast e înghițită,
 * ca să nu strice salvarea unui tichet sau a unui eveniment.
 */
class CockpitSignal
{
    /** @var array<string, bool> semnale deja programate în request-ul curent */
    private static array $pending = [];

    public static function changed(?int $marketplaceClientId, string $category): void
    {
        if (! $marketplaceClientId || config('broadcasting.default') !== 'reverb') {
            return;
        }

        $key = $marketplaceClientId . ':' . $category;
        if (isset(self::$pending[$key])) {
            return;
        }
        self::$pending[$key] = true;

        try {
            DB::afterCommit(function () use ($marketplaceClientId, $category, $key) {
                $send = function () use ($marketplaceClientId, $category, $key) {
                    unset(self::$pending[$key]);
                    self::send($marketplaceClientId, $category);
                };

                if (app()->runningInConsole()) {
                    $send();
                } else {
                    \Illuminate\Support\defer($send);
                }
            });
        } catch (\Throwable $e) {
            unset(self::$pending[$key]);
            report($e);
        }
    }

    private static function send(int $marketplaceClientId, string $category): void
    {
        try {
            if (! self::enabled($marketplaceClientId)) {
                return;
            }

            // Cel mult un semnal pe secundă per marketplace și categorie (importuri, acțiuni în masă).
            if (! Cache::add('cockpit-signal:' . $marketplaceClientId . ':' . $category, 1, 1)) {
                return;
            }

            Broadcast::private('cockpit.' . $marketplaceClientId)
                ->as('changed')
                ->with(['category' => $category])
                ->sendNow();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function enabled(int $marketplaceClientId): bool
    {
        return Cache::remember('cockpit-enabled:' . $marketplaceClientId, 300, function () use ($marketplaceClientId) {
            return AttentionService::isEnabledFor(MarketplaceClient::find($marketplaceClientId));
        });
    }
}
