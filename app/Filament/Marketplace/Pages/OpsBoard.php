<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
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
    protected static ?string $navigationLabel = 'Tablă operațiuni';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'ops-board';
    protected string $view = 'filament.marketplace.pages.ops-board';

    /** week | month — empty until mount() applies the marketplace default. */
    #[Url]
    public string $period = '';

    /** Any day inside the period shown (Y-m-d). */
    #[Url]
    public string $date = '';

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
        return 'Tablă operațiuni';
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
                ])
                ->action(function (array $data) {
                    $pivot = $this->pivot();
                    if (! $pivot || ! $this->canManage()) {
                        return;
                    }
                    $pivot->update(['settings' => array_merge($pivot->settings ?? [], [
                        'default_view' => $data['default_view'] === 'month' ? 'month' : 'week',
                        'track_from' => Carbon::parse($data['track_from'])->toDateString(),
                    ])]);
                    Notification::make()->success()->title('Setări salvate')->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();
        [$from, $to] = $this->range();

        $board = $marketplace
            ? app(OpsBoardService::class)->build($marketplace, $from, $to, $this->trackFrom())
            : ['backlog' => [], 'period' => [], 'upcoming' => [], 'counts' => ['overdue' => 0, 'todo' => 0, 'awaiting_payment' => 0, 'events' => 0]];

        return [
            'board' => $board,
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

    /**
     * Earliest event end that can still show up as backlog: the saved
     * setting, else the day the microservice was switched on.
     */
    protected function trackFrom(): Carbon
    {
        $saved = $this->setting('track_from');
        if ($saved) {
            try {
                return Carbon::createFromFormat('Y-m-d', $saved, $this->tz())->startOfDay();
            } catch (\Throwable $e) {
                // fall through to the activation date
            }
        }
        $activatedAt = $this->pivot()?->activated_at;

        return $activatedAt
            ? $activatedAt->copy()->setTimezone($this->tz())->startOfDay()
            : Carbon::now($this->tz())->startOfDay();
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
