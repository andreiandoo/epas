<?php

namespace App\Filament\Marketplace\Resources\InterestResource\Pages;

use App\Filament\Marketplace\Resources\InterestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInterest extends EditRecord
{
    protected static string $resource = InterestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
