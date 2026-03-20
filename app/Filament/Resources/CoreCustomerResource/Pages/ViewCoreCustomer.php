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

    public int $recordId;

    // MarketplaceCustomer insights data
    public ?int $linkedMarketplaceCustomerId = null;
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

    public function getRecordProperty(): CoreCustomer
    {
        return CoreCustomer::findOrFail($this->recordId);
    }

    public function getLinkedMarketplaceCustomerProperty(): ?MarketplaceCustomer
    {
        return $this->linkedMarketplaceCustomerId
            ? MarketplaceCustomer::find($this->linkedMarketplaceCustomerId)
            : null;
    }

    public function mount($record): void
    {
        $this->recordId = $record instanceof CoreCustomer ? $record->id : (int) $record;
        $coreCustomer = $this->record;

        // Try to find linked MarketplaceCustomer via email
        $email = $coreCustomer->email;
        if ($email && is_string($email)) {
            $mkCustomer = MarketplaceCustomer::where('email', strtolower(trim($email)))->first();
            $this->linkedMarketplaceCustomerId = $mkCustomer?->id;
        }

        if ($this->linkedMarketplaceCustomerId) {
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
        $c = $this->record;
        $name = $c->full_name ?? '';
        $label = $name !== '' ? $name : ($c->email ?? 'Customer');
        return "Customer: {$label}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Edit Tags & Notes')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => CoreCustomerResource::getUrl('edit', ['record' => $this->recordId])),
        ];
    }
}
