<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceCampaignResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAudienceCampaign extends EditRecord
{
    protected static string $resource = AudienceCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('launch')
                ->label('Launch Campaign')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    app(\App\Services\AudienceTargeting\CampaignOrchestrationService::class)
                        ->launchCampaign($this->record);

                    $this->notify('success', 'Campaign launched successfully');
                })
                ->visible(fn () => $this->record->canLaunch()),

            Actions\Action::make('view_stats')
                ->label('View Stats')
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => route('api.audience.campaigns.stats', $this->record->id))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'completed'),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
