<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditEvent extends EditRecord
{

    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        // Merge translatable fields: preserve existing locale keys, add/update .en
        $translatableFields = ['title', 'subtitle', 'short_description', 'description', 'ticket_terms'];
        foreach ($translatableFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $existing = $record->getRawOriginal($field);
                $existing = is_string($existing) ? (json_decode($existing, true) ?? []) : ($existing ?? []);
                $data[$field] = array_merge($existing, $data[$field]);
            }
        }

        // Handle slug: merge if stored as JSON, otherwise keep as-is
        if (isset($data['slug']) && is_array($data['slug'])) {
            $existingSlug = $record->getRawOriginal('slug');
            $decoded = is_string($existingSlug) ? json_decode($existingSlug, true) : null;
            if (is_array($decoded)) {
                $data['slug'] = array_merge($decoded, $data['slug']);
            }
        }

        // Ensure slug.en exists
        $titleEn = $data['title']['en'] ?? null;
        $slugData = $data['slug'] ?? [];
        if (is_array($slugData) && empty($slugData['en']) && $titleEn) {
            $data['slug']['en'] = Str::slug($titleEn);
        }

        // Auto-fill SEO if empty
        $data['seo'] = $this->buildSeoArray(
            current: $data['seo'] ?? [],
            titleEn: $titleEn,
            shortEn: $data['short_description']['en'] ?? null,
            descEn:  $data['description']['en'] ?? null,
        );

        return $data;
    }

    private function buildSeoArray(array $current, ?string $titleEn, ?string $shortEn, ?string $descEn): array
    {
        $metaTitle = $current['meta_title'] ?? null;
        $metaDesc  = $current['meta_description'] ?? null;

        if (!$metaTitle && $titleEn) {
            $metaTitle = $titleEn;
        }

        if (!$metaDesc) {
            $source = $shortEn ?: (is_string($descEn) ? strip_tags($descEn) : null);
            if ($source) {
                $metaDesc = \Illuminate\Support\Str::of($source)->squish()->limit(160, '...')->toString();
            }
        }

        $current['meta_title'] = $metaTitle;
        $current['meta_description'] = $metaDesc;

        return $current;
    }
}
