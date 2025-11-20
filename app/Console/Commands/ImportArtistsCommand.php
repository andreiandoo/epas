<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportArtistsCommand extends Command
{
    protected $signature = 'import:artists {file} {--update : Update existing artists instead of skipping}';
    protected $description = 'Import artists from CSV file';

    public function handle()
    {
        $file = $this->argument('file');
        $updateExisting = $this->option('update');

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
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Ensure row has same number of columns as header
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $data = array_combine($header, $row);

            // Skip if name is empty
            if (empty($data['name'])) {
                continue;
            }

            $slug = $data['slug'] ?? Str::slug($data['name']);

            // Build artist data array
            $artistData = [
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website_url'] ?? $data['website'] ?? null,
                'facebook_url' => $data['facebook_url'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'tiktok_url' => $data['tiktok_url'] ?? null,
                'spotify_url' => $data['spotify_url'] ?? null,
                'youtube_url' => $data['youtube_url'] ?? null,
                'youtube_id' => $data['youtube_id'] ?? null,
                'spotify_id' => $data['spotify_id'] ?? null,
                'main_image_url' => $data['main_image'] ?? $data['main_image_url'] ?? $data['image_url'] ?? null,
                'logo_url' => $data['logo_h'] ?? $data['logo_url'] ?? null,
                'portrait_url' => $data['logo_v'] ?? $data['portrait_url'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,

                // Manager info
                'manager_first_name' => $data['manager_f_name'] ?? $data['manager_first_name'] ?? null,
                'manager_last_name' => $data['manager_l_name'] ?? $data['manager_last_name'] ?? null,
                'manager_email' => $data['manager_email'] ?? null,
                'manager_phone' => $data['manager_phone'] ?? null,
                'manager_website' => $data['manager_website'] ?? null,

                // Agent info
                'agent_first_name' => $data['agent_f_name'] ?? $data['agent_first_name'] ?? null,
                'agent_last_name' => $data['agent_l_name'] ?? $data['agent_last_name'] ?? null,
                'agent_email' => $data['agent_email'] ?? null,
                'agent_phone' => $data['agent_phone'] ?? null,
                'agent_website' => $data['agent_website'] ?? null,
            ];

            // Handle translatable bio field
            $bioEn = $data['bio_en'] ?? $data['bio(en)'] ?? $data['bio'] ?? '';
            $bioRo = $data['bio_ro'] ?? $data['bio(ro)'] ?? '';
            if (!empty($bioEn) || !empty($bioRo)) {
                $artistData['bio_html'] = [
                    'en' => $bioEn,
                    'ro' => $bioRo,
                ];
            }

            // Handle YouTube videos (up to 5)
            $youtubeVideos = [];
            for ($i = 1; $i <= 5; $i++) {
                $videoUrl = $data["youtube_video_{$i}"] ?? $data["youtube_video{$i}"] ?? null;
                if (!empty($videoUrl)) {
                    $videoId = YouTubeService::extractVideoId($videoUrl);
                    if ($videoId) {
                        $youtubeVideos[] = $videoId;
                    } elseif (strlen($videoUrl) === 11) {
                        // Assume it's already a video ID
                        $youtubeVideos[] = $videoUrl;
                    }
                }
            }
            if (!empty($youtubeVideos)) {
                $artistData['youtube_videos'] = $youtubeVideos;
            }

            // Extract IDs from URLs if not provided directly
            if (empty($artistData['youtube_id']) && !empty($artistData['youtube_url'])) {
                $artistData['youtube_id'] = YouTubeService::extractChannelId($artistData['youtube_url']);
            }
            if (empty($artistData['spotify_id']) && !empty($artistData['spotify_url'])) {
                $artistData['spotify_id'] = SpotifyService::extractArtistId($artistData['spotify_url']);
            }

            // Check if already exists
            $existing = Artist::where('slug', $slug)->first();

            if ($existing) {
                if ($updateExisting) {
                    $existing->update(array_filter($artistData, fn($v) => $v !== null));
                    $updated++;
                    $this->line("Updated: {$data['name']}");
                } else {
                    $this->warn("Skipping duplicate: {$data['name']}");
                    $skipped++;
                }
                continue;
            }

            Artist::create($artistData);
            $imported++;
        }

        fclose($handle);

        $this->info("Import complete!");
        $this->info("Imported: {$imported}");
        if ($updateExisting) {
            $this->info("Updated: {$updated}");
        }
        $this->info("Skipped: {$skipped}");

        return 0;
    }
}
