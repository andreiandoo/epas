<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Services\Accounting\AccountingService;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AccountingConfig extends Page implements HasForms
{
    use InteractsWithForms;

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

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('provider')
                    ->label('Provider contabilitate')
                    ->options([
                        'smartbill' => 'SmartBill',
                        'fgo' => 'FGO',
                        'keez' => 'Keez',
                    ])
                    ->live()
                    ->required(),

                // SmartBill fields
                Forms\Components\Section::make('SmartBill')
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
                Forms\Components\Section::make('FGO')
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
                Forms\Components\Section::make('Keez')
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
        $tenantId = "marketplace_{$this->marketplace->id}";

        try {
            $service = app(AccountingService::class);
            $result = $service->connect($tenantId, $this->provider, $credentials);

            // Also store marketplace_client_id
            DB::table('acc_connectors')
                ->where('tenant_id', $tenantId)
                ->where('provider', $this->provider)
                ->update(['marketplace_client_id' => $this->marketplace->id]);

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
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin?->marketplaceClient) return false;
        return $admin->marketplaceClient->hasMicroservice('accounting-connectors');
    }
}
