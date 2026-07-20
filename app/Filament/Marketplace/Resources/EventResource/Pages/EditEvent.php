<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Models\Event;
use App\Models\Tour;
use App\Services\EventSchedulingService;
use App\Services\PerformanceSyncService;
use App\Services\Seating\MarketplaceEventSeatingService;
use App\Models\Seating\EventSeatingLayout;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class EditEvent extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    /**
     * Deferred render of the (potentially huge) ticket-types repeater in the
     * "Bilete" tab. Kept false on load so the page — and especially the
     * "Vânzări" tab — opens fast. Set true only when the page is opened with
     * ?loadTickets=1 (the "Încarcă biletele" button links there and forces a
     * full reload, so the repeater is visible from mount and hydrates
     * normally). While false the repeater is hidden, so Filament skips its
     * render AND (per BelongsToModel::saveRelationships) does NOT touch the
     * ticketTypes relationship on save — existing ticket types are never wiped.
     */
    public bool $loadTickets = false;

    /**
     * Allow editing child events even though they're filtered from the list query.
     */
    public function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        return Event::findOrFail($key);
    }

    /**
     * Strip case, Romanian diacritics, and separators (`- _ . space`) so a
     * search like "concerte vara" matches a slugified filename like
     * "concerte-vara-2024.jpg" or an original "Concerte Vară 2024.jpg".
     */
    private static function normaliseForFilenameSearch(string $value): string
    {
        $diacritics = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's',
            'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S',
            'Ț' => 'T', 'Ţ' => 'T',
        ];
        $value = strtr($value, $diacritics);
        $value = mb_strtolower($value, 'UTF-8');
        return preg_replace('/[\s\-_.]+/u', '', $value) ?? '';
    }

    /**
     * Build a Postgres-safe SQL expression that mirrors the PHP normalisation
     * above: lowercase, strip Romanian diacritics, then strip separators.
     * Returns a parameterised LIKE clause and the bound value.
     *
     * @return array{0: string, 1: string} [whereRaw expression, like value]
     */
    private static function searchableLikeFor(string $column, string $needle): array
    {
        // translate() is case-sensitive; we lower() first so the diacritic
        // map only needs lowercase entries.
        $expr = "regexp_replace(translate(lower(coalesce(\"{$column}\", '')), 'ăâîșşțţ', 'aaisstt'), '[\\s\\-_.]+', '', 'g')";
        return [$expr, '%' . static::normaliseForFilenameSearch($needle) . '%'];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Opt-in to rendering the heavy ticket-types repeater (see $loadTickets).
        $this->loadTickets = request()->boolean('loadTickets');

        // Redirect hosted events to view-guest page (can't edit events you don't own)
        // Marketplace events use marketplace_client_id, not tenant_id
        $marketplace = static::getMarketplaceClient();
        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            redirect(EventResource::getUrl('view-guest', ['record' => $this->record]));
        }
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        $title = $this->record->getTranslation('title', $lang)
            ?? $this->record->getTranslation('title', 'ro')
            ?? $this->record->getTranslation('title', 'en')
            ?? '';

        // Append city and date in parentheses. The date string is
        // produced by Event::displayDateLabel() so range / multi_day /
        // recurring events get their proper interval instead of falling
        // back to a stale event_date (or showing nothing at all).
        $parts = [];
        $city = $this->record->city ?? $this->record->venue?->city ?? null;
        if ($city) {
            $parts[] = $city;
        }
        $dateLabel = $this->record->displayDateLabel();
        if ($dateLabel) {
            $parts[] = $dateLabel;
        }
        if (!empty($parts)) {
            $title .= ' (' . implode(', ', $parts) . ')';
        }

        // Inject CSS for header layout
        $css = '<style>.fi-header{flex-direction:column;align-items:start;}</style>';

        return new \Illuminate\Support\HtmlString(e($title ?: 'Edit Event') . $css);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $marketplace = static::getMarketplaceClient();

        // Check if invitations microservice is active
        $hasInvitations = $marketplace?->microservices()
            ->where('microservices.slug', 'invitations')
            ->wherePivot('is_active', true)
            ->exists() ?? false;

        $actions = [];

        if ($hasInvitations) {
            $actions[] = Actions\Action::make('invitations')
                ->label('Create Invitations')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->url(fn () => route('filament.marketplace.pages.invitations') . '?event=' . $this->record->id);
        }

        // External tickets import
        $extCount = \App\Models\ExternalTicket::where('event_id', $this->record->id)->count();
        $actions[] = Actions\Action::make('external_tickets')
            ->label('Bilete Externe' . ($extCount > 0 ? " ({$extCount})" : ''))
            ->icon('heroicon-o-ticket')
            ->color('gray')
            ->url(fn () => EventResource::getUrl('import-external-tickets', ['record' => $this->record]));

        // Coupon codes button
        $params = http_build_query(array_filter([
            'event_id' => $this->record->id,
            'organizer_id' => $this->record->marketplace_organizer_id,
        ]));
        $couponUrl = \App\Filament\Marketplace\Resources\CouponCodeResource::getUrl('create') . '?' . $params;
        $actions[] = Actions\Action::make('coupon_codes')
            ->label('Coduri reducere')
            ->icon('heroicon-o-tag')
            ->color('gray')
            ->url($couponUrl)
            ->openUrlInNewTab();

        // Newsletter — pre-targets ticket buyers of this event
        $newsletterUrl = \App\Filament\Marketplace\Resources\NewsletterResource::getUrl('create')
            . '?event=' . $this->record->id;
        $actions[] = Actions\Action::make('newsletter')
            ->label('Newsletter')
            ->icon('heroicon-o-megaphone')
            ->color('gray')
            ->url($newsletterUrl);

        // Upload Images action - modal-based to avoid Livewire re-render issues
        $actions[] = $this->getUploadImagesAction();

        // Daily Capacities (leisure venue only)
        if (($this->record->display_template ?? 'standard') === 'leisure_venue') {
            $actions[] = Actions\Action::make('daily_capacities')
                ->label('Capacitate Zilnică')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->url(fn () => EventResource::getUrl('daily-capacities', ['record' => $this->record]));
        }

        // Duplicate action
        $actions[] = Actions\Action::make('duplicate')
            ->label('Duplică')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Duplică evenimentul')
            ->modalDescription('Sigur vrei să duplici acest eveniment? Se va crea o copie draft fără bilete vândute.')
            ->modalSubmitActionLabel('Duplică')
            ->action(function () {
                $record = $this->record;

                $newEvent = $record->replicate([
                    'id', 'slug', 'event_series', 'created_at', 'updated_at',
                    'status', 'is_public', 'submitted_at', 'approved_at', 'approved_by',
                    'venue_name', 'city', 'starts_at', 'ends_at',
                    'seo_title', 'seo_description', 'revenue_target', 'capacity', 'event_type',
                    // Seating mapping: do NOT inherit from the source event. The
                    // organizer often duplicates to move an event to a different
                    // venue, and inheriting seating_layout_id leaves the new event
                    // pointing at the source venue's layout — the "Harta Locuri"
                    // tab stays visible and renders the wrong map even after the
                    // venue is switched. Force a clean start; admin re-picks if
                    // needed.
                    'seating_layout_id', 'seating_performance_id',
                ]);

                $titleArray = $record->title ?? [];
                if (is_array($titleArray)) {
                    foreach ($titleArray as $locale => $value) {
                        if (!empty($value)) {
                            $titleArray[$locale] = '[Duplicat] ' . $value;
                        }
                    }
                }
                $newEvent->title = $titleArray;

                $originalTitle = $record->title ?? [];
                $baseTitle = is_array($originalTitle) ? ($originalTitle['ro'] ?? $originalTitle['en'] ?? reset($originalTitle)) : $originalTitle;
                $baseSlug = \Illuminate\Support\Str::slug($baseTitle ?: 'eveniment');
                $newEvent->slug = $baseSlug . '-temp-' . time();

                $newEvent->is_featured = false;
                $newEvent->is_homepage_featured = false;
                $newEvent->is_general_featured = false;
                $newEvent->is_category_featured = false;
                $newEvent->is_published = false;
                $newEvent->views_count = 0;
                $newEvent->interested_count = 0;
                $newEvent->save();

                // Clear M2M relationships that may have been carried over by replicate
                $newEvent->artists()->detach();

                $newEvent->slug = $baseSlug . '-' . $newEvent->id;
                $newEvent->save();

                if ($record->eventTypes && $record->eventTypes->count() > 0) {
                    $newEvent->eventTypes()->sync($record->eventTypes->pluck('id'));
                }
                if ($record->eventGenres && $record->eventGenres->count() > 0) {
                    $newEvent->eventGenres()->sync($record->eventGenres->pluck('id'));
                }
                if ($record->artists && $record->artists->count() > 0) {
                    $newEvent->artists()->sync($record->artists->pluck('id'));
                }

                foreach ($record->ticketTypes as $ticketType) {
                    $newTicketType = $ticketType->replicate([
                        'id', 'created_at', 'updated_at', 'series_start', 'series_end',
                    ]);
                    $newTicketType->event_id = $newEvent->id;
                    $newTicketType->quota_sold = 0;
                    $newTicketType->series_start = null;
                    $newTicketType->series_end = null;
                    $newTicketType->min_per_order = $ticketType->min_per_order ?? 1;
                    $newTicketType->max_per_order = $ticketType->max_per_order ?? 10;
                    $newTicketType->commission_type = $ticketType->commission_type;
                    $newTicketType->commission_rate = $ticketType->commission_rate;
                    $newTicketType->commission_fixed = $ticketType->commission_fixed;
                    $newTicketType->commission_mode = $ticketType->commission_mode;
                    $newTicketType->save();
                }

                $displayTitle = $newEvent->getTranslation('title') ?? 'Eveniment';
                Notification::make()
                    ->title('Eveniment duplicat')
                    ->body("Evenimentul \"{$displayTitle}\" a fost creat.")
                    ->success()
                    ->send();

                return redirect(EventResource::getUrl('edit', ['record' => $newEvent]));
            });

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    /**
     * Get the Upload Images action for uploading poster and hero images via modal
     */
    protected function getUploadImagesAction(): Actions\Action
    {
        $marketplace = static::getMarketplaceClient();
        $rawLang = $marketplace->language ?? $marketplace->locale ?? null;
        $lang = (!empty($rawLang)) ? $rawLang : 'ro';
        $t = fn($ro, $en) => $lang === 'ro' ? $ro : $en;

        return Actions\Action::make('uploadImages')
            ->label($t('Încarcă Imagini', 'Upload Images'))
            ->icon('heroicon-o-photo')
            ->color('primary')
            ->modalHeading($t('Încarcă Imagini Eveniment', 'Upload Event Images'))
            ->modalWidth('4xl')
            ->fillForm(fn () => [
                'poster_url' => $this->record->poster_url,
                'hero_image_url' => $this->record->hero_image_url,
                'poster_mode' => 'upload',
                'hero_mode' => 'upload',
            ])
            ->form([
                // Poster Section
                SC\Section::make($t('Poster (vertical)', 'Poster (vertical)'))
                    ->schema([
                        Forms\Components\ToggleButtons::make('poster_mode')
                            ->label($t('Mod selectare', 'Selection mode'))
                            ->options([
                                'upload' => $t('Încarcă fișier nou', 'Upload new file'),
                                'library' => $t('Selectează din bibliotecă', 'Select from library'),
                            ])
                            ->icons([
                                'upload' => 'heroicon-o-arrow-up-tray',
                                'library' => 'heroicon-o-photo',
                            ])
                            ->default('upload')
                            ->inline()
                            ->live(),

                        Forms\Components\FileUpload::make('poster_url')
                            ->label('')
                            ->image()
                            ->disk('public')
                            ->directory('events/posters')
                            ->visibility('public')
                            ->imagePreviewHeight('200')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->storeFileNamesIn('poster_original_filename')
                            ->visible(fn (SGet $get) => $get('poster_mode') === 'upload'),

                        Forms\Components\Select::make('poster_from_library')
                            ->label($t('Caută în bibliotecă', 'Search in library'))
                            ->placeholder($t('Caută după numele original...', 'Search by original name...'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($marketplace): array {
                                [$origExpr, $origLike] = static::searchableLikeFor('original_filename', $search);
                                [$fileExpr, $fileLike] = static::searchableLikeFor('filename', $search);
                                [$titleExpr, $titleLike] = static::searchableLikeFor('title', $search);

                                return \App\Models\MediaLibrary::query()
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->where('mime_type', 'LIKE', 'image/%')
                                    ->where(function ($q) use ($origExpr, $origLike, $fileExpr, $fileLike, $titleExpr, $titleLike) {
                                        $q->whereRaw("{$origExpr} LIKE ?", [$origLike])
                                          ->orWhereRaw("{$fileExpr} LIKE ?", [$fileLike])
                                          ->orWhereRaw("{$titleExpr} LIKE ?", [$titleLike]);
                                    })
                                    ->orderBy('created_at', 'desc')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($media) => [
                                        $media->path => $media->original_filename ?: $media->filename
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) use ($marketplace): ?string {
                                $media = \App\Models\MediaLibrary::where('path', $value)
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->first();
                                return $media ? ($media->original_filename ?: $media->filename) : $value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $set('poster_url', $state);
                                }
                            })
                            ->visible(fn (SGet $get) => $get('poster_mode') === 'library'),

                        // Show preview when library mode and image selected
                        Forms\Components\Placeholder::make('poster_library_preview')
                            ->label('')
                            ->content(function (SGet $get) use ($marketplace) {
                                $path = $get('poster_from_library');
                                if (!$path) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-gray-500 text-sm">' .
                                        'Caută și selectează o imagine din bibliotecă' .
                                        '</div>'
                                    );
                                }
                                $media = \App\Models\MediaLibrary::where('path', $path)
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->first();
                                if (!$media) return '';

                                $url = $media->url;
                                return new \Illuminate\Support\HtmlString(
                                    "<div class='mt-2'>
                                        <img src='{$url}' alt='' style='max-height: 200px; border-radius: 8px;'>
                                        <div class='text-xs text-gray-500 mt-1'>{$media->human_readable_size} • {$media->width}×{$media->height}</div>
                                    </div>"
                                );
                            })
                            ->visible(fn (SGet $get) => $get('poster_mode') === 'library'),
                    ])
                    ->columns(1),

                // Hero Image Section
                SC\Section::make($t('Imagine hero (orizontală)', 'Hero image (horizontal)'))
                    ->schema([
                        Forms\Components\ToggleButtons::make('hero_mode')
                            ->label($t('Mod selectare', 'Selection mode'))
                            ->options([
                                'upload' => $t('Încarcă fișier nou', 'Upload new file'),
                                'library' => $t('Selectează din bibliotecă', 'Select from library'),
                            ])
                            ->icons([
                                'upload' => 'heroicon-o-arrow-up-tray',
                                'library' => 'heroicon-o-photo',
                            ])
                            ->default('upload')
                            ->inline()
                            ->live(),

                        Forms\Components\FileUpload::make('hero_image_url')
                            ->label('')
                            ->image()
                            ->disk('public')
                            ->directory('events/hero')
                            ->visibility('public')
                            ->imagePreviewHeight('200')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->storeFileNamesIn('hero_image_original_filename')
                            ->visible(fn (SGet $get) => $get('hero_mode') === 'upload'),

                        Forms\Components\Select::make('hero_from_library')
                            ->label($t('Caută în bibliotecă', 'Search in library'))
                            ->placeholder($t('Caută după numele original...', 'Search by original name...'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($marketplace): array {
                                [$origExpr, $origLike] = static::searchableLikeFor('original_filename', $search);
                                [$fileExpr, $fileLike] = static::searchableLikeFor('filename', $search);
                                [$titleExpr, $titleLike] = static::searchableLikeFor('title', $search);

                                return \App\Models\MediaLibrary::query()
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->where('mime_type', 'LIKE', 'image/%')
                                    ->where(function ($q) use ($origExpr, $origLike, $fileExpr, $fileLike, $titleExpr, $titleLike) {
                                        $q->whereRaw("{$origExpr} LIKE ?", [$origLike])
                                          ->orWhereRaw("{$fileExpr} LIKE ?", [$fileLike])
                                          ->orWhereRaw("{$titleExpr} LIKE ?", [$titleLike]);
                                    })
                                    ->orderBy('created_at', 'desc')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($media) => [
                                        $media->path => $media->original_filename ?: $media->filename
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) use ($marketplace): ?string {
                                $media = \App\Models\MediaLibrary::where('path', $value)
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->first();
                                return $media ? ($media->original_filename ?: $media->filename) : $value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $set('hero_image_url', $state);
                                }
                            })
                            ->visible(fn (SGet $get) => $get('hero_mode') === 'library'),

                        // Show preview when library mode and image selected
                        Forms\Components\Placeholder::make('hero_library_preview')
                            ->label('')
                            ->content(function (SGet $get) use ($marketplace) {
                                $path = $get('hero_from_library');
                                if (!$path) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-gray-500 text-sm">' .
                                        'Caută și selectează o imagine din bibliotecă' .
                                        '</div>'
                                    );
                                }
                                $media = \App\Models\MediaLibrary::where('path', $path)
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->first();
                                if (!$media) return '';

                                $url = $media->url;
                                return new \Illuminate\Support\HtmlString(
                                    "<div class='mt-2'>
                                        <img src='{$url}' alt='' style='max-height: 200px; border-radius: 8px;'>
                                        <div class='text-xs text-gray-500 mt-1'>{$media->human_readable_size} • {$media->width}×{$media->height}</div>
                                    </div>"
                                );
                            })
                            ->visible(fn (SGet $get) => $get('hero_mode') === 'library'),
                    ])
                    ->columns(1),

                Forms\Components\Hidden::make('poster_original_filename'),
                Forms\Components\Hidden::make('hero_image_original_filename'),
            ])
            ->action(function (array $data): void {
                $marketplace = static::getMarketplaceClient();

                // Determine poster URL - from upload or library selection
                $posterUrl = $data['poster_mode'] === 'library'
                    ? ($data['poster_from_library'] ?? $data['poster_url'] ?? null)
                    : ($data['poster_url'] ?? null);

                // Determine hero URL - from upload or library selection
                $heroUrl = $data['hero_mode'] === 'library'
                    ? ($data['hero_from_library'] ?? $data['hero_image_url'] ?? null)
                    : ($data['hero_image_url'] ?? null);

                // Get original filenames
                $posterOriginalFilename = $data['poster_original_filename'] ?? null;
                $heroOriginalFilename = $data['hero_image_original_filename'] ?? null;

                // If from library, get original filename from MediaLibrary
                if ($data['poster_mode'] === 'library' && $posterUrl) {
                    $media = \App\Models\MediaLibrary::where('path', $posterUrl)
                        ->where('marketplace_client_id', $marketplace?->id)
                        ->first();
                    if ($media) {
                        $posterOriginalFilename = $media->original_filename;
                    }
                }

                if ($data['hero_mode'] === 'library' && $heroUrl) {
                    $media = \App\Models\MediaLibrary::where('path', $heroUrl)
                        ->where('marketplace_client_id', $marketplace?->id)
                        ->first();
                    if ($media) {
                        $heroOriginalFilename = $media->original_filename;
                    }
                }

                $updateData = [
                    'poster_url' => $posterUrl,
                    'hero_image_url' => $heroUrl,
                    'poster_original_filename' => $posterOriginalFilename,
                    'hero_image_original_filename' => $heroOriginalFilename,
                ];

                $this->record->update($updateData);

                // Create MediaLibrary entries for searchability (only for new uploads)
                // The observer auto-compresses and may convert to WebP, changing the path
                if ($data['poster_mode'] === 'upload' && !empty($posterUrl)) {
                    $this->createMediaLibraryEntry(
                        $posterUrl,
                        $posterOriginalFilename,
                        'events',
                        $marketplace?->id
                    );
                }

                if ($data['hero_mode'] === 'upload' && !empty($heroUrl)) {
                    $this->createMediaLibraryEntry(
                        $heroUrl,
                        $heroOriginalFilename,
                        'events',
                        $marketplace?->id
                    );
                }

                // After MediaLibrary observer runs compression/WebP conversion,
                // the file path may have changed (e.g. .jpeg → .webp).
                // Re-read the MediaLibrary records to get the final paths.
                $pathUpdates = [];

                if (!empty($posterUrl)) {
                    $posterMedia = \App\Models\MediaLibrary::where('marketplace_client_id', $marketplace?->id)
                        ->where(function ($q) use ($posterUrl) {
                            $q->where('path', $posterUrl)
                              ->orWhere('path', preg_replace('/\.\w+$/', '.webp', $posterUrl));
                        })
                        ->latest()
                        ->first();
                    if ($posterMedia && $posterMedia->path !== $posterUrl) {
                        $pathUpdates['poster_url'] = $posterMedia->path;
                    }
                }

                if (!empty($heroUrl)) {
                    $heroMedia = \App\Models\MediaLibrary::where('marketplace_client_id', $marketplace?->id)
                        ->where(function ($q) use ($heroUrl) {
                            $q->where('path', $heroUrl)
                              ->orWhere('path', preg_replace('/\.\w+$/', '.webp', $heroUrl));
                        })
                        ->latest()
                        ->first();
                    if ($heroMedia && $heroMedia->path !== $heroUrl) {
                        $pathUpdates['hero_image_url'] = $heroMedia->path;
                    }
                }

                if (!empty($pathUpdates)) {
                    $this->record->update($pathUpdates);
                }

                Notification::make()
                    ->success()
                    ->title('Imaginile au fost salvate')
                    ->send();

                // Refresh the page to show updated images
                $this->redirect(EventResource::getUrl('edit', ['record' => $this->record]));
            });
    }

    /**
     * Create a MediaLibrary entry for an uploaded file
     */
    protected function createMediaLibraryEntry(
        string $path,
        ?string $originalFilename,
        string $collection,
        ?int $marketplaceClientId
    ): void {
        // Check if entry already exists for this path
        $existing = \App\Models\MediaLibrary::where('path', $path)
            ->where('marketplace_client_id', $marketplaceClientId)
            ->first();

        if ($existing) {
            // Update original filename if provided and different
            if ($originalFilename && $existing->original_filename !== $originalFilename) {
                $existing->update(['original_filename' => $originalFilename]);
            }
            return;
        }

        // Check if authenticated user exists in the users table
        // Marketplace users might not be in the main users table
        $uploadedBy = null;
        if (auth()->id()) {
            $userExists = \App\Models\User::where('id', auth()->id())->exists();
            if ($userExists) {
                $uploadedBy = auth()->id();
            }
        }

        try {
            $media = \App\Models\MediaLibrary::createFromPath(
                $path,
                'public',
                $collection,
                $marketplaceClientId,
                $uploadedBy
            );

            // Update with original filename if provided
            if ($originalFilename) {
                $media->update(['original_filename' => $originalFilename]);
            }
        } catch (\Throwable $e) {
            // Log error but don't fail the upload
            \Illuminate\Support\Facades\Log::warning("Failed to create MediaLibrary entry: " . $e->getMessage());
        }
    }

    /**
     * Adjust ticket type stock when seats are blocked/unblocked.
     * Negative $amount = reduce stock (blocking), positive = restore stock (unblocking).
     */
    protected function adjustTicketTypeStock(string $sectionName, int $amount): void
    {
        // Find the SeatingSection by name in this event's venue layout
        $section = \App\Models\Seating\SeatingSection::where('name', $sectionName)
            ->whereHas('layout', function ($q) {
                $q->withoutGlobalScopes()
                    ->where('venue_id', $this->record->venue_id)
                    ->where('status', 'published');
            })
            ->first();

        if (!$section) {
            return;
        }

        // Find the ticket type for this event that has this section assigned
        $ticketType = \App\Models\TicketType::where('event_id', $this->record->id)
            ->whereHas('seatingSections', function ($q) use ($section) {
                $q->where('seating_sections.id', $section->id);
            })
            ->first();

        if (!$ticketType) {
            return;
        }

        // Adjust quota_total (ensure it doesn't go below quota_sold)
        $newTotal = max($ticketType->quota_sold ?? 0, ($ticketType->quota_total ?? 0) + $amount);
        \App\Models\TicketType::where('id', $ticketType->id)->update(['quota_total' => $newTotal]);
    }

    /**
     * Block or unblock seats from the interactive seating map editor.
     * Called via $wire.call() from the block mode in seating-map-editor.blade.php.
     */
    public function updateSeatStatuses(array $seatUids, string $action, bool $createInvitations = false): array
    {
        $this->skipRender();

        $event = $this->record;
        $layoutId = $event->seating_layout_id;
        $debug = [
            'event_id' => $event->id,
            'layout_id' => $layoutId,
            'action' => $action,
            'sent_uids' => array_slice($seatUids, 0, 5),
        ];

        if (!$layoutId || empty($seatUids)) {
            return ['updated' => 0, 'invite_url' => null, 'debug' => $debug + ['fail' => 'no_layout_or_empty']];
        }

        // Find or create EventSeatingLayout directly by event_id
        $eventSeating = \App\Models\Seating\EventSeatingLayout::where('event_id', $event->id)->first();
        $debug['esl_found'] = $eventSeating !== null;
        $debug['esl_id'] = $eventSeating?->id;
        $created = false;

        // If existing EventSeatingLayout has stale seats (layout changed or UIDs regenerated), recreate
        if ($eventSeating) {
            $stale = false;
            if ((int) $eventSeating->layout_id !== (int) $layoutId) {
                $stale = true;
                $debug['esl_stale_reason'] = 'layout_id_mismatch';
            } else {
                // Check if seat UIDs actually exist in the EventSeat records
                $uidMatch = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                    ->whereIn('seat_uid', array_slice($seatUids, 0, 3))
                    ->exists();
                if (!$uidMatch) {
                    $stale = true;
                    $debug['esl_stale_reason'] = 'uid_mismatch';
                }
            }
            if ($stale) {
                $debug['esl_stale'] = true;
                $eventSeating->seats()->delete();
                $eventSeating->delete();
                $eventSeating = null;
            }
        }

        if (!$eventSeating) {
            $layout = \App\Models\Seating\SeatingLayout::withoutGlobalScopes()
                ->with(['sections.rows.seats'])
                ->find($layoutId);

            if (!$layout || $layout->sections->isEmpty()) {
                return ['updated' => 0, 'invite_url' => null, 'debug' => $debug + ['fail' => 'no_layout_sections']];
            }

            // Generate geometry snapshot
            $geometry = app(\App\Services\Seating\GeometryStorage::class)->generateGeometrySnapshot($layout);

            // Use firstOrCreate to handle race conditions / partial previous attempts
            $eventSeating = \App\Models\Seating\EventSeatingLayout::firstOrCreate(
                ['event_id' => $event->id, 'layout_id' => $layout->id],
                [
                    'marketplace_client_id' => $event->marketplace_client_id ?? null,
                    'json_geometry' => $geometry,
                    'status' => 'active',
                    'published_at' => now(),
                ]
            );

            // Clear any orphaned/partial seats from previous failed attempts, then recreate
            $eventSeating->seats()->delete();

            $seatCount = 0;
            foreach ($layout->sections as $section) {
                foreach ($section->rows as $row) {
                    foreach ($row->seats as $seat) {
                        $baseStatus = $seat->status ?? 'active';
                        \App\Models\Seating\EventSeat::updateOrCreate(
                            [
                                'event_seating_id' => $eventSeating->id,
                                'seat_uid' => $seat->seat_uid,
                            ],
                            [
                                'section_name' => $section->name,
                                'row_label' => $row->label,
                                'seat_label' => $seat->label,
                                'status' => ($baseStatus === 'imposibil') ? 'disabled' : 'available',
                                'version' => 1,
                            ]
                        );
                        $seatCount++;
                    }
                }
            }
            $created = true;
            $debug['esl_created'] = true;
            $debug['esl_id'] = $eventSeating->id;
            $debug['seats_created'] = $seatCount;
        }

        // Diagnostic: check matching seats
        $totalSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)->count();
        $matchingSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
            ->whereIn('seat_uid', $seatUids)->count();

        // Sample DB seat_uids to compare with sent ones
        $dbSampleUids = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
            ->limit(5)->pluck('seat_uid')->toArray();

        // Check statuses of matching seats
        $matchingStatuses = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
            ->whereIn('seat_uid', $seatUids)
            ->pluck('status', 'seat_uid')->toArray();

        $debug['total_event_seats'] = $totalSeats;
        $debug['matching_seats'] = $matchingSeats;
        $debug['db_sample_uids'] = $dbSampleUids;
        $debug['matching_statuses'] = $matchingStatuses;

        // Block or unblock seats
        $updated = 0;
        if ($action === 'block') {
            $updated = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                ->whereIn('seat_uid', $seatUids)
                ->where('status', 'available')
                ->update([
                    'status' => 'blocked',
                    'version' => \Illuminate\Support\Facades\DB::raw('version + 1'),
                ]);
        } else {
            $updated = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                ->whereIn('seat_uid', $seatUids)
                ->where('status', 'blocked')
                ->update([
                    'status' => 'available',
                    'version' => \Illuminate\Support\Facades\DB::raw('version + 1'),
                ]);
        }

        $debug['updated'] = $updated;

        $inviteUrl = null;
        if ($updated > 0 && $action === 'block' && $createInvitations) {
            $marketplace = static::getMarketplaceClient();
            $hasInvitations = $marketplace?->microservices()
                ->where('microservices.slug', 'invitations')
                ->wherePivot('is_active', true)
                ->exists() ?? false;

            if ($hasInvitations) {
                // Fetch seat details for invitation notes
                $blockedSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                    ->whereIn('seat_uid', $seatUids)
                    ->where('status', 'blocked')
                    ->get(['section_name', 'row_label', 'seat_label']);

                // Group by section+row for readable notes
                $grouped = $blockedSeats->groupBy(fn ($s) => $s->section_name . ' — Rând ' . $s->row_label);
                $notesParts = [];
                foreach ($grouped as $key => $seats) {
                    $notesParts[] = $key . ': Loc ' . $seats->pluck('seat_label')->sort()->implode(', ');
                }

                $notesText = implode('; ', $notesParts);
                $seatCount = $blockedSeats->count();

                // Pass data via URL params (more reliable than session for cross-tab)
                $inviteUrl = route('filament.marketplace.pages.invitations')
                    . '?event=' . $event->id
                    . '&prefill_seats=1'
                    . '&qty=' . $seatCount
                    . '&notes=' . urlencode($notesText)
                    . '&seat_uids=' . urlencode(implode(',', $seatUids));
            }
        }

        return ['updated' => $updated, 'invite_url' => $inviteUrl, 'debug' => $debug];
    }

    /**
     * Toggle a seating row assignment for a ticket type.
     * Called from the interactive seating map editor via $wire.call().
     */
    public function toggleSeatingRowAssignment(int $ticketTypeId, int $rowId): bool
    {
        $this->skipRender();

        $ticketType = \App\Models\TicketType::where('id', $ticketTypeId)
            ->where('event_id', $this->record->id)
            ->first();

        if (!$ticketType) return false;

        // Toggle: if already attached, detach; otherwise attach
        if ($ticketType->seatingRows()->where('seating_row_id', $rowId)->exists()) {
            $ticketType->seatingRows()->detach($rowId);
        } else {
            $ticketType->seatingRows()->attach($rowId);
        }

        // Update ticket type capacity based on total assigned seat count
        $totalSeats = $ticketType->seatingRows()
            ->sum('seat_count');
        $ticketType->update(['capacity' => $totalSeats ?: null]);

        return true;
    }

    /**
     * Manual seat → ticket allocation flow (from harta tab, "Aloc&259; loc" mode).
     * Steps below return data to populate cascading selects in the modal.
     */

    /** Customers who have orders on this event (paid/completed/confirmed). */
    public function getCustomersForEvent(): array
    {
        $this->skipRender();

        $event = $this->record;
        $marketplaceClientId = $event->marketplace_client_id;

        $rows = \DB::table('orders')
            ->join('tickets', 'tickets.order_id', '=', 'orders.id')
            ->join('marketplace_customers', 'marketplace_customers.id', '=', 'orders.marketplace_customer_id')
            ->where('orders.marketplace_client_id', $marketplaceClientId)
            ->where('tickets.event_id', $event->id)
            ->whereIn('orders.status', ['paid', 'completed', 'confirmed'])
            ->select(
                'marketplace_customers.id as id',
                'marketplace_customers.first_name',
                'marketplace_customers.last_name',
                'marketplace_customers.email',
                'marketplace_customers.phone',
            )
            ->distinct()
            ->orderBy('marketplace_customers.first_name')
            ->orderBy('marketplace_customers.last_name')
            ->limit(2000)
            ->get();

        return $rows->map(function ($r) {
            $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
            $label = $name !== '' ? "{$name} ({$r->email})" : (string) $r->email;
            return ['id' => (int) $r->id, 'label' => $label];
        })->all();
    }

    /** Orders for a customer on this event. */
    public function getOrdersForCustomer(int $customerId): array
    {
        $this->skipRender();

        $event = $this->record;

        $orders = \App\Models\Order::where('marketplace_client_id', $event->marketplace_client_id)
            ->where('marketplace_customer_id', $customerId)
            ->whereIn('status', ['paid', 'completed', 'confirmed'])
            ->whereHas('tickets', fn ($q) => $q->where('event_id', $event->id))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'order_number', 'total', 'currency', 'created_at']);

        return $orders->map(function ($o) use ($event) {
            $ticketCount = \App\Models\Ticket::where('order_id', $o->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['valid', 'pending'])
                ->count();
            $when = $o->created_at?->format('d M Y H:i') ?? '—';
            $total = number_format((float) $o->total, 2) . ' ' . ($o->currency ?? 'RON');
            $orderNumber = $o->order_number ?: ('#' . $o->id);
            return [
                'id' => (int) $o->id,
                'label' => "{$orderNumber} · {$ticketCount} bilete · {$total} · {$when}",
            ];
        })->all();
    }

    /**
     * Tickets in an order on this event. When $includeAllocated is false (default),
     * only tickets without a seat are returned. When true, returns all valid/pending
     * tickets — but flags the ones that already have a seat so the UI can warn.
     */
    public function getTicketsForOrder(int $orderId, bool $includeAllocated = false): array
    {
        $this->skipRender();

        $event = $this->record;

        $tickets = \App\Models\Ticket::where('order_id', $orderId)
            ->where('event_id', $event->id)
            ->whereIn('status', ['valid', 'pending'])
            ->with('ticketType:id,name')
            ->orderBy('id')
            ->get(['id', 'ticket_type_id', 'code', 'seat_label', 'meta', 'status']);

        $out = [];
        foreach ($tickets as $t) {
            $currentSeatUid = $t->meta['seat_uid'] ?? null;
            $hasSeat = !empty($currentSeatUid);
            if ($hasSeat && !$includeAllocated) continue;

            $ttName = is_array($t->ticketType?->name)
                ? ($t->ticketType->name['ro'] ?? $t->ticketType->name['en'] ?? reset($t->ticketType->name) ?: '—')
                : ($t->ticketType?->name ?? '—');

            // Detect duplicate-allocation incidents: if another valid
            // ticket on this event has the same seat_uid in its meta,
            // the operator is likely untangling an overlap and needs
            // to know that re-assignment will NOT release the old seat.
            $sharedTicketIds = [];
            if ($hasSeat) {
                $sharedTicketIds = \App\Models\Ticket::where('event_id', $event->id)
                    ->where('id', '!=', $t->id)
                    ->whereJsonContains('meta->seat_uid', $currentSeatUid)
                    ->whereIn('status', ['valid', 'pending', 'used'])
                    ->pluck('id')
                    ->all();
            }

            $label = "#{$t->id} · {$ttName}";
            if ($hasSeat) {
                $label .= ' · loc curent: ' . ($t->seat_label ?: $currentSeatUid);
                if (count($sharedTicketIds) > 0) {
                    $label .= ' · ⚠ suprapunere cu ' . count($sharedTicketIds) . ' bilet(e)';
                }
            }

            $out[] = [
                'id' => (int) $t->id,
                'label' => $label,
                'has_seat' => $hasSeat,
                'current_seat_uid' => $currentSeatUid,
                'current_seat_label' => $t->seat_label,
                'seat_shared_with_ticket_ids' => array_map('intval', $sharedTicketIds),
            ];
        }

        return $out;
    }

    /**
     * Ensure the event has a populated EventSeatingLayout + per-seat
     * EventSeat snapshot. Reused by allocation flow because EventSeats
     * are otherwise lazily created (only when blocked/sold), so a click
     * on a never-touched available seat would otherwise miss the row.
     * Mirrors the bootstrap branch of updateSeatStatuses().
     */
    protected function ensureEventSeatingBootstrapped(): ?\App\Models\Seating\EventSeatingLayout
    {
        $event = $this->record;
        $layoutId = $event->seating_layout_id;
        if (!$layoutId) return null;

        $eventSeating = \App\Models\Seating\EventSeatingLayout::where('event_id', $event->id)->first();

        // Stale snapshot detection (layout swapped or seat UIDs regenerated)
        if ($eventSeating) {
            $stale = (int) $eventSeating->layout_id !== (int) $layoutId;
            if (!$stale) {
                // Sample 1 seat_uid from the source layout to confirm it exists in snapshot
                $sampleUid = \App\Models\Seating\SeatingSeat::withoutGlobalScopes()
                    ->whereHas('row.section', fn ($q) => $q->where('layout_id', $layoutId))
                    ->whereNotNull('seat_uid')
                    ->value('seat_uid');
                if ($sampleUid) {
                    $exists = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                        ->where('seat_uid', $sampleUid)
                        ->exists();
                    if (!$exists) $stale = true;
                }
            }
            if ($stale) {
                $eventSeating->seats()->delete();
                $eventSeating->delete();
                $eventSeating = null;
            }
        }

        $layout = \App\Models\Seating\SeatingLayout::withoutGlobalScopes()
            ->with(['sections.rows.seats'])
            ->find($layoutId);
        if (!$layout || $layout->sections->isEmpty()) {
            return $eventSeating; // best-effort: return whatever we had
        }

        if (!$eventSeating) {
            $geometry = app(\App\Services\Seating\GeometryStorage::class)->generateGeometrySnapshot($layout);
            $eventSeating = \App\Models\Seating\EventSeatingLayout::firstOrCreate(
                ['event_id' => $event->id, 'layout_id' => $layout->id],
                [
                    'marketplace_client_id' => $event->marketplace_client_id ?? null,
                    'json_geometry' => $geometry,
                    'status' => 'active',
                    'published_at' => now(),
                ]
            );
        }

        // Top-up missing EventSeat rows for any layout seat that lacks one.
        // updateOrCreate is idempotent so this is safe to call repeatedly.
        $existingUids = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
            ->pluck('seat_uid')
            ->all();
        $existingSet = array_flip($existingUids);

        foreach ($layout->sections as $section) {
            foreach ($section->rows as $row) {
                foreach ($row->seats as $seat) {
                    if (!$seat->seat_uid) continue;
                    if (isset($existingSet[$seat->seat_uid])) continue;
                    $baseStatus = $seat->status ?? 'active';
                    \App\Models\Seating\EventSeat::create([
                        'event_seating_id' => $eventSeating->id,
                        'seat_uid' => $seat->seat_uid,
                        'section_name' => $section->name,
                        'row_label' => $row->label,
                        'seat_label' => $seat->label,
                        'status' => ($baseStatus === 'imposibil') ? 'disabled' : 'available',
                        'version' => 1,
                    ]);
                }
            }
        }

        return $eventSeating;
    }

    /**
     * Lookup info on a seat the admin just clicked: status + (if sold) the
     * ticket currently holding it. Used by the modal to show context before
     * the operator confirms allocation.
     */
    public function getSeatAllocationContext(string $seatUid): array
    {
        $this->skipRender();

        $event = $this->record;
        $layout = $this->ensureEventSeatingBootstrapped();
        if (!$layout) {
            return ['ok' => false, 'error' => 'no_event_seating_layout'];
        }

        $seat = \App\Models\Seating\EventSeat::where('event_seating_id', $layout->id)
            ->where('seat_uid', $seatUid)
            ->first();
        if (!$seat) {
            return ['ok' => false, 'error' => 'seat_not_found'];
        }

        $occupantTicket = null;
        if (in_array($seat->status, ['sold', 'held'], true)) {
            $t = \App\Models\Ticket::where('event_id', $event->id)
                ->whereJsonContains('meta->seat_uid', $seatUid)
                ->whereIn('status', ['valid', 'pending', 'used'])
                ->orderByDesc('id')
                ->first(['id', 'order_id', 'status', 'seat_label']);
            if ($t) {
                $order = \App\Models\Order::find($t->order_id);
                $occupantTicket = [
                    'id' => $t->id,
                    'order_id' => $t->order_id,
                    'order_number' => $order?->order_number ?: ('#' . $t->order_id),
                    'customer_email' => $order?->customer_email,
                    'customer_name' => $order?->customer_name,
                    'status' => $t->status,
                ];
            }
        }

        return [
            'ok' => true,
            'event_seating_id' => $layout->id,
            'seat_uid' => $seat->seat_uid,
            'section_name' => $seat->section_name,
            'row_label' => $seat->row_label,
            'seat_label' => $seat->seat_label,
            'status' => $seat->status,
            'version' => (int) $seat->version,
            'occupant_ticket' => $occupantTicket,
        ];
    }

    /**
     * Bind a seat to a ticket. Wraps in a transaction with row-level lock so
     * concurrent admins or a checkout in progress cannot race the write.
     * Writes activity log entry under log_name='seat_allocation'.
     */
    public function allocateSeatToTicket(
        string $seatUid,
        int $ticketId,
        string $reason,
        bool $overrideExistingSeat = false,
        bool $confirmHeld = false,
    ): array {
        $this->skipRender();

        $reason = trim(strip_tags($reason));
        if (mb_strlen($reason) < 10) {
            return ['ok' => false, 'error' => 'reason_too_short', 'message' => 'Motivul trebuie să aibă minim 10 caractere.'];
        }

        $event = $this->record;

        $ticket = \App\Models\Ticket::where('id', $ticketId)
            ->where('event_id', $event->id)
            ->whereIn('status', ['valid', 'pending'])
            ->first();
        if (!$ticket) {
            return ['ok' => false, 'error' => 'ticket_not_found', 'message' => 'Biletul nu există, e anulat sau aparține altui eveniment.'];
        }

        $layout = $this->ensureEventSeatingBootstrapped();
        if (!$layout) {
            return ['ok' => false, 'error' => 'no_layout', 'message' => 'Nu există layout de seating activ pentru acest eveniment.'];
        }

        try {
            $result = \DB::transaction(function () use ($seatUid, $ticket, $event, $layout, $reason, $overrideExistingSeat, $confirmHeld) {
                $seat = \App\Models\Seating\EventSeat::where('event_seating_id', $layout->id)
                    ->where('seat_uid', $seatUid)
                    ->lockForUpdate()
                    ->first();
                if (!$seat) {
                    throw new \RuntimeException('seat_not_found');
                }
                if ($seat->status === 'disabled') {
                    throw new \RuntimeException('seat_disabled');
                }
                if ($seat->status === 'held' && !$confirmHeld) {
                    throw new \RuntimeException('seat_held_needs_confirm');
                }
                if ($seat->status === 'sold') {
                    $existingTicket = \App\Models\Ticket::where('event_id', $event->id)
                        ->whereJsonContains('meta->seat_uid', $seatUid)
                        ->whereIn('status', ['valid', 'pending', 'used'])
                        ->orderByDesc('id')
                        ->first();
                    if ($existingTicket && $existingTicket->id !== $ticket->id) {
                        throw new \RuntimeException('seat_already_sold_to_other_ticket:' . $existingTicket->id);
                    }
                    // Same ticket already has it — no-op
                    return [
                        'ok' => true,
                        'noop' => true,
                        'seat_label' => $ticket->seat_label,
                    ];
                }

                // If ticket already has a seat → potentially release old seat.
                // BUT: in duplicate-allocation incidents (two tickets bought the
                // same seat — what this tool is often used to untangle), other
                // valid tickets may still reference the same seat_uid. In that
                // case we MUST keep EventSeat.status='sold' so the legitimate
                // owner's display stays correct; we only unbind this ticket
                // from the seat (clear its meta below). The EventSeat is the
                // single source of truth for the map, so flipping it would
                // visually "free" a seat someone else still holds.
                $oldSeatUid = $ticket->meta['seat_uid'] ?? null;
                $releasedSeatUid = null;
                $oldSeatStatusOutcome = null; // 'released_to_available' | 'kept_sold_other_ticket_holds'
                $oldSeatOtherTickets = 0;
                if ($oldSeatUid && $oldSeatUid !== $seatUid) {
                    if (!$overrideExistingSeat) {
                        throw new \RuntimeException('ticket_has_existing_seat:' . $oldSeatUid);
                    }

                    $oldSeatOtherTickets = \App\Models\Ticket::where('event_id', $event->id)
                        ->where('id', '!=', $ticket->id)
                        ->whereJsonContains('meta->seat_uid', $oldSeatUid)
                        ->whereIn('status', ['valid', 'pending', 'used'])
                        ->count();

                    $oldSeat = \App\Models\Seating\EventSeat::where('event_seating_id', $layout->id)
                        ->where('seat_uid', $oldSeatUid)
                        ->lockForUpdate()
                        ->first();
                    if ($oldSeat) {
                        if ($oldSeatOtherTickets === 0) {
                            $oldSeat->status = 'available';
                            $oldSeat->version = $oldSeat->version + 1;
                            $oldSeat->save();
                            $oldSeatStatusOutcome = 'released_to_available';
                        } else {
                            // Another valid ticket still holds this seat —
                            // keep EventSeat sold; just bump version so any
                            // cached client refreshes.
                            $oldSeat->version = $oldSeat->version + 1;
                            $oldSeat->save();
                            $oldSeatStatusOutcome = 'kept_sold_other_ticket_holds';
                        }
                        $releasedSeatUid = $oldSeatUid;
                    }
                }

                // Update ticket
                $meta = $ticket->meta ?? [];
                $meta['seat_uid'] = $seat->seat_uid;
                $meta['event_seating_id'] = $layout->id;
                $meta['section_name'] = $seat->section_name;
                $meta['row_label'] = $seat->row_label;
                $meta['seat_number'] = $seat->seat_label;
                $ticket->meta = $meta;
                $ticket->seat_label = $seat->section_name . ', Rând ' . $seat->row_label . ', Loc ' . $seat->seat_label;
                $ticket->save();

                // Update seat
                $seatPreviousStatus = $seat->status;
                $seat->status = 'sold';
                $seat->version = $seat->version + 1;
                $seat->save();

                // Update order.meta.seated_items (merge/dedupe)
                $order = \App\Models\Order::find($ticket->order_id);
                if ($order) {
                    $om = $order->meta ?? [];
                    $existing = $om['seated_items'] ?? [];
                    $matched = false;
                    foreach ($existing as &$row) {
                        if ((int) ($row['event_seating_id'] ?? 0) === (int) $layout->id) {
                            $uids = $row['seat_uids'] ?? [];
                            if (!in_array($seat->seat_uid, $uids, true)) {
                                $uids[] = $seat->seat_uid;
                            }
                            // Remove the released seat_uid from this group if any
                            if ($releasedSeatUid) {
                                $uids = array_values(array_filter($uids, fn ($u) => $u !== $releasedSeatUid));
                            }
                            $row['seat_uids'] = array_values(array_unique($uids));
                            $matched = true;
                            break;
                        }
                    }
                    unset($row);
                    if (!$matched) {
                        $existing[] = [
                            'event_seating_id' => $layout->id,
                            'seat_uids' => [$seat->seat_uid],
                        ];
                    }
                    $om['seated_items'] = $existing;
                    $order->meta = $om;
                    $order->save();
                }

                // Activity log
                $causer = auth()->user();
                $orderRefreshed = $order ? $order->refresh() : null;
                $customer = $ticket->order?->marketplaceCustomer;
                $customerName = $customer
                    ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
                    : ($ticket->order?->customer_name ?? null);

                $titleEvent = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title))
                    : $event->title;

                activity('seat_allocation')
                    ->causedBy($causer)
                    ->performedOn($ticket)
                    ->withProperties([
                        'event_id' => $event->id,
                        'event_title' => $titleEvent,
                        'event_seating_id' => $layout->id,
                        'seat_uid' => $seat->seat_uid,
                        'section_name' => $seat->section_name,
                        'row_label' => $seat->row_label,
                        'seat_label_field' => $seat->seat_label,
                        'seat_previous_status' => $seatPreviousStatus,
                        'ticket_seat_label' => $ticket->seat_label,
                        'order_id' => $ticket->order_id,
                        'order_number' => $orderRefreshed?->order_number,
                        'customer_id' => $ticket->order?->marketplace_customer_id,
                        'customer_email' => $ticket->order?->customer_email,
                        'customer_name' => $customerName,
                        'previous_seat_uid' => $releasedSeatUid,
                        'previous_seat_status_outcome' => $oldSeatStatusOutcome,
                        'previous_seat_other_tickets_count' => $oldSeatOtherTickets,
                        'reason' => $reason,
                        'action_type' => $releasedSeatUid ? 'reassigned' : 'allocated',
                    ])
                    ->log($releasedSeatUid
                        ? ($oldSeatStatusOutcome === 'kept_sold_other_ticket_holds'
                            ? "Loc reasignat: {$releasedSeatUid} → {$seat->seat_uid} pentru bilet #{$ticket->id} (vechiul loc rămâne sold — încă deținut de {$oldSeatOtherTickets} alt(e) bilet(e))"
                            : "Loc reasignat: {$releasedSeatUid} → {$seat->seat_uid} pentru bilet #{$ticket->id} (vechiul loc eliberat)")
                        : "Loc alocat manual: {$seat->seat_uid} pentru bilet #{$ticket->id}");

                return [
                    'ok' => true,
                    'ticket_id' => $ticket->id,
                    'seat_uid' => $seat->seat_uid,
                    'seat_label' => $ticket->seat_label,
                    'released_seat_uid' => $releasedSeatUid,
                    'previous_seat_status_outcome' => $oldSeatStatusOutcome,
                    'previous_seat_other_tickets_count' => $oldSeatOtherTickets,
                ];
            });

            return $result;
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = $msg;
            $payload = null;
            if (str_contains($msg, ':')) {
                [$code, $payload] = explode(':', $msg, 2);
            }
            return ['ok' => false, 'error' => $code, 'payload' => $payload, 'message' => $this->describeSeatAllocationError($code, $payload)];
        } catch (\Throwable $e) {
            \Log::channel('marketplace')->error('allocateSeatToTicket failed', [
                'event_id' => $event->id,
                'seat_uid' => $seatUid,
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => 'internal', 'message' => 'Eroare internă: ' . $e->getMessage()];
        }
    }

    protected function describeSeatAllocationError(string $code, ?string $payload): string
    {
        return match ($code) {
            'seat_not_found' => 'Locul selectat nu există pe această hartă.',
            'seat_disabled' => 'Locul este dezactivat (status: disabled).',
            'seat_held_needs_confirm' => 'Locul este în hold activ — confirmă explicit pentru a-l aloca peste.',
            'seat_already_sold_to_other_ticket' => 'Locul este deja alocat altui bilet (#' . ($payload ?? '?') . '). Eliberează-l de acolo întâi.',
            'ticket_has_existing_seat' => 'Biletul are deja un loc alocat (' . ($payload ?? '?') . '). Bifează "Re-asignare" pentru a-l elibera automat.',
            default => 'Eroare: ' . $code,
        };
    }

    /**
     * Recent manual seat allocations on this event, formatted for the
     * collapsible panel under the harta tab.
     */
    public function getRecentSeatAllocations(int $limit = 20): array
    {
        $this->skipRender();

        $event = $this->record;
        $activities = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'seat_allocation')
            ->where('subject_type', \App\Models\Ticket::class)
            ->where('properties->event_id', $event->id)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get(['id', 'description', 'subject_id', 'causer_id', 'causer_type', 'properties', 'created_at']);

        return $activities->map(function ($a) {
            $props = $a->properties ?? collect();
            if ($props instanceof \Illuminate\Support\Collection) $props = $props->all();

            $causer = null;
            if (!empty($a->causer_id) && !empty($a->causer_type) && class_exists($a->causer_type)) {
                $causer = $a->causer_type::find($a->causer_id);
            }
            $causerName = $causer?->name ?? $causer?->full_name ?? null;
            $causerEmail = $causer?->email ?? null;

            $seatHuman = trim(
                (string) ($props['section_name'] ?? '') .
                (!empty($props['row_label']) ? ', Rând ' . $props['row_label'] : '') .
                (!empty($props['seat_label_field']) ? ', Loc ' . $props['seat_label_field'] : '')
            ) ?: '—';

            $customerLabel = trim(($props['customer_name'] ?? '') . ' (' . ($props['customer_email'] ?? '') . ')');
            $customerLabel = trim($customerLabel, '() ') !== '' ? $customerLabel : null;

            $ticketUrl = \App\Filament\Marketplace\Resources\TicketResource::getUrl('view', ['record' => $a->subject_id]);
            $orderUrl = !empty($props['order_id'])
                ? \App\Filament\Marketplace\Resources\OrderResource::getUrl('edit', ['record' => $props['order_id']])
                : null;

            return [
                'id' => (int) $a->id,
                'when' => $a->created_at?->format('d M Y H:i'),
                'causer_name' => $causerName,
                'causer_email' => $causerEmail,
                'action_label' => ($props['action_type'] ?? 'allocated') === 'reassigned' ? 'Reasignare' : 'Alocare nouă',
                'seat_uid' => $props['seat_uid'] ?? '—',
                'seat_human' => $seatHuman,
                'ticket_id' => (int) $a->subject_id,
                'ticket_url' => $ticketUrl,
                'order_number' => $props['order_number'] ?? ('#' . ($props['order_id'] ?? '?')),
                'order_url' => $orderUrl,
                'customer_label' => $customerLabel,
                'reason' => $props['reason'] ?? '',
            ];
        })->all();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Determine the marketplace language (same logic as form())
        $marketplace = static::getMarketplaceClient();
        $rawLang = $marketplace->language ?? $marketplace->locale ?? null;
        $lang = (!empty($rawLang)) ? $rawLang : 'ro';

        // Ensure translatable fields are properly populated for nested form fields
        // The form uses title.{language} syntax, so we need to ensure title is an array
        $translatableFields = ['title', 'subtitle', 'short_description', 'description', 'ticket_terms'];

        foreach ($translatableFields as $field) {
            // Get raw attribute from database (bypasses casts)
            $rawValue = $this->record->getRawOriginal($field);

            if ($rawValue !== null) {
                // If it's a JSON string, decode it
                if (is_string($rawValue)) {
                    $decoded = json_decode($rawValue, true);
                    if (is_array($decoded)) {
                        $data[$field] = $decoded;
                    } else {
                        // Fallback: treat as simple string, wrap in array with current language key
                        $data[$field] = [$lang => $rawValue];
                    }
                } elseif (is_array($rawValue)) {
                    $data[$field] = $rawValue;
                }
            }

            // Ensure the current language key exists — copy first available translation if missing
            if (is_array($data[$field] ?? null) && !isset($data[$field][$lang]) && !empty($data[$field])) {
                $data[$field][$lang] = reset($data[$field]);
            }
        }

        // Tour/Grouping state — populate virtual fields for the Grupare tab
        $data['is_in_tour'] = $this->record->tour_id !== null;
        if ($this->record->tour_id !== null) {
            $tour = Tour::find($this->record->tour_id);
            $data['tour_mode'] = 'existing';
            $data['existing_tour_id'] = $this->record->tour_id;
            $data['tour_name'] = $tour?->name ?? '';
            $data['tour_slug'] = $tour?->slug ?? '';
            $data['grouping_type'] = $tour?->type ?? 'serie_evenimente';
        } else {
            $data['tour_mode'] = 'new';
            $data['existing_tour_id'] = null;
            $data['tour_name'] = '';
            $data['tour_slug'] = '';
            $data['grouping_type'] = 'serie_evenimente';
        }

        return $data;
    }

    /**
     * Auto-fill all SEO keys with the latest event data.
     * Runs on every save to keep SEO data up-to-date.
     * Overwrites all auto-generated keys with fresh values.
     */
    protected function autoFillSeoKeys(): void
    {
        $event = $this->record->fresh(['venue']);
        $marketplace = EventResource::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        // Gather event data
        $title = $event->getTranslation('title', $lang) ?? '';
        $slug = $event->slug ?? '';
        $description = $event->getTranslation('short_description', $lang)
            ?? $event->getTranslation('description', $lang)
            ?? '';
        $shortDesc = strip_tags($description);
        if (strlen($shortDesc) > 160) {
            $shortDesc = substr($shortDesc, 0, 157) . '...';
        }

        $posterUrl = $event->poster_url ?? '';
        $heroUrl = $event->hero_image_url ?? '';
        $imageUrl = $posterUrl ?: $heroUrl;
        $eventDate = $event->event_date?->format('Y-m-d') ?? '';
        $startTime = $event->start_time ?? '';
        $endTime = $event->end_time ?? '';

        $venueName = '';
        $venueAddress = '';
        if ($event->venue) {
            $venueName = $event->venue->getTranslation('name', $lang) ?? $event->venue->name ?? '';
            $venueAddress = $event->venue->address ?? '';
        }

        // Get marketplace's website URL
        $baseUrl = $marketplace?->website ?? '';
        if ($baseUrl && !str_starts_with($baseUrl, 'http://') && !str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://' . $baseUrl;
        }
        $eventUrl = $baseUrl && $slug ? "{$baseUrl}/bilete/{$slug}" : '';

        // Build absolute image URL
        $absoluteImageUrl = '';
        if ($imageUrl) {
            if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
                $absoluteImageUrl = $imageUrl;
            } else {
                $absoluteImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($imageUrl);
            }
        }

        $now = now()->toIso8601String();
        $siteName = $marketplace?->public_name ?? $marketplace?->name ?? '';

        // Min price from active ticket types
        $minPrice = '';
        $ticketTypes = $event->ticketTypes()->where('status', 'active')->get();
        if ($ticketTypes->isNotEmpty()) {
            $paidTickets = $ticketTypes->filter(fn ($t) => ($t->price_cents ?? 0) > 0);
            if ($paidTickets->isNotEmpty()) {
                $minPriceCents = $paidTickets->min(fn ($t) => $t->sale_price_cents > 0 ? $t->sale_price_cents : $t->price_cents);
                $minPrice = number_format($minPriceCents / 100, 2, '.', '');
            }
        }

        // Build all SEO keys (overwrite auto-generated values)
        $seo = [
            // Core
            'meta_title'       => $title,
            'meta_description' => $shortDesc,
            'canonical_url'    => $eventUrl,
            'robots'           => 'index,follow',
            'viewport'         => 'width=device-width, initial-scale=1',
            'referrer'         => 'no-referrer-when-downgrade',

            // International
            'og:locale'        => $lang === 'ro' ? 'ro_RO' : 'en_US',

            // Open Graph
            'og:title'         => $title,
            'og:description'   => $shortDesc,
            'og:type'          => 'event',
            'og:url'           => $eventUrl,
            'og:image'         => $absoluteImageUrl,
            'og:image:alt'     => $title,
            'og:image:width'   => '1200',
            'og:image:height'  => '630',
            'og:site_name'     => $siteName,

            // Article
            'article:author'         => $siteName,
            'article:section'        => 'Events',
            'article:published_time' => $event->created_at?->toIso8601String() ?? $now,
            'article:modified_time'  => $now,

            // Product
            'product:price:amount'   => $minPrice,
            'product:price:currency' => $marketplace?->currency ?? 'RON',
            'product:availability'   => ($event->is_sold_out ?? false) ? 'oos' : 'instock',

            // Twitter
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $title,
            'twitter:description' => $shortDesc,
            'twitter:image'       => $absoluteImageUrl,

            // JSON-LD structured data
            'structured_data' => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'Event',
                'name'     => $title,
                'description' => $shortDesc,
                'image'    => $absoluteImageUrl,
                'startDate' => $eventDate && $startTime ? "{$eventDate}T{$startTime}" : $eventDate,
                'endDate'   => $eventDate && $endTime ? "{$eventDate}T{$endTime}" : '',
                'location' => [
                    '@type'   => 'Place',
                    'name'    => $venueName,
                    'address' => $venueAddress,
                ],
                'organizer' => [
                    '@type' => 'Organization',
                    'name'  => $siteName,
                    'url'   => $baseUrl,
                ],
                'url' => $eventUrl,
                'offers' => $minPrice ? [
                    '@type' => 'Offer',
                    'price' => $minPrice,
                    'priceCurrency' => $marketplace?->currency ?? 'RON',
                    'availability' => ($event->is_sold_out ?? false)
                        ? 'https://schema.org/SoldOut'
                        : 'https://schema.org/InStock',
                    'url' => $eventUrl,
                ] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),

            // Robots advanced
            'max-snippet'       => '-1',
            'max-image-preview' => 'large',
            'max-video-preview' => '-1',
        ];

        // Remove null values from JSON-LD offers
        $jsonLd = json_decode($seo['structured_data'], true);
        if (empty($jsonLd['offers'])) {
            unset($jsonLd['offers']);
            $seo['structured_data'] = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Merge: keep any user-added custom keys, overwrite auto-generated ones
        $existingSeo = (array) ($event->seo ?? []);
        $merged = array_merge($existingSeo, $seo);

        $event->update(['seo' => $merged]);
    }

    protected function afterSave(): void
    {
        // Transform venue_config seasons schedule_list → schedule (keyed by day)
        if (($this->record->display_template ?? 'standard') === 'leisure_venue') {
            $config = $this->record->venue_config ?? [];
            $seasons = $config['seasons'] ?? [];
            $changed = false;

            foreach ($seasons as &$season) {
                $scheduleList = $season['schedule_list'] ?? [];
                if (!empty($scheduleList)) {
                    $schedule = [];
                    foreach ($scheduleList as $entry) {
                        $day = $entry['day'] ?? null;
                        if ($day && !empty($entry['open'])) {
                            $schedule[$day] = ['open' => $entry['open'], 'close' => $entry['close'] ?? '20:00'];
                        } elseif ($day) {
                            $schedule[$day] = null;
                        }
                    }
                    $season['schedule'] = $schedule;
                    $changed = true;
                }
            }
            unset($season);

            if ($changed) {
                $config['seasons'] = $seasons;
                $this->record->update(['venue_config' => $config]);
            }
        }

        // Auto-fill short description from first 80 words of description if empty
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
        $shortDesc = $this->record->getTranslation('short_description', $lang);
        if (empty(trim(strip_tags($shortDesc ?? '')))) {
            $desc = $this->record->getTranslation('description', $lang);
            if ($desc) {
                $text = strip_tags($desc);
                $words = preg_split('/\s+/', trim($text), 81, PREG_SPLIT_NO_EMPTY);
                if (count($words) > 80) {
                    $words = array_slice($words, 0, 80);
                    $text = implode(' ', $words) . '...';
                } else {
                    $text = implode(' ', $words);
                }
                $this->record->setTranslation('short_description', $lang, $text);
                $this->record->saveQuietly();
            }
        }

        // Ticket types are all persisted by now; resolve any "autostart when
        // previous sold out" types (manual sold-out flag or exhausted stock).
        // Runs after the save so it can't be overwritten by a later repeater row.
        \App\Models\TicketType::autostartHiddenAfterSoldOut($this->record->id);

        // Auto-fill all SEO keys with latest event data on every save
        $this->autoFillSeoKeys();

        // Only sync child events if this is a parent event (not a child)
        if (!$this->record->isChild()) {
            app(EventSchedulingService::class)->syncChildEvents($this->record);
        }

        // Sync Performance records from multi_slots (for per-slot pricing)
        \Log::info('[EditEvent afterSave] duration_mode=' . $this->record->duration_mode . ' multi_slots=' . json_encode($this->record->multi_slots));
        if ($this->record->duration_mode === 'multi_day') {
            app(PerformanceSyncService::class)->syncFromMultiSlots($this->record);
        }

        // Update artist pivot data (sort_order, is_headliner, is_co_headliner)
        // for the artists configured in the artist_settings Repeater.
        // Use updateExistingPivot per artist instead of sync() — sync() would
        // detach any artist NOT in artist_settings, which silently removes
        // newly-added artists whose Repeater row hasn't been hydrated yet.
        // The Repeater submits artist_settings keyed by its internal row keys
        // (e.g. {"1": {...}, "0": {...}, "2": {...}} when the user has reordered).
        // PHP iterates in insertion order, so the foreach below walks the rows
        // in the new visual order — but the array key itself is meaningless as
        // a sort index. Use a separate counter that increments per row.
        $artistSettings = $this->data['artist_settings'] ?? [];
        if (!empty($artistSettings)) {
            $sortIndex = 0;
            foreach ($artistSettings as $setting) {
                if (!empty($setting['artist_id'])) {
                    $this->record->artists()->updateExistingPivot($setting['artist_id'], [
                        'sort_order' => $sortIndex,
                        'is_headliner' => $setting['is_headliner'] ?? false,
                        'is_co_headliner' => $setting['is_co_headliner'] ?? false,
                    ]);
                    $sortIndex++;
                }
            }
        }

        // Ticket type sort order — explicitly save based on Repeater array order
        // This is a defensive fix because ->orderColumn('sort_order') may not always persist correctly
        $ticketTypesData = $this->data['ticketTypes'] ?? [];
        if (!empty($ticketTypesData)) {
            $sortIndex = 0;
            foreach ($ticketTypesData as $ttData) {
                if (!empty($ttData['id'])) {
                    \App\Models\TicketType::where('id', (int) $ttData['id'])
                        ->where('event_id', $this->record->id)
                        ->update(['sort_order' => $sortIndex]);
                    $sortIndex++;
                }
            }
        }

        // Sync per-performance ticket type prices → Performance.ticket_overrides JSON
        // Also auto-calculate series_start/series_end per performance
        // Data comes from TicketType.meta.performance_prices repeater
        if ($this->record->duration_mode !== 'multi_day' || !($this->data['has_per_performance_pricing'] ?? false)) {
            // Clear all performance overrides when not in multi_day mode or pricing disabled
            $this->record->performances()->update(['ticket_overrides' => null]);

            // Clear meta.performance_prices from all ticket types
            foreach ($this->record->ticketTypes as $tt) {
                $meta = is_array($tt->meta) ? $tt->meta : (json_decode($tt->meta ?? '{}', true) ?: []);
                if (isset($meta['performance_prices'])) {
                    unset($meta['performance_prices']);
                    \App\Models\TicketType::where('id', $tt->id)->update([
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    ]);
                }
            }
        }

        if ($this->record->duration_mode === 'multi_day' && ($this->data['has_per_performance_pricing'] ?? false)) {
            $performances = $this->record->performances()->get();
            $ticketTypesData = $this->data['ticketTypes'] ?? [];
            $eventSeries = $this->record->event_series ?? '';

            // Auto-calculate performance series and build overrides map
            foreach ($ticketTypesData as &$ttData) {
                $ttId = (int) ($ttData['id'] ?? 0);
                if (!$ttId) continue;

                $sku = $ttData['sku'] ?? $ttId;
                $ttCapacity = (int) ($ttData['capacity'] ?? 0);
                if ($ttCapacity === -1) $ttCapacity = 1000;

                $perfPrices = $ttData['meta']['performance_prices'] ?? [];
                $perfIndex = 1;
                foreach ($perfPrices as $key => &$pp) {
                    $perfId = (int) ($pp['perf_id'] ?? 0);
                    if (!$perfId) continue;

                    $stock = !empty($pp['stock']) ? (int) $pp['stock'] : $ttCapacity;
                    if ($stock <= 0) $stock = $ttCapacity ?: 1000;

                    $prefix = $eventSeries . '-' . $sku . '-P' . $perfIndex;
                    $pp['series_start'] = $prefix . '-' . str_pad(1, 5, '0', STR_PAD_LEFT);
                    $pp['series_end'] = $prefix . '-' . str_pad($stock, 5, '0', STR_PAD_LEFT);
                    $perfIndex++;
                }
                unset($pp);
                $ttData['meta']['performance_prices'] = $perfPrices;

                // Persist the updated meta with series back to the ticket type
                if ($ttId && !empty($perfPrices)) {
                    $existingMeta = \App\Models\TicketType::where('id', $ttId)->value('meta');
                    $existingMeta = is_array($existingMeta) ? $existingMeta : (json_decode($existingMeta ?? '{}', true) ?: []);
                    \App\Models\TicketType::where('id', $ttId)->update([
                        'meta' => json_encode(array_merge(
                            $existingMeta,
                            ['performance_prices' => array_values($perfPrices)]
                        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }
            unset($ttData);

            // Build a map: performance_id → [{ticket_type_id, price_cents}]
            $perfOverrides = [];
            foreach ($ticketTypesData as $ttData) {
                $ttId = (int) ($ttData['id'] ?? 0);
                if (!$ttId) continue;

                $perfPrices = $ttData['meta']['performance_prices'] ?? [];
                foreach ($perfPrices as $pp) {
                    $perfId = (int) ($pp['perf_id'] ?? 0);
                    $price = $pp['price'] ?? null;
                    if ($perfId) {
                        $perfOverrides[$perfId][] = [
                            'ticket_type_id' => $ttId,
                            'price_cents' => $price !== null && $price !== '' ? (int) round((float) $price * 100) : null,
                            'quota' => !empty($pp['stock']) ? (int) $pp['stock'] : null,
                        ];
                    }
                }
            }

            // Update ticket_overrides on each performance
            foreach ($performances as $perf) {
                $overrides = $perfOverrides[$perf->id] ?? [];
                $perf->update(['ticket_overrides' => !empty($overrides) ? $overrides : null]);
            }

            \Log::info('[EditEvent] Synced performance overrides', [
                'event_id' => $this->record->id,
                'overrides_map' => $perfOverrides,
            ]);
        }

        // Tour management — only act if the tour field is present in form data
        // (prevents accidental clearing when the field value is missing/undefined)
        \Log::info('[TourDebug] afterSave entered', [
            'event_id' => $this->record->id,
            'current_tour_id' => $this->record->tour_id,
            'has_is_in_tour_key' => array_key_exists('is_in_tour', $this->data),
            'is_in_tour' => $this->data['is_in_tour'] ?? null,
            'tour_mode' => $this->data['tour_mode'] ?? null,
            'tour_name' => $this->data['tour_name'] ?? null,
            'tour_slug' => $this->data['tour_slug'] ?? null,
            'existing_tour_id' => $this->data['existing_tour_id'] ?? null,
            'grouping_type' => $this->data['grouping_type'] ?? null,
        ]);

        if (!array_key_exists('is_in_tour', $this->data)) {
            \Log::info('[TourDebug] EARLY RETURN — is_in_tour key missing');
            return;
        }

        $isInTour = (bool) ($this->data['is_in_tour'] ?? false);

        if ($isInTour) {
            $tourMode = $this->data['tour_mode'] ?? 'new';
            $groupingType = $this->data['grouping_type'] ?? 'serie_evenimente';

            if ($tourMode === 'new') {
                $tourName = trim($this->data['tour_name'] ?? '');
                $tourSlug = trim($this->data['tour_slug'] ?? '') ?: \Illuminate\Support\Str::slug($tourName);

                \Log::info('[TourDebug] tour_mode=new branch', [
                    'event_id' => $this->record->id,
                    'tour_name' => $tourName,
                    'tour_slug' => $tourSlug,
                    'record_tour_id' => $this->record->tour_id,
                ]);

                if ($this->record->tour_id) {
                    // Update the name, slug and type of the existing tour
                    $updateData = ['name' => $tourName, 'type' => $groupingType];
                    if ($tourSlug) $updateData['slug'] = $tourSlug;
                    Tour::where('id', $this->record->tour_id)->update($updateData);
                    \Log::info('[TourDebug] UPDATED existing tour', ['tour_id' => $this->record->tour_id, 'data' => $updateData]);
                } else {
                    // Create a brand-new tour and assign only this event.
                    // Pass tenant_id through so the public ambilet.ro/turnee/{slug}
                    // page (TenantClient\ToursController) can match the tour
                    // directly without falling back to the events whereHas check.
                    $tour = Tour::create([
                        'marketplace_client_id' => $this->record->marketplace_client_id,
                        'tenant_id' => $this->record->tenant_id,
                        'name' => $tourName,
                        'slug' => $tourSlug,
                        'type' => $groupingType,
                    ]);
                    $this->record->update(['tour_id' => $tour->id]);
                    \Log::info('[TourDebug] CREATED new tour', ['tour_id' => $tour->id, 'event_assigned' => $this->record->id]);
                }
            } else {
                // Existing tour selected
                $existingTourId = (int) ($this->data['existing_tour_id'] ?? 0);

                \Log::info('[TourDebug] tour_mode=existing branch', [
                    'event_id' => $this->record->id,
                    'existing_tour_id_from_data' => $existingTourId,
                    'record_tour_id' => $this->record->tour_id,
                ]);

                if ($existingTourId && $existingTourId !== (int) $this->record->tour_id) {
                    // Remove this event from old tour and clean up if orphaned
                    $oldTourId = $this->record->tour_id;
                    $this->record->update(['tour_id' => $existingTourId]);

                    // Update grouping type and slug on the existing tour
                    $updateData = ['type' => $groupingType];
                    $tourSlug = trim($this->data['tour_slug'] ?? '');
                    if ($tourSlug) $updateData['slug'] = $tourSlug;
                    Tour::where('id', $existingTourId)->update($updateData);

                    if ($oldTourId) {
                        $remaining = Event::where('tour_id', $oldTourId)->count();
                        if ($remaining === 0) {
                            Tour::where('id', $oldTourId)->delete();
                        }
                    }
                } elseif ($existingTourId) {
                    // Same tour, just update the type and slug
                    $updateData = ['type' => $groupingType];
                    $tourSlug = trim($this->data['tour_slug'] ?? '');
                    if ($tourSlug) $updateData['slug'] = $tourSlug;
                    Tour::where('id', $existingTourId)->update($updateData);
                }
            }
        } else {
            // Remove current event from its tour
            if ($this->record->tour_id) {
                $oldTourId = $this->record->tour_id;
                $this->record->update(['tour_id' => null]);

                // Clean up tour if it has no remaining events
                $remaining = Event::where('tour_id', $oldTourId)->count();
                if ($remaining === 0) {
                    Tour::where('id', $oldTourId)->delete();
                }
            }
        }

        // Fallback: copy hero_image_url to featured image fields when empty
        $record = $this->record;
        if ($record->is_homepage_featured && !$record->homepage_featured_image && $record->hero_image_url) {
            $record->updateQuietly(['homepage_featured_image' => $record->hero_image_url]);
        }
        if (($record->is_general_featured || $record->is_category_featured) && !$record->featured_image && $record->hero_image_url) {
            $record->updateQuietly(['featured_image' => $record->hero_image_url]);
        }
    }
}
