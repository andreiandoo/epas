<?php

namespace App\Filament\Resources\MarketplaceCustomerAdminResource\Pages;

use App\Filament\Resources\MarketplaceCustomerAdminResource;
use App\Models\MarketplaceCustomer;
use App\Services\CustomerInsightsService;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceCustomer extends ViewRecord
{
    protected static string $resource = MarketplaceCustomerAdminResource::class;

    // ─── Data exposed to blade (same as marketplace view) ──────────
    public array $lifetimeStats = [];
    public array $priceRange = [];
    public array $orderStatusBreakdown = [];
    public array $venueTypes = [];
    public array $artistGenres = [];
    public array $eventTypes = [];
    public array $eventGenres = [];
    public array $eventTags = [];
    public array $preferredDays = [];
    public array $preferredCities = [];
    public array $preferredStartTimes = [];
    public array $preferredMonths = [];
    public array $preferredMonthPeriods = [];
    public array $ordersList = [];
    public array $ticketsList = [];
    public array $attendees = [];
    public array $emailLogs = [];
    public array $monthlyOrders = [];
    public array $monthlyChart = [];
    public array $recentEvents = [];
    public array $topArtists = [];
    public array $tenantsList = [];
    public array $gamification = [];
    public string $profileNarrative = '';
    public array $weightedProfileData = [];
    public array $favoritesProfile = [];
    public array $notificationPreferences = [];
    public ?string $emailVerifiedDisplay = null;
    public bool $acceptsMarketingDisplay = false;

    public function mount($record): void
    {
        parent::mount($record);

        /** @var MarketplaceCustomer $customer */
        $customer = $this->record;

        $this->emailVerifiedDisplay = $customer->email_verified_at
            ? 'Da (' . $customer->email_verified_at->format('d.m.Y') . ')' : 'Nu';
        $this->acceptsMarketingDisplay = (bool) $customer->accepts_marketing;

        $prefs = $customer->settings['notification_preferences'] ?? [];
        $this->notificationPreferences = [
            'Event Reminders' => $prefs['reminders'] ?? false,
            'Newsletter & Offers' => $prefs['newsletter'] ?? false,
            'Favorite Updates' => $prefs['favorites'] ?? false,
            'Browsing History' => $prefs['history'] ?? false,
            'Marketing Cookies' => $prefs['marketing'] ?? false,
        ];

        $service = CustomerInsightsService::forMarketplaceCustomer($customer);

        $this->profileNarrative = $service->generateProfileNarrative();
        $this->lifetimeStats = $service->lifetimeStats();
        $this->priceRange = $service->priceRange();
        $this->orderStatusBreakdown = $service->orderStatusBreakdown();
        $this->venueTypes = $service->venueTypes();
        $this->artistGenres = $service->artistGenres();
        $this->eventTypes = $service->eventTypes();
        $this->eventGenres = $service->eventGenres();
        $this->eventTags = $service->eventTags();
        $this->preferredDays = $service->preferredDays();
        $this->preferredCities = $service->preferredCities();
        $this->preferredStartTimes = $service->preferredStartTimes();
        $this->preferredMonths = $service->preferredMonths();
        $this->preferredMonthPeriods = $service->preferredMonthPeriods();
        $this->favoritesProfile = $service->favoritesProfile();
        $this->weightedProfileData = $service->weightedProfile();
        $this->ordersList = $service->ordersList();
        $this->ticketsList = $service->ticketsList();
        $this->attendees = $service->attendees();
        $this->emailLogs = $service->emailLogs();
        $this->gamification = $service->gamificationData();
        $this->monthlyChart = $service->monthlyOrdersCurrentYear();
        $this->monthlyOrders = $service->monthlyOrders();
        $this->recentEvents = $service->recentEvents();
        $this->topArtists = $service->topArtists();
        $this->tenantsList = $service->tenantsList();
    }

    public function getTitle(): string
    {
        $name = $this->record->full_name
            ?? trim(($this->record->first_name ?? '') . ' ' . ($this->record->last_name ?? ''));
        $label = $name !== '' ? $name : ($this->record->email ?? 'Customer');

        return "Profil Client: {$label}";
    }

    public function getView(): string
    {
        // Reuse the same blade view as the marketplace panel
        return 'filament.marketplace-customers.pages.view-marketplace-customer';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('edit')
                ->label('Editează')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => MarketplaceCustomerAdminResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
