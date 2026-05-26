<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\Pages;

use App\Filament\Marketplace\Resources\ActivityResource;
use App\Models\Activity;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    /**
     * No header actions — Delete moved into the right sidebar of the form,
     * positioned directly under the Preview button (see ActivityResource::form).
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Use the activity title + organizer in parentheses as the page heading,
     * instead of the default "Edit activitate". Example:
     *   "Camera 13 — Escape Room Mister (Mystery Rooms București)"
     */
    public function getTitle(): string
    {
        /** @var Activity|null $record */
        $record = $this->getRecord();
        if (! $record) {
            return 'Edit activitate';
        }

        $title = is_array($record->title)
            ? ($record->title['ro'] ?? $record->title['en'] ?? '')
            : (string) ($record->title ?? '');

        $organizer = $record->organizer?->name;

        if (! $title && ! $organizer) {
            return 'Edit activitate';
        }
        if ($title && $organizer) {
            return "{$title} ({$organizer})";
        }
        return $title ?: "Activitate ({$organizer})";
    }

    /**
     * Same string shown in the browser tab.
     */
    public function getHeading(): string
    {
        return $this->getTitle();
    }

    /**
     * Auto-populate SEO fields from Detalii + Locație tab values when they're
     * left blank by the admin. Manual overrides survive — we only fill empties.
     * Runs before the form is persisted on edit.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ActivityResource::autoFillSeo($data);
    }
}
