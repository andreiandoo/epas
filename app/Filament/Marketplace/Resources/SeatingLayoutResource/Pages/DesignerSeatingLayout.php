<?php

namespace App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingSection;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Services\Seating\SVGImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components as SC;

class DesignerSeatingLayout extends Page
{
    protected static string $resource = SeatingLayoutResource::class;

    protected string $view = 'filament.resources.seating-layout-resource.pages.designer-konva';

    protected static ?string $title = 'Seating Designer';

    public SeatingLayout $seatingLayout;

    public array $sections = [];

    public ?int $selectedSection = null;

    public function mount(SeatingLayout $record): void
    {
        $this->seatingLayout = $record;
        $this->reloadSections();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importMap')
                ->label('Import Map')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalWidth('5xl')
                ->modalHeading('Import Seating Map')
                ->steps([
                    Step::make('Upload')
                        ->description('Paste HTML/SVG content')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Textarea::make('html_content')
                                ->label('HTML/SVG Content')
                                ->helperText('Paste the HTML content containing SVG areas and seats layers (e.g., from iabilet.ro)')
                                ->rows(12)
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Step::make('Preview')
                        ->description('Review detected elements')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Forms\Components\Placeholder::make('preview_info')
                                ->label('Detected Elements')
                                ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                    $htmlContent = $get('html_content');
                                    if (empty($htmlContent)) {
                                        return 'No content to preview. Please go back and paste HTML/SVG content.';
                                    }

                                    try {
                                        $service = app(SVGImportService::class);
                                        $imported = $service->parseIabiletHtml($htmlContent);

                                        $sectionCount = $imported->sectionCount();
                                        $seatCount = $imported->seatCount();
                                        $categoryIds = $imported->getUniqueCategoryIds();

                                        $preview = "**Sections detected:** {$sectionCount}\n\n";
                                        $preview .= "**Seats detected:** {$seatCount}\n\n";

                                        if (!empty($categoryIds)) {
                                            $preview .= "**Category IDs:** " . implode(', ', $categoryIds) . "\n\n";
                                        }

                                        if ($imported->backgroundUrl) {
                                            $preview .= "**Background image:** Found\n\n";
                                        }

                                        $preview .= "---\n\n**Section Details:**\n\n";

                                        foreach ($imported->sections as $index => $section) {
                                            $sectionNum = $index + 1;
                                            $sectionSeats = count($section->seats);
                                            $preview .= "- Section {$sectionNum}: {$sectionSeats} seats";
                                            if ($section->categoryId) {
                                                $preview .= " (Category: {$section->categoryId})";
                                            }
                                            $preview .= "\n";
                                        }

                                        return new \Illuminate\Support\HtmlString(
                                            \Illuminate\Support\Str::markdown($preview)
                                        );
                                    } catch (\Exception $e) {
                                        return "Error parsing content: " . $e->getMessage();
                                    }
                                })
                                ->columnSpanFull(),
                        ]),
                    Step::make('Options')
                        ->description('Configure import settings')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Toggle::make('import_seats')
                                ->label('Import seats')
                                ->default(true)
                                ->helperText('Uncheck to import only sections/areas'),

                            Forms\Components\Toggle::make('clear_existing')
                                ->label('Clear existing sections')
                                ->default(false)
                                ->helperText('Warning: This will delete all existing sections and their seats'),

                            Forms\Components\Toggle::make('resize_canvas')
                                ->label('Resize canvas to match import')
                                ->default(false)
                                ->helperText('Adjusts canvas size to match the original map dimensions (recommended for better alignment)'),

                            Forms\Components\Placeholder::make('current_layout_info')
                                ->label('Current Layout')
                                ->content(fn () => "Current canvas: **{$this->seatingLayout->canvas_width}x{$this->seatingLayout->canvas_height}px** with **{$this->seatingLayout->sections()->count()}** sections.")
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $service = app(SVGImportService::class);

                    try {
                        $imported = $service->parseIabiletHtml($data['html_content']);

                        // Resize canvas if requested and viewBox is available
                        if (($data['resize_canvas'] ?? false) && $imported->viewBox) {
                            $newWidth = (int) ceil($imported->viewBox['width']);
                            $newHeight = (int) ceil($imported->viewBox['height']);
                            $this->seatingLayout->update([
                                'canvas_w' => $newWidth,
                                'canvas_h' => $newHeight,
                            ]);
                        }

                        $imported->normalizeToCanvas(
                            $this->seatingLayout->canvas_w,
                            $this->seatingLayout->canvas_h
                        );

                        $stats = $service->createLayoutFromImport(
                            $imported,
                            $this->seatingLayout,
                            importSeats: $data['import_seats'] ?? true,
                            clearExisting: $data['clear_existing'] ?? false
                        );

                        // Save background image URL if found
                        if ($imported->backgroundUrl) {
                            $this->seatingLayout->update([
                                'background_image_url' => $imported->backgroundUrl,
                            ]);
                        }

                        $this->reloadSections();

                        // Dispatch event to reload canvas
                        $this->dispatch('layout-imported', sections: $this->sections);

                        $message = "Imported {$stats['sections_created']} sections, {$stats['rows_created']} rows, and {$stats['seats_created']} seats";
                        if ($imported->backgroundUrl) {
                            $message .= ". Background image URL saved.";
                        }

                        Notification::make()
                            ->success()
                            ->title('Import successful')
                            ->body($message)
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('addSection')
                ->label('Add Section')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('section_code')
                        ->label('Section Code')
                        ->required()
                        ->maxLength(20)
                        ->helperText('Unique identifier (e.g., A, B, VIP)')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('section_type')
                        ->options([
                            'standard' => 'Standard (rows & seats)',
                            'general_admission' => 'General Admission (capacity only)',
                        ])
                        ->default('standard')
                        ->required()
                        ->reactive()
                        ->columnSpanFull(),

                    Forms\Components\ColorPicker::make('color_hex')
                        ->label('Section Background Color')
                        ->default('#3B82F6')
                        ->helperText('Background color for the section area')
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('seat_color')
                        ->label('Seat Color (Available)')
                        ->default('#22C55E')
                        ->helperText('Color for available seats in this section')
                        ->columnSpan(1),

                    // Standard section: rows & seats configuration
                    SC\Section::make('Rows & Seats Configuration')
                        ->description('Configure the initial layout of rows and seats')
                        ->visible(fn ($get) => $get('section_type') === 'standard')
                        ->schema([
                            Forms\Components\TextInput::make('num_rows')
                                ->label('Number of Rows')
                                ->numeric()
                                ->default(5)
                                ->minValue(1)
                                ->maxValue(50)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('seats_per_row')
                                ->label('Seats per Row')
                                ->numeric()
                                ->default(10)
                                ->minValue(1)
                                ->maxValue(100)
                                ->columnSpan(1),

                            Forms\Components\Select::make('seat_shape')
                                ->label('Seat Shape')
                                ->options([
                                    'circle' => 'Circle',
                                    'rect' => 'Square',
                                ])
                                ->default('circle')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('seat_size')
                                ->label('Seat Size (px)')
                                ->numeric()
                                ->default(10)
                                ->minValue(4)
                                ->maxValue(30)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('row_spacing')
                                ->label('Row Spacing (px)')
                                ->numeric()
                                ->default(25)
                                ->minValue(10)
                                ->maxValue(100)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('seat_spacing')
                                ->label('Seat Spacing (px)')
                                ->numeric()
                                ->default(18)
                                ->minValue(5)
                                ->maxValue(50)
                                ->columnSpan(1),

                            Forms\Components\Select::make('seat_numbering')
                                ->label('Seat Numbering Direction')
                                ->options([
                                    'ltr' => 'Left to Right (1, 2, 3...)',
                                    'rtl' => 'Right to Left (...3, 2, 1)',
                                ])
                                ->default('ltr')
                                ->columnSpan(1),

                            Forms\Components\Select::make('row_numbering')
                                ->label('Row Numbering Direction')
                                ->options([
                                    'ttb' => 'Top to Bottom (1, 2, 3...)',
                                    'btt' => 'Bottom to Top (...3, 2, 1)',
                                ])
                                ->default('ttb')
                                ->columnSpan(1),
                        ])
                        ->columns(2),

                    Forms\Components\Hidden::make('x_position')
                        ->default(100),

                    Forms\Components\Hidden::make('y_position')
                        ->default(100),

                    Forms\Components\Hidden::make('width')
                        ->default(200),

                    Forms\Components\Hidden::make('height')
                        ->default(150),

                    Forms\Components\Hidden::make('rotation')
                        ->default(0),

                    Forms\Components\Hidden::make('metadata'),

                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Order in which sections are displayed')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $data['layout_id'] = $this->seatingLayout->id;
                    $data['tenant_id'] = $this->seatingLayout->tenant_id;

                    if (!isset($data['display_order']) || $data['display_order'] === null || $data['display_order'] === '') {
                        $data['display_order'] = 0;
                    }

                    if (isset($data['metadata']) && is_string($data['metadata'])) {
                        $data['metadata'] = json_decode($data['metadata'], true);
                    }

                    // Calculate section dimensions based on seats
                    $numRows = (int) ($data['num_rows'] ?? 0);
                    $seatsPerRow = (int) ($data['seats_per_row'] ?? 0);
                    $seatSize = (int) ($data['seat_size'] ?? 10);
                    $rowSpacing = (int) ($data['row_spacing'] ?? 25);
                    $seatSpacing = (int) ($data['seat_spacing'] ?? 18);

                    if ($data['section_type'] === 'standard' && $numRows > 0 && $seatsPerRow > 0) {
                        // Calculate required dimensions with padding
                        $padding = 20;
                        $data['width'] = ($seatsPerRow * $seatSpacing) + $padding;
                        $data['height'] = ($numRows * $rowSpacing) + $padding;
                    }

                    // Store seat settings in metadata
                    $data['metadata'] = array_merge($data['metadata'] ?? [], [
                        'seat_size' => $seatSize,
                        'seat_spacing' => $seatSpacing,
                        'row_spacing' => $rowSpacing,
                        'seat_shape' => $data['seat_shape'] ?? 'circle',
                    ]);

                    // Remove form-only fields before creating
                    $createData = collect($data)->except([
                        'num_rows', 'seats_per_row', 'seat_shape', 'seat_size',
                        'row_spacing', 'seat_spacing', 'seat_numbering', 'row_numbering'
                    ])->toArray();

                    $section = SeatingSection::create($createData);

                    // Create rows and seats for standard sections
                    if ($data['section_type'] === 'standard' && $numRows > 0 && $seatsPerRow > 0) {
                        $this->createSectionSeats(
                            $section,
                            $numRows,
                            $seatsPerRow,
                            $seatSize,
                            $rowSpacing,
                            $seatSpacing,
                            $data['seat_shape'] ?? 'circle',
                            $data['seat_numbering'] ?? 'ltr',
                            $data['row_numbering'] ?? 'ttb'
                        );
                    }

                    $this->reloadSections();

                    $this->dispatch('section-added', section: $section->load('rows.seats')->toArray());

                    Notification::make()
                        ->success()
                        ->title('Section added successfully')
                        ->send();
                }),

            Actions\Action::make('addDecorativeZone')
                ->label('Add Decorative Zone')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->modalHeading('Add Decorative Zone (Stage, etc.)')
                ->modalDescription('Create a non-seat area like a stage, dance floor, or other decorative element.')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Zone Name')
                        ->required()
                        ->maxLength(100)
                        ->helperText('e.g., "Stage", "Dance Floor", "VIP Lounge"')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('section_type')
                        ->label('Zone Type')
                        ->options([
                            'decorative' => 'Decorative (no seats)',
                            'stage' => 'Stage',
                            'dance_floor' => 'Dance Floor',
                        ])
                        ->default('decorative')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\ColorPicker::make('background_color')
                        ->label('Background Color')
                        ->default('#9333EA')
                        ->helperText('Main fill color for this zone')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('corner_radius')
                        ->label('Corner Radius (px)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText('Curved corners (0 = sharp corners)')
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make('background_image')
                        ->label('Background Image')
                        ->image()
                        ->disk('public')
                        ->directory('seating/zones')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                        ->maxSize(10240)
                        ->preserveFilenames()
                        ->nullable()
                        ->helperText('Optional image overlay for this zone')
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('x_position')
                        ->default(300),

                    Forms\Components\Hidden::make('y_position')
                        ->default(100),

                    Forms\Components\Hidden::make('width')
                        ->default(400),

                    Forms\Components\Hidden::make('height')
                        ->default(200),

                    Forms\Components\Hidden::make('rotation')
                        ->default(0),

                    Forms\Components\Hidden::make('metadata'),

                    Forms\Components\Hidden::make('section_code')
                        ->default('DECORATIVE'),

                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear behind other elements')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $data['layout_id'] = $this->seatingLayout->id;
                    $data['tenant_id'] = $this->seatingLayout->tenant_id;

                    if (!isset($data['display_order']) || $data['display_order'] === null || $data['display_order'] === '') {
                        $data['display_order'] = 0;
                    }

                    if (isset($data['metadata']) && is_string($data['metadata'])) {
                        $data['metadata'] = json_decode($data['metadata'], true);
                    }

                    $zone = SeatingSection::create($data);

                    $this->reloadSections();

                    $this->dispatch('section-added', section: $zone->toArray());

                    Notification::make()
                        ->success()
                        ->title('Decorative zone added successfully')
                        ->send();
                }),

            Actions\Action::make('addIcon')
                ->label('Add Icon')
                ->icon('heroicon-o-map-pin')
                ->color('info')
                ->modalHeading('Add Map Icon')
                ->modalDescription('Add an icon marker to your seating map (exit, toilet, info point, etc.)')
                ->form([
                    Forms\Components\Select::make('icon_key')
                        ->label('Icon Type')
                        ->options(fn () => collect(config('seating-icons', []))->mapWithKeys(fn ($icon, $key) => [$key => $icon['label']]))
                        ->required()
                        ->searchable()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Title/Label')
                        ->required()
                        ->maxLength(50)
                        ->helperText('Text that appears below the icon')
                        ->columnSpanFull(),

                    Forms\Components\ColorPicker::make('icon_color')
                        ->label('Icon Color')
                        ->default('#FFFFFF')
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('background_color')
                        ->label('Background Color')
                        ->default('#3B82F6')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('icon_size')
                        ->label('Icon Size (px)')
                        ->numeric()
                        ->default(48)
                        ->minValue(24)
                        ->maxValue(96)
                        ->helperText('Size of the icon marker')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('corner_radius')
                        ->label('Corner Radius')
                        ->numeric()
                        ->default(8)
                        ->minValue(0)
                        ->maxValue(48)
                        ->helperText('Rounded corners (0 = square)')
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('x_position')
                        ->default(200),

                    Forms\Components\Hidden::make('y_position')
                        ->default(200),

                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(100)
                        ->helperText('Higher numbers appear on top')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $iconSize = (int) ($data['icon_size'] ?? 48);

                    $section = SeatingSection::create([
                        'layout_id' => $this->seatingLayout->id,
                        'tenant_id' => $this->seatingLayout->tenant_id,
                        'name' => $data['name'],
                        'section_code' => 'ICON_' . strtoupper($data['icon_key']),
                        'section_type' => 'icon',
                        'x_position' => $data['x_position'] ?? 200,
                        'y_position' => $data['y_position'] ?? 200,
                        'width' => $iconSize,
                        'height' => $iconSize + 20, // Extra space for label
                        'rotation' => 0,
                        'display_order' => $data['display_order'] ?? 100,
                        'background_color' => $data['background_color'] ?? '#3B82F6',
                        'corner_radius' => $data['corner_radius'] ?? 8,
                        'metadata' => [
                            'icon_key' => $data['icon_key'],
                            'icon_color' => $data['icon_color'] ?? '#FFFFFF',
                            'icon_size' => $iconSize,
                        ],
                    ]);

                    $this->reloadSections();

                    $this->dispatch('section-added', section: $section->toArray());

                    Notification::make()
                        ->success()
                        ->title('Icon added successfully')
                        ->send();
                }),

            Actions\Action::make('manageRows')
                ->label('Manage Rows')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->modalWidth('3xl')
                ->modalHeading('Manage Section Rows')
                ->form([
                    Forms\Components\Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => $this->seatingLayout->sections()
                            ->where('section_type', 'standard')
                            ->orderBy('display_order')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('rows_info')
                        ->label('Existing Rows')
                        ->content(function ($get) {
                            $sectionId = $get('section_id');
                            if (!$sectionId) return 'Select a section to see its rows';

                            $section = SeatingSection::with('rows.seats')->find($sectionId);
                            if (!$section) return 'Section not found';

                            if ($section->rows->isEmpty()) {
                                return 'No rows in this section yet';
                            }

                            $rows = $section->rows->map(function ($row) {
                                return "Row {$row->label}: {$row->seats->count()} seats";
                            })->join(', ');

                            return $rows;
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('new_row_label')
                        ->label('New Row Label')
                        ->helperText('Add a new row to this section')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('new_row_seats')
                        ->label('Number of Seats')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(10)
                        ->columnSpan(1),
                ])
                ->action(function (array $data): void {
                    if (empty($data['section_id']) || empty($data['new_row_label'])) {
                        Notification::make()
                            ->warning()
                            ->title('Missing information')
                            ->body('Please select a section and enter a row label')
                            ->send();
                        return;
                    }

                    $section = SeatingSection::find($data['section_id']);
                    if (!$section) return;

                    // Calculate Y position based on existing rows
                    $lastRow = $section->rows()->orderByDesc('y')->first();
                    $newY = $lastRow ? $lastRow->y + 40 : 0;

                    $row = SeatingRow::create([
                        'section_id' => $section->id,
                        'label' => $data['new_row_label'],
                        'y' => $newY,
                        'rotation' => 0,
                        'seat_count' => 0,
                    ]);

                    // Create seats for the new row
                    $numSeats = (int) ($data['new_row_seats'] ?? 10);
                    $seatSpacing = 30;

                    for ($s = 1; $s <= $numSeats; $s++) {
                        SeatingSeat::create([
                            'row_id' => $row->id,
                            'label' => (string) $s,
                            'display_name' => $section->generateSeatDisplayName($data['new_row_label'], (string) $s),
                            'x' => ($s - 1) * $seatSpacing,
                            'y' => 0,
                            'angle' => 0,
                            'shape' => 'circle',
                            'seat_uid' => $section->generateSeatUid($data['new_row_label'], (string) $s),
                        ]);
                    }

                    $row->update(['seat_count' => $numSeats]);

                    $this->reloadSections();
                    $this->dispatch('layout-updated', sections: $this->sections);

                    Notification::make()
                        ->success()
                        ->title('Row added')
                        ->body("Added row '{$data['new_row_label']}' with {$numSeats} seats")
                        ->send();
                }),

            Actions\Action::make('editSection')
                ->label('Edit Section')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->modalHeading('Edit Section')
                ->modalWidth('lg')
                ->fillForm(function () {
                    // Pre-select the selected section from canvas if available
                    if ($this->selectedSection) {
                        $section = SeatingSection::find($this->selectedSection);
                        if ($section && $section->layout_id === $this->seatingLayout->id) {
                            $metadata = $section->metadata ?? [];
                            return [
                                'section_id' => $section->id,
                                'name' => $section->name,
                                'section_code' => $section->section_code,
                                'color_hex' => $section->color_hex,
                                'seat_color' => $section->seat_color,
                                'width' => $section->width,
                                'height' => $section->height,
                                'seat_size' => $metadata['seat_size'] ?? 10,
                                'seat_spacing' => $metadata['seat_spacing'] ?? 18,
                                'row_spacing' => $metadata['row_spacing'] ?? 25,
                                'seat_shape' => $metadata['seat_shape'] ?? 'circle',
                            ];
                        }
                    }
                    return [];
                })
                ->form([
                    Forms\Components\Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => $this->seatingLayout->sections()
                            ->orderBy('display_order')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if ($state) {
                                $section = SeatingSection::find($state);
                                if ($section) {
                                    $metadata = $section->metadata ?? [];
                                    $set('name', $section->name);
                                    $set('section_code', $section->section_code);
                                    $set('color_hex', $section->color_hex);
                                    $set('seat_color', $section->seat_color);
                                    $set('width', $section->width);
                                    $set('height', $section->height);
                                    $set('seat_size', $metadata['seat_size'] ?? 10);
                                    $set('seat_spacing', $metadata['seat_spacing'] ?? 18);
                                    $set('row_spacing', $metadata['row_spacing'] ?? 25);
                                    $set('seat_shape', $metadata['seat_shape'] ?? 'circle');
                                }
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Section Name')
                        ->required()
                        ->maxLength(100)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('section_code')
                        ->label('Section Code')
                        ->required()
                        ->maxLength(20)
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('color_hex')
                        ->label('Background Color')
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('seat_color')
                        ->label('Seat Color')
                        ->columnSpan(1),

                    SC\Section::make('Dimensions')
                        ->schema([
                            Forms\Components\TextInput::make('width')
                                ->label('Width (px)')
                                ->numeric()
                                ->minValue(50)
                                ->maxValue(2000)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('height')
                                ->label('Height (px)')
                                ->numeric()
                                ->minValue(50)
                                ->maxValue(2000)
                                ->columnSpan(1),
                        ])
                        ->columns(2),

                    SC\Section::make('Seat Settings')
                        ->description('Fine-tune the seat appearance and spacing')
                        ->schema([
                            Forms\Components\TextInput::make('seat_size')
                                ->label('Seat Size (px)')
                                ->numeric()
                                ->minValue(4)
                                ->maxValue(30)
                                ->helperText('Size of each seat in pixels')
                                ->columnSpan(1),

                            Forms\Components\Select::make('seat_shape')
                                ->label('Seat Shape')
                                ->options([
                                    'circle' => 'Circle',
                                    'rect' => 'Square',
                                ])
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('seat_spacing')
                                ->label('Seat Spacing (px)')
                                ->numeric()
                                ->minValue(5)
                                ->maxValue(50)
                                ->helperText('Horizontal distance between seats')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('row_spacing')
                                ->label('Row Spacing (px)')
                                ->numeric()
                                ->minValue(10)
                                ->maxValue(100)
                                ->helperText('Vertical distance between rows')
                                ->columnSpan(1),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data): void {
                    $section = SeatingSection::find($data['section_id']);
                    if (!$section) return;

                    // Get old metadata for comparison
                    $oldMetadata = $section->metadata ?? [];
                    $oldSeatSpacing = $oldMetadata['seat_spacing'] ?? 18;
                    $oldRowSpacing = $oldMetadata['row_spacing'] ?? 25;

                    // Update basic fields
                    $updates = [
                        'name' => $data['name'],
                        'section_code' => $data['section_code'],
                    ];

                    if (!empty($data['color_hex'])) {
                        $updates['color_hex'] = $data['color_hex'];
                    }
                    if (!empty($data['seat_color'])) {
                        $updates['seat_color'] = $data['seat_color'];
                    }
                    if (!empty($data['width'])) {
                        $updates['width'] = (int) $data['width'];
                    }
                    if (!empty($data['height'])) {
                        $updates['height'] = (int) $data['height'];
                    }

                    // Update metadata with seat settings
                    $metadata = $section->metadata ?? [];
                    $newSeatSpacing = (int) ($data['seat_spacing'] ?? 18);
                    $newRowSpacing = (int) ($data['row_spacing'] ?? 25);
                    $metadata['seat_size'] = (int) ($data['seat_size'] ?? 10);
                    $metadata['seat_spacing'] = $newSeatSpacing;
                    $metadata['row_spacing'] = $newRowSpacing;
                    $metadata['seat_shape'] = $data['seat_shape'] ?? 'circle';
                    $updates['metadata'] = $metadata;

                    $section->update($updates);

                    // Recalculate seat positions if spacing changed
                    if ($newSeatSpacing !== $oldSeatSpacing || $newRowSpacing !== $oldRowSpacing) {
                        $this->recalculateSeatPositions($section, $newSeatSpacing, $newRowSpacing);
                    }

                    $this->reloadSections();
                    $this->dispatch('layout-updated', sections: $this->sections);

                    Notification::make()
                        ->success()
                        ->title('Section updated')
                        ->body("Section '{$data['name']}' has been updated")
                        ->send();
                }),

            Actions\Action::make('backToEdit')
                ->label('Layout Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->seatingLayout])),
        ];
    }

    /**
     * Bulk generate seats for a section.
     */
    protected function bulkGenerateSeats(array $data): void
    {
        $section = SeatingSection::find($data['section_id']);

        if (! $section) {
            return;
        }

        $rowPrefix  = $data['row_prefix']  ?? '';
        $seatPrefix = $data['seat_prefix'] ?? '';
        $rowSpacing = $data['row_spacing'] ?? 40;
        $seatSpacing = $data['seat_spacing'] ?? 30;
        $numRows = (int) $data['num_rows'];

        // Parse variable seats configuration
        $seatsPerRowArray = [];
        if (!empty($data['use_variable_seats']) && !empty($data['seats_config'])) {
            $seatsPerRowArray = array_map('trim', explode(',', $data['seats_config']));
            $seatsPerRowArray = array_map('intval', $seatsPerRowArray);

            if (count($seatsPerRowArray) !== $numRows) {
                Notification::make()
                    ->danger()
                    ->title('Configuration Error')
                    ->body("Seats configuration must have exactly {$numRows} values (one per row)")
                    ->send();
                return;
            }
        } else {
            $defaultSeats = (int) ($data['seats_per_row'] ?? 10);
            $seatsPerRowArray = array_fill(0, $numRows, $defaultSeats);
        }

        // Parse alignment configuration
        $alignmentArray = [];
        if (!empty($data['use_variable_seats']) && !empty($data['alignment_config'])) {
            $alignmentArray = array_map('trim', explode(',', $data['alignment_config']));

            if (count($alignmentArray) !== $numRows) {
                Notification::make()
                    ->danger()
                    ->title('Configuration Error')
                    ->body("Alignment configuration must have exactly {$numRows} values (one per row)")
                    ->send();
                return;
            }

            $validAlignments = ['left', 'center', 'right'];
            foreach ($alignmentArray as $alignment) {
                if (!in_array($alignment, $validAlignments)) {
                    Notification::make()
                        ->danger()
                        ->title('Configuration Error')
                        ->body("Invalid alignment '{$alignment}'. Valid options: left, center, right")
                        ->send();
                    return;
                }
            }
        } else {
            $defaultAlignment = $data['seat_alignment'] ?? 'center';
            $alignmentArray = array_fill(0, $numRows, $defaultAlignment);
        }

        // Row grouping configuration
        $useGrouping = !empty($data['use_row_grouping']);
        $groupSize = $useGrouping ? (int) ($data['group_size'] ?? 3) : 0;
        $aisleSpacing = $useGrouping ? (int) ($data['aisle_spacing'] ?? 80) : 0;

        // Calculate max row width for alignment purposes
        $maxSeats = max($seatsPerRowArray);
        $maxRowWidth = ($maxSeats - 1) * $seatSpacing;

        $currentY = 0;

        for ($r = 1; $r <= $numRows; $r++) {
            $rowLabel = $rowPrefix . $r;
            $seatsInThisRow = $seatsPerRowArray[$r - 1];
            $rowAlignment = $alignmentArray[$r - 1];

            $row = SeatingRow::create([
                'section_id' => $section->id,
                'label'      => $rowLabel,
                'y'          => $currentY,
                'rotation'   => 0,
            ]);

            // Calculate row width and starting X position based on alignment
            $rowWidth = ($seatsInThisRow - 1) * $seatSpacing;
            $startX = 0;

            switch ($rowAlignment) {
                case 'center':
                    $startX = ($maxRowWidth - $rowWidth) / 2;
                    break;
                case 'right':
                    $startX = $maxRowWidth - $rowWidth;
                    break;
                case 'left':
                default:
                    $startX = 0;
                    break;
            }

            for ($s = 1; $s <= $seatsInThisRow; $s++) {
                $seatLabel = $seatPrefix . $s;

                SeatingSeat::create([
                    'row_id'   => $row->id,
                    'label'    => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                    'x'        => $startX + (($s - 1) * $seatSpacing),
                    'y'        => 0,
                    'angle'    => 0,
                    'shape'    => 'circle',
                    'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                ]);
            }

            $row->update(['seat_count' => $seatsInThisRow]);

            $currentY += $rowSpacing;

            if ($useGrouping && $r < $numRows && $r % $groupSize === 0) {
                $currentY += $aisleSpacing;
            }
        }

        // Dispatch update to canvas
        $this->dispatch('layout-updated', sections: $this->sections);
    }

    /**
     * Create rows and seats for a new section
     */
    protected function createSectionSeats(
        SeatingSection $section,
        int $numRows,
        int $seatsPerRow,
        int $seatSize,
        int $rowSpacing,
        int $seatSpacing,
        string $seatShape,
        string $seatNumbering,
        string $rowNumbering
    ): void {
        $padding = 10; // Offset from section edge

        for ($r = 1; $r <= $numRows; $r++) {
            // Determine row label based on numbering direction
            $rowIndex = $rowNumbering === 'btt' ? ($numRows - $r + 1) : $r;
            $rowLabel = (string) $rowIndex;

            // Calculate Y position for this row
            $rowY = $padding + (($r - 1) * $rowSpacing);

            $row = SeatingRow::create([
                'section_id' => $section->id,
                'label' => $rowLabel,
                'y' => $rowY,
                'rotation' => 0,
                'seat_count' => $seatsPerRow,
            ]);

            for ($s = 1; $s <= $seatsPerRow; $s++) {
                // Determine seat label based on numbering direction
                $seatIndex = $seatNumbering === 'rtl' ? ($seatsPerRow - $s + 1) : $s;
                $seatLabel = (string) $seatIndex;

                // Calculate X position for this seat
                $seatX = $padding + (($s - 1) * $seatSpacing);

                SeatingSeat::create([
                    'row_id' => $row->id,
                    'label' => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                    'x' => $seatX,
                    'y' => $rowY,
                    'angle' => 0,
                    'shape' => $seatShape,
                    'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                ]);
            }
        }
    }

    /**
     * Reload sections for the current layout
     */
    protected function reloadSections(): void
    {
        $this->sections = $this->seatingLayout->sections()
            ->with(['rows.seats'])
            ->orderBy('display_order')
            ->get()
            ->toArray();
    }

    /**
     * Update section position and dimensions (called from Konva.js)
     */
    public function updateSection($sectionId, $updates): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        if ($section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section does not belong to this layout')
                ->send();
            return;
        }

        // Filter to only allowed fields
        $allowedFields = ['x_position', 'y_position', 'width', 'height', 'rotation'];
        $filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));

        if (empty($filteredUpdates)) {
            return;
        }

        // Force save with explicit field assignment
        foreach ($filteredUpdates as $field => $value) {
            $section->{$field} = (int) $value;
        }
        $section->save();

        $this->reloadSections();

        Notification::make()
            ->success()
            ->title('Section updated')
            ->body('Position and dimensions saved')
            ->send();
    }

    /**
     * Move section by offset (for arrow key movement)
     */
    public function moveSection($sectionId, $deltaX, $deltaY): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $section->x_position = max(0, $section->x_position + (int) $deltaX);
        $section->y_position = max(0, $section->y_position + (int) $deltaY);
        $section->save();

        $this->reloadSections();
        $this->dispatch('section-moved', sectionId: $sectionId, x: $section->x_position, y: $section->y_position);
    }

    /**
     * Update section colors (called from frontend)
     */
    public function updateSectionColors($sectionId, $colorHex, $seatColor): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        $section->update([
            'color_hex' => $colorHex,
            'seat_color' => $seatColor,
        ]);

        $this->reloadSections();

        Notification::make()
            ->success()
            ->title('Section colors updated')
            ->send();
    }

    /**
     * Delete section (called from Konva.js)
     */
    public function deleteSection($sectionId): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        if ($section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section does not belong to this layout')
                ->send();
            return;
        }

        $sectionName = $section->name;
        $section->delete();
        $this->reloadSections();

        $this->dispatch('section-deleted', sectionId: $sectionId);

        Notification::make()
            ->success()
            ->title('Section deleted')
            ->body("Section '{$sectionName}' has been deleted")
            ->send();
    }

    /**
     * Delete a row from a section
     */
    public function deleteRow($rowId): void
    {
        $row = SeatingRow::find($rowId);

        if (!$row) {
            Notification::make()
                ->danger()
                ->title('Row not found')
                ->send();
            return;
        }

        $section = $row->section;
        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Row does not belong to this layout')
                ->send();
            return;
        }

        $rowLabel = $row->label;
        $row->delete();
        $this->reloadSections();

        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row deleted')
            ->body("Row '{$rowLabel}' has been deleted")
            ->send();
    }

    /**
     * Delete a seat
     */
    public function deleteSeat($seatId): void
    {
        $seat = SeatingSeat::find($seatId);

        if (!$seat) {
            Notification::make()
                ->danger()
                ->title('Seat not found')
                ->send();
            return;
        }

        $row = $seat->row;
        $section = $row?->section;

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Seat does not belong to this layout')
                ->send();
            return;
        }

        $seatLabel = $seat->label;
        $seat->delete();

        // Update row seat count
        $row->decrement('seat_count');

        // Renumber remaining seats in the row
        $this->renumberSeatsInRow($row);

        $this->reloadSections();

        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Seat deleted')
            ->body("Seat '{$seatLabel}' has been deleted")
            ->send();
    }

    /**
     * Add seat manually (called from Konva.js)
     */
    public function addSeat(array $data): void
    {
        $section = SeatingSection::find($data['section_id']);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Find or create a row for the seat
        $rowLabel = $data['row_label'] ?? 'Manual';
        $row = SeatingRow::firstOrCreate(
            [
                'section_id' => $section->id,
                'label' => $rowLabel,
            ],
            [
                'y' => 0,
                'rotation' => 0,
                'seat_count' => 0,
            ]
        );

        $seatLabel = $data['label'];

        $seat = SeatingSeat::create([
            'row_id' => $row->id,
            'label' => $seatLabel,
            'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
            'x' => $data['x'],
            'y' => $data['y'],
            'angle' => $data['angle'] ?? 0,
            'shape' => $data['shape'] ?? 'circle',
            'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
        ]);

        $row->increment('seat_count');

        $this->reloadSections();

        $this->dispatch('seat-added', seat: $seat->toArray(), sectionId: $section->id);

        Notification::make()
            ->success()
            ->title('Seat added')
            ->body("Seat '{$seatLabel}' added to section")
            ->send();
    }

    /**
     * Assign selected seats to a section (bulk operation)
     */
    public function assignSeatsToSection(array $seatIds, int $sectionId, string $rowLabel): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Find or create the row
        $row = SeatingRow::firstOrCreate(
            [
                'section_id' => $section->id,
                'label' => $rowLabel,
            ],
            [
                'y' => 0,
                'rotation' => 0,
                'seat_count' => 0,
            ]
        );

        $assignedCount = 0;
        $oldRows = [];

        foreach ($seatIds as $index => $seatId) {
            $seat = SeatingSeat::find($seatId);
            if (!$seat) continue;

            // Track old row for seat count update
            $oldRowId = $seat->row_id;
            if ($oldRowId && $oldRowId !== $row->id) {
                $oldRows[$oldRowId] = true;
            }

            // Get old section for coordinate conversion
            $oldRow = $seat->row;
            $oldSection = $oldRow?->section;

            // Calculate absolute position then convert to relative to new section
            $absoluteX = $seat->x;
            $absoluteY = $seat->y;
            if ($oldSection) {
                $absoluteX += $oldSection->x_position;
                $absoluteY += $oldSection->y_position;
            }

            // Convert to new section's relative coordinates
            $newX = $absoluteX - $section->x_position;
            $newY = $absoluteY - $section->y_position;

            // Determine seat label - try to keep original or use index
            $seatLabel = $seat->label ?: (string) ($index + 1);

            // Update seat's row, coordinates, and regenerate UID
            $seat->update([
                'row_id' => $row->id,
                'x' => $newX,
                'y' => $newY,
                'label' => $seatLabel,
                'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
            ]);

            $assignedCount++;
        }

        // Update seat counts for old rows
        foreach (array_keys($oldRows) as $oldRowId) {
            $oldRow = SeatingRow::find($oldRowId);
            if ($oldRow) {
                $oldRow->update(['seat_count' => $oldRow->seats()->count()]);
            }
        }

        // Update new row seat count
        $row->update(['seat_count' => $row->seats()->count()]);

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Seats assigned')
            ->body("Assigned {$assignedCount} seats to row '{$rowLabel}' in section '{$section->name}'")
            ->send();
    }

    /**
     * Save background image settings
     */
    public function saveBackgroundSettings(float $scale, int $x, int $y, float $opacity): void
    {
        $this->seatingLayout->update([
            'background_scale' => $scale,
            'background_x' => $x,
            'background_y' => $y,
            'background_opacity' => $opacity,
        ]);

        Notification::make()
            ->success()
            ->title('Background settings saved')
            ->send();
    }

    /**
     * Recalculate rows for a section based on seat Y-coordinate proximity
     */
    public function recalculateRows(int $sectionId, float $tolerance = 15.0): void
    {
        $section = SeatingSection::with('rows.seats')->find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Get all seats from all rows
        // Note: seat.y is already relative to section (not to row), so we use it directly
        $allSeats = [];
        foreach ($section->rows as $row) {
            foreach ($row->seats as $seat) {
                $allSeats[] = [
                    'id' => $seat->id,
                    'x' => $seat->x,
                    'y' => $seat->y,  // Y is relative to section, use directly for grouping
                    'label' => $seat->label,
                    'seat_uid' => $seat->seat_uid,
                ];
            }
        }

        if (empty($allSeats)) {
            Notification::make()
                ->warning()
                ->title('No seats to recalculate')
                ->send();
            return;
        }

        // Debug: log Y values to understand distribution
        $yValues = array_column($allSeats, 'y');
        \Log::info("RecalculateRows: Found " . count($allSeats) . " seats");
        \Log::info("Y range: " . min($yValues) . " to " . max($yValues));

        // Group seats into rows by rounding Y to buckets
        // Seats with Y values within tolerance of each other go in the same row
        $bucketSize = $tolerance;
        $yBuckets = [];

        foreach ($allSeats as $seat) {
            // Round Y to nearest bucket
            $roundedY = round($seat['y'] / $bucketSize) * $bucketSize;
            if (!isset($yBuckets[$roundedY])) {
                $yBuckets[$roundedY] = [];
            }
            $yBuckets[$roundedY][] = $seat;
        }

        // Sort buckets by Y value (top to bottom)
        ksort($yBuckets);

        // Create row groups, sorting each by X (left to right)
        $rowGroups = [];
        foreach ($yBuckets as $y => $seats) {
            usort($seats, fn($a, $b) => $a['x'] <=> $b['x']);
            $rowGroups[] = $seats;
        }

        \Log::info("RecalculateRows: Created " . count($rowGroups) . " rows from " . count($yBuckets) . " Y buckets");

        // Delete existing rows
        $section->rows()->delete();

        // Create new rows and reassign seats
        $rowIndex = 1;
        foreach ($rowGroups as $rowSeats) {
            $avgY = count($rowSeats) > 0
                ? array_sum(array_column($rowSeats, 'y')) / count($rowSeats)
                : 0;

            $row = SeatingRow::create([
                'section_id' => $section->id,
                'label' => (string) $rowIndex,
                'y' => $avgY,
                'rotation' => 0,
                'seat_count' => count($rowSeats),
            ]);

            $seatIndex = 1;
            foreach ($rowSeats as $seatData) {
                SeatingSeat::create([
                    'row_id' => $row->id,
                    'label' => (string) $seatIndex,
                    'display_name' => $section->generateSeatDisplayName((string) $rowIndex, (string) $seatIndex),
                    'x' => $seatData['x'],
                    'y' => $seatData['y'], // Keep original Y coordinate for future recalculations
                    'angle' => 0,
                    'shape' => 'circle',
                    'seat_uid' => $section->generateSeatUid((string) $rowIndex, (string) $seatIndex),
                ]);
                $seatIndex++;
            }

            $rowIndex++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Rows recalculated')
            ->body("Created " . count($rowGroups) . " rows from " . count($allSeats) . " seats")
            ->send();
    }

    /**
     * Align rows within a section (respects section rotation)
     */
    public function alignRows(int $sectionId, array $rowIds, string $alignment): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Get section properties
        $sectionWidth = $section->width ?? 200;
        $sectionHeight = $section->height ?? 150;
        $sectionX = $section->x_position ?? 0;
        $sectionY = $section->y_position ?? 0;
        $rotation = $section->rotation ?? 0;

        // Calculate section center (pivot point for rotation)
        $centerX = $sectionX + $sectionWidth / 2;
        $centerY = $sectionY + $sectionHeight / 2;

        // Convert rotation to radians (negative to reverse rotation for global->local transform)
        $angleRad = -$rotation * M_PI / 180;
        $cosA = cos($angleRad);
        $sinA = sin($angleRad);

        // Positive angle for local->global transform
        $angleRadPos = $rotation * M_PI / 180;
        $cosAPos = cos($angleRadPos);
        $sinAPos = sin($angleRadPos);

        foreach ($rowIds as $rowId) {
            $row = SeatingRow::with('seats')->find($rowId);
            if (!$row || $row->section_id !== $section->id) continue;

            $seats = $row->seats;
            if ($seats->isEmpty()) continue;

            // Transform seat positions from global to section-local coordinates
            $seatsWithLocal = $seats->map(function ($seat) use ($centerX, $centerY, $cosA, $sinA) {
                // Translate to origin (section center)
                $dx = $seat->x - $centerX;
                $dy = $seat->y - $centerY;

                // Reverse rotation to get local coordinates
                $localX = $dx * $cosA - $dy * $sinA;
                $localY = $dx * $sinA + $dy * $cosA;

                return [
                    'seat' => $seat,
                    'localX' => $localX,
                    'localY' => $localY,
                ];
            })->sortBy('localX')->values();

            // Calculate row width in local space
            $minLocalX = $seatsWithLocal->first()['localX'];
            $maxLocalX = $seatsWithLocal->last()['localX'];
            $rowWidth = $maxLocalX - $minLocalX;

            // Calculate offset in local space
            $padding = 10;
            // Local coordinates are relative to center, so section spans from -width/2 to +width/2
            $localLeft = -$sectionWidth / 2 + $padding;
            $localRight = $sectionWidth / 2 - $padding;
            $availableWidth = $localRight - $localLeft;

            $offset = 0;
            switch ($alignment) {
                case 'left':
                    $offset = $localLeft - $minLocalX;
                    break;
                case 'center':
                    $targetStart = $localLeft + ($availableWidth - $rowWidth) / 2;
                    $offset = $targetStart - $minLocalX;
                    break;
                case 'right':
                    $targetStart = $localRight - $rowWidth;
                    $offset = $targetStart - $minLocalX;
                    break;
            }

            // Apply offset in local space and transform back to global coordinates
            foreach ($seatsWithLocal as $seatData) {
                $seat = $seatData['seat'];
                $newLocalX = $seatData['localX'] + $offset;
                $localY = $seatData['localY'];

                // Transform back to global coordinates (apply rotation)
                $newGlobalX = $centerX + $newLocalX * $cosAPos - $localY * $sinAPos;
                $newGlobalY = $centerY + $newLocalX * $sinAPos + $localY * $cosAPos;

                $seat->update([
                    'x' => round($newGlobalX, 2),
                    'y' => round($newGlobalY, 2),
                ]);
            }
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Rows aligned')
            ->body("Aligned " . count($rowIds) . " rows to {$alignment}")
            ->send();
    }

    /**
     * Delete multiple seats at once (bulk operation)
     */
    public function deleteSeats(array $seatIds): void
    {
        $deletedCount = 0;
        $affectedRows = [];

        foreach ($seatIds as $seatId) {
            $seat = SeatingSeat::find($seatId);
            if (!$seat) continue;

            $row = $seat->row;
            $section = $row?->section;

            if (!$section || $section->layout_id !== $this->seatingLayout->id) continue;

            // Track affected rows for seat count update and renumbering
            $affectedRows[$row->id] = $row;

            $seat->delete();
            $deletedCount++;
        }

        // Update seat counts and renumber remaining seats for affected rows
        foreach ($affectedRows as $row) {
            $row->update(['seat_count' => $row->seats()->count()]);
            $this->renumberSeatsInRow($row);
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Seats deleted')
            ->body("Deleted {$deletedCount} seats")
            ->send();
    }

    /**
     * Renumber seats in a row after deletion (orders by X position left-to-right)
     */
    protected function renumberSeatsInRow(SeatingRow $row): void
    {
        // Get remaining seats ordered by X position (left to right)
        $seats = $row->seats()->orderBy('x', 'asc')->get();

        $number = 1;
        foreach ($seats as $seat) {
            $newLabel = (string) $number;

            // Only update if label changed
            if ($seat->label !== $newLabel) {
                $section = $row->section;
                $seat->update([
                    'label' => $newLabel,
                    'display_name' => $section ? $section->generateSeatDisplayName($row->label, $newLabel) : $newLabel,
                    'seat_uid' => $section ? $section->generateSeatUid($row->label, $newLabel) : uniqid(),
                ]);
            }
            $number++;
        }
    }

    /**
     * Recalculate seat positions based on new spacing values
     */
    protected function recalculateSeatPositions(SeatingSection $section, int $seatSpacing, int $rowSpacing): void
    {
        $padding = 10; // Same padding used when creating seats

        // Load rows ordered by Y position (or label)
        $rows = $section->rows()->orderBy('y', 'asc')->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Find max seats per row to calculate section width
        $maxSeatsPerRow = 0;
        $rowIndex = 0;

        foreach ($rows as $row) {
            // Calculate new row Y position
            $newRowY = $padding + ($rowIndex * $rowSpacing);
            $row->update(['y' => $newRowY]);

            // Get seats for this row ordered by X position
            $seats = $row->seats()->orderBy('x', 'asc')->get();

            $seatsCount = $seats->count();
            if ($seatsCount > $maxSeatsPerRow) {
                $maxSeatsPerRow = $seatsCount;
            }

            $seatIndex = 0;
            foreach ($seats as $seat) {
                // Calculate new seat X and Y position
                $newSeatX = $padding + ($seatIndex * $seatSpacing);
                $newSeatY = $newRowY; // Y is same as row Y

                $seat->update([
                    'x' => $newSeatX,
                    'y' => $newSeatY,
                ]);

                $seatIndex++;
            }

            $rowIndex++;
        }

        // Update section dimensions to fit the new layout
        $numRows = $rows->count();
        $newWidth = ($maxSeatsPerRow * $seatSpacing) + $padding;
        $newHeight = ($numRows * $rowSpacing) + $padding;

        $section->update([
            'width' => $newWidth,
            'height' => $newHeight,
        ]);
    }

    /**
     * Data sent to view
     */
    protected function getViewData(): array
    {
        // Check for background image: use uploaded file first, then external URL
        $backgroundUrl = null;
        if ($this->seatingLayout->background_image_path) {
            $backgroundUrl = \Storage::disk('public')->url($this->seatingLayout->background_image_path);
        } elseif ($this->seatingLayout->background_image_url) {
            $backgroundUrl = $this->seatingLayout->background_image_url;
        }

        return [
            'layout'       => $this->seatingLayout,
            'sections'     => $this->sections,
            'canvasWidth'  => $this->seatingLayout->canvas_width,
            'canvasHeight' => $this->seatingLayout->canvas_height,
            'backgroundUrl' => $backgroundUrl,
            'backgroundScale' => $this->seatingLayout->background_scale ?? 1,
            'backgroundX' => $this->seatingLayout->background_x ?? 0,
            'backgroundY' => $this->seatingLayout->background_y ?? 0,
            'backgroundOpacity' => $this->seatingLayout->background_opacity ?? 0.3,
            'iconDefinitions' => config('seating-icons', []),
        ];
    }
}
