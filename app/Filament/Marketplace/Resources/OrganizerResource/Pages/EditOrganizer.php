<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Filament\Marketplace\Resources\ActivityResource;
use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizer extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = OrganizerResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            // Primary actions: ALWAYS first, aligned LEFT
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),

            // Secondary actions: pushed to the RIGHT via margin-left:auto on first one
            Actions\Action::make('login_as')
                ->label('Login as Organizer')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('warning')
                ->extraAttributes(['style' => 'margin-left: auto;'])
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/login-as'), shouldOpenInNewTab: true),

            Actions\Action::make('view_events')
                ->label('View Events')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('index', ['organizer' => $record->id])),

            Actions\Action::make('view_public_profile')
                ->label('Vezi profil public')
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->visible(fn () => (bool) $record->is_public && $record->getPublicProfileUrl())
                ->url(fn () => $record->getPublicProfileUrl(), shouldOpenInNewTab: true),

            Actions\Action::make('vanity_urls')
                ->label('Vanity URLs')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->url(fn () => '/marketplace/vanity-urls?tableFilters[target_type][value]=organizer', shouldOpenInNewTab: true),

            Actions\Action::make('create_event')
                ->label('Create Event')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('create', ['organizer' => $record->id])),

            // Activities module — only shown when the marketplace has the
            // `activities-module` microservice active. Ambilet/Tics never see these.
            Actions\Action::make('view_activities')
                ->label('View Activities')
                ->icon('heroicon-o-rocket-launch')
                ->color('gray')
                ->visible(fn () => static::marketplaceHasMicroservice('activities-module'))
                ->url(fn () => ActivityResource::getUrl('index', ['tableFilters[marketplace_organizer_id][value]' => $record->id])),

            Actions\Action::make('create_activity')
                ->label('Create Activity')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->visible(fn () => static::marketplaceHasMicroservice('activities-module'))
                ->url(fn () => ActivityResource::getUrl('create', ['organizer' => $record->id])),

            Actions\Action::make('view_contract')
                ->label('Vezi Contract')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn () => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                    ->where('document_type', 'organizer_contract')
                    ->exists())
                ->url(fn () => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                    ->where('document_type', 'organizer_contract')
                    ->latest('issued_at')
                    ->first()?->download_url, shouldOpenInNewTab: true),

            Actions\Action::make('view_balance')
                ->label('View Balance')
                ->icon('heroicon-o-wallet')
                ->color('warning')
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/balance')),

            Actions\Action::make('create_payout')
                ->label('Create Payout')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $record->available_balance > 0)
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/balance')),

            Actions\Action::make('suspend')
                ->label('Suspend Organizer')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $record->status === 'active')
                ->action(function () use ($record) {
                    $record->update(['status' => 'suspended']);
                    \Filament\Notifications\Notification::make()->title('Organizer suspended')->success()->send();
                }),

            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn () => $record->status === 'suspended')
                ->action(function () use ($record) {
                    $record->update(['status' => 'active']);
                    \Filament\Notifications\Notification::make()->title('Organizer reactivated')->success()->send();
                }),
        ];
    }

    /**
     * Tracking integration providers wired to the form (TrackingIntegration table).
     * Form keys: tracking_integrations.{provider}_enabled, tracking_integrations.{provider}_id.
     */
    private const TRACKING_PROVIDERS = [
        'ga4'        => ['consent_category' => 'analytics',  'id_field' => 'measurement_id'],
        'gtm'        => ['consent_category' => 'analytics',  'id_field' => 'container_id'],
        'meta'       => ['consent_category' => 'marketing',  'id_field' => 'pixel_id'],
        'tiktok'     => ['consent_category' => 'marketing',  'id_field' => 'pixel_id'],
        'google_ads' => ['consent_category' => 'marketing',  'id_field' => 'conversion_id'],
    ];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $integrations = \App\Models\TrackingIntegration::where('marketplace_organizer_id', $this->record->id)->get();
        $tracking = [];
        foreach (array_keys(self::TRACKING_PROVIDERS) as $provider) {
            $row = $integrations->firstWhere('provider', $provider);
            // Prefer settings.toggle_enabled (the operator's intent) over
            // the `enabled` column (the runtime gate). They diverge for paid
            // tracking orders where activateTracking() flips toggle_enabled
            // ON but leaves enabled OFF until the organizer fills in their
            // pixel ID — without this preference, the toggle would visually
            // reset to OFF on every page load. Falls back to the enabled
            // column for legacy rows that pre-date the toggle_enabled key.
            $rowSettings = $row && is_array($row->settings) ? $row->settings : [];
            $toggle = array_key_exists('toggle_enabled', $rowSettings)
                ? (bool) $rowSettings['toggle_enabled']
                : (bool) ($row?->enabled ?? false);
            $tracking["{$provider}_enabled"] = $toggle;
            $tracking["{$provider}_id"] = $row?->getProviderId() ?? '';
        }
        $data['tracking_integrations'] = $tracking;

        $capi = \App\Models\Integrations\FacebookCapi\FacebookCapiConnection::where('marketplace_organizer_id', $this->record->id)
            ->orderByDesc('id')
            ->first();
        $data['facebook_capi'] = [
            'enabled' => $capi?->status === 'active',
            'pixel_id' => $capi?->pixel_id ?? '',
            'access_token' => $capi?->access_token ?? '',
            'test_event_code' => $capi?->test_event_code ?? '',
            'ad_account_id' => $capi?->ad_account_id ?? '',
        ];

        $data['audience_subscriptions'] = \App\Models\MarketplaceOrganizerAudienceSubscription::where('marketplace_organizer_id', $this->record->id)
            ->where('is_active', true)
            ->pluck('audience_segment_id')
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Pull tracking_integrations out of $data — they live in their own table, not the organizer row.
        $this->trackingFormState = $data['tracking_integrations'] ?? [];
        unset($data['tracking_integrations']);

        $this->capiFormState = $data['facebook_capi'] ?? null;
        unset($data['facebook_capi']);

        $this->audienceSubscriptionsFormState = isset($data['audience_subscriptions'])
            ? array_map('intval', (array) $data['audience_subscriptions'])
            : null;
        unset($data['audience_subscriptions']);

        return $data;
    }

    protected ?array $trackingFormState = null;
    protected ?array $capiFormState = null;
    protected ?array $audienceSubscriptionsFormState = null;

    protected function afterSave(): void
    {
        try {
            $this->syncTrackingIntegrations();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrganizerResource: tracking sync failed', [
                'organizer_id' => $this->record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Tracking pixels nu au fost salvate')
                ->body($e->getMessage())
                ->danger()->send();
        }

        try {
            $this->syncFacebookCapiConnection();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrganizerResource: facebook capi sync failed', [
                'organizer_id' => $this->record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Facebook CAPI nu a fost salvat')
                ->body($e->getMessage())
                ->danger()->send();
        }

        try {
            $this->syncAudienceSubscriptions();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrganizerResource: audience subscriptions sync failed', [
                'organizer_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Audience subscriptions nu au fost salvate')
                ->body($e->getMessage())
                ->danger()->send();
        }
    }

    protected function syncTrackingIntegrations(): void
    {
        if ($this->trackingFormState === null) {
            return;
        }

        $marketplaceClientId = $this->record->marketplace_client_id;

        foreach (self::TRACKING_PROVIDERS as $provider => $cfg) {
            $enabled = (bool) ($this->trackingFormState["{$provider}_enabled"] ?? false);
            $providerId = trim((string) ($this->trackingFormState["{$provider}_id"] ?? ''));

            \App\Models\TrackingIntegration::updateOrCreate(
                [
                    'marketplace_organizer_id' => $this->record->id,
                    'provider' => $provider,
                ],
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'enabled' => $enabled && $providerId !== '',
                    'consent_category' => $cfg['consent_category'],
                    'settings' => [
                        $cfg['id_field'] => $providerId,
                        'inject_at' => 'head',
                        'page_scope' => 'public',
                        'toggle_enabled' => $enabled,
                    ],
                ]
            );
        }
    }

    protected function syncFacebookCapiConnection(): void
    {
        if ($this->capiFormState === null) {
            return;
        }

        $organizerId = $this->record->id;
        $marketplaceClientId = $this->record->marketplace_client_id;

        $enabled = (bool) ($this->capiFormState['enabled'] ?? false);
        $pixelId = trim((string) ($this->capiFormState['pixel_id'] ?? ''));
        $accessToken = trim((string) ($this->capiFormState['access_token'] ?? ''));
        $testEventCode = trim((string) ($this->capiFormState['test_event_code'] ?? ''));
        $adAccountId = trim((string) ($this->capiFormState['ad_account_id'] ?? ''));

        $existing = \App\Models\Integrations\FacebookCapi\FacebookCapiConnection::where('marketplace_organizer_id', $organizerId)
            ->orderByDesc('id')
            ->first();

        if (!$enabled || $pixelId === '' || $accessToken === '') {
            if ($existing && $existing->status === 'active') {
                $existing->update(['status' => 'inactive']);
                \Illuminate\Support\Facades\Cache::forget("fb_capi_active:org:{$organizerId}");
            }
            return;
        }

        $payload = [
            'pixel_id' => $pixelId,
            'access_token' => $accessToken,
            'test_event_code' => $testEventCode !== '' ? $testEventCode : null,
            'test_mode' => $testEventCode !== '',
            'ad_account_id' => $adAccountId !== '' ? $adAccountId : null,
            'status' => 'active',
            'marketplace_client_id' => $marketplaceClientId,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            \App\Models\Integrations\FacebookCapi\FacebookCapiConnection::create(array_merge($payload, [
                'marketplace_organizer_id' => $organizerId,
                'enabled_events' => ['Purchase', 'AddToCart', 'InitiateCheckout', 'ViewContent', 'PageView', 'Lead', 'CompleteRegistration'],
            ]));
        }

        \Illuminate\Support\Facades\Cache::forget("fb_capi_active:org:{$organizerId}");
    }

    /**
     * Persist the organizer's audience-segment subscriptions (E4).
     * Bifing a checkbox creates/reactivates a row; unbifing it marks
     * the row inactive (we keep history rather than deleting so the
     * Meta audience id stays linked if the user re-subscribes later).
     */
    protected function syncAudienceSubscriptions(): void
    {
        if ($this->audienceSubscriptionsFormState === null) {
            return;
        }

        $organizerId = $this->record->id;
        $selectedSegmentIds = $this->audienceSubscriptionsFormState;

        // Mark all subscriptions for this organizer inactive first…
        \App\Models\MarketplaceOrganizerAudienceSubscription::where('marketplace_organizer_id', $organizerId)
            ->update(['is_active' => false]);

        // …then upsert the selected ones as active.
        foreach ($selectedSegmentIds as $segmentId) {
            \App\Models\MarketplaceOrganizerAudienceSubscription::updateOrCreate(
                [
                    'marketplace_organizer_id' => $organizerId,
                    'audience_segment_id' => (int) $segmentId,
                ],
                [
                    'is_active' => true,
                ]
            );
        }
    }
}
