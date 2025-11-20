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
            'sendgrid_api_key' => $settings->sendgrid_api_key,
            'twilio_account_sid' => $settings->twilio_account_sid,
            'twilio_auth_token' => $settings->twilio_auth_token,
            'twilio_phone_number' => $settings->twilio_phone_number,
            'openweather_api_key' => $settings->openweather_api_key,
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

                SC\Section::make('SendGrid')
                    ->description('Configure SendGrid for transactional emails (confirmations, notifications)')
                    ->schema([
                        Forms\Components\TextInput::make('sendgrid_api_key')
                            ->label('API Key')
                            ->placeholder('SG...')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('SendGrid API key for sending transactional emails'),

                        SC\Actions::make([
                            Actions\Action::make('test_sendgrid')
                                ->label('Test SendGrid')
                                ->icon('heroicon-o-envelope')
                                ->color('info')
                                ->action('testSendGridConnection'),

                            Actions\Action::make('sendgrid_docs')
                                ->label('Get API Key')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url('https://app.sendgrid.com/settings/api_keys', shouldOpenInNewTab: true)
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

    public function testSendGridConnection(): void
    {
        $data = $this->form->getState();

        if (empty($data['sendgrid_api_key'])) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter your SendGrid API key.')
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($data['sendgrid_api_key'])
                ->get('https://api.sendgrid.com/v3/user/profile');

            if ($response->successful()) {
                Notification::make()
                    ->success()
                    ->title('SendGrid API connected!')
                    ->body('API key is valid and working.')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Connection failed')
                    ->body($response->json('errors.0.message') ?? 'Invalid API key')
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
