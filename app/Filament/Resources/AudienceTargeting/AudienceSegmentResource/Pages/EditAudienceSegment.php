<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceSegmentResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAudienceSegment extends EditRecord
{
    protected static string $resource = AudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh Segment')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    app(\App\Services\AudienceTargeting\SegmentationService::class)
                        ->refreshSegment($this->record);

                    $this->notify('success', 'Segment refreshed successfully');
                })
                ->visible(fn () => $this->record->segment_type === 'dynamic'),

            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    \Filament\Forms\Components\Select::make('platform')
                        ->options([
                            'meta' => 'Meta (Facebook/Instagram)',
                            'google' => 'Google Ads',
                            'tiktok' => 'TikTok Ads',
                            'brevo' => 'Brevo (Email)',
                        ])
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('audience_name')
                        ->label('Audience Name')
                        ->default(fn () => $this->record->name)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $exportService = app(\App\Services\AudienceTargeting\AudienceExportService::class);

                    try {
                        $export = $exportService->exportSegment(
                            $this->record,
                            $data['platform'],
                            $data['audience_name']
                        );

                        $this->notify('success', 'Segment exported successfully to ' . $data['platform']);
                    } catch (\Exception $e) {
                        $this->notify('danger', 'Export failed: ' . $e->getMessage());
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
