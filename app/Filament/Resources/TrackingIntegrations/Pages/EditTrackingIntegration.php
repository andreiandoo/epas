<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use App\Services\Tracking\Providers\TrackingProviderFactory;
use App\Services\Tracking\TrackingScriptInjector;
use App\Services\Tracking\SessionConsentService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditTrackingIntegration extends EditRecord
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview Injection')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function () {
                    $tenant = $this->record->tenant;
                    $injector = new TrackingScriptInjector(new SessionConsentService());

                    // Grant all consents for preview
                    $consentService = new SessionConsentService();
                    $consentService->grantAll();

                    $preview = $injector->getInjectionPreview($tenant, 'public');

                    $output = "**Injection Preview for {$tenant->name}**\n\n";

                    if (!empty($preview['head'])) {
                        $output .= "**Head Scripts:**\n";
                        foreach ($preview['head'] as $provider => $code) {
                            $output .= "- {$provider}: " . (strlen($code) > 0 ? 'Yes' : 'No') . "\n";
                        }
                    }

                    if (!empty($preview['body'])) {
                        $output .= "\n**Body Scripts:**\n";
                        foreach ($preview['body'] as $provider => $code) {
                            $output .= "- {$provider}: " . (strlen($code) > 0 ? 'Yes' : 'No') . "\n";
                        }
                    }

                    if (!empty($preview['consent_status'])) {
                        $output .= "\n**Consent Status:**\n";
                        foreach ($preview['consent_status'] as $provider => $status) {
                            $willInject = $status['will_inject'] ? '✓' : '✗';
                            $output .= "- {$provider}: {$willInject} (consent: {$status['has_consent']}, scope: {$status['page_scope_match']})\n";
                        }
                    }

                    Notification::make()
                        ->title('Preview Generated')
                        ->body($output)
                        ->info()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate provider-specific settings
        if (!empty($data['provider']) && !empty($data['settings'])) {
            $errors = TrackingProviderFactory::validateSettings($data['provider'], $data['settings']);

            if (!empty($errors)) {
                Notification::make()
                    ->title('Validation Error')
                    ->body(implode(' ', $errors))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
