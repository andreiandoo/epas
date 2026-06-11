<?php

namespace App\Filament\Marketplace\Resources\ServiceOrderResource\Pages;

use App\Filament\Marketplace\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Models\TrackingIntegration;
use App\Services\OrganizerNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceOrder extends ViewRecord
{
    protected static string $resource = ServiceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Always available for tracking orders — useful both for legacy
            // orders that were paid before activateTracking() existed and as
            // a recovery button for any organizer-side desync (toggle was
            // turned off manually, etc.). Idempotent on the model side.
            Action::make('reactivate_tracking')
                ->label('Re-rulează activate()')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (ServiceOrder $record): bool =>
                    $record->service_type === ServiceOrder::TYPE_TRACKING
                    && $record->payment_status === ServiceOrder::PAYMENT_PAID)
                ->requiresConfirmation()
                ->modalHeading('Re-rulează activate() pe acest tracking order?')
                ->modalDescription('Va seta service_settings.tracking_enabled=true pe organizator + upsert TrackingIntegration pentru fiecare platformă cumpărată. Idempotent: nu rescrie pixel ID-uri deja completate.')
                ->action(function (ServiceOrder $record): void {
                    $record->activate();
                    Notification::make()
                        ->success()
                        ->title('activate() rulat')
                        ->body('Verifică tabelul Status pixel-uri de mai jos.')
                        ->send();
                }),

            // Reminder notification when tracking has at least one platform
            // still missing a pixel ID. Only visible while there's actually
            // something for the organizer to do.
            Action::make('request_pixel_ids')
                ->label('Cere pixel ID de la organizator')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->visible(function (ServiceOrder $record): bool {
                    if ($record->service_type !== ServiceOrder::TYPE_TRACKING) return false;
                    $platforms = $record->config['platforms'] ?? [];
                    if (empty($platforms)) return false;
                    $integrations = TrackingIntegration::where('marketplace_organizer_id', $record->marketplace_organizer_id)->get()->keyBy('provider');
                    foreach ($platforms as $platform) {
                        $provider = ServiceOrder::TRACKING_PLATFORM_PROVIDER_MAP[$platform] ?? null;
                        $row = $provider ? $integrations->get($provider) : null;
                        if (empty($row?->getProviderId() ?? '')) {
                            return true; // at least one platform missing its pixel id
                        }
                    }
                    return false;
                })
                ->requiresConfirmation()
                ->modalHeading('Trimite reminder organizator')
                ->modalDescription('Organizatorul va primi o notificare cu link către pagina comenzii unde poate completa pixel ID-urile lipsă.')
                ->action(function (ServiceOrder $record): void {
                    try {
                        OrganizerNotificationService::notifyServiceOrderStatus($record, 'started');
                        Notification::make()
                            ->success()
                            ->title('Reminder trimis')
                            ->body('Notificarea a fost adăugată în contul organizatorului.')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Eroare la trimitere')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
