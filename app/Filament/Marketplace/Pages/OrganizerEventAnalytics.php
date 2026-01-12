<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Event;
use App\Models\EventMilestone;
use App\Services\Analytics\EventAnalyticsService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\BuyerJourneyService;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class OrganizerEventAnalytics extends Page implements HasForms
{
    use HasMarketplaceContext;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Event Analytics';
    protected static bool $shouldRegisterNavigation = false; // Accessed via event resource

    protected static string $view = 'filament.marketplace.pages.organizer-event-analytics';

    public ?int $eventId = null;
    public ?Event $event = null;
    public string $period = '30d';
    public string $eventMode = 'live';

    // Data
    public array $dashboardData = [];
    public array $milestones = [];
    public array $recentSales = [];

    // Modals
    public bool $showMilestoneModal = false;
    public bool $showMilestoneDetailModal = false;
    public bool $showBuyerJourneyModal = false;
    public bool $showGlobeModal = false;

    public ?array $selectedMilestone = null;
    public ?array $selectedBuyer = null;

    // Milestone form
    public ?array $milestoneData = [];

    protected EventAnalyticsService $analyticsService;
    protected MilestoneAttributionService $attributionService;
    protected BuyerJourneyService $journeyService;

    public function boot(
        EventAnalyticsService $analyticsService,
        MilestoneAttributionService $attributionService,
        BuyerJourneyService $journeyService
    ): void {
        $this->analyticsService = $analyticsService;
        $this->attributionService = $attributionService;
        $this->journeyService = $journeyService;
    }

    public function mount(?int $event = null): void
    {
        $this->eventId = $event ?? request()->query('event');

        if (!$this->eventId) {
            // Redirect to events list if no event specified
            $this->redirect(route('filament.marketplace.resources.events.index'));
            return;
        }

        $this->event = Event::with(['venue', 'ticketTypes', 'marketplaceOrganizer'])
            ->find($this->eventId);

        if (!$this->event) {
            Notification::make()
                ->danger()
                ->title('Event Not Found')
                ->send();
            $this->redirect(route('filament.marketplace.resources.events.index'));
            return;
        }

        // Check authorization
        $this->authorizeAccess();

        // Determine event mode
        $this->eventMode = $this->event->isPast() ? 'past' : 'live';

        // Load initial data
        $this->loadDashboardData();
    }

    protected function authorizeAccess(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            abort(403);
        }

        // Check if event belongs to this marketplace
        if ($this->event->marketplace_client_id !== $marketplace->id) {
            abort(403);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event?->title ?? 'Event Analytics';
    }

    public function getSubheading(): ?string
    {
        if (!$this->event) return null;

        return $this->event->start_date?->format('d M Y') . ' - ' . ($this->event->venue?->name ?? 'TBA');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMilestone')
                ->label('Add Milestone')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn () => $this->eventMode === 'live')
                ->action(fn () => $this->showMilestoneModal = true),

            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->eventMode === 'past')
                ->action(fn () => $this->exportReport()),

            Action::make('refreshData')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadDashboardData()),
        ];
    }

    public function loadDashboardData(): void
    {
        if (!$this->event) return;

        $this->dashboardData = $this->analyticsService->getDashboardData($this->event, $this->period);
        $this->milestones = $this->analyticsService->getMilestonesWithMetrics($this->event);
        $this->recentSales = $this->analyticsService->getRecentSales($this->event, 10);
    }

    public function updatedPeriod(): void
    {
        $this->loadDashboardData();
    }

    public function switchEventMode(string $mode): void
    {
        $this->eventMode = $mode;
    }

    /* Milestone methods */

    protected function getMilestoneFormSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Milestone Type')
                ->options(EventMilestone::TYPE_LABELS)
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required(),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->visible(fn (Forms\Get $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES)),
                ]),

            Forms\Components\TextInput::make('budget')
                ->label('Budget (RON)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Forms\Get $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES)),

            Forms\Components\TextInput::make('targeting')
                ->label('Targeting')
                ->placeholder('e.g., 18-35, Music lovers, Romania')
                ->visible(fn (Forms\Get $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES)),

            Forms\Components\Textarea::make('description')
                ->label('Notes')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\Section::make('UTM Parameters')
                ->description('Auto-generated if left empty')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('utm_source')
                                ->label('UTM Source'),
                            Forms\Components\TextInput::make('utm_medium')
                                ->label('UTM Medium'),
                            Forms\Components\TextInput::make('utm_campaign')
                                ->label('UTM Campaign'),
                            Forms\Components\TextInput::make('utm_content')
                                ->label('UTM Content'),
                        ]),
                ]),
        ];
    }

    public function createMilestone(): void
    {
        $data = $this->milestoneData;

        $milestone = new EventMilestone($data);
        $milestone->event_id = $this->event->id;
        $milestone->tenant_id = $this->event->tenant_id;
        $milestone->created_by = auth()->id();
        $milestone->is_active = true;
        $milestone->autoGenerateUtmParameters();
        $milestone->save();

        // Calculate initial impact
        if (!$milestone->isAdCampaign()) {
            $this->attributionService->calculateMilestoneImpact($milestone);
        }

        $this->showMilestoneModal = false;
        $this->milestoneData = [];

        Notification::make()
            ->success()
            ->title('Milestone Created')
            ->body("Tracking URL: " . $milestone->generateTrackingUrl(url('/event/' . $this->event->slug)))
            ->send();

        $this->loadDashboardData();
    }

    public function openMilestoneDetail(int $milestoneId): void
    {
        $milestone = collect($this->milestones)->firstWhere('id', $milestoneId);
        if ($milestone) {
            $this->selectedMilestone = $milestone;
            $this->showMilestoneDetailModal = true;
        }
    }

    /* Buyer Journey methods */

    public function openBuyerJourney(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if ($order && $order->marketplace_event_id === $this->event->id) {
            $this->selectedBuyer = $this->journeyService->getOrderJourney($order);
            $this->showBuyerJourneyModal = true;
        }
    }

    /* Globe methods */

    public function openGlobeModal(): void
    {
        $this->showGlobeModal = true;
    }

    public function getGlobeData(): array
    {
        return $this->analyticsService->getLiveVisitorsForGlobe($this->event);
    }

    public function getLiveVisitorCount(): int
    {
        $data = $this->analyticsService->getLiveVisitors($this->event);
        return $data['count'] ?? 0;
    }

    /* Export */

    public function exportReport(): void
    {
        // TODO: Implement PDF/Excel export
        Notification::make()
            ->info()
            ->title('Export Started')
            ->body('Your report is being generated.')
            ->send();
    }

    /* View data methods */

    public function getOverviewStats(): array
    {
        return $this->dashboardData['overview'] ?? [];
    }

    public function getChartData(): array
    {
        return $this->dashboardData['chart_data'] ?? [];
    }

    public function getTicketPerformance(): array
    {
        return $this->dashboardData['ticket_performance'] ?? [];
    }

    public function getTrafficSources(): array
    {
        return $this->dashboardData['traffic_sources'] ?? [];
    }

    public function getTopLocations(): array
    {
        return $this->dashboardData['top_locations'] ?? [];
    }

    public function getFunnelMetrics(): array
    {
        return $this->dashboardData['funnel'] ?? [];
    }

    public function getAdCampaigns(): array
    {
        return collect($this->milestones)
            ->filter(fn ($m) => in_array($m['type'], EventMilestone::AD_CAMPAIGN_TYPES) && $m['budget'])
            ->values()
            ->toArray();
    }

    public function getTotalAdSpend(): float
    {
        return collect($this->getAdCampaigns())->sum('budget') ?? 0;
    }

    /* Helpers */

    public function formatCurrency(float $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 2) . 'M RON';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 0) . 'K RON';
        }
        return number_format($value, 0) . ' RON';
    }

    public function getMilestoneIcon(string $type): string
    {
        return EventMilestone::TYPE_ICONS[$type] ?? '...';
    }

    public function getMilestoneIconClass(string $type): string
    {
        return match ($type) {
            'campaign_fb' => 'bg-blue-100',
            'campaign_google' => 'bg-red-100',
            'campaign_tiktok' => 'bg-pink-100',
            'campaign_instagram' => 'bg-fuchsia-100',
            'email' => 'bg-amber-100',
            'price' => 'bg-emerald-100',
            'announcement' => 'bg-purple-100',
            'press' => 'bg-cyan-100',
            'lineup' => 'bg-rose-100',
            default => 'bg-gray-100',
        };
    }

    public function getSourceClass(string $source): string
    {
        return match ($source) {
            'Facebook' => 'bg-blue-100 text-blue-700',
            'Google' => 'bg-red-100 text-red-700',
            'Instagram' => 'bg-pink-100 text-pink-700',
            'TikTok' => 'bg-purple-100 text-purple-700',
            'Email' => 'bg-amber-100 text-amber-700',
            'Direct' => 'bg-gray-100 text-gray-700',
            default => 'bg-green-100 text-green-700',
        };
    }
}
