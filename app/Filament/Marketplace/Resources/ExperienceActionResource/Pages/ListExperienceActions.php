<?php

namespace App\Filament\Marketplace\Resources\ExperienceActionResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ExperienceActionResource;
use App\Models\Gamification\ExperienceAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExperienceActions extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = ExperienceActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('create_defaults')
                ->label('Create Default Actions')
                ->action(function () {
                    $marketplaceClientId = static::getMarketplaceClientId();
                    if ($marketplaceClientId) {
                        ExperienceAction::createDefaultsForMarketplace($marketplaceClientId);
                    }
                    $this->redirect(ExperienceActionResource::getUrl());
                })
                ->requiresConfirmation()
                ->color('gray'),
        ];
    }
}
