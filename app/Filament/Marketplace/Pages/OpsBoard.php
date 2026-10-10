<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceClientMicroservice;
use App\Services\Marketplace\OpsBoardService;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * "Tablă operațiuni" — what is still to be done per event (microservice
 * `ops-board`). The board itself is computed by OpsBoardService; this page
 * only picks the period and holds the two settings.
 */
class OpsBoard extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationLabel = 'Operațiuni';
    // Ungrouped with the Dashboard's own sort: pages register in name order,
    // ahead of resources, so this lands right under it.
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'ops-board';
    protected string $view = 'filament.marketplace.pages.ops-board';

    /** week | month — empty until mount() applies the marketplace default. */
    #[Url]
    public string $period = '';

    /** Any day inside the period shown (Y-m-d). */
    #[Url]
    public string $date = '';

    /** Organizer id, empty = all. */
    #[Url]
    public string $organizer = '';

    /** Tax registry id, empty = all. */
    #[Url]
    public string $registry = '';

    #[Url]
    public bool $onlyOpen = false;

    #[Url]
    public bool $byRegistry = false;

    /** Pills switched on as filters: overdue | todo | awaiting_payment. */
    #[Url]
    public array $status = [];

    /** Event whose history is expanded. */
    public ?int $historyFor = null;

    protected ?MarketplaceClientMicroservice $pivot = null;

    protected bool $pivotLoaded = false;

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice(OpsBoardService::SLUG);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice(OpsBoardService::SLUG);
    }

    public function mount(): void
    {
        if (! in_array($this->period, ['week', 'month'], true)) {
            $this->period = $this->setting('default_view') === 'month' ? 'month' : 'week';
        }
        $this->date = $this->anchor()->toDateString();
    }

    public function getTitle(): string
    {
        return 'Operațiuni';
    }

    public function getSubheading(): string|null
    {
        return 'Ce mai e de făcut pentru fiecare eveniment. Stările se calculează din date, nu se mută manual.';
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === 'month' ? 'month' : 'week';
    }

    public function previous(): void
    {
        $this->shift(-1);
    }

    public function next(): void
    {
        $this->shift(1);
    }

    public function goToday(): void
    {
        $this->date = Carbon::now($this->tz())->toDateString();
    }

    public function toggleStatus(string $status): void
    {
        if (! in_array($status, OpsBoardService::STATUSES, true)) {
            return;
        }
        $this->status = in_array($status, $this->status, true)
            ? array_values(array_diff($this->status, [$status]))
            : [...$this->status, $status];
    }

    public function clearStatus(): void
    {
        $this->status = [];
    }

    public function toggleHistory(int $eventId): void
    {
        $this->historyFor = $this->historyFor === $eventId ? null : $eventId;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')
                ->label('Setări')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => $this->canManage())
                ->modalHeading('Setări tablă')
                ->modalSubmitActionLabel('Salvează')
                ->fillForm(fn () => [
                    'default_view' => $this->setting('default_view') === 'month' ? 'month' : 'week',
                    'track_from' => $this->trackFrom()->toDateString(),
                    'digest_enabled' => (bool) ($this->setting('digest_enabled') ?? true),
                    'escalate_days' => (int) ($this->setting('escalate_days') ?? OpsBoardService::DEFAULT_ESCALATE_DAYS),
                ])
                ->form([
                    Forms\Components\Select::make('default_view')
                        ->label('Perioada afișată implicit')
                        ->options(['week' => 'Săptămână', 'month' => 'Lună'])
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('track_from')
                        ->label('Urmărim restanțele de la')
                        ->helperText('Evenimentele încheiate înainte de această dată nu apar la restanțe.')
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\Toggle::make('digest_enabled')
                        ->label('Rezumat pe email luni dimineața')
                        ->helperText('Către administratori și super administratori: ce e restant și ce e de făcut în săptămâna care începe.'),
                    Forms\Components\TextInput::make('escalate_days')
                        ->label('Escaladare către super administratori după')
                        ->helperText('În zilele lucrătoare, super administratorii primesc lista cu ce e restant de cel puțin atâtea zile. 0 = fără escaladare.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(60)
                        ->suffix('zile')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $pivot = $this->pivot();
                    if (! $pivot || ! $this->canManage()) {
                        return;
                    }
                    $pivot->update(['settings' => array_merge($pivot->settings ?? [], [
                        'default_view' => $data['default_view'] === 'month' ? 'month' : 'week',
                        'track_from' => Carbon::parse($data['track_from'])->toDateString(),
                        'digest_enabled' => (bool) ($data['digest_enabled'] ?? false),
                        'escalate_days' => max(0, (int) ($data['escalate_days'] ?? 0)),
                    ])]);
                    Notification::make()->success()->title('Setări salvate')->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();
        [$from, $to] = $this->range();

        $service = app(OpsBoardService::class);
        $board = $marketplace
            ? $service->build($marketplace, $from, $to, $this->trackFrom(), [
                'organizer_id' => (int) $this->organizer ?: null,
                'registry_id' => (int) $this->registry ?: null,
                'only_open' => $this->onlyOpen,
                'statuses' => $this->status,
            ])
            : ['backlog' => [], 'period' => [], 'upcoming' => [], 'counts' => ['overdue' => 0, 'todo' => 0, 'awaiting_payment' => 0, 'events' => 0], 'options' => ['organizers' => [], 'registries' => []]];

        if ($this->byRegistry) {
            foreach (['backlog', 'period', 'upcoming'] as $zone) {
                usort($board[$zone], fn ($a, $b) => [$a['registry'] === null, $a['registry'], $a['open_count'] === 0, $a['sort']] <=> [$b['registry'] === null, $b['registry'], $b['open_count'] === 0, $b['sort']]);
            }
        }

        $historyEvent = ($marketplace && $this->historyFor)
            ? Event::where('marketplace_client_id', $marketplace->id)->find($this->historyFor)
            : null;

        return [
            'board' => $board,
            'history' => $historyEvent ? $service->history($historyEvent) : null,
            'tasks' => OpsBoardService::TASKS,
            'periodLabel' => $this->period === 'month'
                ? $from->locale('ro')->translatedFormat('F Y')
                : $from->locale('ro')->translatedFormat('j M') . ' – ' . $to->locale('ro')->translatedFormat('j M Y'),
            'trackFromLabel' => $this->trackFrom()->locale('ro')->translatedFormat('j M Y'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(): array
    {
        $anchor = $this->anchor();

        return $this->period === 'month'
            ? [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()->startOfDay()]
            : [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()];
    }

    protected function shift(int $step): void
    {
        $anchor = $this->anchor();
        $this->date = ($this->period === 'month'
            ? $anchor->startOfMonth()->addMonthsNoOverflow($step)
            : $anchor->addWeeks($step))->toDateString();
    }

    protected function anchor(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $this->date, $this->tz())->startOfDay();
        } catch (\Throwable $e) {
            return Carbon::now($this->tz())->startOfDay();
        }
    }

    protected function trackFrom(): Carbon
    {
        return OpsBoardService::trackFrom($this->pivot(), $this->tz());
    }

    protected function setting(string $key): mixed
    {
        return $this->pivot()?->getSetting($key);
    }

    protected function pivot(): ?MarketplaceClientMicroservice
    {
        if ($this->pivotLoaded) {
            return $this->pivot;
        }
        $this->pivotLoaded = true;

        $marketplaceId = static::getMarketplaceClientId();
        if (! $marketplaceId) {
            return null;
        }

        return $this->pivot = MarketplaceClientMicroservice::query()
            ->where('marketplace_client_id', $marketplaceId)
            ->whereHas('microservice', fn ($q) => $q->where('slug', OpsBoardService::SLUG))
            ->first();
    }

    /** Administrator + Super Administrator (not Moderator). */
    protected function canManage(): bool
    {
        return in_array(Auth::guard('marketplace_admin')->user()?->role, ['super_admin', 'admin'], true);
    }

    protected function tz(): string
    {
        return MarketplaceTz::tz(static::getMarketplaceClient());
    }
}
