<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use App\Models\Artist;
use App\Models\ArtistType;
use App\Models\ArtistGenre;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Filament\Actions\Action;
use Filament\Forms\Components as FC;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use BackedEnum;

class ImportArtists extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string $resource = ArtistResource::class;

    protected string $view = 'filament.resources.artists.pages.import-artists';

    protected static ?string $title = 'Import Artists';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    public ?array $data = [];

    public array $importResults = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Section::make('Import Settings')
                    ->schema([
                        FC\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->helperText('Upload a CSV file with artist data. Must have at least a "name" column.'),

                        FC\Toggle::make('update_existing')
                            ->label('Update Existing Artists')
                            ->helperText('If enabled, existing artists (matched by slug) will be updated with new data')
                            ->default(true),

                        FC\Toggle::make('download_images')
                            ->label('Download Images from URLs')
                            ->helperText('If enabled, images will be downloaded from URLs in the CSV and stored locally')
                            ->default(true),
                    ])->columns(1),

                SC\Section::make('CSV Format')
                    ->schema([
                        FC\Placeholder::make('format_info')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="text-sm space-y-2">
                                    <p><strong>Required column:</strong> name</p>
                                    <p><strong>Optional columns:</strong></p>
                                    <ul class="list-disc list-inside ml-4 space-y-1">
                                        <li>slug, email, phone, website, country, city</li>
                                        <li>facebook_url, instagram_url, tiktok_url, spotify_url, youtube_url, twitter_url</li>
                                        <li>wiki_url, lastfm_url, itunes_url, musicbrainz_url</li>
                                        <li>youtube_id, spotify_id</li>
                                        <li>main_image, logo_h, logo_v (URLs for images)</li>
                                        <li>bio_en, bio_ro (or bio(en), bio(ro))</li>
                                        <li><strong>artist_types</strong> (comma-separated slugs, e.g. "band,solo-artist")</li>
                                        <li><strong>artist_genres</strong> (comma-separated slugs, e.g. "pop,rock,electronic")</li>
                                        <li>manager_f_name, manager_l_name, manager_email, manager_phone, manager_website</li>
                                        <li>agent_f_name, agent_l_name, agent_email, agent_phone, agent_website</li>
                                        <li>youtube_video_1, youtube_video_2, ... youtube_video_5</li>
                                    </ul>
                                </div>
                            ')),
                    ])->collapsible(),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $data = $this->form->getState();

        if (empty($data['csv_file'])) {
            Notification::make()
                ->title('No file uploaded')
                ->danger()
                ->send();
            return;
        }

        $filePath = Storage::disk('local')->path($data['csv_file']);

        if (!file_exists($filePath)) {
            Notification::make()
                ->title('File not found')
                ->danger()
                ->send();
            return;
        }

        $updateExisting = $data['update_existing'] ?? false;
        $downloadImages = $data['download_images'] ?? false;

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);

        if ($header === false || !in_array('name', $header)) {
            Notification::make()
                ->title('Invalid CSV format')
                ->body('CSV must have at least a "name" column.')
                ->danger()
                ->send();
            fclose($handle);
            return;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $rowData = array_combine($header, $row);

            if (empty($rowData['name'])) {
                continue;
            }

            try {
                $result = $this->processArtistRow($rowData, $updateExisting, $downloadImages);

                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing {$rowData['name']}: {$e->getMessage()}";
            }
        }

        fclose($handle);

        // Clean up uploaded file
        Storage::disk('local')->delete($data['csv_file']);

        $this->importResults = [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];

        $message = "Imported: {$imported}, Updated: {$updated}, Skipped: {$skipped}";
        if (!empty($errors)) {
            $message .= ", Errors: " . count($errors);
        }

        Notification::make()
            ->title('Import Complete')
            ->body($message)
            ->success()
            ->send();
    }

    protected function processArtistRow(array $data, bool $updateExisting, bool $downloadImages): string
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

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
            'manager_first_name' => $data['manager_f_name'] ?? $data['manager_first_name'] ?? null,
            'manager_last_name' => $data['manager_l_name'] ?? $data['manager_last_name'] ?? null,
            'manager_email' => $data['manager_email'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,
            'manager_website' => $data['manager_website'] ?? null,
            'agent_first_name' => $data['agent_f_name'] ?? $data['agent_first_name'] ?? null,
            'agent_last_name' => $data['agent_l_name'] ?? $data['agent_last_name'] ?? null,
            'agent_email' => $data['agent_email'] ?? null,
            'agent_phone' => $data['agent_phone'] ?? null,
            'agent_website' => $data['agent_website'] ?? null,
        ];

        // Handle translatable bio
        $bioEn = $data['bio_en'] ?? $data['bio(en)'] ?? $data['bio'] ?? '';
        $bioRo = $data['bio_ro'] ?? $data['bio(ro)'] ?? '';
        if (!empty($bioEn) || !empty($bioRo)) {
            $artistData['bio_html'] = [
                'en' => $bioEn,
                'ro' => $bioRo,
            ];
        }

        // Handle YouTube videos
        $youtubeVideos = [];
        for ($i = 1; $i <= 5; $i++) {
            $videoUrl = $data["youtube_video_{$i}"] ?? $data["youtube_video{$i}"] ?? null;
            if (!empty($videoUrl)) {
                $videoId = YouTubeService::extractVideoId($videoUrl);
                if ($videoId) {
                    $youtubeVideos[] = $videoId;
                } elseif (strlen($videoUrl) === 11) {
                    $youtubeVideos[] = $videoUrl;
                }
            }
        }
        if (!empty($youtubeVideos)) {
            $artistData['youtube_videos'] = $youtubeVideos;
        }

        // Extract IDs from URLs
        if (empty($artistData['youtube_id']) && !empty($artistData['youtube_url'])) {
            $artistData['youtube_id'] = YouTubeService::extractChannelId($artistData['youtube_url']);
        }
        if (empty($artistData['spotify_id']) && !empty($artistData['spotify_url'])) {
            $artistData['spotify_id'] = SpotifyService::extractArtistId($artistData['spotify_url']);
        }

        // Download images if enabled
        if ($downloadImages) {
            $mainImageUrl = $data['main_image'] ?? $data['main_image_url'] ?? $data['image_url'] ?? null;
            if (!empty($mainImageUrl)) {
                $localPath = $this->downloadImage($mainImageUrl, $slug, 'main');
                if ($localPath) {
                    $artistData['main_image_url'] = $localPath;
                }
            }

            $logoHUrl = $data['logo_h'] ?? $data['logo_url'] ?? null;
            if (!empty($logoHUrl)) {
                $localPath = $this->downloadImage($logoHUrl, $slug, 'logo_h');
                if ($localPath) {
                    $artistData['logo_url'] = $localPath;
                }
            }

            $logoVUrl = $data['logo_v'] ?? $data['portrait_url'] ?? null;
            if (!empty($logoVUrl)) {
                $localPath = $this->downloadImage($logoVUrl, $slug, 'logo_v');
                if ($localPath) {
                    $artistData['portrait_url'] = $localPath;
                }
            }
        }

        // Parse artist types and genres (comma-separated slugs)
        $artistTypeSlugs = $this->parseCommaSeparatedSlugs($data['artist_types'] ?? $data['types'] ?? '');
        $artistGenreSlugs = $this->parseCommaSeparatedSlugs($data['artist_genres'] ?? $data['genres'] ?? '');

        // Check if exists
        $existing = Artist::where('slug', $slug)->first();

        if ($existing) {
            if ($updateExisting) {
                $existing->update(array_filter($artistData, fn($v) => $v !== null));

                // Sync artist types if provided
                if (!empty($artistTypeSlugs)) {
                    $typeIds = ArtistType::whereIn('slug', $artistTypeSlugs)->pluck('id')->toArray();
                    if (!empty($typeIds)) {
                        $existing->artistTypes()->sync($typeIds);
                    }
                }

                // Sync artist genres if provided
                if (!empty($artistGenreSlugs)) {
                    $genreIds = ArtistGenre::whereIn('slug', $artistGenreSlugs)->pluck('id')->toArray();
                    if (!empty($genreIds)) {
                        $existing->artistGenres()->sync($genreIds);
                    }
                }

                return 'updated';
            }
            return 'skipped';
        }

        $artist = Artist::create($artistData);

        // Attach artist types if provided
        if (!empty($artistTypeSlugs)) {
            $typeIds = ArtistType::whereIn('slug', $artistTypeSlugs)->pluck('id')->toArray();
            if (!empty($typeIds)) {
                $artist->artistTypes()->attach($typeIds);
            }
        }

        // Attach artist genres if provided
        if (!empty($artistGenreSlugs)) {
            $genreIds = ArtistGenre::whereIn('slug', $artistGenreSlugs)->pluck('id')->toArray();
            if (!empty($genreIds)) {
                $artist->artistGenres()->attach($genreIds);
            }
        }

        return 'imported';
    }

    /**
     * Parse comma-separated slugs into an array
     */
    protected function parseCommaSeparatedSlugs(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map(
                fn($s) => trim(strtolower($s)),
                explode(',', $value)
            ),
            fn($s) => !empty($s)
        );
    }

    protected function downloadImage(string $url, string $slug, string $type): ?string
    {
        try {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type');
            $extension = $this->getExtensionFromContentType($contentType);

            if (!$extension) {
                $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
                $extension = strtolower($pathInfo['extension'] ?? 'jpg');
            }

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extension = 'jpg';
            }

            $filename = "{$slug}_{$type}.{$extension}";
            $path = "artists/{$filename}";

            Storage::disk('public')->put($path, $response->body());

            return $path;

        } catch (\Exception $e) {
            return null;
        }
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label('Download CSV Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $headers = [
                        'name', 'slug', 'email', 'phone', 'website', 'country', 'city',
                        'facebook_url', 'instagram_url', 'tiktok_url', 'spotify_url', 'youtube_url',
                        'twitter_url', 'wiki_url', 'lastfm_url', 'itunes_url', 'musicbrainz_url',
                        'youtube_id', 'spotify_id',
                        'main_image', 'logo_h', 'logo_v',
                        'bio_en', 'bio_ro',
                        'artist_types', 'artist_genres',
                        'manager_f_name', 'manager_l_name', 'manager_email', 'manager_phone', 'manager_website',
                        'agent_f_name', 'agent_l_name', 'agent_email', 'agent_phone', 'agent_website',
                        'youtube_video_1', 'youtube_video_2', 'youtube_video_3', 'youtube_video_4', 'youtube_video_5',
                    ];

                    $content = implode(',', $headers) . "\n";
                    $content .= '"Artist Name","artist-slug","email@example.com","+40123456789","https://website.com","RO","Bucharest","https://facebook.com/artist","https://instagram.com/artist","https://tiktok.com/@artist","https://open.spotify.com/artist/123","https://youtube.com/channel/123","https://x.com/artist","https://en.wikipedia.org/wiki/Artist","https://www.last.fm/music/Artist","https://music.apple.com/artist/123","https://musicbrainz.org/artist/uuid","","","https://example.com/image.jpg","https://example.com/logo.png","https://example.com/portrait.jpg","English bio text","Bio în română","band,solo-artist","pop,rock,electronic","John","Doe","manager@email.com","+40123456789","https://manager.com","Jane","Smith","agent@email.com","+40987654321","https://agent.com","https://youtube.com/watch?v=VIDEO1","","","",""';

                    return response()->streamDownload(function () use ($content) {
                        echo $content;
                    }, 'artists-import-template.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
