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
        // The checkbox lists here only carry the fixed keys, so a save would drop whatever the operator wrote
        // themselves ("custom:<label>"). Put those back, exactly as they were.
        $data['facilities'] = self::keepCustom($data['facilities'] ?? [], (array) ($this->getRecord()->facilities ?? []));
        if (isset($data['lodging']) && is_array($data['lodging'])) {
            $data['lodging']['facilities'] = self::keepCustom(
                $data['lodging']['facilities'] ?? [],
                (array) (($this->getRecord()->lodging ?? [])['facilities'] ?? [])
            );
        }
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

    /** The ticked boxes plus the operator's own entries that were already stored. */
    private static function keepCustom($ticked, array $stored): array
    {
        $prefix = \App\Services\Activities\OrganizerCatalog::CUSTOM_FACILITY;
        $own = array_filter($stored, fn ($f) => is_string($f) && str_starts_with($f, $prefix));

        return array_values(array_unique(array_merge(array_values((array) $ticked), array_values($own))));
    }

    /** Tell the operator how the review went. */
    protected function afterSave(): void
    {
        if ($this->reviewedAs) {
            \App\Services\Activities\ReviewNotifier::location($this->getRecord(), $this->reviewedAs);
        }
    }
}
