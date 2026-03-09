<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceClient;
use App\Services\Accounting\AccountingService;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AccountingConfig extends Page implements HasForms
{
    use InteractsWithForms;
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Contabilitate';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 11;
    protected string $view = 'filament.marketplace.pages.accounting-config';

    public ?MarketplaceClient $marketplace = null;
    public ?string $provider = null;
    public ?string $connectionStatus = null;
    public ?string $lastError = null;

    // SmartBill fields
    public ?string $smartbill_username = '';
    public ?string $smartbill_token = '';
    public ?string $smartbill_company_vat = '';

    // FGO fields
    public ?string $fgo_cod_unic = '';
    public ?string $fgo_cheie_privata = '';
    public ?string $fgo_serie = 'FACT';
    public ?string $fgo_environment = 'test';

    // Keez fields
    public ?string $keez_application_id = '';
    public ?string $keez_client_secret = '';
    public ?string $keez_client_eid = '';
    public ?string $keez_environment = 'staging';

    // Oblio fields
    public ?string $oblio_client_id = '';
    public ?string $oblio_client_secret = '';
    public ?string $oblio_cif = '';
    public ?string $oblio_series_name = 'FACT';
    public bool $oblio_use_draft = true;

    public function mount(): void
    {
        $this->marketplace = static::getMarketplaceClient();

        if (!$this->marketplace) {
            abort(404);
        }

        $this->loadExistingConfig();
    }

    protected function loadExistingConfig(): void
    {
        $connector = DB::table('acc_connectors')
            ->where('marketplace_client_id', $this->marketplace->id)
            ->first();

        if (!$connector) {
            return;
        }

        $this->provider = $connector->provider;
        $this->connectionStatus = $connector->status;
        $this->lastError = $connector->last_error;

        // Decrypt and load credentials
        try {
            $auth = json_decode(Crypt::decryptString($connector->auth), true);

            match ($this->provider) {
                'smartbill' => $this->loadSmartBillConfig($auth),
                'fgo' => $this->loadFgoConfig($auth),
                'keez' => $this->loadKeezConfig($auth),
                'oblio' => $this->loadOblioConfig($auth),
                default => null,
            };
        } catch (\Exception $e) {
            // Credentials may be corrupted
        }
    }

    protected function loadSmartBillConfig(array $auth): void
    {
        $this->smartbill_username = $auth['username'] ?? '';
        $this->smartbill_token = $auth['token'] ?? '';
        $this->smartbill_company_vat = $auth['company_vat'] ?? '';
    }

    protected function loadFgoConfig(array $auth): void
    {
        $this->fgo_cod_unic = $auth['cod_unic'] ?? '';
        $this->fgo_cheie_privata = $auth['cheie_privata'] ?? '';
        $this->fgo_serie = $auth['serie'] ?? 'FACT';
        $this->fgo_environment = $auth['environment'] ?? 'test';
    }

    protected function loadKeezConfig(array $auth): void
    {
        $this->keez_application_id = $auth['application_id'] ?? '';
        $this->keez_client_secret = $auth['client_secret'] ?? '';
        $this->keez_client_eid = $auth['client_eid'] ?? '';
        $this->keez_environment = $auth['environment'] ?? 'staging';
    }

    protected function loadOblioConfig(array $auth): void
    {
        $this->oblio_client_id = $auth['client_id'] ?? '';
        $this->oblio_client_secret = $auth['client_secret'] ?? '';
        $this->oblio_cif = $auth['cif'] ?? '';
        $this->oblio_series_name = $auth['series_name'] ?? 'FACT';
        $this->oblio_use_draft = $auth['use_draft'] ?? true;
    }

    public function form(Form|\Filament\Schemas\Schema $form): Form|\Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('provider')
                    ->label('Provider contabilitate')
                    ->options([
                        'oblio' => 'Oblio.eu',
                        'smartbill' => 'SmartBill',
                        'fgo' => 'FGO',
                        'keez' => 'Keez',
                    ])
                    ->live()
                    ->required(),

                // Oblio fields
                Section::make('Oblio.eu')
                    ->description('Conectare la Oblio.eu pentru facturare automată. Nu există sandbox — folosiți opțiunea Draft pentru teste.')
                    ->schema([
                        Forms\Components\TextInput::make('oblio_client_id')
                            ->label('Email (Client ID)')
                            ->email()
                            ->required()
                            ->helperText('Email-ul contului Oblio.eu'),
                        Forms\Components\TextInput::make('oblio_client_secret')
                            ->label('API Token (Client Secret)')
                            ->password()
                            ->required()
                            ->helperText('Token-ul API din setările Oblio.eu'),
                        Forms\Components\TextInput::make('oblio_cif')
                            ->label('CIF Companie')
                            ->required()
                            ->helperText('CUI/CIF-ul companiei pentru care se emit facturi'),
                        Forms\Components\TextInput::make('oblio_series_name')
                            ->label('Serie Facturi')
                            ->default('FACT')
                            ->required()
                            ->helperText('Seria de facturare configurată în Oblio'),
                        Forms\Components\Toggle::make('oblio_use_draft')
                            ->label('Trimite ca Draft')
                            ->helperText('Facturile vor fi create ca draft (ciornă) — util pentru testare. Dezactivați pentru producție.')
                            ->default(true),
                    ])
                    ->visible(fn () => $this->provider === 'oblio')
                    ->columns(2),

                // SmartBill fields
                Section::make('SmartBill')
                    ->schema([
                        Forms\Components\TextInput::make('smartbill_username')
                            ->label('Email / Username')
                            ->required(),
                        Forms\Components\TextInput::make('smartbill_token')
                            ->label('API Token')
                            ->password()
                            ->required(),
                        Forms\Components\TextInput::make('smartbill_company_vat')
                            ->label('CUI Companie')
                            ->required(),
                    ])
                    ->visible(fn () => $this->provider === 'smartbill')
                    ->columns(2),

                // FGO fields
                Section::make('FGO')
                    ->schema([
                        Forms\Components\TextInput::make('fgo_cod_unic')
                            ->label('CUI (Cod Unic)')
                            ->required(),
                        Forms\Components\TextInput::make('fgo_cheie_privata')
                            ->label('Cheie Privată API')
                            ->password()
                            ->required(),
                        Forms\Components\TextInput::make('fgo_serie')
                            ->label('Serie Facturi')
                            ->default('FACT'),
                        Forms\Components\Select::make('fgo_environment')
                            ->label('Environment')
                            ->options([
                                'test' => 'Test (Sandbox)',
                                'production' => 'Producție',
                            ])
                            ->default('test'),
                    ])
                    ->visible(fn () => $this->provider === 'fgo')
                    ->columns(2),

                // Keez fields
                Section::make('Keez')
                    ->schema([
                        Forms\Components\TextInput::make('keez_application_id')
                            ->label('Application ID')
                            ->required(),
                        Forms\Components\TextInput::make('keez_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->required(),
                        Forms\Components\TextInput::make('keez_client_eid')
                            ->label('Client EID')
                            ->required(),
                        Forms\Components\Select::make('keez_environment')
                            ->label('Environment')
                            ->options([
                                'staging' => 'Staging (Test)',
                                'production' => 'Producție',
                            ])
                            ->default('staging'),
                    ])
                    ->visible(fn () => $this->provider === 'keez')
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        if (!$this->provider) {
            Notification::make()->danger()->title('Selectați un provider.')->send();
            return;
        }

        $credentials = $this->getCredentials();

        try {
            $service = app(AccountingService::class);
            $result = $service->connectMarketplace($this->marketplace->id, $this->provider, $credentials);

            $this->connectionStatus = $result['success'] ? 'connected' : 'error';
            $this->lastError = $result['success'] ? null : $result['message'];

            if ($result['success']) {
                Notification::make()->success()
                    ->title('Conectat cu succes')
                    ->body($result['message'])
                    ->send();
            } else {
                Notification::make()->danger()
                    ->title('Eroare la conectare')
                    ->body($result['message'])
                    ->send();
            }
        } catch (\Throwable $e) {
            $this->connectionStatus = 'error';
            $this->lastError = $e->getMessage();

            Notification::make()->danger()
                ->title('Eroare')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function testConnection(): void
    {
        if (!$this->provider) {
            Notification::make()->danger()->title('Selectați un provider.')->send();
            return;
        }

        $credentials = $this->getCredentials();

        try {
            $service = app(AccountingService::class);
            $adapter = $service->getAdapterPublic($this->provider);
            $adapter->authenticate($credentials);
            $result = $adapter->testConnection();

            if ($result['connected']) {
                Notification::make()->success()
                    ->title('Conexiune reușită')
                    ->body($result['message'])
                    ->send();
            } else {
                Notification::make()->danger()
                    ->title('Conexiune eșuată')
                    ->body($result['message'])
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Eroare')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function disconnect(): void
    {
        DB::table('acc_connectors')
            ->where('marketplace_client_id', $this->marketplace->id)
            ->delete();

        $this->connectionStatus = null;
        $this->lastError = null;
        $this->provider = null;

        Notification::make()->success()->title('Deconectat cu succes.')->send();
    }

    protected function getCredentials(): array
    {
        return match ($this->provider) {
            'oblio' => [
                'client_id' => $this->oblio_client_id,
                'client_secret' => $this->oblio_client_secret,
                'cif' => $this->oblio_cif,
                'series_name' => $this->oblio_series_name,
                'use_draft' => $this->oblio_use_draft,
            ],
            'smartbill' => [
                'username' => $this->smartbill_username,
                'token' => $this->smartbill_token,
                'company_vat' => $this->smartbill_company_vat,
            ],
            'fgo' => [
                'cod_unic' => $this->fgo_cod_unic,
                'cheie_privata' => $this->fgo_cheie_privata,
                'serie' => $this->fgo_serie,
                'environment' => $this->fgo_environment,
            ],
            'keez' => [
                'application_id' => $this->keez_application_id,
                'client_secret' => $this->keez_client_secret,
                'client_eid' => $this->keez_client_eid,
                'environment' => $this->keez_environment,
            ],
            default => [],
        };
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('accounting-connectors');
    }
}
