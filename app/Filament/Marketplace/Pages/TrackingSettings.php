<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\TrackingIntegration;
use BackedEnum;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class TrackingSettings extends Page
{
    use HasMarketplaceContext;

    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Tracking & Pixels';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.tracking-settings';

    public ?array $data = [];

    public function getHeading(): string
    {
        return '';
    }

    /**
     * Tracking settings are tenant-specific, not applicable to marketplace panel
     */
        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('tracking-pixels-manager');
    }

    public function mount(): void
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            abort(404);
        }

        // Check if microservice is active
        $hasAccess = $marketplace->microservices()
            ->where('microservices.slug', 'tracking-pixels-manager')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAccess) {
            Notification::make()
                ->warning()
                ->title('Microservice Not Active')
                ->body('You need to activate the Tracking & Pixels microservice first.')
                ->send();

            redirect()->route('filament.marketplace.pages.microservices');
            return;
        }

        // Load existing integrations
        $integrations = TrackingIntegration::where('marketplace_client_id', $marketplace->id)->get();

        $formData = [];
        foreach (['ga4', 'gtm', 'meta', 'tiktok'] as $provider) {
            $integration = $integrations->where('provider', $provider)->first();
            if ($integration) {
                $settings = $integration->getSettings();
                // Use toggle_enabled from settings if available, otherwise fall back to enabled
                $formData["{$provider}_enabled"] = (bool) ($settings['toggle_enabled'] ?? $integration->enabled);
                $formData["{$provider}_id"] = $integration->getProviderId() ?? '';
                $formData["{$provider}_inject_at"] = $integration->getInjectAt();
                $formData["{$provider}_page_scope"] = $integration->getPageScope();
            } else {
                $formData["{$provider}_enabled"] = false;
                $formData["{$provider}_id"] = '';
                $formData["{$provider}_inject_at"] = 'head';
                $formData["{$provider}_page_scope"] = 'public';
            }
        }

        $this->data = $formData;
        $this->form->fill($this->data);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Section::make('Google Analytics 4 (GA4)')
                    ->description('Track website analytics with Google Analytics 4')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        Forms\Components\Toggle::make('ga4_enabled')
                            ->label('Enable GA4')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Enable Google Analytics 4 tracking')
                            ->live(),

                        Forms\Components\TextInput::make('ga4_id')
                            ->label('Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your GA4 Measurement ID from Google Analytics')
                            ->maxLength(20)
                            ->visible(fn ($get) => $get('ga4_enabled')),

                        SC\Grid::make(2)->schema([
                            Forms\Components\Select::make('ga4_inject_at')
                                ->label('Inject Location')
                                ->options([
                                    'head' => 'Head (recommended)',
                                    'body' => 'Body End',
                                ])
                                ->default('head')
                                ->visible(fn ($get) => $get('ga4_enabled')),

                            Forms\Components\Select::make('ga4_page_scope')
                                ->label('Page Scope')
                                ->options([
                                    'public' => 'Public pages only',
                                    'all' => 'All pages',
                                ])
                                ->default('public')
                                ->visible(fn ($get) => $get('ga4_enabled')),
                        ]),
                    ])
                    ->collapsible(),

                SC\Section::make('Google Tag Manager (GTM)')
                    ->description('Manage all your tags with Google Tag Manager')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Forms\Components\Toggle::make('gtm_enabled')
                            ->label('Enable GTM')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Enable Google Tag Manager')
                            ->live(),

                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Container ID')
                            ->placeholder('GTM-XXXXXX')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your GTM Container ID')
                            ->maxLength(15)
                            ->visible(fn ($get) => $get('gtm_enabled')),

                        SC\Grid::make(2)->schema([
                            Forms\Components\Select::make('gtm_inject_at')
                                ->label('Inject Location')
                                ->options([
                                    'head' => 'Head (recommended)',
                                    'body' => 'Body End',
                                ])
                                ->default('head')
                                ->visible(fn ($get) => $get('gtm_enabled')),

                            Forms\Components\Select::make('gtm_page_scope')
                                ->label('Page Scope')
                                ->options([
                                    'public' => 'Public pages only',
                                    'all' => 'All pages',
                                ])
                                ->default('public')
                                ->visible(fn ($get) => $get('gtm_enabled')),
                        ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                SC\Section::make('Meta Pixel (Facebook)')
                    ->description('Track conversions for Facebook & Instagram ads')
                    ->icon('heroicon-o-share')
                    ->schema([
                        Forms\Components\Toggle::make('meta_enabled')
                            ->label('Enable Meta Pixel')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Enable Facebook/Meta Pixel tracking')
                            ->live(),

                        Forms\Components\TextInput::make('meta_id')
                            ->label('Pixel ID')
                            ->placeholder('1234567890123456')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Meta Pixel ID from Facebook Business Manager')
                            ->maxLength(20)
                            ->visible(fn ($get) => $get('meta_enabled')),

                        SC\Grid::make(2)->schema([
                            Forms\Components\Select::make('meta_inject_at')
                                ->label('Inject Location')
                                ->options([
                                    'head' => 'Head (recommended)',
                                    'body' => 'Body End',
                                ])
                                ->default('head')
                                ->visible(fn ($get) => $get('meta_enabled')),

                            Forms\Components\Select::make('meta_page_scope')
                                ->label('Page Scope')
                                ->options([
                                    'public' => 'Public pages only',
                                    'all' => 'All pages',
                                ])
                                ->default('public')
                                ->visible(fn ($get) => $get('meta_enabled')),
                        ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                SC\Section::make('TikTok Pixel')
                    ->description('Track conversions for TikTok ads')
                    ->icon('heroicon-o-play')
                    ->schema([
                        Forms\Components\Toggle::make('tiktok_enabled')
                            ->label('Enable TikTok Pixel')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Enable TikTok Pixel tracking')
                            ->live(),

                        Forms\Components\TextInput::make('tiktok_id')
                            ->label('Pixel ID')
                            ->placeholder('CXXXXXXXXXXXXXXXXX')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your TikTok Pixel ID from TikTok Ads Manager')
                            ->maxLength(25)
                            ->visible(fn ($get) => $get('tiktok_enabled')),

                        SC\Grid::make(2)->schema([
                            Forms\Components\Select::make('tiktok_inject_at')
                                ->label('Inject Location')
                                ->options([
                                    'head' => 'Head (recommended)',
                                    'body' => 'Body End',
                                ])
                                ->default('head')
                                ->visible(fn ($get) => $get('tiktok_enabled')),

                            Forms\Components\Select::make('tiktok_page_scope')
                                ->label('Page Scope')
                                ->options([
                                    'public' => 'Public pages only',
                                    'all' => 'All pages',
                                ])
                                ->default('public')
                                ->visible(fn ($get) => $get('tiktok_enabled')),
                        ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                SC\Section::make('GDPR Compliance')
                    ->schema([
                        Forms\Components\Placeholder::make('gdpr_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="space-y-2 text-sm">
                                    <p><strong>Important:</strong> All tracking pixels are GDPR-compliant with opt-in consent.</p>
                                    <ul class="text-gray-600 list-disc list-inside dark:text-gray-400">
                                        <li><strong>Analytics</strong> (GA4, GTM) - Requires "Analytics" consent</li>
                                        <li><strong>Marketing</strong> (Meta, TikTok) - Requires "Marketing" consent</li>
                                    </ul>
                                    <p class="text-gray-500">No tracking occurs without explicit user consent via your cookie consent banner.</p>
                                </div>
                            ')),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return;
        }

        // Provider configurations
        $providers = [
            'ga4' => [
                'consent_category' => 'analytics',
                'id_field' => 'measurement_id',
            ],
            'gtm' => [
                'consent_category' => 'analytics',
                'id_field' => 'container_id',
            ],
            'meta' => [
                'consent_category' => 'marketing',
                'id_field' => 'pixel_id',
            ],
            'tiktok' => [
                'consent_category' => 'marketing',
                'id_field' => 'pixel_id',
            ],
        ];

        foreach ($providers as $provider => $config) {
            $toggleEnabled = (bool) ($data["{$provider}_enabled"] ?? false);
            $providerId = $data["{$provider}_id"] ?? '';

            // enabled = true only when toggle is on AND provider ID exists
            // toggle_enabled in settings stores the UI toggle state
            TrackingIntegration::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplace->id,
                    'provider' => $provider,
                ],
                [
                    'enabled' => $toggleEnabled && !empty($providerId),
                    'consent_category' => $config['consent_category'],
                    'settings' => [
                        $config['id_field'] => $providerId,
                        'inject_at' => $data["{$provider}_inject_at"] ?? 'head',
                        'page_scope' => $data["{$provider}_page_scope"] ?? 'public',
                        'toggle_enabled' => $toggleEnabled,
                    ],
                ]
            );
        }

        Notification::make()
            ->success()
            ->title('Tracking settings saved')
            ->body('Your tracking pixel configurations have been updated.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Tracking & Pixels';
    }
}
