<?php

namespace App\Filament\Marketplace\Resources\ChatBlocklistResource\Pages;

use App\Filament\Marketplace\Resources\ChatBlocklistResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChatBlocklist extends CreateRecord
{
    protected static string $resource = ChatBlocklistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        $data['created_by_marketplace_admin_id'] = Auth::guard('marketplace_admin')->user()?->id;
        return $data;
    }
}
