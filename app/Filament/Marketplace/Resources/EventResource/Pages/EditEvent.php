<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Services\EventSchedulingService;
use App\Services\Seating\MarketplaceEventSeatingService;
use App\Models\Seating\EventSeatingLayout;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class EditEvent extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redirect hosted events to view-guest page (can't edit events you don't own)
        // Marketplace events use marketplace_client_id, not tenant_id
        $marketplace = static::getMarketplaceClient();
        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            redirect(EventResource::getUrl('view-guest', ['record' => $this->record]));
        }
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

        // Statistics button - always visible
        $actions[] = Actions\Action::make('statistics')
            ->label('Statistics')
            ->icon('heroicon-o-chart-bar')
            ->color('info')
            ->url(fn () => EventResource::getUrl('statistics', ['record' => $this->record]));

        // Analytics Dashboard button
        $actions[] = Actions\Action::make('analytics')
            ->label('Analytics Dashboard')
            ->icon('heroicon-o-presentation-chart-line')
            ->color('success')
            ->url(fn () => EventResource::getUrl('analytics', ['record' => $this->record]));

        // Activity Log button - always visible
        $actions[] = Actions\Action::make('activity_log')
            ->label('Activity Log')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->url(fn () => EventResource::getUrl('activity-log', ['record' => $this->record]));

        if ($hasInvitations) {
            $actions[] = Actions\Action::make('invitations')
                ->label('Create Invitations')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->url(fn () => route('filament.marketplace.pages.invitations') . '?event=' . $this->record->id);
        }

        // Block Seats action - only show if event has seating layout
        // Use withoutGlobalScopes() to bypass TenantScope on SeatingLayout in marketplace context
        if ($this->record->venue?->seatingLayouts()->withoutGlobalScopes()->where('status', 'published')->exists()) {
            $actions[] = $this->getBlockSeatsAction($hasInvitations);
        }

        // Upload Images action - modal-based to avoid Livewire re-render issues
        $actions[] = $this->getUploadImagesAction();

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
                                return \App\Models\MediaLibrary::query()
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->where('mime_type', 'LIKE', 'image/%')
                                    ->where(function ($q) use ($search) {
                                        $q->where('original_filename', 'LIKE', "%{$search}%")
                                          ->orWhere('filename', 'LIKE', "%{$search}%")
                                          ->orWhere('title', 'LIKE', "%{$search}%");
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
                                return \App\Models\MediaLibrary::query()
                                    ->where('marketplace_client_id', $marketplace?->id)
                                    ->where('mime_type', 'LIKE', 'image/%')
                                    ->where(function ($q) use ($search) {
                                        $q->where('original_filename', 'LIKE', "%{$search}%")
                                          ->orWhere('filename', 'LIKE', "%{$search}%")
                                          ->orWhere('title', 'LIKE', "%{$search}%");
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
     * Get the Block Seats action for managing event-level seat blocking
     */
    protected function getBlockSeatsAction(bool $hasInvitations): Actions\Action
    {
        $seatingService = app(MarketplaceEventSeatingService::class);
        $eventSeating = $seatingService->getOrCreateEventSeatingByEventId($this->record->id);

        return Actions\Action::make('blockSeats')
            ->label('Block Seats')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->modalHeading('Block Seats from Purchase')
            ->modalDescription('Block seats to prevent them from being purchased. Blocked seats can still be used for invitations.')
            ->modalWidth('lg')
            ->form([
                Forms\Components\Select::make('section_name')
                    ->label('Section')
                    ->options(function () use ($eventSeating) {
                        if (!$eventSeating) return [];

                        return \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                            ->select('section_name')
                            ->distinct()
                            ->orderBy('section_name')
                            ->pluck('section_name', 'section_name');
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Select::make('row_label')
                    ->label('Row')
                    ->options(function (SGet $get) use ($eventSeating) {
                        $sectionName = $get('section_name');
                        if (!$eventSeating || !$sectionName) return [];

                        return \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                            ->where('section_name', $sectionName)
                            ->select('row_label')
                            ->distinct()
                            ->orderByRaw('CAST(row_label AS UNSIGNED), row_label')
                            ->pluck('row_label', 'row_label');
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Select::make('action')
                    ->label('Action')
                    ->options([
                        'block' => 'Block seats (prevent purchase)',
                        'unblock' => 'Unblock seats (make available)',
                    ])
                    ->required()
                    ->default('block')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('seat_range')
                    ->label('Seat Range')
                    ->helperText('Enter seat numbers to modify. Examples: "1,3,5" or "1-5" or "1-3,7,9-12"')
                    ->required()
                    ->placeholder('e.g., 1-5,8,10-12')
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('current_blocked')
                    ->label('Current Blocked Seats in Row')
                    ->content(function (SGet $get) use ($eventSeating) {
                        $sectionName = $get('section_name');
                        $rowLabel = $get('row_label');

                        if (!$eventSeating || !$sectionName || !$rowLabel) {
                            return 'Select section and row to see blocked seats';
                        }

                        $blockedSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                            ->where('section_name', $sectionName)
                            ->where('row_label', $rowLabel)
                            ->where('status', 'blocked')
                            ->orderByRaw('CAST(seat_label AS UNSIGNED), seat_label')
                            ->pluck('seat_label')
                            ->toArray();

                        if (empty($blockedSeats)) {
                            return 'No blocked seats in this row';
                        }

                        return 'Blocked: ' . implode(', ', $blockedSeats);
                    })
                    ->columnSpanFull(),

                $hasInvitations ? Forms\Components\Toggle::make('create_invitations')
                    ->label('Generate invitation for blocked seats')
                    ->helperText('If enabled, invitations will be created for the blocked seats.')
                    ->default(false)
                    ->columnSpanFull() : Forms\Components\Hidden::make('create_invitations')->default(false),
            ])
            ->action(function (array $data) use ($seatingService, $eventSeating, $hasInvitations): void {
                if (!$eventSeating) {
                    Notification::make()
                        ->danger()
                        ->title('Event seating not found')
                        ->send();
                    return;
                }

                // Parse seat range
                $seatLabels = $this->parseSeatRange($data['seat_range']);
                if (empty($seatLabels)) {
                    Notification::make()
                        ->danger()
                        ->title('Invalid seat range')
                        ->body('Could not parse the seat range. Use format: 1-5 or 1,3,5 or 1-3,7')
                        ->send();
                    return;
                }

                $updated = 0;
                if ($data['action'] === 'block') {
                    $updated = $seatingService->blockSeatsByLocation(
                        $eventSeating->id,
                        $data['section_name'],
                        $data['row_label'],
                        $seatLabels
                    );

                    // Reduce ticket type stock for the affected section
                    if ($updated > 0) {
                        $this->adjustTicketTypeStock($data['section_name'], -$updated);
                    }

                    // Create invitations if requested
                    if ($hasInvitations && ($data['create_invitations'] ?? false) && $updated > 0) {
                        // Store blocked seat info in session for the invitations page to pick up
                        session()->put('blocked_seats_for_invitation', [
                            'event_id' => $this->record->id,
                            'section' => $data['section_name'],
                            'row' => $data['row_label'],
                            'seats' => $seatLabels,
                        ]);

                        $url = route('filament.marketplace.pages.invitations') . '?event=' . $this->record->id . '&prefill_seats=1';

                        Notification::make()
                            ->success()
                            ->title("{$updated} seats blocked")
                            ->body('Opening invitation creation in new tab...')
                            ->send();

                        $this->js("window.open('{$url}', '_blank'); setTimeout(() => window.location.reload(), 800)");
                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Seats blocked')
                        ->body("{$updated} seats have been blocked from purchase")
                        ->send();

                    // Reload page to avoid Alpine re-render errors with file upload components
                    $this->js('setTimeout(() => window.location.reload(), 800)');
                } else {
                    // Find blocked seats by location and unblock them
                    $updated = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                        ->where('section_name', $data['section_name'])
                        ->where('row_label', $data['row_label'])
                        ->whereIn('seat_label', $seatLabels)
                        ->where('status', 'blocked')
                        ->update([
                            'status' => 'available',
                            'version' => \Illuminate\Support\Facades\DB::raw('version + 1'),
                        ]);

                    // Restore ticket type stock for the affected section
                    if ($updated > 0) {
                        $this->adjustTicketTypeStock($data['section_name'], $updated);
                    }

                    Notification::make()
                        ->success()
                        ->title('Seats unblocked')
                        ->body("{$updated} seats are now available for purchase")
                        ->send();

                    // Reload page to avoid Alpine re-render errors with file upload components
                    $this->js('setTimeout(() => window.location.reload(), 800)');
                }
            });
    }

    /**
     * Parse seat range string like "1-5,8,10-12" into array of labels
     */
    protected function parseSeatRange(string $range): array
    {
        $labels = [];
        $parts = explode(',', $range);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);
                $start = (int) trim($start);
                $end = (int) trim($end);
                if ($start > 0 && $end > 0 && $end >= $start) {
                    for ($i = $start; $i <= $end; $i++) {
                        $labels[] = (string) $i;
                    }
                }
            } else {
                $num = (int) trim($part);
                if ($num > 0) {
                    $labels[] = (string) $num;
                }
            }
        }

        return array_unique($labels);
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

        return $data;
    }

    protected function afterSave(): void
    {
        // Only sync child events if this is a parent event (not a child)
        if (!$this->record->isChild()) {
            app(EventSchedulingService::class)->syncChildEvents($this->record);
        }

        // Sync artist pivot data (sort_order, is_headliner, is_co_headliner)
        $artistSettings = $this->data['artist_settings'] ?? [];
        if (!empty($artistSettings)) {
            $syncData = [];
            foreach ($artistSettings as $index => $setting) {
                if (!empty($setting['artist_id'])) {
                    $syncData[$setting['artist_id']] = [
                        'sort_order' => $index,
                        'is_headliner' => $setting['is_headliner'] ?? false,
                        'is_co_headliner' => $setting['is_co_headliner'] ?? false,
                    ];
                }
            }

            if (!empty($syncData)) {
                $this->record->artists()->sync($syncData);
            }
        }
    }
}
