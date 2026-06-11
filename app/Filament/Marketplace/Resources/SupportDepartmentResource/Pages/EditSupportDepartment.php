<?php

namespace App\Filament\Marketplace\Resources\SupportDepartmentResource\Pages;

use App\Filament\Marketplace\Resources\SupportDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportDepartment extends EditRecord
{
    protected static string $resource = SupportDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
