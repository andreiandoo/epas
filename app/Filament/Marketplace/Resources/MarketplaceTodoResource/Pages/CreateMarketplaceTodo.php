<?php

namespace App\Filament\Marketplace\Resources\MarketplaceTodoResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceTodoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMarketplaceTodo extends CreateRecord
{
    protected static string $resource = MarketplaceTodoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $client = $admin?->marketplaceClient;

        $data['marketplace_client_id'] = $admin?->marketplace_client_id;
        $data['created_by_marketplace_admin_id'] = $admin?->id;

        // Auto-route to the marketplace's designated TODO admin if the
        // creator didn't pick an assignee. For Ambilet this is admin #5.
        if (empty($data['assigned_to_marketplace_admin_id']) && $client?->default_todo_admin_id) {
            $data['assigned_to_marketplace_admin_id'] = $client->default_todo_admin_id;
        }

        $data['opened_at'] = now();
        $data['last_activity_at'] = now();
        $data['status'] = $data['status'] ?? 'open';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
