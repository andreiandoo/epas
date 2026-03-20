<?php

namespace App\Filament\Resources\CoreCustomerResource\Pages;

use App\Filament\Resources\CoreCustomerResource;
use App\Models\MarketplaceCustomer;
use App\Models\Platform\CoreCustomer;
use App\Services\CustomerInsightsService;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class ViewCoreCustomer extends Page
{
    protected static string $resource = CoreCustomerResource::class;

    protected string $view = 'filament.resources.core-customer.pages.view-core-customer';

    public CoreCustomer $record;

    // MarketplaceCustomer insights data
    public ?MarketplaceCustomer $linkedMarketplaceCustomer = null;
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

    public function mount($record): void
    {
        $this->record = CoreCustomer::findOrFail($record);

        // Try to find linked MarketplaceCustomer via email
        $email = $this->record->email;
        if ($email) {
            $this->linkedMarketplaceCustomer = MarketplaceCustomer::where('email', strtolower(trim($email)))->first();
        }

        if ($this->linkedMarketplaceCustomer) {
            $this->hasMarketplaceData = true;
            $customer = $this->linkedMarketplaceCustomer;

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
    }

    public function getTitle(): string
    {
        $name = $this->record->full_name ?? '';
        $label = $name !== '' ? $name : ($this->record->email ?? 'Customer');
        return "Customer: {$label}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Edit Tags & Notes')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => CoreCustomerResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
