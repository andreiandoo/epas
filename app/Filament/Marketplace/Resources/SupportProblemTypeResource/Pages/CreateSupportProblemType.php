<?php

namespace App\Filament\Marketplace\Resources\SupportProblemTypeResource\Pages;

use App\Filament\Marketplace\Resources\SupportProblemTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSupportProblemType extends CreateRecord
{
    protected static string $resource = SupportProblemTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }
}
