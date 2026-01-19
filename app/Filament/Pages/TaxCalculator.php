<?php

namespace App\Filament\Pages;

use App\Models\EventType;
use App\Services\Tax\TaxService;
use BackedEnum;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\Computed;

class TaxCalculator extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament.pages.tax-calculator';

    protected static ?string $navigationLabel = 'Tax Calculator';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Tax Calculator';

    public ?array $data = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill([
            'amount' => 100,
            'country' => null,
            'county' => null,
            'city' => null,
            'event_type_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $countries = require resource_path('data/countries.php');
        $countryOptions = array_combine($countries, $countries);

        return $schema
            ->schema([
                SC\Section::make('Calculate Taxes')
                    ->description('Enter the details below to preview tax calculations')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix($tenant->currency ?? 'EUR')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->calculateTaxes()),

                        Forms\Components\Select::make('event_type_id')
                            ->label('Event Type')
                            ->options(function () use ($tenantLanguage) {
                                return EventType::all()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => $type->name[$tenantLanguage] ?? $type->name['en'] ?? $type->slug
                                    ]);
                            })
                            ->searchable()
                            ->placeholder('All Event Types')
                            ->live()
                            ->afterStateUpdated(fn () => $this->calculateTaxes()),

                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options($countryOptions)
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->calculateTaxes())
                            ->placeholder('Select country (optional)'),

                        Forms\Components\TextInput::make('county')
                            ->label('County / State')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->calculateTaxes())
                            ->placeholder('Enter county or state (optional)'),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->calculateTaxes())
                            ->placeholder('Enter city (optional)'),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function calculateTaxes(): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $amount = (float) ($this->data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->result = null;
            return;
        }

        $taxService = app(TaxService::class);

        $taxResult = $taxService->calculateTaxes(
            tenantId: $tenant->id,
            amount: $amount,
            eventTypeId: $this->data['event_type_id'] ?? null,
            country: $this->data['country'] ?? null,
            county: $this->data['county'] ?? null,
            city: $this->data['city'] ?? null,
            currency: $tenant->currency ?? 'EUR'
        );

        $this->result = $taxResult->toArray();
    }

    #[Computed]
    public function hasResult(): bool
    {
        return $this->result !== null;
    }
}
