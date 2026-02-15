<?php

namespace App\Filament\Resources\AdsCampaignResource\Pages;

use App\Filament\Resources\AdsCampaignResource;
use App\Filament\Widgets\AdsCampaignPerformanceWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdsCampaign extends ViewRecord
{
    protected static string $resource = AdsCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AdsCampaignPerformanceWidget::class,
        ];
    }
}
