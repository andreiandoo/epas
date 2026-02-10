<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
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
        $this->seatingLayout = $record->load('venue');
        $this->reloadSections();
    }

    public function getSubheading(): ?string
    {
        $parts = [$this->seatingLayout->name];
        if ($this->seatingLayout->venue) {
            $parts[] = $this->seatingLayout->venue->getTranslation('name');
        }
        return implode(' — ', $parts);
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
                        ->helperText('Unique identifier per map (e.g., A, B, VIP)')
                        ->rules([
                            fn () => function (string $attribute, $value, $fail) {
                                if (SeatingSection::where('layout_id', $this->seatingLayout->id)
                                    ->where('section_code', $value)
                                    ->exists()) {
                                    $fail('This section code already exists in this layout.');
                                }
                            },
                        ])
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

                            Forms\Components\Select::make('numbering_mode')
                                ->label('Seat Numbering Mode')
                                ->options([
                                    'normal' => 'Normal (per row: 1-10, 1-10, 1-10...)',
                                    'section' => 'Section (continuous: 1-10, 11-20, 21-30...)',
                                    'snake' => 'Snake (alternating: 1-10→, ←11-20, 21-30→...)',
                                ])
                                ->default('normal')
                                ->helperText('How seat numbers are assigned across rows')
                                ->columnSpanFull(),
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
                        'numbering_mode' => $data['numbering_mode'] ?? 'normal',
                    ]);

                    // Remove form-only fields before creating
                    $createData = collect($data)->except([
                        'num_rows', 'seats_per_row', 'seat_shape', 'seat_size',
                        'row_spacing', 'seat_spacing', 'seat_numbering', 'row_numbering', 'numbering_mode'
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
                            $data['row_numbering'] ?? 'ttb',
                            $data['numbering_mode'] ?? 'normal'
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

            // Note: manageRows, editSection, and manageSeatStatus actions removed
            // These functions are now handled directly in the canvas UI

            Actions\Action::make('backToEdit')
                ->label('Layout Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->seatingLayout])),
        ];
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

        // Get numbering mode from section metadata
        $sectionMetadata = $section->metadata ?? [];
        $numberingMode = $sectionMetadata['numbering_mode'] ?? 'normal';

        $currentY = 0;
        $continuousSeatNumber = 1; // For section/snake numbering modes

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

            // Determine if this row should be reversed (for snake mode)
            $isReversedRow = ($numberingMode === 'snake' && $r % 2 === 0);

            for ($s = 1; $s <= $seatsInThisRow; $s++) {
                // Calculate seat label based on numbering mode
                switch ($numberingMode) {
                    case 'section':
                        // Continuous numbering across all rows
                        $seatLabel = $seatPrefix . $continuousSeatNumber;
                        $continuousSeatNumber++;
                        break;

                    case 'snake':
                        // Alternating direction - odd rows L→R, even rows R→L
                        $seatLabel = $seatPrefix . $continuousSeatNumber;
                        $continuousSeatNumber++;
                        break;

                    case 'normal':
                    default:
                        // Standard per-row numbering
                        $seatLabel = $seatPrefix . $s;
                        break;
                }

                // Calculate X position (reversed for snake mode on even rows)
                $seatX = $isReversedRow
                    ? $startX + (($seatsInThisRow - $s) * $seatSpacing)
                    : $startX + (($s - 1) * $seatSpacing);

                SeatingSeat::create([
                    'row_id'   => $row->id,
                    'label'    => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                    'x'        => $seatX,
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
        string $rowNumbering,
        string $numberingMode = 'normal'
    ): void {
        $padding = 10; // Offset from section edge
        $continuousSeatNumber = 1; // For section/snake numbering modes

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

            // Determine if this row should be reversed (for snake mode)
            $isReversedRow = ($numberingMode === 'snake' && $r % 2 === 0);

            for ($s = 1; $s <= $seatsPerRow; $s++) {
                // Calculate seat label based on numbering mode
                switch ($numberingMode) {
                    case 'section':
                        // Continuous numbering across all rows
                        $seatLabel = (string) $continuousSeatNumber;
                        $continuousSeatNumber++;
                        break;

                    case 'snake':
                        // Alternating direction - odd rows L→R, even rows R→L
                        $seatLabel = (string) $continuousSeatNumber;
                        $continuousSeatNumber++;
                        break;

                    case 'normal':
                    default:
                        // Standard per-row numbering with direction
                        $seatIndex = $seatNumbering === 'rtl' ? ($seatsPerRow - $s + 1) : $s;
                        $seatLabel = (string) $seatIndex;
                        break;
                }

                // Calculate X position for this seat (reversed for snake mode on even rows)
                $seatX = $isReversedRow
                    ? $padding + (($seatsPerRow - $s) * $seatSpacing)
                    : $padding + (($s - 1) * $seatSpacing);

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
     * Create a new section from canvas drawing (called from Konva.js)
     */
    public function createSection(array $data): void
    {
        // Map field names from JS to PHP
        $mappedData = [
            'name' => $data['name'] ?? 'New Section',
            'x' => $data['x_position'] ?? $data['x'] ?? 100,
            'y' => $data['y_position'] ?? $data['y'] ?? 100,
            'width' => $data['width'] ?? 200,
            'height' => $data['height'] ?? 150,
            'color' => $data['color_hex'] ?? $data['color'] ?? '#3B82F6',
        ];

        // Handle polygon sections
        if (($data['section_type'] ?? 'standard') === 'polygon' && isset($data['polygon_points'])) {
            $this->addDrawnShape('polygon', [
                'x' => $mappedData['x'],
                'y' => $mappedData['y'],
                'width' => $mappedData['width'],
                'height' => $mappedData['height'],
                'metadata' => ['points' => $data['polygon_points']],
            ], $mappedData['color'], 0.3, ['label' => $mappedData['name']]);
            return;
        }

        // Standard rectangle section
        $this->addRectSection($mappedData);
    }

    /**
     * Update section position (called from Konva.js drag end)
     */
    public function updateSectionPosition(int $sectionId, int $x, int $y): void
    {
        $this->updateSection($sectionId, [
            'x_position' => $x,
            'y_position' => $y,
        ]);
    }

    /**
     * Update section transform (position, size, rotation) from Konva.js
     */
    public function updateSectionTransform(int $sectionId, array $data): void
    {
        $this->updateSection($sectionId, [
            'x_position' => $data['x_position'] ?? null,
            'y_position' => $data['y_position'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'rotation' => $data['rotation'] ?? null,
        ]);
    }

    /**
     * Add seats to a section (called from Konva.js row drawing)
     */
    public function addSeatsToSection(int $sectionId, array $seats): void
    {
        if (empty($seats)) {
            return;
        }

        $this->addRowWithSeats($sectionId, $seats, [
            'numberingMode' => 'alpha',
            'startNumber' => 1,
        ]);
    }

    /**
     * Add a drawn shape (polygon, circle, text, line) as a decorative section
     */
    public function addDrawnShape(string $type, array $geometry, string $color, float $opacity, array $extra = []): void
    {
        $metadata = array_merge($geometry['metadata'] ?? [], [
            'shape' => $type,
            'opacity' => $opacity,
        ]);

        // Add extra properties (text content, font, stroke width, etc.)
        if (!empty($extra)) {
            $metadata = array_merge($metadata, $extra);
        }

        $section = SeatingSection::create([
            'layout_id' => $this->seatingLayout->id,
            'tenant_id' => $this->seatingLayout->tenant_id,
            'name' => $extra['label'] ?? ucfirst($type),
            'section_code' => strtoupper(substr($type, 0, 3)) . '_' . time(),
            'section_type' => 'decorative',
            'x_position' => (int) ($geometry['x_position'] ?? 100),
            'y_position' => (int) ($geometry['y_position'] ?? 100),
            'width' => (int) ($geometry['width'] ?? 100),
            'height' => (int) ($geometry['height'] ?? 100),
            'rotation' => 0,
            'background_color' => $color,
            'metadata' => $metadata,
            'display_order' => 0,
        ]);

        $this->reloadSections();
        $this->dispatch('section-added', section: $section->toArray());

        Notification::make()
            ->success()
            ->title(ucfirst($type) . ' added')
            ->send();
    }

    /**
     * Add a rectangle section directly from canvas drawing
     */
    public function addRectSection(array $data): void
    {
        // Generate section code from name or timestamp
        $sectionCode = strtoupper(substr(str_replace(' ', '', $data['name'] ?? 'SECT'), 0, 5)) . '_' . time();

        $section = SeatingSection::create([
            'layout_id' => $this->seatingLayout->id,
            'tenant_id' => $this->seatingLayout->tenant_id,
            'name' => $data['name'] ?? 'New Section',
            'section_code' => $sectionCode,
            'section_type' => 'standard',
            'x_position' => (int) ($data['x'] ?? 100),
            'y_position' => (int) ($data['y'] ?? 100),
            'width' => (int) ($data['width'] ?? 200),
            'height' => (int) ($data['height'] ?? 150),
            'rotation' => 0,
            'color_hex' => $data['color'] ?? '#3B82F6',
            'seat_color' => '#22C55E',
            'display_order' => $this->seatingLayout->sections()->count(),
            'metadata' => [
                'seat_size' => 15,
                'seat_spacing' => 20,
                'row_spacing' => 20,
                'seat_shape' => 'circle',
            ],
        ]);

        // Update local sections array without full re-render
        $this->sections[] = $section->toArray();

        // Dispatch event for JavaScript to handle
        $this->dispatch('section-added', section: $section->toArray());

        Notification::make()
            ->success()
            ->title('Section created')
            ->body("Section '{$section->name}' has been added")
            ->send();

        // Skip render to prevent Livewire from re-rendering the component
        // This avoids Alpine scope issues with the @foreach loops
        $this->skipRender();
    }

    /**
     * Add a single row with seats to an existing section
     */
    public function addRowWithSeats(int $sectionId, array $seats, array $settings = []): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Get row numbering settings
        $numberingMode = $settings['numberingMode'] ?? 'alpha';
        $startNumber = $settings['startNumber'] ?? 1;
        $existingRowCount = $section->rows()->count();
        $customLabel = $settings['customLabel'] ?? null;

        // Generate row label
        if (!empty($customLabel)) {
            $rowLabel = $customLabel;
        } elseif ($numberingMode === 'alpha') {
            $rowLabel = chr(ord('A') + $existingRowCount);
        } elseif ($numberingMode === 'roman') {
            $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
                       'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX'];
            $rowLabel = $romans[$existingRowCount] ?? (string) ($existingRowCount + 1);
        } else {
            $rowLabel = (string) ($startNumber + $existingRowCount);
        }

        // Calculate row Y position (average of seat Y positions)
        $avgY = count($seats) > 0 ? array_sum(array_column($seats, 'y')) / count($seats) : 0;

        $row = SeatingRow::create([
            'section_id' => $section->id,
            'label' => $rowLabel,
            'y' => (int) $avgY,
            'rotation' => 0,
            'seat_count' => count($seats),
        ]);

        // Create seats
        $seatNumberingType = $settings['seatNumberingType'] ?? 'numeric';
        $seatNumberingDirection = $settings['seatNumberingDirection'] ?? 'ltr';

        // Sort seats by X position
        usort($seats, fn($a, $b) => $a['x'] <=> $b['x']);

        if ($seatNumberingDirection === 'rtl') {
            $seats = array_reverse($seats);
        }

        foreach ($seats as $index => $seatData) {
            $seatIndex = $index + 1;
            if ($seatNumberingType === 'alpha') {
                $seatLabel = chr(ord('A') + $index);
            } else {
                $seatLabel = (string) $seatIndex;
            }

            SeatingSeat::create([
                'row_id' => $row->id,
                'label' => $seatLabel,
                'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                'x' => (int) $seatData['x'],
                'y' => (int) $seatData['y'],
                'angle' => 0,
                'shape' => $seatData['shape'] ?? 'circle',
                'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
            ]);
        }

        // Reload sections and update state
        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row added')
            ->body("Row '{$rowLabel}' with " . count($seats) . " seats added to '{$section->name}'")
            ->send();

        // Skip render to prevent Livewire from re-rendering
        $this->skipRender();
    }

    /**
     * Add a table with seats arranged around it
     */
    public function addTableWithSeats(int $sectionId, array $tableData): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        $type = $tableData['type'] ?? 'round';
        $seats = $tableData['seats'] ?? [];
        $seatSize = $tableData['seatSize'] ?? 15;

        if (empty($seats)) {
            Notification::make()
                ->warning()
                ->title('No seats specified')
                ->send();
            return;
        }

        // Get row numbering settings
        $existingRowCount = $section->rows()->count();
        // Use custom table name if provided, otherwise generate T1, T2, etc.
        $rowLabel = !empty($tableData['name']) ? $tableData['name'] : ('T' . ($existingRowCount + 1));

        // Convert absolute coordinates to section-relative
        $sectionX = (int) $section->x_position;
        $sectionY = (int) $section->y_position;

        // Calculate row Y position (average of seat Y positions)
        $avgY = count($seats) > 0 ? array_sum(array_column($seats, 'y')) / count($seats) : 0;

        // Store table metadata
        $metadata = [
            'is_table' => true,
            'table_type' => $type,
            'center_x' => $tableData['centerX'] ?? 0,
            'center_y' => $tableData['centerY'] ?? 0,
        ];

        if ($type === 'round') {
            $metadata['radius'] = $tableData['radius'] ?? 30;
        } else {
            $metadata['width'] = $tableData['width'] ?? 80;
            $metadata['height'] = $tableData['height'] ?? 30;
        }

        $row = SeatingRow::create([
            'section_id' => $section->id,
            'label' => $rowLabel,
            'y' => (int) $avgY,
            'rotation' => 0,
            'seat_count' => count($seats),
            'metadata' => $metadata,
        ]);

        // Create seats with relative coordinates
        foreach ($seats as $index => $seatData) {
            $seatLabel = (string) ($index + 1);

            SeatingSeat::create([
                'row_id' => $row->id,
                'label' => $seatLabel,
                'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                'x' => (int) $seatData['x'] - $sectionX,
                'y' => (int) $seatData['y'] - $sectionY,
                'angle' => 0,
                'shape' => $seatData['shape'] ?? 'circle',
                'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
            ]);
        }

        // Reload sections and update state
        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        $tableTypeLabel = $type === 'round' ? 'rotundă' : 'dreptunghiulară';
        Notification::make()
            ->success()
            ->title('Masă adăugată')
            ->body("Masă {$tableTypeLabel} cu " . count($seats) . " locuri adăugată în '{$section->name}'")
            ->send();

        // Skip render to prevent Livewire from re-rendering
        $this->skipRender();
    }

    /**
     * Add multiple rows with seats to an existing section
     */
    public function addMultipleRowsWithSeats(int $sectionId, array $rows, array $settings = []): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Section not found')
                ->send();
            return;
        }

        // Get row numbering settings
        $numberingMode = $settings['numberingMode'] ?? 'alpha';
        $startNumber = $settings['startNumber'] ?? 1;
        $seatNumberingType = $settings['seatNumberingType'] ?? 'numeric';
        $seatNumberingDirection = $settings['seatNumberingDirection'] ?? 'ltr';
        $existingRowCount = $section->rows()->count();

        $totalSeats = 0;
        $rowsCreated = 0;

        // Handle both formats:
        // 1. Array of seat arrays: [[{x,y}, {x,y}], [{x,y}]]
        // 2. Array of row objects: [{y, seats: [{x,y}]}]
        $normalizedRows = [];
        foreach ($rows as $rowData) {
            if (isset($rowData['seats'])) {
                // Object format with y and seats
                $normalizedRows[] = $rowData;
            } else {
                // Array of seats - derive Y from first seat
                $seats = $rowData;
                $rowY = count($seats) > 0 ? ($seats[0]['y'] ?? 0) : 0;
                $normalizedRows[] = ['y' => $rowY, 'seats' => $seats];
            }
        }

        // Sort rows by Y position (top to bottom)
        usort($normalizedRows, fn($a, $b) => $a['y'] <=> $b['y']);

        foreach ($normalizedRows as $rowIndex => $rowData) {
            // Generate row label
            if ($numberingMode === 'alpha') {
                $rowLabel = chr(ord('A') + $existingRowCount + $rowIndex);
            } else {
                $rowLabel = (string) ($startNumber + $existingRowCount + $rowIndex);
            }

            $seats = $rowData['seats'] ?? [];

            $row = SeatingRow::create([
                'section_id' => $section->id,
                'label' => $rowLabel,
                'y' => (int) $rowData['y'],
                'rotation' => 0,
                'seat_count' => count($seats),
            ]);

            // Sort seats by X position
            usort($seats, fn($a, $b) => $a['x'] <=> $b['x']);

            if ($seatNumberingDirection === 'rtl') {
                $seats = array_reverse($seats);
            }

            foreach ($seats as $seatIndex => $seatData) {
                $seatNum = $seatIndex + 1;
                if ($seatNumberingType === 'alpha') {
                    $seatLabel = chr(ord('A') + $seatIndex);
                } else {
                    $seatLabel = (string) $seatNum;
                }

                SeatingSeat::create([
                    'row_id' => $row->id,
                    'label' => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                    'x' => (int) $seatData['x'],
                    'y' => (int) $seatData['y'],
                    'angle' => 0,
                    'shape' => $seatData['shape'] ?? 'circle',
                    'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                ]);

                $totalSeats++;
            }

            $rowsCreated++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Rows added')
            ->body("{$rowsCreated} rows with {$totalSeats} seats added to '{$section->name}'")
            ->send();

        // Skip render to prevent Livewire from re-rendering
        $this->skipRender();
    }

    /**
     * Update section geometry (dimensions, position)
     */
    public function updateSectionGeometry(int $sectionId, array $data): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $updates = [];
        if (isset($data['width'])) $updates['width'] = (int) $data['width'];
        if (isset($data['height'])) $updates['height'] = (int) $data['height'];
        if (isset($data['x'])) $updates['x_position'] = (int) $data['x'];
        if (isset($data['y'])) $updates['y_position'] = (int) $data['y'];
        if (isset($data['rotation'])) $updates['rotation'] = (int) $data['rotation'];

        if (!empty($updates)) {
            $section->update($updates);
        }

        // Update metadata if provided (handle both snake_case and camelCase)
        $cornerRadius = $data['corner_radius'] ?? $data['cornerRadius'] ?? null;
        $scale = $data['scale'] ?? null;

        if ($cornerRadius !== null || $scale !== null) {
            $metadata = $section->metadata ?? [];
            if ($cornerRadius !== null) $metadata['corner_radius'] = (int) $cornerRadius;
            if ($scale !== null) $metadata['scale'] = (float) $scale;
            $section->update(['metadata' => $metadata]);
        }

        // Update local sections array
        foreach ($this->sections as &$s) {
            if ($s['id'] === $sectionId) {
                if (isset($updates['width'])) $s['width'] = $updates['width'];
                if (isset($updates['height'])) $s['height'] = $updates['height'];
                if (isset($updates['x_position'])) $s['x_position'] = $updates['x_position'];
                if (isset($updates['y_position'])) $s['y_position'] = $updates['y_position'];
                if (isset($updates['rotation'])) $s['rotation'] = $updates['rotation'];
                break;
            }
        }
        unset($s);

        $this->skipRender();
    }

    /**
     * Update section name/label
     */
    public function updateSectionName(int $sectionId, string $name): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $section->update(['name' => $name]);

        // Update local sections array
        foreach ($this->sections as &$s) {
            if ($s['id'] === $sectionId) {
                $s['name'] = $name;
                break;
            }
        }
        unset($s);

        $this->skipRender();
    }

    /**
     * Update section metadata (curve, label settings, etc.)
     */
    public function updateSectionMetadata(int $sectionId, array $metadata): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $existingMetadata = $section->metadata ?? [];
        $newMetadata = array_merge($existingMetadata, $metadata);
        $section->update(['metadata' => $newMetadata]);

        // Update local sections array
        foreach ($this->sections as &$s) {
            if ($s['id'] === $sectionId) {
                $s['metadata'] = $newMetadata;
                break;
            }
        }
        unset($s);

        $this->skipRender();
    }

    /**
     * Update section color (individual field)
     */
    public function updateSectionColor(int $sectionId, string $field, string $color): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $allowedFields = ['color_hex', 'seat_color', 'background_color'];
        if (!in_array($field, $allowedFields)) {
            return;
        }

        $section->update([$field => $color]);

        // Update local sections array
        foreach ($this->sections as &$s) {
            if ($s['id'] === $sectionId) {
                $s[$field] = $color;
                break;
            }
        }
        unset($s);

        $this->skipRender();
    }

    /**
     * Restore a previous state (for undo functionality)
     */
    public function restoreState(array $previousSections): void
    {
        // Get current section IDs for this layout
        $currentSectionIds = SeatingSection::where('layout_id', $this->seatingLayout->id)
            ->pluck('id')
            ->toArray();

        $previousSectionIds = array_column($previousSections, 'id');

        // Delete sections that don't exist in previous state
        foreach ($currentSectionIds as $sectionId) {
            if (!in_array($sectionId, $previousSectionIds)) {
                SeatingSection::find($sectionId)?->delete();
            }
        }

        // Restore or create sections from previous state
        foreach ($previousSections as $sectionData) {
            $section = SeatingSection::find($sectionData['id'] ?? 0);

            if (!$section) {
                // Section was deleted, recreate it
                $section = SeatingSection::create([
                    'layout_id' => $this->seatingLayout->id,
                    'tenant_id' => $this->seatingLayout->tenant_id,
                    'name' => $sectionData['name'] ?? 'Restored Section',
                    'section_code' => $sectionData['section_code'] ?? 'RST' . uniqid(),
                    'section_type' => $sectionData['section_type'] ?? 'standard',
                    'x_position' => $sectionData['x_position'] ?? 0,
                    'y_position' => $sectionData['y_position'] ?? 0,
                    'width' => $sectionData['width'] ?? 200,
                    'height' => $sectionData['height'] ?? 150,
                    'color_hex' => $sectionData['color_hex'] ?? '#3B82F6',
                    'seat_color' => $sectionData['seat_color'] ?? '#22C55E',
                ]);
            }

            // Restore rows and seats
            if (isset($sectionData['rows']) && is_array($sectionData['rows'])) {
                $currentRowIds = $section->rows()->pluck('id')->toArray();
                $previousRowIds = array_column($sectionData['rows'], 'id');

                // Delete rows that don't exist in previous state
                foreach ($currentRowIds as $rowId) {
                    if (!in_array($rowId, $previousRowIds)) {
                        SeatingRow::find($rowId)?->delete();
                    }
                }

                foreach ($sectionData['rows'] as $rowData) {
                    $row = SeatingRow::find($rowData['id'] ?? 0);

                    if (!$row) {
                        $row = SeatingRow::create([
                            'section_id' => $section->id,
                            'label' => $rowData['label'] ?? 'R',
                            'y' => $rowData['y'] ?? 0,
                            'rotation' => $rowData['rotation'] ?? 0,
                            'seat_count' => count($rowData['seats'] ?? []),
                        ]);
                    }

                    // Restore seats
                    if (isset($rowData['seats']) && is_array($rowData['seats'])) {
                        $currentSeatIds = $row->seats()->pluck('id')->toArray();
                        $previousSeatIds = array_column($rowData['seats'], 'id');

                        // Delete seats that don't exist in previous state
                        foreach ($currentSeatIds as $seatId) {
                            if (!in_array($seatId, $previousSeatIds)) {
                                SeatingSeat::find($seatId)?->delete();
                            }
                        }

                        foreach ($rowData['seats'] as $seatData) {
                            $seat = SeatingSeat::find($seatData['id'] ?? 0);

                            if (!$seat) {
                                SeatingSeat::create([
                                    'row_id' => $row->id,
                                    'label' => $seatData['label'] ?? '?',
                                    'display_name' => $seatData['display_name'] ?? '',
                                    'x' => $seatData['x'] ?? 0,
                                    'y' => $seatData['y'] ?? 0,
                                    'angle' => $seatData['angle'] ?? 0,
                                    'shape' => $seatData['shape'] ?? 'circle',
                                    'seat_uid' => $seatData['seat_uid'] ?? ($section->section_code . '_' . ($rowData['label'] ?? 'R') . '_' . ($seatData['label'] ?? '?') . '_' . uniqid()),
                                    'status' => $seatData['status'] ?? 'active',
                                ]);
                            } else {
                                $seat->update([
                                    'x' => $seatData['x'] ?? $seat->x,
                                    'y' => $seatData['y'] ?? $seat->y,
                                    'status' => $seatData['status'] ?? $seat->status,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Anulat')
            ->body('Modificarea a fost anulată')
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
     * Update row label (name)
     */
    public function updateRowLabel($rowId, string $newLabel): void
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

        $oldLabel = $row->label;
        $row->update(['label' => $newLabel]);
        $this->reloadSections();

        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row updated')
            ->body("Row name changed from '{$oldLabel}' to '{$newLabel}'")
            ->send();
    }

    /**
     * Update table seats count (regenerate seats around table)
     */
    public function updateTableSeats($rowId, int $newSeatCount): void
    {
        $row = SeatingRow::with(['section', 'seats'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Table not found')
                ->send();
            return;
        }

        $metadata = $row->metadata ?? [];
        if (!($metadata['is_table'] ?? false)) {
            return;
        }

        $section = $row->section;
        $tableType = $metadata['table_type'] ?? 'round';
        $centerX = $metadata['center_x'] ?? 50;
        $centerY = $metadata['center_y'] ?? 50;

        // Delete existing seats
        $row->seats()->delete();

        // Generate new seats based on table type
        $seats = [];
        if ($tableType === 'round') {
            $tableRadius = $metadata['radius'] ?? 25;
            $seatRadius = $tableRadius + 20;
            for ($i = 0; $i < $newSeatCount; $i++) {
                $angle = ($i / $newSeatCount) * M_PI * 2 - M_PI / 2;
                $seats[] = [
                    'x' => $centerX + cos($angle) * $seatRadius,
                    'y' => $centerY + sin($angle) * $seatRadius,
                    'label' => (string) ($i + 1),
                ];
            }
        } else {
            $tableWidth = $metadata['width'] ?? 80;
            $tableHeight = $metadata['height'] ?? 30;
            $seatsPerSide = ceil($newSeatCount / 2);
            $spacing = $tableWidth / ($seatsPerSide + 1);

            // Top seats
            for ($i = 0; $i < $seatsPerSide && count($seats) < $newSeatCount; $i++) {
                $seats[] = [
                    'x' => $centerX - $tableWidth / 2 + ($i + 1) * $spacing,
                    'y' => $centerY - $tableHeight / 2 - 15,
                    'label' => (string) (count($seats) + 1),
                ];
            }
            // Bottom seats
            for ($i = 0; $i < $seatsPerSide && count($seats) < $newSeatCount; $i++) {
                $seats[] = [
                    'x' => $centerX - $tableWidth / 2 + ($i + 1) * $spacing,
                    'y' => $centerY + $tableHeight / 2 + 15,
                    'label' => (string) (count($seats) + 1),
                ];
            }
        }

        // Create new seats
        foreach ($seats as $seatData) {
            SeatingSeat::create([
                'row_id' => $row->id,
                'label' => $seatData['label'],
                'display_name' => $section->generateSeatDisplayName($row->label, $seatData['label']),
                'x' => (int) $seatData['x'],
                'y' => (int) $seatData['y'],
                'angle' => 0,
                'shape' => 'circle',
                'seat_uid' => $section->generateSeatUid($row->label, $seatData['label']),
            ]);
        }

        $row->update(['seat_count' => $newSeatCount]);
        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Locuri actualizate')
            ->body("Masa '{$row->label}' are acum {$newSeatCount} locuri")
            ->send();
    }

    /**
     * Update table dimensions (radius for round, width/height for rect)
     */
    public function updateTableDimensions($rowId, array $dimensions): void
    {
        $row = SeatingRow::with(['section', 'seats'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Table not found')
                ->send();
            return;
        }

        $metadata = $row->metadata ?? [];
        if (!($metadata['is_table'] ?? false)) {
            return;
        }

        // Update metadata with new dimensions
        if (isset($dimensions['radius'])) {
            $metadata['radius'] = $dimensions['radius'];
        }
        if (isset($dimensions['width'])) {
            $metadata['width'] = $dimensions['width'];
        }
        if (isset($dimensions['height'])) {
            $metadata['height'] = $dimensions['height'];
        }

        $row->update(['metadata' => $metadata]);

        // Regenerate seat positions
        $this->updateTableSeats($rowId, $row->seats()->count());
    }

    /**
     * Update row seats count
     */
    public function updateRowSeats($rowId, int $newSeatCount): void
    {
        $row = SeatingRow::with(['section', 'seats'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Row not found')
                ->send();
            return;
        }

        $section = $row->section;
        $currentSeats = $row->seats()->orderBy('x')->get();
        $currentCount = $currentSeats->count();

        if ($newSeatCount === $currentCount) {
            return;
        }

        $isRtl = $row->alignment === 'right';
        $startNumber = $row->seat_start_number ?? 1;
        $metadata = $row->metadata ?? [];
        $numberingMode = $metadata['numbering_mode'] ?? 'numeric';

        if ($newSeatCount > $currentCount) {
            // Add seats to the end (right side for LTR, left side for RTL)
            $lastSeat = $currentSeats->last();
            $spacing = 20;

            // Calculate spacing from existing seats if available
            if ($currentCount >= 2) {
                $spacing = abs($currentSeats->get(1)->x - $currentSeats->get(0)->x);
            }

            $startX = $lastSeat ? ((float) $lastSeat->x + $spacing) : 0;
            $y = $lastSeat ? (float) $lastSeat->y : (float) ($row->y ?? 0);

            for ($i = 0; $i < $newSeatCount - $currentCount; $i++) {
                $seatNum = $currentCount + $i + 1;
                $seatLabel = $this->formatSeatLabel($seatNum, $numberingMode);
                SeatingSeat::create([
                    'row_id' => $row->id,
                    'label' => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($row->label, $seatLabel),
                    'x' => $startX + ($i * $spacing),
                    'y' => $y,
                    'angle' => 0,
                    'shape' => 'circle',
                    'seat_uid' => $section->generateSeatUid($row->label, $seatLabel),
                ]);
            }
        } else {
            // Remove seats from the end (based on X position order)
            $seatsToRemove = $currentSeats->slice($newSeatCount);
            foreach ($seatsToRemove as $seat) {
                $seat->delete();
            }
        }

        $row->update(['seat_count' => $newSeatCount]);

        // Renumber remaining seats to respect the start number and direction
        $remainingSeats = $row->seats()->orderBy('x')->get();
        if ($isRtl) {
            $remainingSeats = $remainingSeats->reverse()->values();
        }

        // First pass: temporary UIDs to avoid conflicts
        $tempSuffix = '_temp_' . time() . '_';
        foreach ($remainingSeats as $seat) {
            $seat->update(['seat_uid' => $tempSuffix . $seat->id]);
        }

        // Second pass: renumber with proper labels
        $seatNum = $startNumber;
        foreach ($remainingSeats as $seat) {
            $seatLabel = $this->formatSeatLabel($seatNum, $numberingMode);
            $seat->update([
                'label' => $seatLabel,
                'display_name' => $section->generateSeatDisplayName($row->label, $seatLabel),
                'seat_uid' => $section->generateSeatUid($row->label, $seatLabel),
            ]);
            $seatNum++;
        }

        // Re-apply curve if it was set
        $curveOffset = $row->curve_offset ?? 0;
        if ($curveOffset != 0) {
            $this->applyCurveToRow($row, $curveOffset);
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Locuri actualizate')
            ->body("Rândul '{$row->label}' are acum {$newSeatCount} locuri")
            ->send();
    }

    /**
     * Apply parabolic curve to a row's seats (internal helper)
     */
    private function applyCurveToRow(SeatingRow $row, float $curveOffset): void
    {
        $seats = $row->seats()->orderBy('x')->get();
        if ($seats->isEmpty()) return;

        $minX = $seats->first()->x;
        $maxX = $seats->last()->x;
        $centerX = ($minX + $maxX) / 2;
        $rowWidth = $maxX - $minX;
        $baseY = $seats->avg('y');

        foreach ($seats as $seat) {
            if ($rowWidth > 0) {
                $normalizedX = ($seat->x - $centerX) / ($rowWidth / 2);
                $curveY = $curveOffset * (1 - ($normalizedX * $normalizedX));
                $newY = $baseY + $curveY;
            } else {
                $newY = $baseY;
            }

            $seat->update(['y' => round($newY, 2)]);
        }
    }

    /**
     * Update row numbering
     */
    public function updateRowNumbering($rowId, array $settings): void
    {
        $row = SeatingRow::with(['section', 'seats'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Row not found')
                ->send();
            return;
        }

        $section = $row->section;
        $startNumber = $settings['startNumber'] ?? 1;
        $direction = $settings['direction'] ?? 'ltr';
        $numberingMode = $settings['numberingMode'] ?? 'numeric';

        // Update row settings including numbering mode in metadata
        $metadata = $row->metadata ?? [];
        $metadata['numbering_mode'] = $numberingMode;
        $row->update([
            'seat_start_number' => $startNumber,
            'alignment' => $direction === 'rtl' ? 'right' : 'left',
            'metadata' => $metadata,
        ]);

        // Get seats ordered by X position
        $seats = $row->seats()->orderBy('x')->get();
        if ($direction === 'rtl') {
            $seats = $seats->reverse()->values();
        }

        // First pass: Set all seat_uids to temporary unique values to avoid conflicts
        $tempSuffix = '_temp_' . time() . '_';
        foreach ($seats as $index => $seat) {
            $seat->update([
                'seat_uid' => $tempSuffix . $seat->id,
            ]);
        }

        // Second pass: Set the actual new labels and seat_uids
        $seatNum = $startNumber;
        foreach ($seats as $seat) {
            $seatLabel = $this->formatSeatLabel($seatNum, $numberingMode);
            $seat->update([
                'label' => $seatLabel,
                'display_name' => $section->generateSeatDisplayName($row->label, $seatLabel),
                'seat_uid' => $section->generateSeatUid($row->label, $seatLabel),
            ]);
            $seatNum++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Numerotare actualizată')
            ->body("Locurile din rândul '{$row->label}' au fost renumerotate")
            ->send();
    }

    /**
     * Format seat label based on numbering mode
     */
    private function formatSeatLabel(int $number, string $mode): string
    {
        return match ($mode) {
            'alpha' => $this->intToAlphaLabel($number),
            'roman' => $this->intToRomanLabel($number),
            default => (string) $number,
        };
    }

    /**
     * Convert integer to alphabetic label (1=A, 2=B, ..., 27=AA, etc.)
     */
    private function intToAlphaLabel(int $number): string
    {
        $result = '';
        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }
        return $result;
    }

    /**
     * Convert integer to Roman numerals
     */
    private function intToRomanLabel(int $number): string
    {
        $map = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        ];
        $result = '';
        foreach ($map as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }
        return $result;
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
    public function saveBackgroundSettings(array $settings): void
    {
        $this->seatingLayout->update([
            'background_scale' => $settings['background_scale'] ?? $this->seatingLayout->background_scale,
            'background_x' => $settings['background_x'] ?? $this->seatingLayout->background_x,
            'background_y' => $settings['background_y'] ?? $this->seatingLayout->background_y,
            'background_opacity' => $settings['background_opacity'] ?? $this->seatingLayout->background_opacity,
        ]);

        Notification::make()
            ->success()
            ->title('Background settings saved')
            ->send();
    }

    /**
     * Upload background image via Livewire
     */
    public function uploadBackgroundImage($file): ?string
    {
        if (!$file) return null;

        try {
            // Store the file
            $path = $file->store('seating/backgrounds', 'public');

            // Update the layout with new image path
            $this->seatingLayout->update([
                'background_image_path' => $path,
                'background_image_url' => null, // Clear external URL if any
            ]);

            Notification::make()
                ->success()
                ->title('Imagine încărcată')
                ->send();

            return \Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Eroare la încărcare')
                ->body($e->getMessage())
                ->send();
            return null;
        }
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
     * Align rows within a section.
     * Seat coordinates are LOCAL (relative to section origin), so no rotation transform needed.
     * Seats rotate automatically with the Konva group.
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

        $sectionWidth = $section->width ?? 200;
        $padding = 10;

        foreach ($rowIds as $rowId) {
            $row = SeatingRow::with('seats')->find($rowId);
            if (!$row || $row->section_id !== $section->id) continue;

            $seats = $row->seats->sortBy('x')->values();
            if ($seats->isEmpty()) continue;

            // Seat x coordinates are local (relative to section origin, 0 to sectionWidth)
            $minX = $seats->first()->x;
            $maxX = $seats->last()->x;
            $rowWidth = $maxX - $minX;

            $offset = 0;
            switch ($alignment) {
                case 'left':
                    $offset = $padding - $minX;
                    break;
                case 'center':
                    $availableWidth = $sectionWidth - (2 * $padding);
                    $targetStart = $padding + ($availableWidth - $rowWidth) / 2;
                    $offset = $targetStart - $minX;
                    break;
                case 'right':
                    $offset = ($sectionWidth - $padding) - $maxX;
                    break;
            }

            // Apply offset to each seat's x coordinate (stays in local space)
            foreach ($seats as $seat) {
                $seat->update([
                    'x' => round($seat->x + $offset, 2),
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
     * Update row spacing - recalculate seat positions based on new seat size and spacing
     */
    public function updateRowSpacing(int $rowId, array $settings): void
    {
        $row = SeatingRow::with(['seats', 'section'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Row not found')
                ->send();
            return;
        }

        $seatSize = (int) ($settings['seatSize'] ?? 20);
        $seatSpacing = (int) ($settings['seatSpacing'] ?? 25);

        // Clamp values to reasonable limits
        $seatSize = max(8, min(40, $seatSize));
        $seatSpacing = max(15, min(100, $seatSpacing));

        // Get seats ordered by current X position
        $seats = $row->seats->sortBy('x')->values();
        if ($seats->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No seats in row')
                ->send();
            return;
        }

        // Recalculate seat positions maintaining alignment
        $firstSeatX = $seats->first()->x;
        $avgY = $seats->avg('y'); // Keep average Y position

        foreach ($seats as $index => $seat) {
            $newX = $firstSeatX + ($index * $seatSpacing);
            $seat->update([
                'x' => round($newX, 2),
                'y' => round($avgY, 2),
            ]);
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row spacing updated')
            ->body("Applied seat size {$seatSize}px and spacing {$seatSpacing}px")
            ->send();
    }

    /**
     * Set uniform vertical spacing between multiple rows
     */
    public function setRowSpacing(array $rowIds, float $spacing): void
    {
        if (count($rowIds) < 2) {
            Notification::make()
                ->warning()
                ->title('Select at least 2 rows')
                ->send();
            return;
        }

        // Clamp spacing to reasonable limits
        $spacing = max(20, min(200, $spacing));

        // Get rows and sort by average Y position
        $rows = SeatingRow::with(['seats', 'section'])
            ->whereIn('id', $rowIds)
            ->get()
            ->filter(fn($row) => $row->section && $row->section->layout_id === $this->seatingLayout->id)
            ->sortBy(fn($row) => $row->seats->avg('y'))
            ->values();

        if ($rows->count() < 2) {
            Notification::make()
                ->danger()
                ->title('Invalid rows')
                ->send();
            return;
        }

        // Get the starting Y from the first row
        $startY = $rows->first()->seats->avg('y');

        // Adjust each row's Y position
        foreach ($rows as $index => $row) {
            $targetY = $startY + ($index * $spacing);
            $currentAvgY = $row->seats->avg('y');
            $offsetY = $targetY - $currentAvgY;

            foreach ($row->seats as $seat) {
                $seat->update([
                    'y' => round($seat->y + $offsetY, 2),
                ]);
            }
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row spacing applied')
            ->body("Set {$spacing}px spacing between " . count($rowIds) . " rows")
            ->send();
    }

    /**
     * Set seat spacing for multiple rows
     */
    public function setMultiRowSeatSpacing(array $rowIds, float $spacing): void
    {
        if (count($rowIds) === 0) {
            return;
        }

        // Clamp spacing to reasonable limits
        $spacing = max(15, min(100, $spacing));
        $updatedCount = 0;

        foreach ($rowIds as $rowId) {
            $row = SeatingRow::with(['seats', 'section'])->find($rowId);
            if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
                continue;
            }

            $seats = $row->seats->sortBy('x')->values();
            if ($seats->isEmpty()) {
                continue;
            }

            // Recalculate seat X positions with new spacing
            $firstSeatX = $seats->first()->x;
            foreach ($seats as $index => $seat) {
                $newX = $firstSeatX + ($index * $spacing);
                $seat->update(['x' => round($newX, 2)]);
            }
            $updatedCount++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Seat spacing applied')
            ->body("Applied {$spacing}px spacing to {$updatedCount} rows")
            ->send();
    }

    /**
     * Update row label settings (show/hide and position)
     */
    public function updateRowLabelSettings(array $rowIds, array $settings): void
    {
        if (count($rowIds) === 0) {
            return;
        }

        $showLabel = $settings['showLabel'] ?? true;
        $position = $settings['position'] ?? 'left';
        $updatedCount = 0;

        foreach ($rowIds as $rowId) {
            $row = SeatingRow::with('section')->find($rowId);
            if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
                continue;
            }

            // Store in metadata
            $metadata = $row->metadata ?? [];
            $metadata['show_label'] = $showLabel;
            $metadata['label_position'] = $position;
            $row->update(['metadata' => $metadata]);
            $updatedCount++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row labels updated')
            ->body("Updated label settings for {$updatedCount} rows")
            ->send();
    }

    /**
     * Update row curve - applies a parabolic curve to seat Y positions
     */
    public function updateRowCurve(int $rowId, float $curveOffset): void
    {
        $row = SeatingRow::with(['seats', 'section'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            Notification::make()
                ->danger()
                ->title('Row not found')
                ->send();
            return;
        }

        // Clamp curve to reasonable limits
        $curveOffset = max(-50, min(50, $curveOffset));

        // Get seats ordered by X position
        $seats = $row->seats->sortBy('x')->values();
        if ($seats->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No seats in row')
                ->send();
            return;
        }

        // Calculate the center X and base Y
        $minX = $seats->first()->x;
        $maxX = $seats->last()->x;
        $centerX = ($minX + $maxX) / 2;
        $rowWidth = $maxX - $minX;
        $baseY = $seats->avg('y');

        // Apply parabolic curve: y = baseY + curveOffset * (1 - ((x - centerX) / (rowWidth/2))^2)
        foreach ($seats as $seat) {
            if ($rowWidth > 0) {
                $normalizedX = ($seat->x - $centerX) / ($rowWidth / 2); // -1 to 1
                $curveY = $curveOffset * (1 - ($normalizedX * $normalizedX)); // Parabola
                $newY = $baseY + $curveY;
            } else {
                $newY = $baseY;
            }

            $seat->update([
                'y' => round($newY, 2),
            ]);
        }

        // Save curve offset to row
        $row->update(['curve_offset' => $curveOffset]);

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Row curve applied')
            ->body("Applied curve offset {$curveOffset} to row")
            ->send();
    }

    /**
     * Move a row (and all its seats) by a delta offset
     * Called from Konva.js when dragging a selected row/table
     */
    public function moveRow(int $rowId, float $deltaX, float $deltaY): void
    {
        $row = SeatingRow::with(['seats', 'section'])->find($rowId);

        if (!$row || !$row->section || $row->section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        // Update all seat positions
        foreach ($row->seats as $seat) {
            $seat->update([
                'x' => round($seat->x + $deltaX, 2),
                'y' => round($seat->y + $deltaY, 2),
            ]);
        }

        // Update table center position if it's a table
        $metadata = $row->metadata ?? [];
        if (!empty($metadata['is_table'])) {
            $metadata['center_x'] = round(($metadata['center_x'] ?? 0) + $deltaX, 2);
            $metadata['center_y'] = round(($metadata['center_y'] ?? 0) + $deltaY, 2);
            $row->update(['metadata' => $metadata]);
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);
    }

    /**
     * Block seats (mark as imposibil - permanently unavailable)
     * @param string $reason One of: stricat, lipsa, indisponibil
     */
    public function blockSeats(array $seatIds, string $reason = 'indisponibil'): void
    {
        $blockedCount = 0;
        $reasonLabels = [
            'stricat' => 'Stricat',
            'lipsa' => 'Lipsă',
            'indisponibil' => 'Indisponibil',
        ];

        foreach ($seatIds as $seatId) {
            $seat = SeatingSeat::find($seatId);
            if (!$seat) continue;

            $row = $seat->row;
            $section = $row?->section;

            if (!$section || $section->layout_id !== $this->seatingLayout->id) continue;

            $seat->update([
                'status' => SeatingSeat::STATUS_IMPOSIBIL,
                'block_reason' => $reason,
            ]);
            $blockedCount++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        $reasonLabel = $reasonLabels[$reason] ?? $reason;
        Notification::make()
            ->success()
            ->title('Seats blocked')
            ->body("Blocked {$blockedCount} seat(s) - Reason: {$reasonLabel}")
            ->send();
    }

    /**
     * Unblock seats (mark as active)
     */
    public function unblockSeats(array $seatIds): void
    {
        $unblockedCount = 0;

        foreach ($seatIds as $seatId) {
            $seat = SeatingSeat::find($seatId);
            if (!$seat) continue;

            $row = $seat->row;
            $section = $row?->section;

            if (!$section || $section->layout_id !== $this->seatingLayout->id) continue;

            $seat->update([
                'status' => SeatingSeat::STATUS_ACTIVE,
                'block_reason' => null,
            ]);
            $unblockedCount++;
        }

        $this->reloadSections();
        $this->dispatch('layout-updated', sections: $this->sections);

        Notification::make()
            ->success()
            ->title('Seats unblocked')
            ->body("Unblocked {$unblockedCount} seat(s)")
            ->send();
    }

    /**
     * Delete seats and renumber remaining (alias for deleteSeats which already does this)
     */
    public function deleteSeatsAndRenumber(array $seatIds): void
    {
        $this->deleteSeats($seatIds);
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
     * Convert a starting value + offset to alphabetic label
     */
    protected function numberToAlpha(string $start, int $offset, bool $uppercase = true): string
    {
        // If start is a letter, use letter-based offset
        if (ctype_alpha($start)) {
            $base = $uppercase ? ord('A') : ord('a');
            $startOrd = ord(strtoupper($start)) - ord('A');
            $result = $startOrd + $offset;
            $char = chr($base + ($result % 26));
            return $char;
        }

        // If start is numeric, convert number to letter
        $num = (int) $start + $offset;
        $base = $uppercase ? ord('A') : ord('a');
        if ($num < 1) $num = 1;
        $char = chr($base + (($num - 1) % 26));
        return $char;
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
            'backgroundColor' => $this->seatingLayout->background_color ?? '#F3F4F6',
            'iconDefinitions' => config('seating-icons', []),
        ];
    }

    /**
     * Save background color for the layout canvas
     */
    public function saveBackgroundColor(string $color): void
    {
        $this->seatingLayout->update(['background_color' => $color]);

        Notification::make()
            ->success()
            ->title('Background color saved')
            ->send();
    }

    /**
     * Update section display order (z-index) based on direction
     */
    public function updateDisplayOrder(int $sectionId, string $direction): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $allSections = $this->seatingLayout->sections()
            ->orderBy('display_order')
            ->get();

        $currentIndex = $allSections->search(fn ($s) => $s->id === $sectionId);

        if ($currentIndex === false) return;

        switch ($direction) {
            case 'front':
                $maxOrder = $allSections->max('display_order') ?? 0;
                $section->update(['display_order' => $maxOrder + 1]);
                break;

            case 'back':
                $minOrder = $allSections->min('display_order') ?? 0;
                $section->update(['display_order' => $minOrder - 1]);
                break;

            case 'up':
                if ($currentIndex < $allSections->count() - 1) {
                    $nextSection = $allSections[$currentIndex + 1];
                    $tempOrder = $section->display_order;
                    $section->update(['display_order' => $nextSection->display_order]);
                    $nextSection->update(['display_order' => $tempOrder]);
                    if ($section->display_order === $nextSection->display_order) {
                        $section->update(['display_order' => $section->display_order + 1]);
                    }
                }
                break;

            case 'down':
                if ($currentIndex > 0) {
                    $prevSection = $allSections[$currentIndex - 1];
                    $tempOrder = $section->display_order;
                    $section->update(['display_order' => $prevSection->display_order]);
                    $prevSection->update(['display_order' => $tempOrder]);
                    if ($section->display_order === $prevSection->display_order) {
                        $prevSection->update(['display_order' => $prevSection->display_order + 1]);
                    }
                }
                break;
        }

        $this->reloadSections();
    }
}
