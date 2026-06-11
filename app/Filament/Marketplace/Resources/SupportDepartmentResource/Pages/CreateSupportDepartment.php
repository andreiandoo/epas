<?php

namespace App\Filament\Marketplace\Resources\SupportDepartmentResource\Pages;

use App\Filament\Marketplace\Resources\SupportDepartmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSupportDepartment extends CreateRecord
{
    protected static string $resource = SupportDepartmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }
}
