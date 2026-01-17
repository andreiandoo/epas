<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\EventGoal;
use App\Models\EventMilestone;
use App\Models\EventReportSchedule;
use App\Services\Analytics\EventAnalyticsService;
use App\Services\Analytics\EventExportService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\BuyerJourneyService;
use App\Services\Analytics\ScheduledReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

class EventAnalytics extends Page implements HasForms
{
    use InteractsWithRecord;
    use HasMarketplaceContext;
    use InteractsWithForms;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Event Analytics';

    protected string $view = 'filament.marketplace.resources.event-resource.pages.event-analytics';

    public ?int $eventId = null;
    public Event|MarketplaceEvent|null $event = null;
    public string $period = '30d';
    public string $eventMode = 'live';

    // Data
    public array $dashboardData = [];
    public array $milestones = [];
    public array $recentSales = [];
    public array $goals = [];
    public array $reportSchedules = [];

    // Modals
    public bool $showMilestoneModal = false;
    public bool $showMilestoneDetailModal = false;
    public bool $showBuyerJourneyModal = false;
    public bool $showGlobeModal = false;
    public bool $showGoalModal = false;
    public bool $showExportModal = false;
    public bool $showScheduleModal = false;

    public ?array $selectedMilestone = null;
    public ?array $selectedBuyer = null;
    public ?int $editingGoalId = null;
    public ?int $editingScheduleId = null;

    // Milestone form
    public ?array $milestoneData = [];

    // Goal form
    public ?array $goalData = [
        'type' => 'tickets',
        'target_value' => null,
        'alert_thresholds' => [25, 50, 75, 90, 100],
        'email_alerts' => true,
        'in_app_alerts' => true,
    ];

    // Export form
    public string $exportFormat = 'csv';
    public array $exportSections = ['overview', 'traffic', 'milestones', 'goals'];

    // Schedule form
    public ?array $scheduleData = [
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'day_of_month' => 1,
        'send_at' => '09:00',
        'recipients' => [],
        'sections' => ['overview', 'chart', 'traffic', 'milestones', 'goals'],
        'format' => 'email',
        'include_comparison' => true,
    ];

    protected EventAnalyticsService $analyticsService;
    protected EventExportService $exportService;
    protected MilestoneAttributionService $attributionService;
    protected BuyerJourneyService $journeyService;
    protected ScheduledReportService $scheduledReportService;

    public function boot(
        EventAnalyticsService $analyticsService,
        EventExportService $exportService,
        MilestoneAttributionService $attributionService,
        BuyerJourneyService $journeyService,
        ScheduledReportService $scheduledReportService
    ): void {
        $this->analyticsService = $analyticsService;
        $this->exportService = $exportService;
        $this->attributionService = $attributionService;
        $this->journeyService = $journeyService;
        $this->scheduledReportService = $scheduledReportService;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->event = $this->record;
        $this->eventId = $this->record?->id;

        if (!$this->event) {
            Notification::make()
                ->danger()
                ->title('Event Not Found')
                ->send();
            $this->redirect(EventResource::getUrl('index'));
            return;
        }

        // Check authorization
        $this->authorizeAccess();

        // Determine event mode
        $this->eventMode = $this->event->starts_at?->isPast() ? 'past' : 'live';

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
        $name = $this->event?->name ?? $this->event?->title ?? 'Event Analytics';
        // Handle translatable fields (JSON arrays)
        if (is_array($name)) {
            $locale = app()->getLocale();
            $name = $name[$locale] ?? $name['ro'] ?? $name['en'] ?? reset($name) ?: 'Event Analytics';
        }
        return $name;
    }

    public function getSubheading(): ?string
    {
        if (!$this->event) return null;

        $venueName = $this->event->venue_name ?? $this->event->venue?->name ?? 'TBA';
        // Handle translatable fields (JSON arrays)
        if (is_array($venueName)) {
            $locale = app()->getLocale();
            $venueName = $venueName[$locale] ?? $venueName['ro'] ?? $venueName['en'] ?? reset($venueName) ?: 'TBA';
        }
        return $this->event->starts_at?->format('d M Y') . ' - ' . $venueName;
    }

