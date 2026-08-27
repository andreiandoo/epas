<?php

namespace App\Filament\Marketplace\Resources\ChatOperatorScheduleResource\Pages;

use App\Filament\Marketplace\Resources\ChatOperatorScheduleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChatOperatorSchedule extends CreateRecord
{
    protected static string $resource = ChatOperatorScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }
}
