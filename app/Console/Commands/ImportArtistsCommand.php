<?php

namespace App\Console\Commands;

use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportArtistsCommand extends Command
{
    protected $signature = 'import:artists {file}';
    protected $description = 'Import artists from CSV file';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Importing artists from {$file}...");

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
            if (Artist::where('slug', $slug)->exists()) {
                $this->warn("Skipping duplicate: {$data['name']}");
                $skipped++;
                continue;
            }

            Artist::create([
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'facebook_url' => $data['facebook_url'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'tiktok_url' => $data['tiktok_url'] ?? null,
                'spotify_url' => $data['spotify_url'] ?? null,
                'youtube_url' => $data['youtube_url'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'bio' => $data['bio'] ?? null,
                'formed_at' => !empty($data['formed_at']) ? $data['formed_at'] : null,
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
