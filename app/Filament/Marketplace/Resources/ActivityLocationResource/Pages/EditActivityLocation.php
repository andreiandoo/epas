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

    private ?string $reviewedAs = null;

    /** Record who approved / rejected, and when; approval publishes. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $status = $data['review_status'] ?? null;
        if ($status !== $this->getRecord()->review_status && in_array($status, ['approved', 'rejected'], true)) {
            $data['reviewed_at'] = now();
            $data['reviewed_by'] = auth()->id();
            if ($status === 'approved') {
                $data['is_published'] = true;
            }
            $this->reviewedAs = $status;
        }
        return $data;
    }

    /** Tell the operator how the review went. */
    protected function afterSave(): void
    {
        if ($this->reviewedAs) {
            \App\Services\Activities\ReviewNotifier::location($this->getRecord(), $this->reviewedAs);
        }
    }
}