    public function getBreadcrumb(): string
    {
        return 'Analytics';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addGoal')
                ->label('Add Goal')
                ->icon('heroicon-o-flag')
                ->color('success')
                ->action(fn () => $this->openGoalModal()),

            Action::make('addMilestone')
                ->label('Add Milestone')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn () => $this->eventMode === 'live')
                ->modalHeading('Add Campaign Milestone')
                ->modalWidth('lg')
                ->form($this->getMilestoneFormSchema())
                ->action(function (array $data) {
                    $milestone = new EventMilestone($data);
                    $milestone->event_id = $this->event->id;
                    $milestone->tenant_id = $this->event->tenant_id;
                    $milestone->save();

                    $this->loadDashboardData();

                    Notification::make()
                        ->success()
                        ->title('Milestone created successfully')
                        ->send();
                }),

            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->showExportModal = true),

            Action::make('scheduleReports')
                ->label('Schedule Reports')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->action(fn () => $this->openScheduleModal()),

            Action::make('refreshData')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadDashboardData()),

            Action::make('back_to_edit')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function loadDashboardData(): void
    {
        if (!$this->event) return;

        $this->dashboardData = $this->analyticsService->getDashboardData($this->event, $this->period);
        $this->milestones = $this->analyticsService->getMilestonesWithMetrics($this->event);
        $this->recentSales = $this->analyticsService->getRecentSales($this->event, 10);
        $this->loadGoals();
        $this->loadReportSchedules();
    }

    protected function loadGoals(): void
    {
        $this->goals = EventGoal::where('event_id', $this->event->id)
            ->orderBy('type')
            ->get()
            ->map(fn ($goal) => [
                'id' => $goal->id,
                'type' => $goal->type,
                'type_label' => $goal->type_label,
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'current_value' => $goal->current_value,
                'formatted_target' => $goal->formatted_target,
                'formatted_current' => $goal->formatted_current,
                'progress_percent' => $goal->progress_percent,
                'status' => $goal->status,
                'progress_status' => $goal->progress_status,
                'deadline' => $goal->deadline?->format('Y-m-d'),
                'days_remaining' => $goal->days_remaining,
                'email_alerts' => $goal->email_alerts,
                'in_app_alerts' => $goal->in_app_alerts,
                'alert_thresholds' => $goal->alert_thresholds,
            ])
            ->toArray();
    }

    protected function loadReportSchedules(): void
    {
        $this->reportSchedules = EventReportSchedule::where('event_id', $this->event->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'frequency' => $schedule->frequency,
                'frequency_label' => $schedule->frequency_label,
                'recipients' => $schedule->recipients,
                'sections' => $schedule->sections,
                'format' => $schedule->format,
                'include_comparison' => $schedule->include_comparison,
                'is_active' => $schedule->is_active,
                'next_send_at' => $schedule->next_send_at?->format('M d, Y H:i'),
                'last_sent_at' => $schedule->last_sent_at?->format('M d, Y H:i'),
            ])
            ->toArray();
    }

    public function updatedPeriod(): void
    {
        $this->loadDashboardData();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->loadDashboardData();
    }

    /**
     * Get dashboard data for Alpine.js without full re-render
     */
    #[Renderless]
    public function fetchDashboardData(string $period): array
    {
        $this->period = $period;
        $this->loadDashboardData();

        return [
            'period' => $this->period,
            'overview' => $this->getOverviewStats(),
            'chartData' => $this->getChartData(),
            'ticketPerformance' => $this->getTicketPerformance(),
            'trafficSources' => $this->getTrafficSources(),
            'topLocations' => $this->getTopLocations(),
            'milestones' => $this->milestones,
            'recentSales' => $this->recentSales,
        ];
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

            Forms\Components\DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->columnSpan(1),

            Forms\Components\DatePicker::make('end_date')
                ->label('End Date')
                ->visible(fn (SGet $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES))
                ->columnSpan(1),

            Forms\Components\TextInput::make('budget')
                ->label('Budget (RON)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (SGet $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES)),

            Forms\Components\TextInput::make('targeting')
                ->label('Targeting')
                ->placeholder('e.g., 18-35, Music lovers, Romania')
                ->visible(fn (SGet $get) => in_array($get('type'), EventMilestone::AD_CAMPAIGN_TYPES)),

            Forms\Components\Textarea::make('description')
                ->label('Notes')
                ->rows(2)
                ->columnSpanFull(),

            SC\Section::make('UTM Parameters')
                ->description('Auto-generated if left empty')
                ->collapsible()
                ->collapsed()
                ->columns(2)
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

    #[On('open-buyer-journey')]
    public function openBuyerJourney(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            return;
        }

        // Check ownership based on event type
        $isMarketplace = $this->event instanceof MarketplaceEvent;
        $ownsOrder = $isMarketplace
            ? $order->marketplace_event_id === $this->event->id
            : $order->event_id === $this->event->id;

        if ($ownsOrder) {
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

    /* Goals methods */

    public function openGoalModal(?int $goalId = null): void
    {
        $this->editingGoalId = $goalId;

        if ($goalId) {
            $goal = EventGoal::find($goalId);
            if ($goal) {
                $this->goalData = [
                    'type' => $goal->type,
                    'name' => $goal->name,
                    'target_value' => $goal->type === EventGoal::TYPE_REVENUE
                        ? $goal->target_value / 100
                        : ($goal->type === EventGoal::TYPE_CONVERSION ? $goal->target_value / 100 : $goal->target_value),
                    'deadline' => $goal->deadline?->format('Y-m-d'),
                    'alert_thresholds' => $goal->alert_thresholds,
                    'email_alerts' => $goal->email_alerts,
                    'in_app_alerts' => $goal->in_app_alerts,
                    'alert_email' => $goal->alert_email,
                ];
            }
        } else {
            $this->goalData = [
                'type' => 'tickets',
                'name' => null,
                'target_value' => null,
                'deadline' => null,
                'alert_thresholds' => [25, 50, 75, 90, 100],
                'email_alerts' => true,
                'in_app_alerts' => true,
                'alert_email' => null,
            ];
        }

        $this->showGoalModal = true;
    }

    public function saveGoal(): void
    {
        $data = $this->goalData;

        // Convert target value for storage
        $targetValue = match ($data['type']) {
            EventGoal::TYPE_REVENUE => (int) ($data['target_value'] * 100), // cents
            EventGoal::TYPE_CONVERSION => (int) ($data['target_value'] * 100), // basis points
            default => (int) $data['target_value'],
        };

        $goalData = [
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'target_value' => $targetValue,
            'deadline' => $data['deadline'] ?? null,
            'alert_thresholds' => $data['alert_thresholds'] ?? EventGoal::DEFAULT_THRESHOLDS,
            'email_alerts' => $data['email_alerts'] ?? true,
            'in_app_alerts' => $data['in_app_alerts'] ?? true,
            'alert_email' => $data['alert_email'] ?? null,
        ];

        if ($this->editingGoalId) {
            $goal = EventGoal::find($this->editingGoalId);
            if ($goal) {
                $goal->update($goalData);
                $goal->load('event');
                $goal->updateProgress();
            }
            $message = 'Goal updated successfully';
        } else {
            $goal = EventGoal::create(array_merge($goalData, [
                'event_id' => $this->event->id,
            ]));
            $goal->load('event');
            $goal->updateProgress();
            $message = 'Goal created successfully';
        }

        $this->showGoalModal = false;
        $this->editingGoalId = null;
        $this->loadGoals();

        Notification::make()
            ->success()
            ->title($message)
            ->send();
    }

    public function deleteGoal(int $goalId): void
    {
        EventGoal::where('id', $goalId)
            ->where('event_id', $this->event->id)
            ->delete();

        $this->loadGoals();

        Notification::make()
            ->success()
            ->title('Goal deleted')
            ->send();
    }

    public function refreshGoalProgress(int $goalId): void
    {
        $goal = EventGoal::with('event')->find($goalId);
        if ($goal) {
            $goal->updateProgress();
            $this->loadGoals();
        }
    }

    protected function getGoalFormSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Goal Type')
                ->options([
                    EventGoal::TYPE_REVENUE => 'Revenue Target',
                    EventGoal::TYPE_TICKETS => 'Tickets Target',
                    EventGoal::TYPE_VISITORS => 'Visitors Target',
                    EventGoal::TYPE_CONVERSION => 'Conversion Rate Target',
                ])
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('name')
                ->label('Goal Name (Optional)')
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('target_value')
                ->label(fn (SGet $get) => match ($get('type')) {
                    'revenue' => 'Target Revenue (RON)',
                    'tickets' => 'Target Tickets',
                    'visitors' => 'Target Visitors',
                    'conversion_rate' => 'Target Conversion Rate (%)',
                    default => 'Target Value',
                })
                ->numeric()
                ->required()
                ->minValue(0),

            Forms\Components\DatePicker::make('deadline')
                ->label('Deadline (Optional)'),

            Forms\Components\Section::make('Alerts')
                ->schema([
                    Forms\Components\CheckboxList::make('alert_thresholds')
                        ->label('Alert at milestones')
                        ->options([
                            25 => '25%',
                            50 => '50%',
                            75 => '75%',
                            90 => '90%',
                            100 => '100% (Goal achieved!)',
                        ])
                        ->columns(5),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('email_alerts')
                                ->label('Email Alerts'),
                            Forms\Components\Toggle::make('in_app_alerts')
                                ->label('In-App Alerts'),
                        ]),

                    Forms\Components\TextInput::make('alert_email')
                        ->label('Alert Email (Optional)')
                        ->email()
                        ->helperText('Leave empty to use organizer email'),
                ]),
        ];
    }

    /* Export methods */

    public function exportToCsv(): void
    {
        try {
            $filepath = $this->exportService->exportToCsv($this->event, [
                'period' => $this->period,
                'sections' => $this->exportSections,
            ]);

            Notification::make()
                ->success()
                ->title('CSV Export Ready')
                ->body('Your export has been generated.')
                ->send();

            $this->showExportModal = false;
            $this->redirect($this->exportService->getDownloadUrl($filepath));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Export Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function exportToPdf(): void
    {
        try {
            $filepath = $this->exportService->exportToPdf($this->event, [
                'period' => $this->period,
                'sections' => $this->exportSections,
                'include_comparison' => true,
            ]);

            Notification::make()
                ->success()
                ->title('PDF Export Ready')
                ->body('Your export has been generated.')
                ->send();

            $this->showExportModal = false;
            $this->redirect($this->exportService->getDownloadUrl($filepath));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Export Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function exportSales(): void
    {
        try {
            $dateRange = $this->analyticsService->getDateRange($this->period);
            $filepath = $this->exportService->exportSalesToCsv($this->event, $dateRange);

            Notification::make()
                ->success()
                ->title('Sales Export Ready')
                ->send();

            $this->showExportModal = false;
            $this->redirect($this->exportService->getDownloadUrl($filepath));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Export Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    /* Report Schedule methods */

    public function openScheduleModal(?int $scheduleId = null): void
    {
        $this->editingScheduleId = $scheduleId;

        if ($scheduleId) {
            $schedule = EventReportSchedule::find($scheduleId);
            if ($schedule) {
                $this->scheduleData = [
                    'frequency' => $schedule->frequency,
                    'day_of_week' => $schedule->day_of_week,
                    'day_of_month' => $schedule->day_of_month,
                    'send_at' => $schedule->send_at?->format('H:i'),
                    'recipients' => $schedule->recipients,
                    'sections' => $schedule->sections,
                    'format' => $schedule->format,
                    'include_comparison' => $schedule->include_comparison,
                    'is_active' => $schedule->is_active,
                ];
            }
        } else {
            $organizer = $this->event->marketplaceOrganizer;
            $this->scheduleData = [
                'frequency' => 'weekly',
                'day_of_week' => 1,
                'day_of_month' => 1,
                'send_at' => '09:00',
                'recipients' => [$organizer?->email ?? auth()->user()->email],
                'sections' => EventReportSchedule::DEFAULT_SECTIONS,
                'format' => 'email',
                'include_comparison' => true,
                'is_active' => true,
            ];
        }

        $this->showScheduleModal = true;
    }

    public function saveSchedule(): void
    {
        $data = $this->scheduleData;

        $scheduleData = [
            'frequency' => $data['frequency'],
            'day_of_week' => $data['frequency'] === 'weekly' ? ($data['day_of_week'] ?? 1) : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? ($data['day_of_month'] ?? 1) : null,
            'send_at' => $data['send_at'] ?? '09:00:00',
            'timezone' => 'Europe/Bucharest',
            'recipients' => array_filter(array_map('trim', (array) $data['recipients'])),
            'sections' => $data['sections'] ?? EventReportSchedule::DEFAULT_SECTIONS,
            'format' => $data['format'] ?? 'email',
            'include_comparison' => $data['include_comparison'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($this->editingScheduleId) {
            $schedule = EventReportSchedule::find($this->editingScheduleId);
            if ($schedule) {
                $schedule->update($scheduleData);
                $schedule->calculateNextSendAt();
                $schedule->save();
            }
            $message = 'Report schedule updated';
        } else {
            $schedule = EventReportSchedule::create(array_merge($scheduleData, [
                'event_id' => $this->event->id,
                'marketplace_organizer_id' => $this->event->marketplace_organizer_id,
            ]));
            $schedule->calculateNextSendAt();
            $schedule->save();
            $message = 'Report schedule created';
        }

        $this->showScheduleModal = false;
        $this->editingScheduleId = null;
        $this->loadReportSchedules();

        Notification::make()
            ->success()
            ->title($message)
            ->send();
    }

    public function deleteSchedule(int $scheduleId): void
    {
        EventReportSchedule::where('id', $scheduleId)
            ->where('event_id', $this->event->id)
            ->delete();

        $this->loadReportSchedules();

        Notification::make()
            ->success()
            ->title('Report schedule deleted')
            ->send();
    }

    public function toggleScheduleActive(int $scheduleId): void
    {
        $schedule = EventReportSchedule::find($scheduleId);
        if ($schedule && $schedule->event_id === $this->event->id) {
            $schedule->update(['is_active' => !$schedule->is_active]);
            $this->loadReportSchedules();
        }
    }

    public function sendTestReport(int $scheduleId): void
    {
        try {
            $schedule = EventReportSchedule::find($scheduleId);
            if ($schedule && $schedule->event_id === $this->event->id) {
                $this->scheduledReportService->sendScheduledReport($schedule);

                Notification::make()
                    ->success()
                    ->title('Test report sent')
                    ->body('Check your email for the report.')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Failed to send report')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getScheduleFormSchema(): array
    {
        return [
            Forms\Components\Select::make('frequency')
                ->label('Frequency')
                ->options([
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                ])
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\Select::make('day_of_week')
                ->label('Day of Week')
                ->options([
                    0 => 'Sunday',
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                ])
                ->visible(fn (SGet $get) => $get('frequency') === 'weekly'),

            Forms\Components\Select::make('day_of_month')
                ->label('Day of Month')
                ->options(array_combine(range(1, 28), range(1, 28)))
                ->visible(fn (SGet $get) => $get('frequency') === 'monthly'),

            Forms\Components\TimePicker::make('send_at')
                ->label('Send At')
                ->seconds(false)
                ->required(),

            Forms\Components\TagsInput::make('recipients')
                ->label('Recipients')
                ->placeholder('Add email...')
                ->required()
                ->columnSpanFull(),

            Forms\Components\CheckboxList::make('sections')
                ->label('Report Sections')
                ->options([
                    'overview' => 'Overview Stats',
                    'chart' => 'Performance Chart',
                    'traffic' => 'Traffic Sources',
                    'milestones' => 'Campaigns & Milestones',
                    'goals' => 'Goals Progress',
                    'top_locations' => 'Top Locations',
                    'funnel' => 'Conversion Funnel',
                ])
                ->columns(3)
                ->columnSpanFull(),

            Forms\Components\Select::make('format')
                ->label('Attachment Format')
                ->options([
                    'email' => 'Email Only',
                    'pdf' => 'Include PDF',
                    'csv' => 'Include CSV',
                ])
                ->required(),

            Forms\Components\Toggle::make('include_comparison')
                ->label('Include Period Comparison'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active'),
        ];
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
