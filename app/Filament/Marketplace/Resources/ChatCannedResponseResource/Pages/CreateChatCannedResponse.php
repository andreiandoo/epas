<?php

namespace App\Filament\Marketplace\Resources\ChatCannedResponseResource\Pages;

use App\Filament\Marketplace\Resources\ChatCannedResponseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChatCannedResponse extends CreateRecord
{
    protected static string $resource = ChatCannedResponseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }
}
