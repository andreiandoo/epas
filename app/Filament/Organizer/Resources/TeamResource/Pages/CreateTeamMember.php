<?php

namespace App\Filament\Organizer\Resources\TeamResource\Pages;

use App\Filament\Organizer\Resources\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamMember extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        $data['organizer_id'] = $organizer?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
