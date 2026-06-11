<?php

namespace App\Filament\Tenant\Resources\ExperienceActionResource\Pages;

use App\Filament\Tenant\Resources\ExperienceActionResource;
use App\Models\Gamification\ExperienceAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExperienceActions extends ListRecords
{
    protected static string $resource = ExperienceActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('create_defaults')
                ->label('Create Default Actions')
                ->action(function () {
                    $tenant = auth()->user()->tenant;
                    if ($tenant) {
                        ExperienceAction::createDefaultsForTenant($tenant->id);
                    }
                    $this->redirect(ExperienceActionResource::getUrl());
                })
                ->requiresConfirmation()
                ->color('gray'),
        ];
    }
}
