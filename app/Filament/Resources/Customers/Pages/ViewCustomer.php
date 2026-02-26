<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerInsightsService;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    // ─── Data exposed to blade ────────────────────────────────────
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
    public array $gamification = [];
    public array $monthlyOrders = [];
    public array $monthlyChart = [];
    public array $recentEvents = [];
    public array $topArtists = [];
    public array $tenantsList = [];
    public array $trackingData = [];

    public function mount($record): void
    {
        parent::mount($record);

        /** @var Customer $customer */
        $customer = $this->record;
        $service = CustomerInsightsService::forCustomer($customer);

        // Lifetime stats
        $this->lifetimeStats = $service->lifetimeStats();
        $this->priceRange = $service->priceRange();
        $this->orderStatusBreakdown = $service->orderStatusBreakdown();

        // Insights
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

        // History
        $this->ordersList = $service->ordersList();
        $this->ticketsList = $service->ticketsList();
        $this->attendees = $service->attendees();
        $this->emailLogs = $service->emailLogs();

        // Gamification
        $this->gamification = $service->gamificationData();

        // Chart + existing stats
        $this->monthlyChart = $service->monthlyOrdersCurrentYear();
        $this->monthlyOrders = $service->monthlyOrders();
        $this->recentEvents = $service->recentEvents();
        $this->topArtists = $service->topArtists();
        $this->tenantsList = $service->tenantsList();

        // CoreCustomer tracking data (Meta, Google, TikTok)
        $this->trackingData = $service->trackingData();
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
        return 'filament.customers.pages.view-customer';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('edit')
                ->label('Editează')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => CustomerResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
