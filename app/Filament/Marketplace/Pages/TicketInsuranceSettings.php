<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceClientMicroservice;
use App\Models\Microservice;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TicketInsuranceSettings extends Page
{
    use HasMarketplaceContext;
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Taxa de Retur';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'ticket-insurance';
    protected string $view = 'filament.marketplace.pages.ticket-insurance-settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('ticket-insurance');
    }

    public function mount(): void
    {
        $marketplace = static::getMarketplaceClient();
        $settings = $this->getInsuranceSettings();

        $this->form->fill([
            'is_enabled' => $settings['is_enabled'] ?? false,
            'label' => $settings['label'] ?? 'Taxa de retur',
            'description' => $settings['description'] ?? 'Prin selectarea acestei opțiuni, puteți solicita returnarea biletelor în cazul în care evenimentul este amânat sau anulat.',
            'price' => $settings['price'] ?? 5.00,
            'price_type' => $settings['price_type'] ?? 'fixed',
            'price_percentage' => $settings['price_percentage'] ?? 5,
            'apply_to' => $settings['apply_to'] ?? 'all',
            'terms_url' => $settings['terms_url'] ?? '',
            'show_in_checkout' => $settings['show_in_checkout'] ?? true,
            'pre_checked' => $settings['pre_checked'] ?? false,
        ]);
    }

    protected function getInsuranceSettings(): array
    {
        $marketplace = static::getMarketplaceClient();

        $pivot = MarketplaceClientMicroservice::where('marketplace_client_id', $marketplace?->id)
            ->whereHas('microservice', fn($q) => $q->where('slug', 'ticket-insurance'))
            ->first();

        return $pivot?->settings ?? [];
    }

    protected function saveInsuranceSettings(array $settings): void
    {
        $marketplace = static::getMarketplaceClient();

        $pivot = MarketplaceClientMicroservice::where('marketplace_client_id', $marketplace?->id)
            ->whereHas('microservice', fn($q) => $q->where('slug', 'ticket-insurance'))
            ->first();

        if ($pivot) {
            $pivot->update(['settings' => $settings]);
        }
    }

    public function form(Schema $form): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $currency = $marketplace?->currency ?? 'RON';

        return $form
            ->schema([
                SC\Section::make('Configurare Taxa de Retur')
                    ->description('Configurați opțiunea de protecție pentru returnare bilete în checkout.')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Activat')
                            ->helperText('Activează checkbox-ul de protecție retur în pagina de checkout')
                            ->onColor('success')
                            ->offColor('gray')
                            ->live(),

                        Forms\Components\Toggle::make('show_in_checkout')
                            ->label('Afișează în Checkout')
                            ->helperText('Afișează opțiunea în pagina de finalizare comandă')
                            ->default(true)
                            ->visible(fn (Get $get) => $get('is_enabled')),

                        Forms\Components\Toggle::make('pre_checked')
                            ->label('Pre-bifat')
                            ->helperText('Checkbox-ul va fi bifat implicit (clientul poate debifa)')
                            ->default(false)
                            ->visible(fn (Get $get) => $get('is_enabled')),
                    ])->columns(3),

                SC\Section::make('Denumire și Descriere')
                    ->visible(fn (Get $get) => $get('is_enabled'))
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Etichetă')
                            ->placeholder('Taxa de retur')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Textul afișat lângă checkbox în checkout'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descriere')
                            ->placeholder('Descrierea opțiunii de protecție...')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Text explicativ afișat sub checkbox'),

                        Forms\Components\TextInput::make('terms_url')
                            ->label('Link Termeni și Condiții')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Link către termenii și condițiile pentru protecția returnare (opțional)'),
                    ]),

                SC\Section::make('Preț')
                    ->visible(fn (Get $get) => $get('is_enabled'))
                    ->schema([
                        Forms\Components\Radio::make('price_type')
                            ->label('Tip preț')
                            ->options([
                                'fixed' => 'Sumă fixă',
                                'percentage' => 'Procent din valoarea comenzii',
                            ])
                            ->default('fixed')
                            ->live()
                            ->inline(),

                        Forms\Components\TextInput::make('price')
                            ->label('Preț fix')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix($currency)
                            ->placeholder('5.00')
                            ->required()
                            ->visible(fn (Get $get) => $get('price_type') === 'fixed')
                            ->helperText('Suma fixă adăugată la comandă'),

                        Forms\Components\TextInput::make('price_percentage')
                            ->label('Procent')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->suffix('%')
                            ->placeholder('5')
                            ->required()
                            ->visible(fn (Get $get) => $get('price_type') === 'percentage')
                            ->helperText('Procentul din valoarea totală a comenzii'),
                    ])->columns(2),

                SC\Section::make('Aplicare')
                    ->visible(fn (Get $get) => $get('is_enabled'))
                    ->schema([
                        Forms\Components\Radio::make('apply_to')
                            ->label('Aplică pentru')
                            ->options([
                                'all' => 'Toate evenimentele',
                                'refundable_only' => 'Doar bilete returnabile (cu opțiunea "Returnabil" activată)',
                            ])
                            ->default('all')
                            ->helperText('Selectați la ce tipuri de bilete se aplică opțiunea de protecție'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = [
            'is_enabled' => $data['is_enabled'] ?? false,
            'label' => $data['label'] ?? 'Taxa de retur',
            'description' => $data['description'] ?? '',
            'price' => (float) ($data['price'] ?? 5.00),
            'price_type' => $data['price_type'] ?? 'fixed',
            'price_percentage' => (float) ($data['price_percentage'] ?? 5),
            'apply_to' => $data['apply_to'] ?? 'all',
            'terms_url' => $data['terms_url'] ?? '',
            'show_in_checkout' => $data['show_in_checkout'] ?? true,
            'pre_checked' => $data['pre_checked'] ?? false,
        ];

        $this->saveInsuranceSettings($settings);

        Notification::make()
            ->title('Setări salvate')
            ->body('Configurația pentru Taxa de Retur a fost actualizată cu succes.')
            ->success()
            ->send();
    }

    public function getHeading(): string
    {
        return 'Taxa de Retur';
    }

    public function getSubheading(): ?string
    {
        return 'Configurați opțiunea de protecție pentru returnare bilete';
    }
}
