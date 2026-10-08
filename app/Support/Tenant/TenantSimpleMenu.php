<?php

namespace App\Support\Tenant;

use App\Models\Tenant;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

/**
 * „Meniul simplu” al panoului de tenant: pentru un organizator care vinde doar bilete la evenimentele
 * lui, meniul complet (zeci de intrări, multe în engleză) e redus la ce folosește zi de zi, cu etichete
 * în română și grupat pe sarcini.
 *
 * Se activează per tenant, din settings.panel.simple_menu = true. Nimic nu e șters sau blocat:
 * paginile ascunse rămân accesibile prin adresă, iar utilizatorul poate trece oricând la meniul complet
 * din pagina „Primii pași” (alegerea ține cât sesiunea). Tenanții fără această setare nu sunt atinși.
 */
class TenantSimpleMenu
{
    public const PANEL = 'tenant';
    public const SESSION_FULL = 'tenant_menu_full';

    private const SALES = 'Vânzări';
    private const PROMO = 'Promovare';
    private const TICKETS = 'Bilete și săli';
    private const ACCOUNT = 'Cont';

    /**
     * Calea paginii => [etichetă, grup, ordine]. Mai multe căi cu aceeași cheie de „alternativă”
     * (al patrulea element) înseamnă: păstrează prima care există în meniu.
     */
    private const MAP = [
        '/tenant/start'              => ['Primii pași', null, 1],
        '/tenant/dashboard'          => ['Panou de control', null, 2],
        '/tenant/events'             => ['Competiții', null, 3],

        '/tenant/orders'             => ['Comenzi', self::SALES, 10],
        '/tenant/tickets'            => ['Bilete vândute', self::SALES, 11],
        '/tenant/customers'          => ['Clienți', self::SALES, 12],
        '/tenant/tax-reports'        => ['Rapoarte fiscale', self::SALES, 13],

        '/tenant/coupon-codes-list'  => ['Coduri de reducere', self::PROMO, 20, 'coupons'],
        '/tenant/coupon-codes'       => ['Coduri de reducere', self::PROMO, 20, 'coupons'],
        '/tenant/invitations'        => ['Invitații', self::PROMO, 21],
        '/tenant/tracking-settings'  => ['Tracking și pixeli', self::PROMO, 22],
        '/tenant/shop-products'      => ['Magazin', self::PROMO, 23],

        '/tenant/ticket-templates'   => ['Design bilet', self::TICKETS, 30, 'ticket-design'],
        '/tenant/ticket-customizer'  => ['Design bilet', self::TICKETS, 30, 'ticket-design'],
        '/tenant/seating-layouts'    => ['Hărți de sală', self::TICKETS, 31],
        '/tenant/fiscal-templates'   => ['Șabloane documente', self::TICKETS, 32],

        '/tenant/settings'           => ['Setări', self::ACCOUNT, 40],
        '/tenant/microservices'      => ['Servicii active', self::ACCOUNT, 41],
        '/tenant/invoices'           => ['Facturi Tixello', self::ACCOUNT, 42],
        '/tenant/profile'            => ['Profilul meu', self::ACCOUNT, 43],
    ];

    /** Tenantul utilizatorului autentificat în panou. */
    public static function tenant(): ?Tenant
    {
        try {
            $tenant = auth()->user()?->tenant;

            return $tenant instanceof Tenant ? $tenant : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Tenantul are meniul simplu configurat (deci vede și pagina „Primii pași”). */
    public static function configured(?Tenant $tenant = null): bool
    {
        $tenant ??= self::tenant();
        $panel = $tenant && is_array($tenant->settings) ? ($tenant->settings['panel'] ?? null) : null;

        return is_array($panel) && ! empty($panel['simple_menu']);
    }

    /** Meniul simplu e cel afișat acum (configurat și nu s-a cerut meniul complet în această sesiune). */
    public static function active(?Tenant $tenant = null): bool
    {
        if (! self::configured($tenant)) {
            return false;
        }
        try {
            return ! session()->get(self::SESSION_FULL, false);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Aplică meniul simplu peste intrările înregistrate de Filament.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, NavigationGroup>|null  grupurile în ordinea dorită, sau null dacă meniul rămâne neschimbat
     */
    public static function apply(array $items): ?array
    {
        if (! self::active()) {
            return null;
        }

        // Ce căi există efectiv în meniu (pentru alternative)
        $present = [];
        foreach ($items as $item) {
            if ($item instanceof NavigationItem) {
                $present[self::path($item->getUrl())] = true;
            }
        }
        $taken = [];
        $keep = [];
        foreach (self::MAP as $path => $def) {
            if (! isset($present[$path])) {
                continue;
            }
            $alt = $def[3] ?? null;
            if ($alt !== null) {
                if (isset($taken[$alt])) {
                    continue;
                }
                $taken[$alt] = true;
            }
            $keep[$path] = $def;
        }

        foreach ($items as $item) {
            if (! $item instanceof NavigationItem) {
                continue;
            }
            $def = $keep[self::path($item->getUrl())] ?? null;
            if ($def === null) {
                $item->visible(false);

                continue;
            }
            // Fără părinte: o intrare păstrată nu trebuie să dispară odată cu părintele ei ascuns
            $item->label($def[0])->group($def[1])->sort($def[2])->parentItem(null);
        }

        return [
            NavigationGroup::make(self::SALES),
            NavigationGroup::make(self::PROMO),
            NavigationGroup::make(self::TICKETS),
            NavigationGroup::make(self::ACCOUNT),
        ];
    }

    private static function path(?string $url): string
    {
        $path = (string) parse_url((string) $url, PHP_URL_PATH);

        return rtrim($path, '/') ?: '/';
    }
}
