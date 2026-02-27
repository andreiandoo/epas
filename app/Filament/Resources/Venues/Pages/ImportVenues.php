<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use App\Models\VenueType;
use App\Models\VenueCategory;
use Filament\Actions\Action;
use Filament\Forms\Components as FC;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use BackedEnum;

class ImportVenues extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string $resource = VenueResource::class;

    protected string $view = 'filament.resources.venues.pages.import-venues';

    protected static ?string $title = 'Import Venues';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    public ?array $data = [];

    public array $importResults = [];

    // Live progress tracking
    public bool $isImporting = false;
    public int $importProgress = 0;
    public int $importTotal = 0;
    public string $currentVenueName = '';

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
                            ->helperText('Upload a CSV file with venue data. Must have at least a "name" column.'),

                        FC\Toggle::make('update_existing')
                            ->label('Update Existing Venues')
                            ->helperText('If enabled, existing venues (matched by slug column from CSV) will be updated with new data')
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $set('skip_existing', false);
                                }
                            }),

                        FC\Toggle::make('skip_existing')
                            ->label('Skip Existing Venues')
                            ->helperText('If enabled, only new venues will be imported — existing venues (matched by slug column from CSV) are skipped. Rows without a slug column always create new venues.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $set('update_existing', false);
                                }
                            }),

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
                                        <li>slug, address, city, state, country</li>
                                        <li>website_url, phone, phone2, email, email2</li>
                                        <li>facebook_url, instagram_url, tiktok_url</li>
                                        <li>image_url (main image URL)</li>
                                        <li>video_type (youtube/vimeo), video_url</li>
                                        <li>gallery (pipe-separated URLs)</li>
                                        <li>capacity, capacity_total, capacity_standing, capacity_seated</li>
                                        <li>lat, lng, google_maps_url</li>
                                        <li>established_at (YYYY-MM-DD), timezone</li>
                                        <li>open_hours, general_rules, child_rules, accepted_payment</li>
                                        <li>has_historical_monument_tax (1/0)</li>
                                        <li>description_en, description_ro (or description(en), description(ro))</li>
                                        <li>name_en, name_ro (for translatable name; if not provided, "name" is used for all locales)</li>
                                        <li><strong>venue_types</strong> (pipe-separated slugs, e.g. "arena|stadium")</li>
                                        <li><strong>venue_categories</strong> (pipe-separated slugs, e.g. "concert-hall|theater")</li>
                                        <li>facilities (pipe-separated slugs, e.g. "parking|wheelchair_access|wifi")</li>
                                    </ul>
                                </div>
                            ')),
                    ])->collapsible(),
            ])
            ->statePath('data');
    }

    protected function importCacheKey(): string
    {
        return 'venue_import_' . auth()->id();
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

        $handle = fopen($filePath, 'r');

        // Detect delimiter (comma vs semicolon)
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);

        // Strip UTF-8 BOM from first column if present (Excel adds this)
        if ($header && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        // Trim whitespace from all headers
        $header = $header ? array_map('trim', $header) : $header;

        if ($header === false || !in_array('name', $header)) {
            Notification::make()
                ->title('Invalid CSV format')
                ->body('CSV must have at least a "name" column. Found columns: ' . implode(', ', $header ?: []))
                ->danger()
                ->send();
            fclose($handle);
            return;
        }

        // Parse all rows upfront
        $allRows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }
            $rowData = array_combine($header, $row);
            if (!empty($rowData['name'])) {
                $allRows[] = $rowData;
            }
        }
        fclose($handle);

        // Clean up uploaded file
        Storage::disk('local')->delete($data['csv_file']);

        if (empty($allRows)) {
            Notification::make()
                ->title('No valid rows found')
                ->body('The CSV file contained no rows with a "name" value.')
                ->warning()
                ->send();
            return;
        }

        $updateExisting = $data['update_existing'] ?? false;
        $skipExisting = $data['skip_existing'] ?? false;
        if ($skipExisting) {
            $updateExisting = false;
        }

        // Store import state in cache
        Cache::put($this->importCacheKey(), [
            'rows' => $allRows,
            'updateExisting' => $updateExisting,
            'downloadImages' => $data['download_images'] ?? false,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ], now()->addHour());

        // Initialize progress UI
        $this->importTotal = count($allRows);
        $this->importProgress = 0;
        $this->importResults = [];
        $this->currentVenueName = '';
        $this->isImporting = true;
    }

    public function processNextBatch(): void
    {
        if (!$this->isImporting) {
            return;
        }

        $state = Cache::get($this->importCacheKey());

        if (!$state || empty($state['rows'])) {
            $this->finishImport($state);
            return;
        }

        // Process 5 rows per tick
        $batchSize = min(5, count($state['rows']));
        $batch = array_slice($state['rows'], 0, $batchSize);
        $state['rows'] = array_slice($state['rows'], $batchSize);

        foreach ($batch as $rowData) {
            $this->currentVenueName = $rowData['name'] ?? '';

            try {
                $result = $this->processVenueRow($rowData, $state['updateExisting'], $state['downloadImages']);

                if ($result === 'imported') {
                    $state['imported']++;
                } elseif ($result === 'updated') {
                    $state['updated']++;
                } else {
                    $state['skipped']++;
                }
            } catch (\Exception $e) {
                $state['errors'][] = "Error processing {$rowData['name']}: {$e->getMessage()}";
            }

            $this->importProgress++;
        }

        if (empty($state['rows'])) {
            $this->finishImport($state);
        } else {
            Cache::put($this->importCacheKey(), $state, now()->addHour());
        }
    }

    protected function finishImport(?array $state): void
    {
        $this->isImporting = false;
        $this->currentVenueName = '';
        Cache::forget($this->importCacheKey());

        if ($state) {
            $this->importResults = [
                'imported' => $state['imported'],
                'updated' => $state['updated'],
                'skipped' => $state['skipped'],
                'errors' => $state['errors'],
            ];

            $message = "Imported: {$state['imported']}, Updated: {$state['updated']}, Skipped: {$state['skipped']}";
            if (!empty($state['errors'])) {
                $message .= ", Errors: " . count($state['errors']);
            }

            Notification::make()
                ->title('Import Complete')
                ->body($message)
                ->success()
                ->send();
        }
    }

    public function cancelImport(): void
    {
        Cache::forget($this->importCacheKey());
        $this->isImporting = false;
        $this->currentVenueName = '';

        Notification::make()
            ->title('Import Cancelled')
            ->body("Stopped at {$this->importProgress} / {$this->importTotal}. Already processed rows were saved.")
            ->warning()
            ->send();
    }

    protected function processVenueRow(array $data, bool $updateExisting, bool $downloadImages): string
    {
        // Only use explicit slug from CSV for matching — never auto-generate for matching
        $csvSlug = !empty($data['slug']) ? trim($data['slug']) : null;
        $slug = $csvSlug ?? Str::slug($data['name']);

        // Build translatable name
        $nameEn = $data['name_en'] ?? $data['name(en)'] ?? $data['name'] ?? '';
        $nameRo = $data['name_ro'] ?? $data['name(ro)'] ?? $data['name'] ?? '';
        $name = array_filter([
            'en' => $nameEn,
            'ro' => $nameRo,
        ], fn($v) => !empty($v));

        // Build translatable description
        $descEn = $data['description_en'] ?? $data['description(en)'] ?? $data['description'] ?? '';
        $descRo = $data['description_ro'] ?? $data['description(ro)'] ?? '';
        $description = array_filter([
            'en' => $descEn,
            'ro' => $descRo,
        ], fn($v) => !empty($v));

        // Parse gallery (pipe-separated URLs)
        $gallery = null;
        $galleryRaw = $data['gallery'] ?? $data['galerie_imagini'] ?? '';
        if (!empty($galleryRaw)) {
            $gallery = array_filter(array_map('trim', explode('|', $galleryRaw)));
        }

        // Parse facilities (pipe-separated slugs)
        $facilitiesRaw = $data['facilities'] ?? '';
        $facilities = null;
        if (!empty($facilitiesRaw)) {
            $facilities = array_filter(array_map('trim', explode('|', $facilitiesRaw)));
        }

        // Parse accepted payment methods (pipe-separated)
        $paymentRaw = $data['accepted_payment'] ?? $data['payment_methods'] ?? '';
        $acceptedPayment = !empty($paymentRaw) ? implode('|', array_map('trim', explode('|', $paymentRaw))) : null;

        $venueData = [
            'name' => $name ?: ['en' => $data['name']],
            'slug' => $slug,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'website_url' => $data['website_url'] ?? $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone2' => $data['phone2'] ?? null,
            'email' => $data['email'] ?? null,
            'email2' => $data['email2'] ?? null,
            'facebook_url' => $data['facebook_url'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'tiktok_url' => $data['tiktok_url'] ?? null,
            'image_url' => $data['image_url'] ?? $data['main_image'] ?? null,
            'video_type' => $data['video_type'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'gallery' => $gallery,
            'capacity' => !empty($data['capacity']) ? (int) $data['capacity'] : null,
            'capacity_total' => !empty($data['capacity_total']) ? (int) $data['capacity_total'] : null,
            'capacity_standing' => !empty($data['capacity_standing']) ? (int) $data['capacity_standing'] : null,
            'capacity_seated' => !empty($data['capacity_seated']) ? (int) $data['capacity_seated'] : null,
            'lat' => !empty($data['lat']) ? (float) $data['lat'] : null,
            'lng' => !empty($data['lng']) ? (float) $data['lng'] : null,
            'google_maps_url' => $data['google_maps_url'] ?? null,
            'established_at' => !empty($data['established_at']) ? $data['established_at'] : null,
            'timezone' => $data['timezone'] ?? null,
            'open_hours' => $data['open_hours'] ?? null,
            'general_rules' => $data['general_rules'] ?? null,
            'child_rules' => $data['child_rules'] ?? null,
            'accepted_payment' => $acceptedPayment,
            'has_historical_monument_tax' => isset($data['has_historical_monument_tax']) ? (bool) $data['has_historical_monument_tax'] : null,
            'facilities' => $facilities,
        ];

        if (!empty($description)) {
            $venueData['description'] = $description;
        }

        // Download images if enabled
        if ($downloadImages) {
            $mainImageUrl = $data['image_url'] ?? $data['main_image'] ?? null;
            if (!empty($mainImageUrl) && filter_var($mainImageUrl, FILTER_VALIDATE_URL)) {
                $localPath = $this->downloadImage($mainImageUrl, $slug, 'main');
                if ($localPath) {
                    $venueData['image_url'] = $localPath;
                }
            }

            // Download gallery images
            if (!empty($gallery)) {
                $localGallery = [];
                foreach ($gallery as $i => $galleryUrl) {
                    if (filter_var($galleryUrl, FILTER_VALIDATE_URL)) {
                        $localPath = $this->downloadImage($galleryUrl, $slug, "gallery_{$i}");
                        $localGallery[] = $localPath ?: $galleryUrl;
                    } else {
                        $localGallery[] = $galleryUrl;
                    }
                }
                $venueData['gallery'] = $localGallery;
            }
        }

        // Parse venue types and categories (pipe-separated slugs)
        $venueTypeSlugs = $this->parsePipeSeparatedSlugs($data['venue_types'] ?? '');
        $venueCategorySlugs = $this->parsePipeSeparatedSlugs($data['venue_categories'] ?? '');

        // Only match existing venues when CSV provides an explicit slug
        if ($csvSlug) {
            $existing = Venue::where('slug', $csvSlug)->first();

            if ($existing) {
                if ($updateExisting) {
                    $existing->update(array_filter($venueData, fn($v) => $v !== null));

                    if (!empty($venueTypeSlugs)) {
                        $typeIds = VenueType::whereIn('slug', $venueTypeSlugs)->pluck('id')->toArray();
                        if (!empty($typeIds)) {
                            $existing->venueTypes()->sync($typeIds);
                        }
                    }

                    if (!empty($venueCategorySlugs)) {
                        $catIds = VenueCategory::whereIn('slug', $venueCategorySlugs)->pluck('id')->toArray();
                        if (!empty($catIds)) {
                            $existing->coreCategories()->sync($catIds);
                        }
                    }

                    return 'updated';
                }
                return 'skipped';
            }
        } else {
            // No slug in CSV — leave blank so Venue::booted() auto-generates a unique one
            $venueData['slug'] = '';
        }

        $venue = Venue::create($venueData);

        if (!empty($venueTypeSlugs)) {
            $typeIds = VenueType::whereIn('slug', $venueTypeSlugs)->pluck('id')->toArray();
            if (!empty($typeIds)) {
                $venue->venueTypes()->attach($typeIds);
            }
        }

        if (!empty($venueCategorySlugs)) {
            $catIds = VenueCategory::whereIn('slug', $venueCategorySlugs)->pluck('id')->toArray();
            if (!empty($catIds)) {
                $venue->coreCategories()->attach($catIds);
            }
        }

        return 'imported';
    }

    protected function parsePipeSeparatedSlugs(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map(
                fn($s) => trim(strtolower($s)),
                explode('|', $value)
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
            $path = "venues/{$filename}";

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
                        'name', 'slug', 'address', 'city', 'state', 'country',
                        'website_url', 'phone', 'phone2', 'email', 'email2',
                        'facebook_url', 'instagram_url', 'tiktok_url',
                        'image_url', 'video_type', 'video_url', 'gallery',
                        'capacity', 'capacity_total', 'capacity_standing', 'capacity_seated',
                        'lat', 'lng', 'google_maps_url',
                        'established_at', 'timezone',
                        'open_hours', 'general_rules', 'child_rules', 'accepted_payment',
                        'has_historical_monument_tax',
                        'name_en', 'name_ro', 'description_en', 'description_ro',
                        'venue_types', 'venue_categories', 'facilities',
                    ];

                    $content = implode(',', $headers) . "\n";
                    $content .= '"Arena Nationala","arena-nationala","Bd. Basarabia 37-39","Bucuresti","Bucuresti","RO","https://arenanationala.ro","+40213456789","",""contact@arena.ro","","https://facebook.com/arena","https://instagram.com/arena","","https://example.com/arena.jpg","youtube","https://youtube.com/watch?v=xxx","https://img1.com/g1.jpg|https://img2.com/g2.jpg","55000","55000","40000","15000","44.4375","26.1530","https://maps.google.com/?q=arena","2011-09-06","Europe/Bucharest","Non-Stop pe durata evenimentelor","Regulament general","Copii sub 7 ani acces gratuit","cash|card","0","Arena Nationala","Arena Nationala","Large multi-purpose stadium","Stadion multifunctional","stadium|arena","concert-hall|sports","parking|wheelchair_access|wifi|food_court"';

                    return response()->streamDownload(function () use ($content) {
                        echo $content;
                    }, 'venues-import-template.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
