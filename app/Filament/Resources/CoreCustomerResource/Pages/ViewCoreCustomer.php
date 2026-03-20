<?php

namespace App\Filament\Resources\CoreCustomerResource\Pages;

use App\Filament\Resources\CoreCustomerResource;
use App\Models\MarketplaceCustomer;
use App\Models\Platform\CoreCustomer;
use App\Services\CustomerInsightsService;
use Filament\Resources\Pages\ViewRecord;

class ViewCoreCustomer extends ViewRecord
{
    protected static string $resource = CoreCustomerResource::class;

    // MarketplaceCustomer insights data
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
    public bool $hasMarketplaceData = false;
    public ?int $linkedMktCustomerId = null;

    public function mount($record): void
    {
        parent::mount($record);

        /** @var CoreCustomer $coreCustomer */
        $coreCustomer = $this->record;

        // Try to find linked MarketplaceCustomer via email
        $email = $coreCustomer->email;
        if ($email && is_string($email)) {
            $mkCustomer = MarketplaceCustomer::where('email', strtolower(trim($email)))->first();
            if ($mkCustomer) {
                $this->linkedMktCustomerId = $mkCustomer->id;
                $this->hasMarketplaceData = true;
                $this->loadMarketplaceData($mkCustomer);
            }
        }
    }

    protected function loadMarketplaceData(MarketplaceCustomer $customer): void
    {
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
        $name = $this->record->full_name ?? '';
        $label = $name !== '' ? $name : ($this->record->email ?? 'Customer');
        return "Customer: {$label}";
    }

    public function getView(): string
    {
        return 'filament.resources.core-customer.pages.view-core-customer';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make()
                ->label('Edit Tags'),
        ];
    }
}
