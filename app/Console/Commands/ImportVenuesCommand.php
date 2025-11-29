<?php

namespace App\Console\Commands;

use App\Models\Venue;
use Illuminate\Console\Command;
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

            Venue::create([
                'name' => $data['name'],
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
                'capacity' => !empty($data['capacity']) ? $data['capacity'] : null,
                'capacity_total' => !empty($data['capacity_total']) ? $data['capacity_total'] : null,
                'capacity_standing' => !empty($data['capacity_standing']) ? $data['capacity_standing'] : null,
                'capacity_seated' => !empty($data['capacity_seated']) ? $data['capacity_seated'] : null,
                'lat' => !empty($data['lat']) ? $data['lat'] : null,
                'lng' => !empty($data['lng']) ? $data['lng'] : null,
                'google_maps_url' => !empty($data['google_maps_url']) ? $data['google_maps_url'] : null,
                'established_at' => !empty($data['established_at']) ? $data['established_at'] : null,
                'description' => !empty($data['description']) ? $data['description'] : null,
            ]);

            $imported++;
        }

        fclose($handle);

        $this->info("Import complete!");
        $this->info("Imported: {$imported}");
        $this->info("Skipped: {$skipped}");

        return 0;
    }
}
