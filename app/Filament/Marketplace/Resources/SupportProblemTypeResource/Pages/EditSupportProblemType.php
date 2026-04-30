<?php

namespace App\Filament\Marketplace\Resources\SupportProblemTypeResource\Pages;

use App\Filament\Marketplace\Resources\SupportProblemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportProblemType extends EditRecord
{
    protected static string $resource = SupportProblemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
