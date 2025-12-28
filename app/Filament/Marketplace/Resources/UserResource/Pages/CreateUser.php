<?php

namespace App\Filament\Marketplace\Resources\UserResource\Pages;

use App\Filament\Marketplace\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        $data['role'] = 'editor';
        return $data;
    }
}
