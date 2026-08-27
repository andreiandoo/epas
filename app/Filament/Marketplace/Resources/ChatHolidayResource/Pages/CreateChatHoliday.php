<?php

namespace App\Filament\Marketplace\Resources\ChatHolidayResource\Pages;

use App\Filament\Marketplace\Resources\ChatHolidayResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChatHoliday extends CreateRecord
{
    protected static string $resource = ChatHolidayResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }
}
