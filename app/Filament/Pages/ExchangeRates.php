<?php

namespace App\Filament\Pages;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ExchangeRates extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-euro';
    protected string $view = 'filament.pages.exchange-rates';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 60;
    protected static ?string $title = 'Exchange Rates';

    public ?array $manualRate = [];
    public array $recentRates = [];
    public ?float $currentRate = null;

    public function mount(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->currentRate = ExchangeRate::getLatestRate('EUR', 'RON');

        $this->recentRates = ExchangeRate::where('base_currency', 'EUR')
            ->where('target_currency', 'RON')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetch')
                ->label('Fetch Today\'s Rate')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $service = app(ExchangeRateService::class);

                    if ($service->fetchAndStoreRates()) {
                        $this->loadData();
                        Notification::make()
                            ->title('Exchange rate fetched successfully')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Failed to fetch exchange rate')
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('backfill')
                ->label('Backfill Last 30 Days')
                ->icon('heroicon-o-clock')
                ->requiresConfirmation()
                ->action(function () {
                    $service = app(ExchangeRateService::class);
                    $count = $service->backfillRates(now()->subDays(30), now());

                    $this->loadData();

                    Notification::make()
                        ->title("Backfilled {$count} exchange rates")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function manualRateForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('rate')
                    ->label('EUR to RON Rate')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.0001)
                    ->placeholder('e.g., 4.9750'),
            ])
            ->statePath('manualRate');
    }

    public function saveManualRate(): void
    {
        $data = $this->manualRate;

        if (empty($data['date']) || empty($data['rate'])) {
            Notification::make()
                ->title('Please fill all fields')
                ->danger()
                ->send();
            return;
        }

        $service = app(ExchangeRateService::class);
        $service->setManualRate(
            Carbon::parse($data['date']),
            'EUR',
            'RON',
            (float) $data['rate']
        );

        $this->manualRate = [];
        $this->loadData();

        Notification::make()
            ->title('Manual rate saved')
            ->success()
            ->send();
    }

    /**
     * Only super-admin can access
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->isSuperAdmin();
    }
}
