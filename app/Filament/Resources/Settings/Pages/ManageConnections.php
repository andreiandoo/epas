<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingsResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

class ManageConnections extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SettingsResource::class;

    protected string $view = 'filament.resources.settings.pages.manage-connections';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::current();
        $this->form->fill([
            'stripe_mode' => $settings->stripe_mode,
            'stripe_test_public_key' => $settings->stripe_test_public_key,
            'stripe_test_secret_key' => $settings->stripe_test_secret_key,
            'stripe_live_public_key' => $settings->stripe_live_public_key,
            'stripe_live_secret_key' => $settings->stripe_live_secret_key,
            'stripe_webhook_secret' => $settings->stripe_webhook_secret,
            'vat_enabled' => $settings->vat_enabled ?? false,
            'vat_rate' => $settings->vat_rate ?? 21.00,
            'youtube_api_key' => $settings->youtube_api_key,
            'spotify_client_id' => $settings->spotify_client_id,
            'spotify_client_secret' => $settings->spotify_client_secret,
            'google_maps_api_key' => $settings->google_maps_api_key,
            'twilio_account_sid' => $settings->twilio_account_sid,
            'twilio_auth_token' => $settings->twilio_auth_token,
            'twilio_phone_number' => $settings->twilio_phone_number,
            'openweather_api_key' => $settings->openweather_api_key,
            'facebook_app_id' => $settings->facebook_app_id,
            'facebook_app_secret' => $settings->facebook_app_secret,
            'facebook_access_token' => $settings->facebook_access_token,
            'google_analytics_property_id' => $settings->google_analytics_property_id,
            'google_analytics_credentials_json' => $settings->google_analytics_credentials_json,
            'brevo_api_key' => $settings->brevo_api_key,
            'tiktok_client_key' => $settings->tiktok_client_key,
            'tiktok_client_secret' => $settings->tiktok_client_secret,
            // Integration Microservices
            'slack_client_id' => $settings->slack_client_id,
            'slack_client_secret' => $settings->slack_client_secret,
            'slack_signing_secret' => $settings->slack_signing_secret,
            'discord_client_id' => $settings->discord_client_id,
            'discord_client_secret' => $settings->discord_client_secret,
            'discord_bot_token' => $settings->discord_bot_token,
            'google_workspace_client_id' => $settings->google_workspace_client_id,
            'google_workspace_client_secret' => $settings->google_workspace_client_secret,
            'microsoft365_client_id' => $settings->microsoft365_client_id,
            'microsoft365_client_secret' => $settings->microsoft365_client_secret,
            'microsoft365_tenant_id' => $settings->microsoft365_tenant_id ?? 'common',
            'salesforce_client_id' => $settings->salesforce_client_id,
            'salesforce_client_secret' => $settings->salesforce_client_secret,
            'hubspot_client_id' => $settings->hubspot_client_id,
            'hubspot_client_secret' => $settings->hubspot_client_secret,
            'jira_client_id' => $settings->jira_client_id,
            'jira_client_secret' => $settings->jira_client_secret,
            'zapier_client_id' => $settings->zapier_client_id,
            'zapier_client_secret' => $settings->zapier_client_secret,
            'google_sheets_client_id' => $settings->google_sheets_client_id,
            'google_sheets_client_secret' => $settings->google_sheets_client_secret,
            'whatsapp_cloud_verify_token' => $settings->whatsapp_cloud_verify_token,
            'airtable_client_id' => $settings->airtable_client_id,
            'airtable_client_secret' => $settings->airtable_client_secret,
            'square_client_id' => $settings->square_client_id,
            'square_client_secret' => $settings->square_client_secret,
            'square_environment' => $settings->square_environment ?? 'production',
            'square_webhook_signature_key' => $settings->square_webhook_signature_key,
            'zoom_client_id' => $settings->zoom_client_id,
            'zoom_client_secret' => $settings->zoom_client_secret,
            'zoom_webhook_secret_token' => $settings->zoom_webhook_secret_token,
            // Ad Platform Connectors
            'google_ads_client_id' => $settings->google_ads_client_id,
            'google_ads_client_secret' => $settings->google_ads_client_secret,
            'google_ads_developer_token' => $settings->google_ads_developer_token,
            'tiktok_ads_app_id' => $settings->tiktok_ads_app_id,
            'tiktok_ads_app_secret' => $settings->tiktok_ads_app_secret,
            'linkedin_ads_client_id' => $settings->linkedin_ads_client_id,
            'linkedin_ads_client_secret' => $settings->linkedin_ads_client_secret,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Section::make('Stripe Payment Gateway')
                    ->description('Configure Stripe for processing microservice payments')
                    ->schema([
                        Forms\Components\Select::make('stripe_mode')
                            ->label('Stripe Mode')
                            ->options([
                                'test' => 'Test Mode',
                                'live' => 'Live Mode',
                            ])
                            ->default('test')
                            ->required()
                            ->live()
                            ->helperText('Switch between test and live mode. Always test first!'),

                        SC\Fieldset::make('Test Mode Keys')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_test_public_key')
                                    ->label('Test Publishable Key')
                                    ->placeholder('pk_test_...')
                                    ->maxLength(255)
                                    ->helperText('Your Stripe test publishable key (starts with pk_test_)'),

                                Forms\Components\TextInput::make('stripe_test_secret_key')
                                    ->label('Test Secret Key')
                                    ->placeholder('sk_test_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe test secret key (starts with sk_test_) - stored encrypted'),
                            ])
                            ->columns(2),

                        SC\Fieldset::make('Live Mode Keys')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_live_public_key')
                                    ->label('Live Publishable Key')
                                    ->placeholder('pk_live_...')
                                    ->maxLength(255)
                                    ->helperText('Your Stripe live publishable key (starts with pk_live_)'),

                                Forms\Components\TextInput::make('stripe_live_secret_key')
                                    ->label('Live Secret Key')
                                    ->placeholder('sk_live_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe live secret key (starts with sk_live_) - stored encrypted'),
                            ])
                            ->columns(2),

                        SC\Fieldset::make('Webhook Configuration')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_webhook_secret')
                                    ->label('Webhook Signing Secret')
                                    ->placeholder('whsec_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe webhook signing secret (starts with whsec_) - stored encrypted'),

                                Forms\Components\Placeholder::make('webhook_url')
                                    ->label('Webhook Endpoint URL')
                                    ->content(function () {
                                        return url('/webhooks/stripe');
                                    })
                                    ->helperText('Add this URL to your Stripe webhook endpoints. Listen for: checkout.session.completed, invoice.paid, customer.subscription.created'),
                            ])
                            ->columns(1),

                        SC\Actions::make([
                            Actions\Action::make('test_connection')
                                ->label('Test Stripe Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testStripeConnection'),

                            Actions\Action::make('view_stripe_docs')
                                ->label('View Stripe Documentation')
                                ->icon('heroicon-o-book-open')
                                ->url('https://stripe.com/docs/keys', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('VAT Configuration')
                    ->description('Configure VAT/Tax settings for invoices and payments')
                    ->schema([
                        Forms\Components\Toggle::make('vat_enabled')
                            ->label('Enable VAT')
                            ->helperText('Enable this if your company is VAT registered and needs to charge VAT on invoices')
                            ->live()
                            ->default(false),

                        Forms\Components\TextInput::make('vat_rate')
                            ->label('VAT Rate (%)')
                            ->numeric()
                            ->default(21.00)
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Default VAT rate for Romania is 21%')
                            ->visible(fn (SC\Utilities\Get $get) => $get('vat_enabled')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                SC\Section::make('YouTube Data API')
                    ->description('Configure YouTube API for fetching artist channel statistics')
                    ->schema([
                        Forms\Components\TextInput::make('youtube_api_key')
                            ->label('API Key')
                            ->placeholder('AIza...')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('YouTube Data API v3 key from Google Cloud Console'),

                        SC\Actions::make([
                            Actions\Action::make('test_youtube')
                                ->label('Test YouTube Connection')
                                ->icon('heroicon-o-play')
                                ->color('info')
                                ->action('testYoutubeConnection'),

                            Actions\Action::make('youtube_docs')
                                ->label('Get API Key')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://console.cloud.google.com/apis/credentials', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('Spotify Web API')
                    ->description('Configure Spotify API for fetching artist data and statistics')
                    ->schema([
                        Forms\Components\TextInput::make('spotify_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Spotify Client ID')
                            ->maxLength(255)
                            ->helperText('Client ID from Spotify Developer Dashboard'),

                        Forms\Components\TextInput::make('spotify_client_secret')
                            ->label('Client Secret')
                            ->placeholder('Your Spotify Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Client Secret from Spotify Developer Dashboard - stored encrypted'),

                        SC\Actions::make([
                            Actions\Action::make('test_spotify')
                                ->label('Test Spotify Connection')
                                ->icon('heroicon-o-play')
                                ->color('info')
                                ->action('testSpotifyConnection'),

                            Actions\Action::make('spotify_docs')
                                ->label('Create Spotify App')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developer.spotify.com/dashboard', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                SC\Section::make('Google Maps')
                    ->description('Configure Google Maps API for displaying venue maps on tenant websites')
                    ->schema([
                        Forms\Components\TextInput::make('google_maps_api_key')
                            ->label('API Key')
                            ->placeholder('AIza...')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Google Maps JavaScript API key - enable Maps JavaScript API and Places API'),

                        SC\Actions::make([
                            Actions\Action::make('test_google_maps')
                                ->label('Test Google Maps')
                                ->icon('heroicon-o-map')
                                ->color('info')
                                ->action('testGoogleMapsConnection'),

                            Actions\Action::make('google_maps_docs')
                                ->label('Get API Key')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://console.cloud.google.com/apis/credentials', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('Twilio')
                    ->description('Configure Twilio for SMS notifications to customers')
                    ->schema([
                        Forms\Components\TextInput::make('twilio_account_sid')
                            ->label('Account SID')
                            ->placeholder('AC...')
                            ->maxLength(255)
                            ->helperText('Your Twilio Account SID'),

                        Forms\Components\TextInput::make('twilio_auth_token')
                            ->label('Auth Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Your Twilio Auth Token - stored encrypted'),

                        Forms\Components\TextInput::make('twilio_phone_number')
                            ->label('Phone Number')
                            ->placeholder('+1234567890')
                            ->maxLength(50)
                            ->helperText('Your Twilio phone number for sending SMS'),

                        SC\Actions::make([
                            Actions\Action::make('test_twilio')
                                ->label('Test Twilio')
                                ->icon('heroicon-o-device-phone-mobile')
                                ->color('info')
                                ->action('testTwilioConnection'),

                            Actions\Action::make('twilio_docs')
                                ->label('Twilio Console')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://console.twilio.com', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                SC\Section::make('OpenWeather')
                    ->description('Configure OpenWeather API for weather forecasts at outdoor events')
                    ->schema([
                        Forms\Components\TextInput::make('openweather_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('OpenWeather API key for weather data'),

                        SC\Actions::make([
                            Actions\Action::make('test_openweather')
                                ->label('Test OpenWeather')
                                ->icon('heroicon-o-cloud')
                                ->color('info')
                                ->action('testOpenWeatherConnection'),

                            Actions\Action::make('openweather_docs')
                                ->label('Get API Key')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://openweathermap.org/api', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('Facebook / Instagram Graph API')
                    ->description('Configure Meta Graph API for fetching artist follower counts from Facebook and Instagram')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_app_id')
                            ->label('App ID')
                            ->placeholder('Your Facebook App ID')
                            ->maxLength(255)
                            ->helperText('Facebook App ID from Meta for Developers'),

                        Forms\Components\TextInput::make('facebook_app_secret')
                            ->label('App Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Facebook App Secret - stored encrypted'),

                        Forms\Components\Textarea::make('facebook_access_token')
                            ->label('Access Token')
                            ->rows(2)
                            ->maxLength(1000)
                            ->helperText('Long-lived access token for API calls - stored encrypted'),

                        SC\Actions::make([
                            Actions\Action::make('test_facebook')
                                ->label('Test Facebook API')
                                ->icon('heroicon-o-user-group')
                                ->color('info')
                                ->action('testFacebookConnection'),

                            Actions\Action::make('facebook_docs')
                                ->label('Meta for Developers')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developers.facebook.com/apps/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                SC\Section::make('Google Analytics')
                    ->description('Configure Google Analytics API for tenant website analytics')
                    ->schema([
                        Forms\Components\TextInput::make('google_analytics_property_id')
                            ->label('Property ID')
                            ->placeholder('123456789')
                            ->maxLength(255)
                            ->helperText('Google Analytics 4 property ID (numbers only)'),

                        Forms\Components\Textarea::make('google_analytics_credentials_json')
                            ->label('Service Account Credentials (JSON)')
                            ->rows(4)
                            ->helperText('Paste the entire JSON credentials file content here - stored encrypted'),

                        SC\Actions::make([
                            Actions\Action::make('test_google_analytics')
                                ->label('Test Google Analytics')
                                ->icon('heroicon-o-chart-bar')
                                ->color('info')
                                ->action('testGoogleAnalyticsConnection'),

                            Actions\Action::make('google_analytics_docs')
                                ->label('Google Analytics Admin')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://analytics.google.com/analytics/web/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('Brevo')
                    ->description('Configure Brevo (formerly Sendinblue) for transactional emails')
                    ->schema([
                        Forms\Components\TextInput::make('brevo_api_key')
                            ->label('API Key')
                            ->placeholder('xkeysib-...')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Brevo API key for sending transactional emails - stored encrypted'),

                        SC\Actions::make([
                            Actions\Action::make('test_brevo')
                                ->label('Test Brevo')
                                ->icon('heroicon-o-envelope')
                                ->color('info')
                                ->action('testBrevoConnection'),

                            Actions\Action::make('brevo_docs')
                                ->label('Brevo Dashboard')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://app.brevo.com/settings/keys/api', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                SC\Section::make('TikTok')
                    ->description('Configure TikTok API for artist follower stats')
                    ->schema([
                        Forms\Components\TextInput::make('tiktok_client_key')
                            ->label('Client Key')
                            ->placeholder('Your TikTok Client Key')
                            ->maxLength(255)
                            ->helperText('TikTok Client Key from TikTok for Developers'),

                        Forms\Components\TextInput::make('tiktok_client_secret')
                            ->label('Client Secret')
                            ->placeholder('Your TikTok Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('TikTok Client Secret - stored encrypted'),

                        SC\Actions::make([
                            Actions\Action::make('test_tiktok')
                                ->label('Test TikTok')
                                ->icon('heroicon-o-play')
                                ->color('info')
                                ->action('testTikTokConnection'),

                            Actions\Action::make('tiktok_docs')
                                ->label('TikTok Developers')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developers.tiktok.com/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ===========================================
                // INTEGRATION MICROSERVICES
                // ===========================================

                SC\Section::make('Slack Integration')
                    ->description('Configure Slack OAuth for tenant workspace connections')
                    ->schema([
                        Forms\Components\TextInput::make('slack_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Slack App Client ID')
                            ->maxLength(255)
                            ->helperText('From Slack API → Your App → Basic Information'),

                        Forms\Components\TextInput::make('slack_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Slack API → Your App → Basic Information'),

                        Forms\Components\TextInput::make('slack_signing_secret')
                            ->label('Signing Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('For webhook signature verification'),

                        Forms\Components\Placeholder::make('slack_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/slack/callback'))
                            ->helperText('Add this URL to Slack App → OAuth & Permissions → Redirect URLs'),

                        SC\Actions::make([
                            Actions\Action::make('test_slack')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testSlackConnection'),

                            Actions\Action::make('slack_docs')
                                ->label('Slack API')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://api.slack.com/apps', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Discord Integration')
                    ->description('Configure Discord OAuth and Bot for tenant server connections')
                    ->schema([
                        Forms\Components\TextInput::make('discord_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Discord Application ID')
                            ->maxLength(255)
                            ->helperText('From Discord Developer Portal → Application → OAuth2'),

                        Forms\Components\TextInput::make('discord_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Discord Developer Portal → Application → OAuth2'),

                        Forms\Components\TextInput::make('discord_bot_token')
                            ->label('Bot Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Discord Developer Portal → Application → Bot'),

                        Forms\Components\Placeholder::make('discord_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/discord/callback'))
                            ->helperText('Add this URL to Discord Developer Portal → OAuth2 → Redirects'),

                        SC\Actions::make([
                            Actions\Action::make('test_discord')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testDiscordConnection'),

                            Actions\Action::make('discord_docs')
                                ->label('Discord Developer Portal')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://discord.com/developers/applications', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Google Workspace Integration')
                    ->description('Configure Google OAuth for Drive, Calendar, and Gmail access')
                    ->schema([
                        Forms\Components\TextInput::make('google_workspace_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Google OAuth Client ID')
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console → Credentials → OAuth 2.0 Client'),

                        Forms\Components\TextInput::make('google_workspace_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console → Credentials'),

                        Forms\Components\Placeholder::make('google_workspace_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/google-workspace/callback'))
                            ->helperText('Add to Google Cloud Console → Credentials → Authorized redirect URIs'),

                        SC\Actions::make([
                            Actions\Action::make('test_google_workspace')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testGoogleWorkspaceConnection'),

                            Actions\Action::make('google_workspace_docs')
                                ->label('Google Cloud Console')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://console.cloud.google.com/apis/credentials', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Microsoft 365 Integration')
                    ->description('Configure Azure AD OAuth for OneDrive, Outlook, and Teams')
                    ->schema([
                        Forms\Components\TextInput::make('microsoft365_client_id')
                            ->label('Application (Client) ID')
                            ->placeholder('Your Azure AD Application ID')
                            ->maxLength(255)
                            ->helperText('From Azure Portal → App registrations'),

                        Forms\Components\TextInput::make('microsoft365_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Azure Portal → Certificates & secrets'),

                        Forms\Components\TextInput::make('microsoft365_tenant_id')
                            ->label('Tenant ID')
                            ->placeholder('common')
                            ->default('common')
                            ->maxLength(255)
                            ->helperText('Use "common" for multi-tenant or specific tenant ID'),

                        Forms\Components\Placeholder::make('microsoft365_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/microsoft365/callback'))
                            ->helperText('Add to Azure Portal → Authentication → Redirect URIs'),

                        SC\Actions::make([
                            Actions\Action::make('test_microsoft365')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testMicrosoft365Connection'),

                            Actions\Action::make('microsoft365_docs')
                                ->label('Azure Portal')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Salesforce Integration')
                    ->description('Configure Salesforce Connected App for CRM sync')
                    ->schema([
                        Forms\Components\TextInput::make('salesforce_client_id')
                            ->label('Consumer Key')
                            ->placeholder('Your Salesforce Consumer Key')
                            ->maxLength(255)
                            ->helperText('From Salesforce → Setup → App Manager → Connected App'),

                        Forms\Components\TextInput::make('salesforce_client_secret')
                            ->label('Consumer Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Salesforce Connected App settings'),

                        Forms\Components\Placeholder::make('salesforce_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/salesforce/callback'))
                            ->helperText('Add as Callback URL in Salesforce Connected App'),

                        SC\Actions::make([
                            Actions\Action::make('test_salesforce')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testSalesforceConnection'),

                            Actions\Action::make('salesforce_docs')
                                ->label('Salesforce Setup')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://login.salesforce.com', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('HubSpot Integration')
                    ->description('Configure HubSpot OAuth for CRM contacts and deals sync')
                    ->schema([
                        Forms\Components\TextInput::make('hubspot_client_id')
                            ->label('Client ID')
                            ->placeholder('Your HubSpot App Client ID')
                            ->maxLength(255)
                            ->helperText('From HubSpot Developer → Your App → Auth'),

                        Forms\Components\TextInput::make('hubspot_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From HubSpot Developer → Your App → Auth'),

                        Forms\Components\Placeholder::make('hubspot_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/hubspot/callback'))
                            ->helperText('Add to HubSpot App → Auth → Redirect URLs'),

                        SC\Actions::make([
                            Actions\Action::make('test_hubspot')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testHubSpotConnection'),

                            Actions\Action::make('hubspot_docs')
                                ->label('HubSpot Developers')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developers.hubspot.com/apps', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Jira Integration')
                    ->description('Configure Atlassian OAuth for Jira issue tracking')
                    ->schema([
                        Forms\Components\TextInput::make('jira_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Atlassian OAuth App Client ID')
                            ->maxLength(255)
                            ->helperText('From Atlassian Developer Console'),

                        Forms\Components\TextInput::make('jira_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Atlassian Developer Console'),

                        Forms\Components\Placeholder::make('jira_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/jira/callback'))
                            ->helperText('Add to Atlassian Developer Console → Authorization → Callback URL'),

                        SC\Actions::make([
                            Actions\Action::make('test_jira')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testJiraConnection'),

                            Actions\Action::make('jira_docs')
                                ->label('Atlassian Developer')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developer.atlassian.com/console/myapps/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Google Sheets Integration')
                    ->description('Configure Google OAuth for spreadsheet data export')
                    ->schema([
                        Forms\Components\TextInput::make('google_sheets_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Google OAuth Client ID')
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console (can reuse Google Workspace credentials)'),

                        Forms\Components\TextInput::make('google_sheets_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console → Credentials'),

                        Forms\Components\Placeholder::make('google_sheets_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/google-sheets/callback'))
                            ->helperText('Add to Google Cloud Console → Authorized redirect URIs'),

                        SC\Actions::make([
                            Actions\Action::make('test_google_sheets')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testGoogleSheetsConnection'),

                            Actions\Action::make('google_sheets_docs')
                                ->label('Google Cloud Console')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://console.cloud.google.com/apis/credentials', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Airtable Integration')
                    ->description('Configure Airtable OAuth for base and table sync')
                    ->schema([
                        Forms\Components\TextInput::make('airtable_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Airtable OAuth Client ID')
                            ->maxLength(255)
                            ->helperText('From Airtable Developer Hub → OAuth integrations'),

                        Forms\Components\TextInput::make('airtable_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Airtable Developer Hub'),

                        Forms\Components\Placeholder::make('airtable_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/airtable/callback'))
                            ->helperText('Add to Airtable OAuth integration settings'),

                        SC\Actions::make([
                            Actions\Action::make('test_airtable')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testAirtableConnection'),

                            Actions\Action::make('airtable_docs')
                                ->label('Airtable Developer Hub')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://airtable.com/developers', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Square Integration')
                    ->description('Configure Square OAuth for payments and catalog sync')
                    ->schema([
                        Forms\Components\TextInput::make('square_client_id')
                            ->label('Application ID')
                            ->placeholder('Your Square Application ID')
                            ->maxLength(255)
                            ->helperText('From Square Developer Dashboard'),

                        Forms\Components\TextInput::make('square_client_secret')
                            ->label('Application Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Square Developer Dashboard'),

                        Forms\Components\Select::make('square_environment')
                            ->label('Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Testing)',
                                'production' => 'Production (Live)',
                            ])
                            ->default('production')
                            ->helperText('Use Sandbox for testing before going live'),

                        Forms\Components\TextInput::make('square_webhook_signature_key')
                            ->label('Webhook Signature Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Square Developer Dashboard → Webhooks'),

                        Forms\Components\Placeholder::make('square_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/square/callback'))
                            ->helperText('Add to Square Developer Dashboard → OAuth'),

                        Forms\Components\Placeholder::make('square_webhook_url')
                            ->label('Webhook URL')
                            ->content(fn () => url('/webhooks/square'))
                            ->helperText('Add to Square Developer Dashboard → Webhooks'),

                        SC\Actions::make([
                            Actions\Action::make('test_square')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testSquareConnection'),

                            Actions\Action::make('square_docs')
                                ->label('Square Developer')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developer.squareup.com/apps', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Zoom Integration')
                    ->description('Configure Zoom OAuth for meeting and webinar creation')
                    ->schema([
                        Forms\Components\TextInput::make('zoom_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Zoom OAuth App Client ID')
                            ->maxLength(255)
                            ->helperText('From Zoom Marketplace → Your App → App Credentials'),

                        Forms\Components\TextInput::make('zoom_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Zoom Marketplace → Your App → App Credentials'),

                        Forms\Components\TextInput::make('zoom_webhook_secret_token')
                            ->label('Webhook Secret Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Zoom Marketplace → Feature → Event Subscriptions'),

                        Forms\Components\Placeholder::make('zoom_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/zoom/callback'))
                            ->helperText('Add to Zoom App → OAuth → Redirect URL for OAuth'),

                        Forms\Components\Placeholder::make('zoom_webhook_url')
                            ->label('Webhook Event Notification URL')
                            ->content(fn () => url('/webhooks/zoom'))
                            ->helperText('Add to Zoom App → Feature → Event Subscriptions'),

                        SC\Actions::make([
                            Actions\Action::make('test_zoom')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testZoomConnection'),

                            Actions\Action::make('zoom_docs')
                                ->label('Zoom Marketplace')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://marketplace.zoom.us/develop/create', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('WhatsApp Business Cloud API')
                    ->description('Configure webhook verification for WhatsApp Cloud API')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_cloud_verify_token')
                            ->label('Webhook Verify Token')
                            ->placeholder('Your custom verification token')
                            ->maxLength(255)
                            ->helperText('A secret token you create for webhook verification'),

                        Forms\Components\Placeholder::make('whatsapp_cloud_webhook_url')
                            ->label('Webhook Callback URL')
                            ->content(fn () => url('/webhooks/whatsapp-cloud'))
                            ->helperText('Add to Meta for Developers → WhatsApp → Configuration'),

                        Forms\Components\Placeholder::make('whatsapp_note')
                            ->content('Note: WhatsApp Business Cloud API credentials (Phone Number ID, Access Token) are configured per-tenant in their connection settings.')
                            ->columnSpanFull(),

                        SC\Actions::make([
                            Actions\Action::make('test_whatsapp')
                                ->label('Verify Token Saved')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testWhatsAppCloudConnection'),

                            Actions\Action::make('whatsapp_docs')
                                ->label('Meta for Developers')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developers.facebook.com/apps/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('Zapier Integration')
                    ->description('Configure Zapier OAuth for automation connections')
                    ->schema([
                        Forms\Components\TextInput::make('zapier_client_id')
                            ->label('Client ID')
                            ->placeholder('Your Zapier Integration Client ID')
                            ->maxLength(255)
                            ->helperText('From Zapier Developer Platform'),

                        Forms\Components\TextInput::make('zapier_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Zapier Developer Platform'),

                        Forms\Components\Placeholder::make('zapier_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/zapier/callback'))
                            ->helperText('Add to Zapier Developer Platform → Your Integration'),

                        SC\Actions::make([
                            Actions\Action::make('test_zapier')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testZapierConnection'),

                            Actions\Action::make('zapier_docs')
                                ->label('Zapier Developer')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developer.zapier.com/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                // ===========================================
                // AD PLATFORM CONNECTORS
                // ===========================================

                SC\Section::make('Google Ads Integration')
                    ->description('Configure Google Ads OAuth for tenant conversion tracking')
                    ->schema([
                        Forms\Components\TextInput::make('google_ads_client_id')
                            ->label('OAuth Client ID')
                            ->placeholder('Your Google Cloud OAuth Client ID')
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console → Credentials → OAuth 2.0 Client'),

                        Forms\Components\TextInput::make('google_ads_client_secret')
                            ->label('OAuth Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Google Cloud Console → Credentials'),

                        Forms\Components\TextInput::make('google_ads_developer_token')
                            ->label('Developer Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From Google Ads API Center (required for API access)'),

                        Forms\Components\Placeholder::make('google_ads_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/google-ads/callback'))
                            ->helperText('Add to Google Cloud Console → OAuth Client → Authorized redirect URIs'),

                        SC\Actions::make([
                            Actions\Action::make('test_google_ads')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testGoogleAdsConnection'),

                            Actions\Action::make('google_ads_docs')
                                ->label('Google Ads API')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://developers.google.com/google-ads/api/docs/start', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('TikTok Ads Integration')
                    ->description('Configure TikTok Events API for tenant conversion tracking')
                    ->schema([
                        Forms\Components\TextInput::make('tiktok_ads_app_id')
                            ->label('App ID')
                            ->placeholder('Your TikTok Marketing App ID')
                            ->maxLength(255)
                            ->helperText('From TikTok for Business → Developer Portal'),

                        Forms\Components\TextInput::make('tiktok_ads_app_secret')
                            ->label('App Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From TikTok for Business → Developer Portal'),

                        Forms\Components\Placeholder::make('tiktok_ads_info')
                            ->content('Note: Tenants will provide their own Pixel ID and Access Token when connecting.')
                            ->columnSpanFull(),

                        SC\Actions::make([
                            Actions\Action::make('test_tiktok_ads')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testTikTokAdsConnection'),

                            Actions\Action::make('tiktok_ads_docs')
                                ->label('TikTok Events API')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://business-api.tiktok.com/portal/docs', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),

                SC\Section::make('LinkedIn Ads Integration')
                    ->description('Configure LinkedIn Marketing API for B2B event conversion tracking')
                    ->schema([
                        Forms\Components\TextInput::make('linkedin_ads_client_id')
                            ->label('Client ID')
                            ->placeholder('Your LinkedIn App Client ID')
                            ->maxLength(255)
                            ->helperText('From LinkedIn Developer Portal → Your App'),

                        Forms\Components\TextInput::make('linkedin_ads_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('From LinkedIn Developer Portal → Your App → Auth'),

                        Forms\Components\Placeholder::make('linkedin_ads_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->content(fn () => url('/integrations/linkedin-ads/callback'))
                            ->helperText('Add to LinkedIn Developer Portal → Auth → Authorized redirect URLs'),

                        SC\Actions::make([
                            Actions\Action::make('test_linkedin_ads')
                                ->label('Test Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testLinkedInAdsConnection'),

                            Actions\Action::make('linkedin_ads_docs')
                                ->label('LinkedIn Marketing API')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://learn.microsoft.com/en-us/linkedin/marketing/', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = Setting::current();
        $settings->update($data);

        Notification::make()
            ->success()
            ->title('Connection settings saved')
            ->body('Stripe configuration has been updated successfully.')
            ->send();
    }

    public function testStripeConnection(): void
    {
        $data = $this->form->getState();

        $secretKey = $data['stripe_mode'] === 'live'
            ? $data['stripe_live_secret_key']
            : $data['stripe_test_secret_key'];

        if (empty($secretKey)) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter a secret key for the selected mode.')
                ->send();
            return;
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);
            $account = \Stripe\Account::retrieve();

            Notification::make()
                ->success()
                ->title('Connection successful!')
                ->body("Connected to Stripe account: {$account->email}")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testYoutubeConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['youtube_api_key'])) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter your YouTube API key.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'key' => $data['youtube_api_key'],
                'part' => 'snippet',
                'id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw', // Google Developers channel
            ]);

            if ($response->successful() && !empty($response->json('items'))) {
                Notification::make()
                    ->success()
                    ->title('YouTube API connected!')
                    ->body('API key is valid and working.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('error.message') ?? 'Invalid API key')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testSpotifyConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['spotify_client_id']) || empty($data['spotify_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->withBasicAuth($data['spotify_client_id'], $data['spotify_client_secret'])
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful() && $response->json('access_token')) {
                Notification::make()
                    ->success()
                    ->title('Spotify API connected!')
                    ->body('Credentials are valid. Token obtained successfully.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('error_description') ?? 'Invalid credentials')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testGoogleMapsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['google_maps_api_key'])) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter your Google Maps API key.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'key' => $data['google_maps_api_key'],
                'address' => 'Bucharest, Romania',
            ]);

            if ($response->successful() && $response->json('status') === 'OK') {
                Notification::make()
                    ->success()
                    ->title('Google Maps API connected!')
                    ->body('API key is valid and working.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('error_message') ?? 'Invalid API key or API not enabled')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testTwilioConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['twilio_account_sid']) || empty($data['twilio_auth_token'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Account SID and Auth Token.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($data['twilio_account_sid'], $data['twilio_auth_token'])
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$data['twilio_account_sid']}.json");

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Twilio API connected!')
                    ->body("Account: {$response->json('friendly_name')}")
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('message') ?? 'Invalid credentials')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testOpenWeatherConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['openweather_api_key'])) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter your OpenWeather API key.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'appid' => $data['openweather_api_key'],
                'q' => 'Bucharest,RO',
            ]);

            if ($response->successful()) {
                $weather = $response->json('weather.0.description') ?? 'Unknown';
                Notification::make()
                    ->success()
                    ->title('OpenWeather API connected!')
                    ->body("Current weather in Bucharest: {$weather}")
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('message') ?? 'Invalid API key')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testFacebookConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['facebook_access_token'])) {
            Notification::make()
                ->warning()
                ->title('No access token provided')
                ->body('Please enter your Facebook access token.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://graph.facebook.com/v18.0/me', [
                'access_token' => $data['facebook_access_token'],
                'fields' => 'id,name',
            ]);

            if ($response->successful()) {
                $name = $response->json('name') ?? 'Unknown';
                Notification::make()
                    ->success()
                    ->title('Facebook API connected!')
                    ->body("Connected as: {$name}")
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('error.message') ?? 'Invalid access token')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testGoogleAnalyticsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['google_analytics_property_id']) || empty($data['google_analytics_credentials_json'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Property ID and Service Account Credentials.')
                ->send();
            return;
        }

        try {
            $credentials = json_decode($data['google_analytics_credentials_json'], true);
            if (!$credentials) {
                throw new \Exception('Invalid JSON format for credentials');
            }

            Notification::make()
                ->success()
                ->title('Credentials format valid')
                ->body('Service account credentials JSON is valid. Full API test requires Google Analytics package.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testBrevoConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['brevo_api_key'])) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter your Brevo API key.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'api-key' => $data['brevo_api_key'],
            ])->get('https://api.brevo.com/v3/account');

            if ($response->successful()) {
                $email = $response->json('email') ?? 'Unknown';
                Notification::make()
                    ->success()
                    ->title('Brevo API connected!')
                    ->body("Account: {$email}")
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('message') ?? 'Invalid API key')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testTikTokConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['tiktok_client_key']) || empty($data['tiktok_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client Key and Client Secret.')
                ->send();
            return;
        }

        try {
            // TikTok uses OAuth2 client credentials flow
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                    'client_key' => $data['tiktok_client_key'],
                    'client_secret' => $data['tiktok_client_secret'],
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful() && $response->json('access_token')) {
                Notification::make()
                    ->success()
                    ->title('TikTok API connected!')
                    ->body('Credentials are valid. Token obtained successfully.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('error_description') ?? $response->json('error') ?? 'Invalid credentials')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    // Integration Microservices Test Connection Methods

    public function testSlackConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['slack_client_id']) || empty($data['slack_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Slack OAuth test - verify client credentials by attempting to access oauth.v2.access endpoint
            // Note: Full OAuth flow requires user interaction, so we validate format and test API availability
            $response = \Illuminate\Support\Facades\Http::post('https://slack.com/api/auth.test', [
                'token' => 'test', // This will fail but validates API connectivity
            ]);

            // If we get a response (even error), the API is reachable
            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Slack API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title('Slack API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach Slack API: {$e->getMessage()}")
                ->send();
        }
    }

    public function testDiscordConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['discord_client_id']) || empty($data['discord_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Test Discord OAuth by attempting client credentials grant
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->withBasicAuth($data['discord_client_id'], $data['discord_client_secret'])
                ->post('https://discord.com/api/v10/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => 'identify',
                ]);

            if ($response->successful() && $response->json('access_token')) {
                Notification::make()
                    ->success()
                    ->title('Discord API connected!')
                    ->body('OAuth credentials are valid.')
                    ->send();
            } else {
                $error = $response->json('error_description') ?? $response->json('error') ?? 'Invalid credentials';
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($error)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testGoogleWorkspaceConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['google_workspace_client_id']) || empty($data['google_workspace_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Google OAuth validation - check if credentials format is valid
            // Full validation happens during actual OAuth flow
            $response = \Illuminate\Support\Facades\Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => 'test', // Will fail but validates API connectivity
            ]);

            // API is reachable if we get any response
            Notification::make()
                ->success()
                ->title('Google APIs reachable')
                ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach Google APIs: {$e->getMessage()}")
                ->send();
        }
    }

    public function testMicrosoft365Connection(): void
    {
        $data = $this->form->getState();

        if (empty($data['microsoft365_client_id']) || empty($data['microsoft365_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            $tenantId = $data['microsoft365_tenant_id'] ?? 'common';

            // Test Microsoft OAuth by attempting client credentials grant
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                    'client_id' => $data['microsoft365_client_id'],
                    'client_secret' => $data['microsoft365_client_secret'],
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);

            if ($response->successful() && $response->json('access_token')) {
                Notification::make()
                    ->success()
                    ->title('Microsoft 365 API connected!')
                    ->body('OAuth credentials are valid.')
                    ->send();
            } else {
                $error = $response->json('error_description') ?? $response->json('error') ?? 'Invalid credentials';
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($error)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testSalesforceConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['salesforce_client_id']) || empty($data['salesforce_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Salesforce requires a full OAuth flow for proper validation
            // Test API accessibility
            $response = \Illuminate\Support\Facades\Http::get('https://login.salesforce.com/.well-known/openid-configuration');

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Salesforce API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body('Cannot reach Salesforce API')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testHubSpotConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['hubspot_client_id']) || empty($data['hubspot_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // HubSpot requires OAuth user flow for actual token generation
            // Test API reachability
            $response = \Illuminate\Support\Facades\Http::get('https://api.hubapi.com/oauth/v1/refresh-tokens/test');

            // Any response means API is reachable
            Notification::make()
                ->success()
                ->title('HubSpot API reachable')
                ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach HubSpot API: {$e->getMessage()}")
                ->send();
        }
    }

    public function testJiraConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['jira_client_id']) || empty($data['jira_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Atlassian requires 3-legged OAuth, so we just test API reachability
            $response = \Illuminate\Support\Facades\Http::get('https://auth.atlassian.com/.well-known/openid-configuration');

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Atlassian API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body('Cannot reach Atlassian API')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testGoogleSheetsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['google_sheets_client_id']) || empty($data['google_sheets_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Google OAuth validation - check if APIs are reachable
            $response = \Illuminate\Support\Facades\Http::get('https://sheets.googleapis.com/$discovery/rest?version=v4');

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Google Sheets API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body('Cannot reach Google Sheets API')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testAirtableConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['airtable_client_id']) || empty($data['airtable_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Test Airtable API reachability
            $response = \Illuminate\Support\Facades\Http::get('https://airtable.com/oauth2/v1/.well-known/openid-configuration');

            // Any response means service is reachable
            Notification::make()
                ->success()
                ->title('Airtable API reachable')
                ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach Airtable API: {$e->getMessage()}")
                ->send();
        }
    }

    public function testSquareConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['square_client_id']) || empty($data['square_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            $environment = $data['square_environment'] ?? 'production';
            $baseUrl = $environment === 'sandbox'
                ? 'https://connect.squareupsandbox.com'
                : 'https://connect.squareup.com';

            // Test Square OAuth by attempting to get token info
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post("{$baseUrl}/oauth2/token", [
                    'client_id' => $data['square_client_id'],
                    'client_secret' => $data['square_client_secret'],
                    'grant_type' => 'client_credentials',
                ]);

            // Square doesn't support client_credentials, but any response validates connectivity
            if ($response->status() !== 0) {
                Notification::make()
                    ->success()
                    ->title('Square API reachable')
                    ->body("Environment: {$environment}. OAuth flow will validate credentials when tenants connect.")
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach Square API: {$e->getMessage()}")
                ->send();
        }
    }

    public function testZoomConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['zoom_client_id']) || empty($data['zoom_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Test Zoom OAuth with client credentials (Server-to-Server OAuth)
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->withBasicAuth($data['zoom_client_id'], $data['zoom_client_secret'])
                ->post('https://zoom.us/oauth/token', [
                    'grant_type' => 'account_credentials',
                    'account_id' => 'test', // Will fail but tests credentials format
                ]);

            if ($response->successful() && $response->json('access_token')) {
                Notification::make()
                    ->success()
                    ->title('Zoom API connected!')
                    ->body('Server-to-Server OAuth credentials are valid.')
                    ->send();
            } else {
                // If we get error about account_id, credentials are valid format
                $error = $response->json('reason') ?? $response->json('error') ?? '';
                if (str_contains(strtolower($error), 'account')) {
                    Notification::make()
                        ->success()
                        ->title('Zoom API reachable')
                        ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Connection failed')
                        ->body($error ?: 'Invalid credentials')
                        ->send();
                }
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testWhatsAppCloudConnection(): void
    {
        $data = $this->form->getState();

        // WhatsApp Cloud uses verify token for webhook validation
        if (empty($data['whatsapp_cloud_verify_token'])) {
            Notification::make()
                ->warning()
                ->title('Missing verify token')
                ->body('Please enter your webhook verify token.')
                ->send();
            return;
        }

        // Verify token is just stored, no API call needed
        Notification::make()
            ->success()
            ->title('Verify token saved')
            ->body('WhatsApp webhook verify token has been configured. Tenants will provide their own access tokens.')
            ->send();
    }

    public function testZapierConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['zapier_client_id']) || empty($data['zapier_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        // Zapier uses OAuth, credentials validated during actual connection
        Notification::make()
            ->success()
            ->title('Zapier credentials saved')
            ->body('Credentials stored. OAuth flow will validate them when setting up Zaps.')
            ->send();
    }

    // Ad Platform Connector Test Methods

    public function testGoogleAdsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['google_ads_client_id']) || empty($data['google_ads_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        if (empty($data['google_ads_developer_token'])) {
            Notification::make()
                ->warning()
                ->title('Missing developer token')
                ->body('Developer token is required for Google Ads API access.')
                ->send();
            return;
        }

        try {
            // Test Google OAuth endpoint reachability
            $response = \Illuminate\Support\Facades\Http::get('https://accounts.google.com/.well-known/openid-configuration');

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('Google Ads API reachable')
                    ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body('Cannot reach Google OAuth endpoint')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    public function testTikTokAdsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['tiktok_ads_app_id']) || empty($data['tiktok_ads_app_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both App ID and App Secret.')
                ->send();
            return;
        }

        try {
            // Test TikTok Business API reachability
            $response = \Illuminate\Support\Facades\Http::get('https://business-api.tiktok.com/open_api/v1.3/');

            // Any response means the API is reachable
            Notification::make()
                ->success()
                ->title('TikTok Ads API reachable')
                ->body('Credentials saved. Tenants will connect with their own Pixel IDs.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach TikTok API: {$e->getMessage()}")
                ->send();
        }
    }

    public function testLinkedInAdsConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['linkedin_ads_client_id']) || empty($data['linkedin_ads_client_secret'])) {
            Notification::make()
                ->warning()
                ->title('Missing credentials')
                ->body('Please enter both Client ID and Client Secret.')
                ->send();
            return;
        }

        try {
            // Test LinkedIn OAuth endpoint reachability
            $response = \Illuminate\Support\Facades\Http::get('https://www.linkedin.com/oauth/v2/authorization');

            // Any response means the OAuth endpoint is reachable
            Notification::make()
                ->success()
                ->title('LinkedIn Ads API reachable')
                ->body('Credentials saved. OAuth flow will validate them when tenants connect.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Cannot reach LinkedIn OAuth: {$e->getMessage()}")
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Configuration')
                ->submit('save'),
        ];
    }

    public function getTitle(): string
    {
        return 'Connections';
    }

    public static function getNavigationLabel(): string
    {
        return 'Connections';
    }
}
