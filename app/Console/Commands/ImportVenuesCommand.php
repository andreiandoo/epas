<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Models\VenueCategory;
use App\Models\VenueType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportVenuesCommand extends Command
{
    protected $signature = 'import:venues {file}';
    protected $description = 'Import venues from CSV file';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Importing venues from {$file}...");

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false || !in_array('name', $header)) {
            $this->error("Invalid CSV format. Must have at least a 'name' column.");
            return 1;
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            // Skip if name is empty
            if (empty($data['name'])) {
                continue;
            }

            $slug = $data['slug'] ?? Str::slug($data['name']);

            // Check if already exists
            if (Venue::where('slug', $slug)->exists()) {
                $this->warn("Skipping duplicate: {$data['name']}");
                $skipped++;
                continue;
            }

            // Translatable fields need to be stored as JSON with locale keys
            $name = !empty($data['name']) ? ['en' => $data['name'], 'ro' => $data['name']] : null;
            $description = !empty($data['description']) ? ['en' => $data['description'], 'ro' => $data['description']] : null;

            // Download gallery images from pipe-separated URLs
            $gallery = [];
            if (!empty($data['galerie_imagini'])) {
                $urls = array_filter(array_map('trim', explode('|', $data['galerie_imagini'])));
                foreach ($urls as $url) {
                    $path = $this->downloadImage($url);
                    if ($path) {
                        $gallery[] = $path;
                    }
                }
            }

            // Parse pipe-separated payment methods into a single string
            $acceptedPayment = null;
            if (!empty($data['payment_methods'])) {
                $methods = array_filter(array_map('trim', explode('|', $data['payment_methods'])));
                $acceptedPayment = implode(', ', $methods);
            }

            $venue = Venue::create([
                'name' => $name,
                'slug' => $slug,
                'address' => !empty($data['address']) ? $data['address'] : null,
                'city' => !empty($data['city']) ? $data['city'] : null,
                'state' => !empty($data['state']) ? $data['state'] : null,
                'country' => !empty($data['country']) ? $data['country'] : 'RO',
                'website_url' => !empty($data['website_url']) ? $data['website_url'] : null,
                'phone' => !empty($data['phone']) ? $data['phone'] : null,
                'phone2' => !empty($data['phone2']) ? $data['phone2'] : null,
                'email' => !empty($data['email']) ? $data['email'] : null,
                'email2' => !empty($data['email2']) ? $data['email2'] : null,
                'facebook_url' => !empty($data['facebook_url']) ? $data['facebook_url'] : null,
                'instagram_url' => !empty($data['instagram_url']) ? $data['instagram_url'] : null,
                'tiktok_url' => !empty($data['tiktok_url']) ? $data['tiktok_url'] : null,
                'image_url' => !empty($data['image_url']) ? $data['image_url'] : null,
                'video_type' => !empty($data['video_type']) ? $data['video_type'] : null,
                'video_url' => !empty($data['video_url']) ? $data['video_url'] : null,
                'gallery' => !empty($gallery) ? $gallery : null,
                'capacity' => !empty($data['capacity']) ? $data['capacity'] : null,
                'capacity_total' => !empty($data['capacity_total']) ? $data['capacity_total'] : null,
                'capacity_standing' => !empty($data['capacity_standing']) ? $data['capacity_standing'] : null,
                'capacity_seated' => !empty($data['capacity_seated']) ? $data['capacity_seated'] : null,
                'lat' => !empty($data['lat']) ? $data['lat'] : null,
                'lng' => !empty($data['lng']) ? $data['lng'] : null,
                'google_maps_url' => !empty($data['google_maps_url']) ? $data['google_maps_url'] : null,
                'established_at' => !empty($data['established_at']) ? $data['established_at'] : null,
                'description' => $description,
                'has_historical_monument_tax' => isset($data['taxa_monument']) && $data['taxa_monument'] === '1',
                'timezone' => !empty($data['timezone']) ? $data['timezone'] : null,
                'open_hours' => !empty($data['open_hours']) ? $data['open_hours'] : null,
                'general_rules' => !empty($data['general_rules']) ? $data['general_rules'] : null,
                'child_rules' => !empty($data['child_rules']) ? $data['child_rules'] : null,
                'accepted_payment' => $acceptedPayment,
            ]);

            // Attach core venue categories (pipe-separated slugs or EN names)
            if (!empty($data['venue_categories'])) {
                $values = array_filter(array_map('trim', explode('|', $data['venue_categories'])));
                $ids = VenueCategory::whereIn('slug', $values)->pluck('id');
                if ($ids->isEmpty()) {
                    // Fallback: match by EN name (case-insensitive)
                    $lower = array_map('strtolower', $values);
                    $ids = VenueCategory::get()->filter(function ($cat) use ($lower) {
                        $nameEn = strtolower(is_array($cat->name) ? ($cat->name['en'] ?? '') : $cat->name);
                        return in_array($nameEn, $lower);
                    })->pluck('id');
                }
                if ($ids->isNotEmpty()) {
                    $venue->coreCategories()->sync($ids->toArray());
                }
            }

            // Attach venue types (pipe-separated slugs or EN names)
            if (!empty($data['venue_types'])) {
                $values = array_filter(array_map('trim', explode('|', $data['venue_types'])));
                $ids = VenueType::whereIn('slug', $values)->pluck('id');
                if ($ids->isEmpty()) {
                    $lower = array_map('strtolower', $values);
                    $ids = VenueType::get()->filter(function ($type) use ($lower) {
                        $nameEn = strtolower(is_array($type->name) ? ($type->name['en'] ?? '') : $type->name);
                        return in_array($nameEn, $lower);
                    })->pluck('id');
                }
                if ($ids->isNotEmpty()) {
                    $venue->venueTypes()->sync($ids->toArray());
                }
            }

            $imported++;
            $this->line("Imported: {$data['name']}");
        }

        fclose($handle);

        $this->info("Import complete! Imported: {$imported} | Skipped: {$skipped}");

        return 0;
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $this->warn("Failed to download: {$url}");
                return null;
            }

            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $contentType = $response->header('Content-Type') ?? '';
                $ext = match (true) {
                    str_contains($contentType, 'jpeg') => 'jpg',
                    str_contains($contentType, 'png') => 'png',
                    str_contains($contentType, 'webp') => 'webp',
                    str_contains($contentType, 'gif') => 'gif',
                    default => 'jpg',
                };
            }

            $filename = 'venues/' . Str::random(40) . '.' . $ext;
            Storage::disk('public')->put($filename, $response->body());

            return $filename;
        } catch (\Throwable $e) {
            $this->warn("Error downloading {$url}: " . $e->getMessage());
            return null;
        }
    }
}
