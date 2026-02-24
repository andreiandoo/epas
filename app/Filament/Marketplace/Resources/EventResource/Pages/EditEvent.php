<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Models\Event;
use App\Models\Tour;
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

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        $title = $this->record->getTranslation('title', $lang)
            ?? $this->record->getTranslation('title', 'ro')
            ?? $this->record->getTranslation('title', 'en')
            ?? '';

        // Append city and date in parentheses
        $parts = [];
        $city = $this->record->city ?? $this->record->venue?->city ?? null;
        if ($city) {
            $parts[] = $city;
        }
        $eventDate = $this->record->event_date ?? null;
        if ($eventDate) {
            $parts[] = \Carbon\Carbon::parse($eventDate)->translatedFormat('d M Y');
        }
        if (!empty($parts)) {
            $title .= ' (' . implode(', ', $parts) . ')';
        }

        return $title ?: 'Edit Event';
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
                        \App\Models\Seating\EventSeat::create([
                            'event_seating_id' => $eventSeating->id,
                            'seat_uid' => $seat->seat_uid,
                            'section_name' => $section->name,
                            'row_label' => $row->label,
                            'seat_label' => $seat->label,
                            'status' => ($baseStatus === 'imposibil') ? 'disabled' : 'available',
                            'version' => 1,
                        ]);
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

        // Tour state — populate virtual fields for the Turneu tab
        $data['is_in_tour'] = $this->record->tour_id !== null;
        if ($this->record->tour_id !== null) {
            $tour = Tour::find($this->record->tour_id);
            $data['tour_mode'] = 'existing';
            $data['existing_tour_id'] = $this->record->tour_id;
            $data['tour_name'] = $tour?->name ?? '';
        } else {
            $data['tour_mode'] = 'new';
            $data['existing_tour_id'] = null;
            $data['tour_name'] = '';
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

        // Tour management — only act if the tour field is present in form data
        // (prevents accidental clearing when the field value is missing/undefined)
        if (!array_key_exists('is_in_tour', $this->data)) {
            return;
        }

        $isInTour = (bool) ($this->data['is_in_tour'] ?? false);

        if ($isInTour) {
            $tourMode = $this->data['tour_mode'] ?? 'new';

            if ($tourMode === 'new') {
                $tourName = trim($this->data['tour_name'] ?? '');

                if ($this->record->tour_id) {
                    // Update the name of the existing tour
                    Tour::where('id', $this->record->tour_id)->update(['name' => $tourName]);
                } else {
                    // Create a brand-new tour and assign only this event
                    $tour = Tour::create([
                        'marketplace_client_id' => $this->record->marketplace_client_id,
                        'name' => $tourName,
                    ]);
                    $this->record->update(['tour_id' => $tour->id]);
                }
            } else {
                // Existing tour selected
                $existingTourId = (int) ($this->data['existing_tour_id'] ?? 0);

                if ($existingTourId && $existingTourId !== (int) $this->record->tour_id) {
                    // Remove this event from old tour and clean up if orphaned
                    $oldTourId = $this->record->tour_id;
                    $this->record->update(['tour_id' => $existingTourId]);

                    if ($oldTourId) {
                        $remaining = Event::where('tour_id', $oldTourId)->count();
                        if ($remaining === 0) {
                            Tour::where('id', $oldTourId)->delete();
                        }
                    }
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
    }
}
