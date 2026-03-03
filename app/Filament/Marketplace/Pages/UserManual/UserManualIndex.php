<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class UserManualIndex extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Manual Utilizator';

    protected static \UnitEnum|string|null $navigationGroup = 'Help';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'manual';

    protected string $view = 'filament.marketplace.pages.user-manual.index';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    public function switchLocale(string $locale): void
    {
        $this->locale = in_array($locale, ['ro', 'en']) ? $locale : 'ro';
    }

    public function t(array|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value[$this->locale] ?? $value['ro'] ?? $value['en'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->locale === 'ro' ? 'Manual Utilizator' : 'User Manual';
    }

    public function getViewData(): array
    {
        return [
            'modules' => $this->getAllModules(),
            'locale' => $this->locale,
        ];
    }

    protected function getAllModules(): array
    {
        return [
            [
                'class' => DashboardManual::class,
                'icon' => 'heroicon-o-home',
                'title' => ['ro' => 'Dashboard', 'en' => 'Dashboard'],
                'description' => ['ro' => 'Prezentare generala KPI-uri, grafice si statistici', 'en' => 'KPI overview, charts and statistics'],
            ],
            [
                'class' => EventsManual::class,
                'icon' => 'heroicon-o-calendar',
                'title' => ['ro' => 'Evenimente', 'en' => 'Events'],
                'description' => ['ro' => 'Creare, editare, bilete, program, categorii', 'en' => 'Create, edit, tickets, schedule, categories'],
            ],
            [
                'class' => VenuesManual::class,
                'icon' => 'heroicon-o-building-office',
                'title' => ['ro' => 'Locatii', 'en' => 'Venues'],
                'description' => ['ro' => 'Creare, editare, parteneri, categorii', 'en' => 'Create, edit, partners, categories'],
            ],
            [
                'class' => ArtistsManual::class,
                'icon' => 'heroicon-o-user-group',
                'title' => ['ro' => 'Artisti', 'en' => 'Artists'],
                'description' => ['ro' => 'Creare, editare, genuri, parteneriate', 'en' => 'Create, edit, genres, partnerships'],
            ],
            [
                'class' => OrganizersManual::class,
                'icon' => 'heroicon-o-briefcase',
                'title' => ['ro' => 'Organizatori', 'en' => 'Organizers'],
                'description' => ['ro' => 'Creare, editare, comisioane, documente, plati', 'en' => 'Create, edit, commissions, documents, payouts'],
            ],
            [
                'class' => OrdersManual::class,
                'icon' => 'heroicon-o-shopping-cart',
                'title' => ['ro' => 'Comenzi', 'en' => 'Orders'],
                'description' => ['ro' => 'Vizualizare comenzi, detalii, bilete, plati', 'en' => 'View orders, details, tickets, payments'],
            ],
            [
                'class' => TicketsManual::class,
                'icon' => 'heroicon-o-ticket',
                'title' => ['ro' => 'Bilete', 'en' => 'Tickets'],
                'description' => ['ro' => 'Vizualizare bilete, statusuri, coduri', 'en' => 'View tickets, statuses, codes'],
            ],
            [
                'class' => CustomersManual::class,
                'icon' => 'heroicon-o-users',
                'title' => ['ro' => 'Clienti', 'en' => 'Customers'],
                'description' => ['ro' => 'Creare, editare, istoric comenzi', 'en' => 'Create, edit, order history'],
            ],
            [
                'class' => CommunicationsManual::class,
                'icon' => 'heroicon-o-envelope',
                'title' => ['ro' => 'Comunicare', 'en' => 'Communications'],
                'description' => ['ro' => 'Email template-uri, newslettere, liste de contact', 'en' => 'Email templates, newsletters, contact lists'],
            ],
            [
                'class' => CouponsManual::class,
                'icon' => 'heroicon-o-receipt-percent',
                'title' => ['ro' => 'Cupoane', 'en' => 'Coupons'],
                'description' => ['ro' => 'Campanii cupoane, coduri de reducere', 'en' => 'Coupon campaigns, discount codes'],
            ],
            [
                'class' => SettingsManual::class,
                'icon' => 'heroicon-o-cog-6-tooth',
                'title' => ['ro' => 'Setari', 'en' => 'Settings'],
                'description' => ['ro' => 'Setari marketplace, domenii, plati, utilizatori', 'en' => 'Marketplace settings, domains, payments, users'],
            ],
            [
                'class' => ReportsManual::class,
                'icon' => 'heroicon-o-chart-bar',
                'title' => ['ro' => 'Rapoarte', 'en' => 'Reports'],
                'description' => ['ro' => 'Venituri, rapoarte fiscale, balante organizatori', 'en' => 'Income, tax reports, organizer balances'],
            ],
        ];
    }
}
