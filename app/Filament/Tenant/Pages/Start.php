<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Coupon\CouponCode;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TicketTemplate;
use App\Models\TicketType;
use App\Support\Tenant\TenantSimpleMenu;
use BackedEnum;
use Filament\Pages\Page;

/**
 * „Primii pași”: pagina de pornire a panoului pentru tenanții cu meniul simplu
 * (settings.panel.simple_menu). Arată ce mai are de făcut organizatorul până la prima vânzare,
 * cu legături directe, și de aici se trece între meniul simplu și cel complet.
 */
class Start extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Primii pași';
    protected static ?int $navigationSort = 0;
    protected static ?string $slug = 'start';
    protected string $view = 'filament.tenant.pages.start';

    public ?Tenant $tenant = null;

    public static function shouldRegisterNavigation(): bool
    {
        return TenantSimpleMenu::configured();
    }

    public static function canAccess(): bool
    {
        return TenantSimpleMenu::configured();
    }

    public function mount(): void
    {
        $this->tenant = TenantSimpleMenu::tenant();
    }

    public function getTitle(): string
    {
        return 'Primii pași';
    }

    /** Trece între meniul simplu și cel complet (ține cât sesiunea). */
    public function toggleMenu(): void
    {
        if (session()->get(TenantSimpleMenu::SESSION_FULL, false)) {
            session()->forget(TenantSimpleMenu::SESSION_FULL);
        } else {
            session()->put(TenantSimpleMenu::SESSION_FULL, true);
        }

        $this->redirect(static::getUrl());
    }

    public function getViewData(): array
    {
        $tenant = $this->tenant;
        if (! $tenant) {
            return ['tenant' => null, 'steps' => [], 'done' => 0, 'simple' => true, 'siteUrl' => null, 'shortcuts' => []];
        }

        $count = function (callable $fn): int {
            try {
                return (int) $fn();
            } catch (\Throwable) {
                return 0;
            }
        };

        $eventIds = Event::where('tenant_id', $tenant->id)->pluck('id');
        $events = $eventIds->count();
        $ticketTypes = $count(fn () => TicketType::whereIn('event_id', $eventIds)->where('status', 'active')->count());
        $paidOrders = $count(fn () => Order::where('tenant_id', $tenant->id)->whereIn('status', ['paid', 'confirmed', 'completed'])->count());
        $templates = $count(fn () => TicketTemplate::where('tenant_id', $tenant->id)->where('status', 'active')->count());
        $coupons = $count(fn () => CouponCode::where('tenant_id', $tenant->id)->count());
        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        $domain = null;
        try {
            $domain = $tenant->domains()->where('is_active', true)->orderByDesc('is_primary')->value('domain');
        } catch (\Throwable) {
            $domain = null;
        }
        $siteUrl = $domain ? 'https://' . $domain : null;

        $steps = [
            [
                'title' => 'Creează o competiție',
                'text'  => 'Titlu, dată, locație și afiș. Competiția apare pe site imediat ce o publici.',
                'done'  => $events > 0,
                'state' => $events > 0 ? ($events === 1 ? 'Ai o competiție' : "Ai {$events} competiții") : null,
                'url'   => '/tenant/events',
                'cta'   => $events > 0 ? 'Vezi competițiile' : 'Adaugă competiția',
            ],
            [
                'title' => 'Pune bilete în vânzare',
                'text'  => 'Tipuri de bilet cu preț și număr de locuri sau, pentru sălile cu locuri numerotate, o hartă de sală.',
                'done'  => $ticketTypes > 0,
                'state' => $ticketTypes > 0 ? "{$ticketTypes} tipuri de bilet active" : null,
                'url'   => '/tenant/events',
                'cta'   => 'Deschide o competiție',
            ],
            [
                'title' => 'Alege cine plătește taxa de procesare',
                'text'  => 'Taxa procesatorului de plăți poate rămâne la tine sau poate fi adăugată la totalul cumpărătorului.',
                'done'  => array_key_exists('payment_fees', $settings),
                'state' => ! empty($settings['payment_fees']['pass_to_customer']) ? 'O plătește cumpărătorul' : (array_key_exists('payment_fees', $settings) ? 'O plătești tu' : null),
                'url'   => '/tenant/settings',
                'cta'   => 'Setări → Plăți',
            ],
            [
                'title' => 'Personalizează biletul',
                'text'  => 'Biletul în culorile tale, cu siglă și cod QR. Îl modifici într-un editor vizual.',
                'done'  => $templates > 0,
                'state' => $templates > 0 ? 'Ai un șablon activ' : null,
                'url'   => '/tenant/ticket-templates',
                'cta'   => 'Design bilet',
            ],
            [
                'title' => 'Pregătește un cod de reducere',
                'text'  => 'Opțional: coduri cu procent sau sumă fixă, pentru cluburi, sponsori sau campanii.',
                'done'  => $coupons > 0,
                'state' => $coupons > 0 ? ($coupons === 1 ? 'Ai un cod' : "Ai {$coupons} coduri") : null,
                'url'   => '/tenant/coupon-codes-list',
                'cta'   => 'Coduri de reducere',
            ],
            [
                'title' => 'Fă o comandă de test',
                'text'  => 'Cumpără un bilet de pe site ca să vezi exact ce vede un spectator: coș, plată, email și bilet.',
                'done'  => $paidOrders > 0,
                'state' => $paidOrders > 0 ? ($paidOrders === 1 ? 'O comandă plătită' : "{$paidOrders} comenzi plătite") : null,
                'url'   => $siteUrl ?: '/tenant/orders',
                'cta'   => $siteUrl ? 'Deschide site-ul' : 'Vezi comenzile',
                'external' => (bool) $siteUrl,
            ],
        ];

        return [
            'tenant'  => $tenant,
            'steps'   => $steps,
            'done'    => count(array_filter($steps, fn ($s) => $s['done'])),
            'simple'  => TenantSimpleMenu::active($tenant),
            'siteUrl' => $siteUrl,
            'shortcuts' => [
                ['Comenzi', 'Cine a cumpărat, cât și când; retrimiți biletele de aici.', '/tenant/orders'],
                ['Bilete vândute', 'Fiecare bilet, cu starea lui și codul de acces.', '/tenant/tickets'],
                ['Clienți', 'Lista cumpărătorilor, cu istoricul comenzilor.', '/tenant/customers'],
                ['Invitații', 'Bilete gratuite pentru oficiali, sponsori și presă.', '/tenant/invitations'],
                ['Hărți de sală', 'Tribunele și locurile numerotate ale unei săli.', '/tenant/seating-layouts'],
                ['Setări', 'Datele organizației, plăți, emailuri și domeniu.', '/tenant/settings'],
            ],
        ];
    }
}
