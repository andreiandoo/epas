<?php

namespace App\Filament\Marketplace\Resources\ActivityLocationResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActivityLocation extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ActivityLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        $name = $this->getRecord()->name;
        return is_array($name) ? ($name['ro'] ?? reset($name) ?: 'Locație') : (string) $name;
    }

    /** Record who approved / rejected, and when. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $status = $data['review_status'] ?? null;
        if ($status !== $this->getRecord()->review_status && in_array($status, ['approved', 'rejected'], true)) {
            $data['reviewed_at'] = now();
            $data['reviewed_by'] = auth()->id();
        }
        return $data;
    }
}
