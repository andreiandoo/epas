<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Microservice;
use App\Models\Tenant;
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
    public ?Tenant $tenant = null;
    public ?string $activatedAt = null;
    public ?array $data = [];

    public function mount(string $slug): void
    {
        $this->tenant = auth()->user()->tenant;

        if (!$this->tenant) {
            abort(404);
        }

        $this->microserviceSlug = $slug;
        $this->microservice = Microservice::where('slug', $slug)->firstOrFail();

        // Check if tenant has this microservice active (using pivot table)
        $activeMicroservice = $this->tenant->microservices()
            ->where('microservices.id', $this->microservice->id)
            ->wherePivot('is_active', true)
            ->first();

        if (!$activeMicroservice) {
            abort(403, 'You do not have access to this microservice.');
        }

        // Store activation date for view (as string to avoid Livewire serialization issues)
        $this->activatedAt = $activeMicroservice->pivot->activated_at;

        // Load saved settings from pivot configuration
        // Handle case where configuration might be stored as JSON string
        $config = $activeMicroservice->pivot->configuration;
        if (is_string($config)) {
            $config = json_decode($config, true) ?? [];
        }
        $this->form->fill($config ?? []);
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
            'whatsapp-notifications', 'whatsapp', 'whatsapp-cloud' => $this->getWhatsAppSchema(),
            'shop' => $this->getShopSchema(),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Find this in your GA4 Admin > Data Streams')
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Find this in GTM > Admin > Container Settings')
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Find this in Meta Events Manager')
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Find this in TikTok Ads Manager')
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('tiktok_pixel_enabled')),
                ])->columns(1),

            SC\Section::make('GDPR Consent')
                ->description('Configure consent management')
                ->schema([
                    Forms\Components\Toggle::make('require_consent')
                        ->label('Require Cookie Consent')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Show a consent banner before loading tracking pixels'),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Default number of invitations when creating a batch'),

                    Forms\Components\TextInput::make('invitation_validity_days')
                        ->label('Validity Period (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->maxValue(365)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How long invitations remain valid'),
                ])->columns(2),

            SC\Section::make('Email Settings')
                ->description('Configure invitation email delivery')
                ->schema([
                    Forms\Components\Toggle::make('auto_send_email')
                        ->label('Auto-send Emails')
                        ->default(false)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Automatically send invitation emails when created'),

                    Forms\Components\TextInput::make('email_from_name')
                        ->label('From Name')
                        ->placeholder('Your Event Team')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender name for invitation emails'),

                    Forms\Components\TextInput::make('email_reply_to')
                        ->label('Reply-to Email')
                        ->email()
                        ->placeholder('events@yourdomain.com')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Where recipients can reply'),
                ])->columns(1),

            SC\Section::make('PDF Customization')
                ->description('Customize invitation PDF appearance')
                ->schema([
                    Forms\Components\TextInput::make('watermark_text')
                        ->label('Watermark Text')
                        ->placeholder('INVITATION')
                        ->default('INVITATION')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Text shown as watermark on the ticket'),

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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Select your insurance provider'),

                    Forms\Components\TextInput::make('provider_api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Your insurance provider API key'),

                    Forms\Components\TextInput::make('provider_merchant_id')
                        ->label('Merchant ID')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Your merchant ID with the provider'),
                ])->columns(1),

            SC\Section::make('Insurance Display')
                ->description('How insurance is shown during checkout')
                ->schema([
                    Forms\Components\Toggle::make('show_by_default')
                        ->label('Pre-select Insurance')
                        ->default(false)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Show insurance as pre-selected during checkout'),

                    Forms\Components\TextInput::make('insurance_percentage')
                        ->label('Insurance Rate (%)')
                        ->numeric()
                        ->suffix('%')
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(50)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Percentage of ticket price for insurance'),

                    Forms\Components\Textarea::make('insurance_description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Protect your purchase. Get a full refund if you can\'t attend.')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Shown to customers during checkout'),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Receive a weekly summary every Monday'),

                    Forms\Components\Toggle::make('monthly_report_enabled')
                        ->label('Monthly Report')
                        ->default(false)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Receive a monthly summary on the 1st'),

                    Forms\Components\TextInput::make('report_email')
                        ->label('Report Email')
                        ->email()
                        ->placeholder('reports@yourcompany.com')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Where to send automated reports'),
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
                            <div class="py-4 text-center">
                                <p class="mb-4 text-gray-600">Configure your Stripe API keys in the Payment Processor settings page.</p>
                                <a href="' . route('filament.tenant.pages.payment-config') . '"
                                   class="inline-flex items-center px-4 py-2 text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Automatically create customer profiles from orders'),

                    Forms\Components\Toggle::make('track_email_opens')
                        ->label('Track Email Opens')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Track when customers open your emails'),

                    Forms\Components\Select::make('customer_segments')
                        ->label('Default Segments')
                        ->multiple()
                        ->options([
                            'vip' => 'VIP Customers',
                            'repeat' => 'Repeat Buyers',
                            'inactive' => 'Inactive (90+ days)',
                            'high_value' => 'High Value (€500+)',
                        ])
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Auto-segment customers based on behavior'),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Apple Developer Team ID'),

                    Forms\Components\TextInput::make('apple_pass_type_id')
                        ->label('Pass Type ID')
                        ->placeholder('pass.com.yourcompany.event')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Registered pass type identifier'),
                ])->columns(2),

            SC\Section::make('Google Wallet')
                ->description('Configure Google Wallet passes')
                ->schema([
                    Forms\Components\TextInput::make('google_issuer_id')
                        ->label('Issuer ID')
                        ->placeholder('1234567890')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Google Pay Issuer ID'),

                    Forms\Components\Textarea::make('google_service_account')
                        ->label('Service Account JSON')
                        ->rows(4)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Paste your Google service account JSON'),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Automatically email waitlisted customers when tickets become available'),

                    Forms\Components\TextInput::make('hold_duration_minutes')
                        ->label('Hold Duration (minutes)')
                        ->numeric()
                        ->default(30)
                        ->minValue(5)
                        ->maxValue(1440)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How long to hold tickets for notified customers'),

                    Forms\Components\TextInput::make('max_waitlist_size')
                        ->label('Max Waitlist Size')
                        ->numeric()
                        ->default(100)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Maximum customers on waitlist per event (0 = unlimited)'),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Automatically print receipt after each sale'),

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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Default commission rate for affiliates'),

                    Forms\Components\TextInput::make('cookie_duration_days')
                        ->label('Cookie Duration (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->maxValue(365)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How long affiliate cookies last'),

                    Forms\Components\TextInput::make('minimum_payout')
                        ->label('Minimum Payout (€)')
                        ->numeric()
                        ->prefix('€')
                        ->default(50)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Minimum balance before payout is available'),
                ])->columns(1),
        ];
    }

    protected function getWhatsAppSchema(): array
    {
        return [
            SC\Section::make('WhatsApp Cloud API')
                ->description('Configure your WhatsApp Business API credentials')
                ->schema([
                    Forms\Components\TextInput::make('phone_number_id')
                        ->label('Phone Number ID')
                        ->placeholder('123456789012345')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'From Meta Business Suite > WhatsApp > Phone Numbers'),

                    Forms\Components\TextInput::make('business_account_id')
                        ->label('Business Account ID')
                        ->placeholder('123456789012345')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'From Meta Business Suite > Business Settings'),

                    Forms\Components\TextInput::make('access_token')
                        ->label('Permanent Access Token')
                        ->password()
                        ->revealable()
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Generate a permanent token in Meta Business Settings'),

                    Forms\Components\TextInput::make('webhook_verify_token')
                        ->label('Webhook Verify Token')
                        ->placeholder('your-custom-verify-token')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Custom token for webhook verification (you create this)'),

                    Forms\Components\Placeholder::make('webhook_url')
                        ->label('Webhook URL')
                        ->content(new HtmlString('
                            <code class="px-2 py-1 text-sm bg-gray-100 rounded dark:bg-gray-700">
                                ' . url('/webhooks/whatsapp-cloud') . '
                            </code>
                            <p class="mt-1 text-xs text-gray-500">Use this URL in Meta Business Suite webhook configuration</p>
                        ')),
                ])->columns(1),

            SC\Section::make('Notification Types')
                ->description('Choose which notifications to send via WhatsApp')
                ->schema([
                    Forms\Components\Toggle::make('notify_order_confirmation')
                        ->label('Order Confirmation')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Send confirmation when order is placed'),

                    Forms\Components\Toggle::make('notify_ticket_delivery')
                        ->label('Ticket Delivery')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Send tickets as PDF via WhatsApp'),

                    Forms\Components\Toggle::make('notify_event_reminder')
                        ->label('Event Reminders')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Send reminders before the event'),

                    Forms\Components\Toggle::make('notify_event_updates')
                        ->label('Event Updates')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Notify about event changes or cancellations'),
                ])->columns(2),

            SC\Section::make('Reminder Settings')
                ->description('Configure when to send event reminders')
                ->schema([
                    Forms\Components\Toggle::make('reminder_1_day')
                        ->label('1 Day Before')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Send reminder 24 hours before event'),

                    Forms\Components\Toggle::make('reminder_3_hours')
                        ->label('3 Hours Before')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Send reminder 3 hours before event'),

                    Forms\Components\TextInput::make('reminder_custom_hours')
                        ->label('Custom Reminder (hours)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(168)
                        ->placeholder('e.g., 48 for 2 days')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Optional: Send additional reminder X hours before'),
                ])->columns(3),

            SC\Section::make('Message Templates')
                ->description('Configure message templates (must be approved by Meta)')
                ->schema([
                    Forms\Components\TextInput::make('template_order_confirmation')
                        ->label('Order Confirmation Template')
                        ->placeholder('order_confirmation_v1')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Template name for order confirmations'),

                    Forms\Components\TextInput::make('template_ticket_delivery')
                        ->label('Ticket Delivery Template')
                        ->placeholder('ticket_delivery_v1')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Template name for ticket delivery'),

                    Forms\Components\TextInput::make('template_event_reminder')
                        ->label('Event Reminder Template')
                        ->placeholder('event_reminder_v1')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Template name for event reminders'),
                ])->columns(1),

            SC\Section::make('Consent & Opt-in')
                ->description('GDPR compliance settings')
                ->schema([
                    Forms\Components\Toggle::make('require_explicit_optin')
                        ->label('Require Explicit Opt-in')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Customers must explicitly opt-in to receive WhatsApp messages'),

                    Forms\Components\Textarea::make('optin_message')
                        ->label('Opt-in Checkbox Text')
                        ->rows(2)
                        ->default('I agree to receive order updates and event reminders via WhatsApp')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Text shown next to the opt-in checkbox during checkout'),
                ])->columns(1),
        ];
    }

    protected function getShopSchema(): array
    {
        return [
            SC\Section::make('Shop Pages')
                ->description('Quick access to shop management pages')
                ->schema([
                    Forms\Components\Placeholder::make('shop_links')
                        ->content(new HtmlString('
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <a href="' . route('filament.tenant.resources.shop-products.index') . '"
                                   class="flex items-center p-4 bg-white border rounded-lg shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                    <svg class="w-8 h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-900 dark:text-white">Products</p>
                                        <p class="text-sm text-gray-500">Manage products & variants</p>
                                    </div>
                                </a>
                                <a href="' . route('filament.tenant.resources.shop-categories.index') . '"
                                   class="flex items-center p-4 bg-white border rounded-lg shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                    <svg class="w-8 h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-900 dark:text-white">Categories</p>
                                        <p class="text-sm text-gray-500">Organize your products</p>
                                    </div>
                                </a>
                                <a href="' . route('filament.tenant.resources.shop-orders.index') . '"
                                   class="flex items-center p-4 bg-white border rounded-lg shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                    <svg class="w-8 h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-900 dark:text-white">Orders</p>
                                        <p class="text-sm text-gray-500">View & manage orders</p>
                                    </div>
                                </a>
                            </div>
                        ')),
                ]),

            SC\Section::make('Store Settings')
                ->description('Configure your store defaults')
                ->schema([
                    Forms\Components\TextInput::make('store_name')
                        ->label('Store Name')
                        ->placeholder('My Online Store')
                        ->maxLength(100),

                    Forms\Components\Select::make('default_currency')
                        ->label('Default Currency')
                        ->options([
                            'RON' => 'RON - Romanian Leu',
                            'EUR' => 'EUR - Euro',
                            'USD' => 'USD - US Dollar',
                        ])
                        ->default('RON'),

                    Forms\Components\TextInput::make('tax_rate')
                        ->label('Default Tax Rate (%)')
                        ->numeric()
                        ->suffix('%')
                        ->default(19)
                        ->minValue(0)
                        ->maxValue(100)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Default VAT rate for products'),

                    Forms\Components\Select::make('tax_mode')
                        ->label('Tax Mode')
                        ->options([
                            'included' => 'Tax Included in Prices',
                            'added_on_top' => 'Tax Added at Checkout',
                        ])
                        ->default('included')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How tax is calculated for products'),
                ])->columns(2),

            SC\Section::make('Checkout Settings')
                ->description('Configure checkout behavior')
                ->schema([
                    Forms\Components\Toggle::make('require_account')
                        ->label('Require Customer Account')
                        ->default(false)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Require customers to create an account to checkout'),

                    Forms\Components\Toggle::make('guest_checkout')
                        ->label('Allow Guest Checkout')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Allow customers to checkout without an account'),

                    Forms\Components\Toggle::make('combined_checkout')
                        ->label('Combined with Tickets')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Allow products and tickets in the same checkout'),

                    Forms\Components\TextInput::make('cart_expiry_hours')
                        ->label('Cart Expiry (hours)')
                        ->numeric()
                        ->default(72)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How long carts remain active for abandonment recovery'),
                ])->columns(2),

            SC\Section::make('Inventory Settings')
                ->description('Configure inventory behavior')
                ->schema([
                    Forms\Components\Toggle::make('track_inventory')
                        ->label('Track Inventory by Default')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Default setting for new products'),

                    Forms\Components\TextInput::make('low_stock_threshold')
                        ->label('Low Stock Alert Threshold')
                        ->numeric()
                        ->default(5)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Default threshold for low stock alerts'),

                    Forms\Components\Toggle::make('allow_backorders')
                        ->label('Allow Backorders')
                        ->default(false)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Allow orders when products are out of stock'),

                    Forms\Components\Toggle::make('stock_alert_emails')
                        ->label('Low Stock Email Alerts')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Receive email when stock is low'),
                ])->columns(2),

            SC\Section::make('Digital Products')
                ->description('Settings for digital downloads')
                ->schema([
                    Forms\Components\TextInput::make('download_limit')
                        ->label('Default Download Limit')
                        ->numeric()
                        ->default(5)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Maximum downloads per purchase (0 = unlimited)'),

                    Forms\Components\TextInput::make('download_expiry_days')
                        ->label('Download Expiry (days)')
                        ->numeric()
                        ->default(30)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Days until download link expires (0 = never)'),
                ])->columns(2),

            SC\Section::make('Abandoned Cart Recovery')
                ->description('Recover abandoned carts with email reminders')
                ->schema([
                    Forms\Components\Toggle::make('abandoned_cart_enabled')
                        ->label('Enable Recovery Emails')
                        ->default(true)
                        ->live(),

                    Forms\Components\TextInput::make('abandoned_cart_hours')
                        ->label('Hours Before First Email')
                        ->numeric()
                        ->default(1)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('abandoned_cart_enabled'))
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Wait time before sending first recovery email'),

                    Forms\Components\TextInput::make('abandoned_cart_max_emails')
                        ->label('Maximum Recovery Emails')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(5)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('abandoned_cart_enabled'))
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'How many recovery emails to send'),
                ])->columns(3),

            SC\Section::make('Reviews & Ratings')
                ->description('Configure product review settings')
                ->schema([
                    Forms\Components\Toggle::make('reviews_enabled')
                        ->label('Enable Product Reviews')
                        ->default(true),

                    Forms\Components\Toggle::make('reviews_require_purchase')
                        ->label('Require Purchase to Review')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Only customers who purchased can leave reviews'),

                    Forms\Components\Toggle::make('reviews_moderation')
                        ->label('Moderate Reviews')
                        ->default(true)
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Require approval before reviews are visible'),
                ])->columns(3),
        ];
    }

    protected function getDefaultSchema(): array
    {
        return [
            SC\Section::make('Microservice Information')
                ->schema([
                    Forms\Components\Placeholder::make('info')
                        ->content(new HtmlString('
                            <div class="py-8 text-center">
                                <x-heroicon-o-cog-6-tooth class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                <p class="text-gray-600">This microservice does not require additional configuration.</p>
                                <p class="mt-2 text-sm text-gray-500">It\'s ready to use with default settings.</p>
                            </div>
                        ')),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Update configuration in pivot table
        $this->tenant->microservices()->updateExistingPivot(
            $this->microservice->id,
            ['configuration' => $data]
        );

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
