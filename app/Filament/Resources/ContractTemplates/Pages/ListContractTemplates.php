<?php

namespace App\Filament\Resources\ContractTemplates\Pages;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContractTemplates extends ListRecords
{
    protected static string $resource = ContractTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
