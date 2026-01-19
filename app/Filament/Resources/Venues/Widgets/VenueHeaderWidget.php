<?php

namespace App\Filament\Resources\Venues\Widgets;

use Filament\Widgets\Widget;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VenueHeaderWidget extends Widget
{
    protected string $view = 'filament.venues.widgets.venue-header';

    protected int|string|array $columnSpan = 'full';

    protected function resolveVenue(): Venue
    {
        $key = request()->route('record'); // poate fi id sau slug
        return Venue::query()
            ->when(is_numeric($key), fn($q) => $q->where('id', $key), fn($q) => $q->where('slug', $key))
            ->with('tenant')
            ->firstOrFail();
    }

    protected function getViewData(): array
    {
        $venue = $this->resolveVenue();
        $image = $venue->image_url;

        // Handle image URL properly
        if ($image) {
            // If it's already a full URL, use it
            if (Str::startsWith($image, ['http://', 'https://'])) {
                // Verify the image exists if it's a local URL
                if (Str::contains($image, config('app.url'))) {
                    $relativePath = str_replace('/storage/', '', parse_url($image, PHP_URL_PATH));
                    if (!Storage::disk('public')->exists($relativePath)) {
                        $image = null;
                    }
                }
            } else {
                // It's a relative path - check if file exists before generating URL
                if (Storage::disk('public')->exists($image)) {
                    $image = Storage::disk('public')->url($image);
                } else {
                    // File doesn't exist, set to null
                    $image = null;
                }
            }
        }

        return compact('venue','image');
    }
}
