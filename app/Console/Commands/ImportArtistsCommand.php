<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportArtistsCommand extends Command
{
    protected $signature = 'import:artists {file} {--update : Update existing artists instead of skipping} {--download-images : Download images from URLs}';
    protected $description = 'Import artists from CSV file with optional image download';

    public function handle()
    {
        $file = $this->argument('file');
        $updateExisting = $this->option('update');
        $downloadImages = $this->option('download-images');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Importing artists from {$file}...");
        if ($downloadImages) {
            $this->info("Image download enabled - images will be fetched from URLs");
        }

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
                'twitter_url' => $data['twitter_url'] ?? null,
                'wiki_url' => $data['wiki_url'] ?? $data['wikipedia_url'] ?? null,
                'lastfm_url' => $data['lastfm_url'] ?? $data['last_fm_url'] ?? null,
                'itunes_url' => $data['itunes_url'] ?? $data['apple_music_url'] ?? null,
                'musicbrainz_url' => $data['musicbrainz_url'] ?? null,
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

            // Download images if enabled
            if ($downloadImages) {
                // Main image
                $mainImageUrl = $data['main_image'] ?? $data['main_image_url'] ?? $data['image_url'] ?? null;
                if (!empty($mainImageUrl)) {
                    $localPath = $this->downloadImage($mainImageUrl, $slug, 'main');
                    if ($localPath) {
                        $artistData['main_image_url'] = $localPath;
                    }
                }

                // Horizontal logo
                $logoHUrl = $data['logo_h'] ?? $data['logo_url'] ?? null;
                if (!empty($logoHUrl)) {
                    $localPath = $this->downloadImage($logoHUrl, $slug, 'logo_h');
                    if ($localPath) {
                        $artistData['logo_url'] = $localPath;
                    }
                }

                // Vertical/portrait logo
                $logoVUrl = $data['logo_v'] ?? $data['portrait_url'] ?? null;
                if (!empty($logoVUrl)) {
                    $localPath = $this->downloadImage($logoVUrl, $slug, 'logo_v');
                    if ($localPath) {
                        $artistData['portrait_url'] = $localPath;
                    }
                }
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

    /**
     * Download image from URL and store locally
     */
    protected function downloadImage(string $url, string $slug, string $type): ?string
    {
        try {
            // Skip if not a valid URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->warn("  Invalid URL for {$type}: {$url}");
                return null;
            }

            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $this->warn("  Failed to download {$type} image: HTTP {$response->status()}");
                return null;
            }

            // Determine file extension from content type or URL
            $contentType = $response->header('Content-Type');
            $extension = $this->getExtensionFromContentType($contentType);

            if (!$extension) {
                // Try to get from URL
                $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
                $extension = strtolower($pathInfo['extension'] ?? 'jpg');
            }

            // Validate it's an image extension
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extension = 'jpg';
            }

            // Create filename and path
            $filename = "{$slug}_{$type}.{$extension}";
            $path = "artists/{$filename}";

            // Store the image
            Storage::disk('public')->put($path, $response->body());

            $this->line("  Downloaded {$type} image: {$filename}");

            return $path;

        } catch (\Exception $e) {
            $this->warn("  Error downloading {$type} image: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get file extension from content type
     */
    protected function getExtensionFromContentType(?string $contentType): ?string
    {
        if (!$contentType) {
            return null;
        }

        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        foreach ($map as $mime => $ext) {
            if (str_contains($contentType, $mime)) {
                return $ext;
            }
        }

        return null;
    }
}
