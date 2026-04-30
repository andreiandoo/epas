<?php

namespace App\Filament\Marketplace\Resources\SupportDepartmentResource\Pages;

use App\Filament\Marketplace\Resources\SupportDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportDepartments extends ListRecords
{
    protected static string $resource = SupportDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
