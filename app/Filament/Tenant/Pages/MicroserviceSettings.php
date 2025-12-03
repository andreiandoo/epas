<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Microservice;
use App\Models\TenantMicroservice;
use BackedEnum;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class MicroserviceSettings extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'microservices/{slug}/settings';
    protected string $view = 'filament.tenant.pages.microservice-settings';

    public ?string $microserviceSlug = null;
    public ?Microservice $microservice = null;
    public ?TenantMicroservice $tenantMicroservice = null;
    public ?array $data = [];

    public function mount(string $slug): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(404);
        }

        $this->microserviceSlug = $slug;
        $this->microservice = Microservice::where('slug', $slug)->firstOrFail();

        // Check if tenant has this microservice active
        $this->tenantMicroservice = TenantMicroservice::where('tenant_id', $tenant->id)
            ->where('microservice_id', $this->microservice->id)
            ->where('is_active', true)
            ->first();

        if (!$this->tenantMicroservice) {
            abort(403, 'You do not have access to this microservice.');
        }

        // Load saved settings
        $this->form->fill($this->tenantMicroservice->settings ?? []);
    }

    public function getTitle(): string
    {
        return $this->microservice?->getTranslation('name', app()->getLocale()) . ' Settings';
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.tenant.pages.microservices') => 'Microservices',
            '#' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Microservices')
                ->url(route('filament.tenant.pages.microservices'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function form(Schema $form): Schema
    {
        $schemaComponents = $this->getSchemaForMicroservice();

        return $form
            ->schema($schemaComponents)
            ->statePath('data');
    }

    protected function getSchemaForMicroservice(): array
    {
        return match ($this->microserviceSlug) {
            'tracking-pixels-manager' => $this->getTrackingPixelsSchema(),
            'invitations' => $this->getInvitationsSchema(),
            'ticket-insurance' => $this->getTicketInsuranceSchema(),
            'analytics' => $this->getAnalyticsSchema(),
            'payment-stripe' => $this->getStripeSchema(),
            'payment-netopia' => $this->getNetopiaSchema(),
            'payment-euplatesc' => $this->getEuplatescSchema(),
            'payment-payu' => $this->getPayuSchema(),
            'crm' => $this->getCrmSchema(),
            'mobile-wallet' => $this->getMobileWalletSchema(),
            'waitlist' => $this->getWaitlistSchema(),
            'door-sales' => $this->getDoorSalesSchema(),
            'affiliate-tracking' => $this->getAffiliateTrackingSchema(),
            default => $this->getDefaultSchema(),
        };
    }

    protected function getTrackingPixelsSchema(): array
    {
        return [
            SC\Section::make('Google Analytics 4')
                ->description('Track website visitors and conversions')
                ->schema([
                    Forms\Components\Toggle::make('ga4_enabled')
                        ->label('Enable Google Analytics 4')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('ga4_measurement_id')
                        ->label('Measurement ID')
                        ->placeholder('G-XXXXXXXXXX')
                        ->helperText('Find this in your GA4 Admin > Data Streams')
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('ga4_enabled')),
                ])->columns(1),

            SC\Section::make('Google Tag Manager')
                ->description('Manage all your tags in one place')
                ->schema([
                    Forms\Components\Toggle::make('gtm_enabled')
                        ->label('Enable Google Tag Manager')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('gtm_container_id')
                        ->label('Container ID')
                        ->placeholder('GTM-XXXXXXX')
                        ->helperText('Find this in GTM > Admin > Container Settings')
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('gtm_enabled')),
                ])->columns(1),

            SC\Section::make('Meta Pixel (Facebook)')
                ->description('Track conversions from Meta ads')
                ->schema([
                    Forms\Components\Toggle::make('meta_pixel_enabled')
                        ->label('Enable Meta Pixel')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('meta_pixel_id')
                        ->label('Pixel ID')
                        ->placeholder('123456789012345')
                        ->helperText('Find this in Meta Events Manager')
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('meta_pixel_enabled')),
                ])->columns(1),

            SC\Section::make('TikTok Pixel')
                ->description('Track conversions from TikTok ads')
                ->schema([
                    Forms\Components\Toggle::make('tiktok_pixel_enabled')
                        ->label('Enable TikTok Pixel')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('tiktok_pixel_id')
                        ->label('Pixel ID')
                        ->placeholder('XXXXXXXXXXXXXXXXXX')
                        ->helperText('Find this in TikTok Ads Manager')
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('tiktok_pixel_enabled')),
                ])->columns(1),

            SC\Section::make('GDPR Consent')
                ->description('Configure consent management')
                ->schema([
                    Forms\Components\Toggle::make('require_consent')
                        ->label('Require Cookie Consent')
                        ->default(true)
                        ->helperText('Show a consent banner before loading tracking pixels'),
                    Forms\Components\Textarea::make('privacy_policy_url')
                        ->label('Privacy Policy URL')
                        ->placeholder('https://yoursite.com/privacy')
                        ->rows(1),
                ])->columns(1),
        ];
    }

    protected function getInvitationsSchema(): array
    {
        return [
            SC\Section::make('Invitation Settings')
                ->description('Configure default invitation behavior')
                ->schema([
                    Forms\Components\TextInput::make('default_batch_size')
                        ->label('Default Batch Size')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->helperText('Default number of invitations when creating a batch'),

                    Forms\Components\TextInput::make('invitation_validity_days')
                        ->label('Validity Period (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->maxValue(365)
                        ->helperText('How long invitations remain valid'),
                ])->columns(2),

            SC\Section::make('Email Settings')
                ->description('Configure invitation email delivery')
                ->schema([
                    Forms\Components\Toggle::make('auto_send_email')
                        ->label('Auto-send Emails')
                        ->default(false)
                        ->helperText('Automatically send invitation emails when created'),

                    Forms\Components\TextInput::make('email_from_name')
                        ->label('From Name')
                        ->placeholder('Your Event Team')
                        ->helperText('Sender name for invitation emails'),

                    Forms\Components\TextInput::make('email_reply_to')
                        ->label('Reply-to Email')
                        ->email()
                        ->placeholder('events@yourdomain.com')
                        ->helperText('Where recipients can reply'),
                ])->columns(1),

            SC\Section::make('PDF Customization')
                ->description('Customize invitation PDF appearance')
                ->schema([
                    Forms\Components\TextInput::make('watermark_text')
                        ->label('Watermark Text')
                        ->placeholder('INVITATION')
                        ->default('INVITATION')
                        ->helperText('Text shown as watermark on the ticket'),

                    Forms\Components\ColorPicker::make('watermark_color')
                        ->label('Watermark Color')
                        ->default('#cccccc'),
                ])->columns(2),
        ];
    }

    protected function getTicketInsuranceSchema(): array
    {
        return [
            SC\Section::make('Insurance Provider')
                ->description('Configure your insurance provider integration')
                ->schema([
                    Forms\Components\Select::make('insurance_provider')
                        ->label('Provider')
                        ->options([
                            'refundable' => 'Refundable.me',
                            'allianz' => 'Allianz Event Insurance',
                            'axa' => 'AXA Partners',
                            'custom' => 'Custom Provider',
                        ])
                        ->default('refundable')
                        ->live()
                        ->helperText('Select your insurance provider'),

                    Forms\Components\TextInput::make('provider_api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->helperText('Your insurance provider API key'),

                    Forms\Components\TextInput::make('provider_merchant_id')
                        ->label('Merchant ID')
                        ->helperText('Your merchant ID with the provider'),
                ])->columns(1),

            SC\Section::make('Insurance Display')
                ->description('How insurance is shown during checkout')
                ->schema([
                    Forms\Components\Toggle::make('show_by_default')
                        ->label('Pre-select Insurance')
                        ->default(false)
                        ->helperText('Show insurance as pre-selected during checkout'),

                    Forms\Components\TextInput::make('insurance_percentage')
                        ->label('Insurance Rate (%)')
                        ->numeric()
                        ->suffix('%')
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Percentage of ticket price for insurance'),

                    Forms\Components\Textarea::make('insurance_description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Protect your purchase. Get a full refund if you can\'t attend.')
                        ->helperText('Shown to customers during checkout'),
                ])->columns(1),
        ];
    }

    protected function getAnalyticsSchema(): array
    {
        return [
            SC\Section::make('Dashboard Settings')
                ->description('Configure your analytics dashboard')
                ->schema([
                    Forms\Components\Select::make('default_period')
                        ->label('Default Time Period')
                        ->options([
                            '7d' => 'Last 7 days',
                            '30d' => 'Last 30 days',
                            '90d' => 'Last 90 days',
                            'ytd' => 'Year to date',
                        ])
                        ->default('30d'),

                    Forms\Components\Select::make('currency_display')
                        ->label('Currency Display')
                        ->options([
                            'EUR' => 'Euro (€)',
                            'USD' => 'US Dollar ($)',
                            'GBP' => 'British Pound (£)',
                            'RON' => 'Romanian Leu (lei)',
                        ])
                        ->default('EUR'),
                ])->columns(2),

            SC\Section::make('Report Scheduling')
                ->description('Automated report delivery')
                ->schema([
                    Forms\Components\Toggle::make('weekly_report_enabled')
                        ->label('Weekly Report')
                        ->default(false)
                        ->helperText('Receive a weekly summary every Monday'),

                    Forms\Components\Toggle::make('monthly_report_enabled')
                        ->label('Monthly Report')
                        ->default(false)
                        ->helperText('Receive a monthly summary on the 1st'),

                    Forms\Components\TextInput::make('report_email')
                        ->label('Report Email')
                        ->email()
                        ->placeholder('reports@yourcompany.com')
                        ->helperText('Where to send automated reports'),
                ])->columns(1),
        ];
    }

    protected function getStripeSchema(): array
    {
        return [
            SC\Section::make('Stripe Configuration')
                ->description('Your Stripe integration is managed in Payment Processor settings')
                ->schema([
                    Forms\Components\Placeholder::make('stripe_redirect')
                        ->content(new HtmlString('
                            <div class="text-center py-4">
                                <p class="text-gray-600 mb-4">Configure your Stripe API keys in the Payment Processor settings page.</p>
                                <a href="' . route('filament.tenant.pages.payment-config') . '"
                                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    Go to Payment Settings
                                </a>
                            </div>
                        ')),
                ]),
        ];
    }

    protected function getNetopiaSchema(): array
    {
        return $this->getStripeSchema(); // Same redirect
    }

    protected function getEuplatescSchema(): array
    {
        return $this->getStripeSchema(); // Same redirect
    }

    protected function getPayuSchema(): array
    {
        return $this->getStripeSchema(); // Same redirect
    }

    protected function getCrmSchema(): array
    {
        return [
            SC\Section::make('CRM Settings')
                ->description('Configure customer relationship management')
                ->schema([
                    Forms\Components\Toggle::make('auto_create_customers')
                        ->label('Auto-create Customer Profiles')
                        ->default(true)
                        ->helperText('Automatically create customer profiles from orders'),

                    Forms\Components\Toggle::make('track_email_opens')
                        ->label('Track Email Opens')
                        ->default(true)
                        ->helperText('Track when customers open your emails'),

                    Forms\Components\Select::make('customer_segments')
                        ->label('Default Segments')
                        ->multiple()
                        ->options([
                            'vip' => 'VIP Customers',
                            'repeat' => 'Repeat Buyers',
                            'inactive' => 'Inactive (90+ days)',
                            'high_value' => 'High Value (€500+)',
                        ])
                        ->helperText('Auto-segment customers based on behavior'),
                ])->columns(1),
        ];
    }

    protected function getMobileWalletSchema(): array
    {
        return [
            SC\Section::make('Apple Wallet')
                ->description('Configure Apple Wallet passes')
                ->schema([
                    Forms\Components\TextInput::make('apple_team_id')
                        ->label('Team ID')
                        ->placeholder('XXXXXXXXXX')
                        ->helperText('Your Apple Developer Team ID'),

                    Forms\Components\TextInput::make('apple_pass_type_id')
                        ->label('Pass Type ID')
                        ->placeholder('pass.com.yourcompany.event')
                        ->helperText('Registered pass type identifier'),
                ])->columns(2),

            SC\Section::make('Google Wallet')
                ->description('Configure Google Wallet passes')
                ->schema([
                    Forms\Components\TextInput::make('google_issuer_id')
                        ->label('Issuer ID')
                        ->placeholder('1234567890')
                        ->helperText('Your Google Pay Issuer ID'),

                    Forms\Components\Textarea::make('google_service_account')
                        ->label('Service Account JSON')
                        ->rows(4)
                        ->helperText('Paste your Google service account JSON'),
                ])->columns(1),
        ];
    }

    protected function getWaitlistSchema(): array
    {
        return [
            SC\Section::make('Waitlist Settings')
                ->description('Configure waitlist behavior')
                ->schema([
                    Forms\Components\Toggle::make('auto_notify')
                        ->label('Auto-notify on Availability')
                        ->default(true)
                        ->helperText('Automatically email waitlisted customers when tickets become available'),

                    Forms\Components\TextInput::make('hold_duration_minutes')
                        ->label('Hold Duration (minutes)')
                        ->numeric()
                        ->default(30)
                        ->minValue(5)
                        ->maxValue(1440)
                        ->helperText('How long to hold tickets for notified customers'),

                    Forms\Components\TextInput::make('max_waitlist_size')
                        ->label('Max Waitlist Size')
                        ->numeric()
                        ->default(100)
                        ->helperText('Maximum customers on waitlist per event (0 = unlimited)'),
                ])->columns(1),
        ];
    }

    protected function getDoorSalesSchema(): array
    {
        return [
            SC\Section::make('Door Sales Settings')
                ->description('Configure point-of-sale settings')
                ->schema([
                    Forms\Components\Toggle::make('allow_cash')
                        ->label('Accept Cash')
                        ->default(true),

                    Forms\Components\Toggle::make('allow_card')
                        ->label('Accept Card')
                        ->default(true),

                    Forms\Components\Toggle::make('print_receipt')
                        ->label('Auto-print Receipts')
                        ->default(true)
                        ->helperText('Automatically print receipt after each sale'),

                    Forms\Components\Select::make('receipt_printer')
                        ->label('Receipt Printer Type')
                        ->options([
                            'thermal' => 'Thermal Printer (58mm)',
                            'thermal_80' => 'Thermal Printer (80mm)',
                            'a4' => 'Standard A4 Printer',
                        ])
                        ->default('thermal'),
                ])->columns(2),
        ];
    }

    protected function getAffiliateTrackingSchema(): array
    {
        return [
            SC\Section::make('Affiliate Program')
                ->description('Configure your affiliate program')
                ->schema([
                    Forms\Components\TextInput::make('default_commission')
                        ->label('Default Commission (%)')
                        ->numeric()
                        ->suffix('%')
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Default commission rate for affiliates'),

                    Forms\Components\TextInput::make('cookie_duration_days')
                        ->label('Cookie Duration (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->maxValue(365)
                        ->helperText('How long affiliate cookies last'),

                    Forms\Components\TextInput::make('minimum_payout')
                        ->label('Minimum Payout (€)')
                        ->numeric()
                        ->prefix('€')
                        ->default(50)
                        ->helperText('Minimum balance before payout is available'),
                ])->columns(1),
        ];
    }

    protected function getDefaultSchema(): array
    {
        return [
            SC\Section::make('Microservice Information')
                ->schema([
                    Forms\Components\Placeholder::make('info')
                        ->content(new HtmlString('
                            <div class="text-center py-8">
                                <x-heroicon-o-cog-6-tooth class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                <p class="text-gray-600">This microservice does not require additional configuration.</p>
                                <p class="text-sm text-gray-500 mt-2">It\'s ready to use with default settings.</p>
                            </div>
                        ')),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->tenantMicroservice->update([
            'settings' => $data,
        ]);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Your microservice settings have been updated.')
            ->send();
    }

    public static function getRoutes(): \Illuminate\Routing\RouteGroup|\Closure
    {
        return function () {
            \Illuminate\Support\Facades\Route::get('/microservices/{slug}/settings', static::class)
                ->name('microservice-settings');
        };
    }
}
