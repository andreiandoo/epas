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
        // Ensure slug.en exists
        $titleEn = $data['title']['en'] ?? null;
        if (empty($data['slug']['en']) && $titleEn) {
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
