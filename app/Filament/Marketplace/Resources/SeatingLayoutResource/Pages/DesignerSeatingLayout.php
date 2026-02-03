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
        $this->seatingLayout = $record->load('venue');
        $this->reloadSections();
    }

    public function getSubheading(): ?string
    {
        $parts = [$this->seatingLayout->name];
        if ($this->seatingLayout->venue) {
            $parts[] = $this->seatingLayout->venue->name;
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
                        ->helperText('Unique identifier (e.g., A, B, VIP)')
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

            Actions\Action::make('editSection')
                ->label('Edit Section')
                ->color('warning')
                ->extraAttributes(['class' => '!hidden'])
                ->modalHeading('Edit Section')
                ->modalWidth('2xl')
                ->fillForm(function () {
                    // Pre-select the selected section from canvas if available
                    if ($this->selectedSection) {
                        $section = SeatingSection::find($this->selectedSection);
                        if ($section && $section->layout_id === $this->seatingLayout->id) {
                            $metadata = $section->metadata ?? [];

                            // Load rows for this section
                            $rows = $section->rows()->orderBy('y', 'asc')->get()->map(function ($row) {
                                return [
                                    'id' => $row->id,
                                    'label' => $row->label,
                                    'seat_start_number' => $row->seat_start_number ?? 1,
                                    'alignment' => $row->alignment ?? 'left',
                                ];
                            })->toArray();

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
                                'numbering_mode' => $metadata['numbering_mode'] ?? 'normal',
                                'rows' => $rows,
                                'curve_amount' => $metadata['curve_amount'] ?? 0,
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
                                    $set('numbering_mode', $metadata['numbering_mode'] ?? 'normal');
                                    $set('curve_amount', $metadata['curve_amount'] ?? 0);

                                    // Load rows for this section
                                    $rows = $section->rows()->orderBy('y', 'asc')->get()->map(function ($row) {
                                        return [
                                            'id' => $row->id,
                                            'label' => $row->label,
                                            'seat_start_number' => $row->seat_start_number ?? 1,
                                            'alignment' => $row->alignment ?? 'left',
                                        ];
                                    })->toArray();
                                    $set('rows', $rows);
                                }
                            }
                        })
                        ->visible(fn () => !$this->selectedSection)
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
                        ->rules([
                            fn (\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, $fail) use ($get) {
                                $sectionId = $get('section_id');
                                $exists = SeatingSection::where('layout_id', $this->seatingLayout->id)
                                    ->where('section_code', $value)
                                    ->when($sectionId, fn ($q) => $q->where('id', '!=', $sectionId))
                                    ->exists();
                                if ($exists) {
                                    $fail('This section code already exists in this layout.');
                                }
                            },
                        ])
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

                            Forms\Components\Select::make('numbering_mode')
                                ->label('Seat Numbering Mode')
                                ->options([
                                    'normal' => 'Normal (per row: 1-10, 1-10, 1-10...)',
                                    'section' => 'Section (continuous: 1-10, 11-20, 21-30...)',
                                    'snake' => 'Snake (alternating: 1-10→, ←11-20, 21-30→...)',
                                ])
                                ->default('normal')
                                ->helperText('How seats are numbered across rows')
                                ->columnSpanFull(),

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

                    SC\Section::make('Row Settings')
                        ->description('Configure individual row settings (numbering start and alignment)')
                        ->schema([
                            Forms\Components\TextInput::make('row_renumber_start')
                                ->label('Renumber rows from')
                                ->placeholder('e.g. 5')
                                ->helperText('Renumber all row labels starting from this number')
                                ->columnSpan(1),

                            Forms\Components\Select::make('row_renumber_type')
                                ->label('Label type')
                                ->options([
                                    'numeric' => 'Numeric (1, 2, 3...)',
                                    'alpha_upper' => 'Letters A, B, C...',
                                    'alpha_lower' => 'Letters a, b, c...',
                                ])
                                ->default('numeric')
                                ->columnSpan(1),

                            Forms\Components\Select::make('row_renumber_direction')
                                ->label('Direction')
                                ->options([
                                    'top_to_bottom' => 'Top to bottom',
                                    'bottom_to_top' => 'Bottom to top',
                                ])
                                ->default('top_to_bottom')
                                ->columnSpan(1),

                            Forms\Components\Repeater::make('rows')
                                ->schema([
                                    Forms\Components\Hidden::make('id'),

                                    Forms\Components\TextInput::make('label')
                                        ->label('Row Label')
                                        ->disabled()
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('seat_start_number')
                                        ->label('Start Number')
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->helperText('First seat number')
                                        ->columnSpan(1),

                                    Forms\Components\Select::make('alignment')
                                        ->label('Alignment')
                                        ->options([
                                            'left' => 'Left',
                                            'center' => 'Center',
                                            'right' => 'Right',
                                        ])
                                        ->default('left')
                                        ->columnSpan(1),
                                ])
                                ->columns(3)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->collapsed()
                        ->collapsible(),

                    SC\Section::make('Row Configuration')
                        ->description('Manage rows: rename, add, or delete rows')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('rows_overview')
                                ->label('Current Rows')
                                ->content(function ($get) {
                                    $sectionId = $get('section_id');
                                    if (!$sectionId) return 'Select a section first';

                                    $section = SeatingSection::with('rows.seats')->find($sectionId);
                                    if (!$section) return 'Section not found';

                                    if ($section->rows->isEmpty()) {
                                        return 'No rows in this section';
                                    }

                                    $rows = $section->rows->sortBy('y')->map(function ($row) {
                                        return "Row {$row->label}: {$row->seats->count()} seats";
                                    })->join(' | ');

                                    return $rows;
                                })
                                ->columnSpanFull(),

                            Forms\Components\Select::make('row_action')
                                ->label('Row Action')
                                ->options([
                                    '' => '— No action —',
                                    'rename' => 'Rename a row',
                                    'add' => 'Add a new row',
                                    'delete' => 'Delete a row',
                                    'renumber' => 'Renumber all rows',
                                ])
                                ->default('')
                                ->reactive()
                                ->columnSpanFull(),

                            // Rename row fields
                            Forms\Components\Select::make('rename_row_id')
                                ->label('Row to Rename')
                                ->options(function ($get) {
                                    $sectionId = $get('section_id');
                                    if (!$sectionId) return [];
                                    return SeatingRow::where('section_id', $sectionId)
                                        ->orderBy('y')
                                        ->pluck('label', 'id');
                                })
                                ->visible(fn ($get) => $get('row_action') === 'rename')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('rename_row_new_label')
                                ->label('New Row Label')
                                ->visible(fn ($get) => $get('row_action') === 'rename')
                                ->columnSpan(1),

                            // Add row fields
                            Forms\Components\TextInput::make('add_row_label')
                                ->label('New Row Label')
                                ->visible(fn ($get) => $get('row_action') === 'add')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('add_row_seats')
                                ->label('Number of Seats')
                                ->numeric()
                                ->default(10)
                                ->minValue(1)
                                ->maxValue(100)
                                ->visible(fn ($get) => $get('row_action') === 'add')
                                ->columnSpan(1),

                            // Delete row fields
                            Forms\Components\Select::make('delete_row_id')
                                ->label('Row to Delete')
                                ->options(function ($get) {
                                    $sectionId = $get('section_id');
                                    if (!$sectionId) return [];
                                    return SeatingRow::where('section_id', $sectionId)
                                        ->orderBy('y')
                                        ->get()
                                        ->mapWithKeys(fn ($row) => [$row->id => "Row {$row->label} ({$row->seats()->count()} seats)"]);
                                })
                                ->visible(fn ($get) => $get('row_action') === 'delete')
                                ->helperText('Warning: This will delete the row and all its seats')
                                ->columnSpanFull(),

                            // Renumber rows fields
                            Forms\Components\TextInput::make('renumber_start')
                                ->label('Start from')
                                ->placeholder('e.g. 5 or A')
                                ->helperText('Renumber all row labels starting from this value')
                                ->visible(fn ($get) => $get('row_action') === 'renumber')
                                ->columnSpan(1),

                            Forms\Components\Select::make('renumber_type')
                                ->label('Label type')
                                ->options([
                                    'numeric' => 'Numeric (1, 2, 3...)',
                                    'alpha_upper' => 'Letters A, B, C...',
                                    'alpha_lower' => 'Letters a, b, c...',
                                ])
                                ->default('numeric')
                                ->visible(fn ($get) => $get('row_action') === 'renumber')
                                ->columnSpan(1),

                            Forms\Components\Select::make('renumber_direction')
                                ->label('Direction')
                                ->options([
                                    'top_to_bottom' => 'Top to bottom',
                                    'bottom_to_top' => 'Bottom to top',
                                ])
                                ->default('top_to_bottom')
                                ->visible(fn ($get) => $get('row_action') === 'renumber')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    SC\Section::make('Seat Configuration')
                        ->description('Change the number of seats in a row')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Select::make('seat_config_row_id')
                                ->label('Row')
                                ->options(function ($get) {
                                    $sectionId = $get('section_id');
                                    if (!$sectionId) return [];
                                    return SeatingRow::where('section_id', $sectionId)
                                        ->orderBy('y')
                                        ->get()
                                        ->mapWithKeys(fn ($row) => [$row->id => "Row {$row->label} ({$row->seats()->count()} seats)"]);
                                })
                                ->reactive()
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('seat_config_current')
                                ->label('Current Seats')
                                ->content(function ($get) {
                                    $rowId = $get('seat_config_row_id');
                                    if (!$rowId) return 'Select a row';

                                    $row = SeatingRow::with('seats')->find($rowId);
                                    if (!$row) return 'Row not found';

                                    $seatLabels = $row->seats->sortBy('x')->pluck('label')->join(', ');
                                    return "Seats ({$row->seats->count()}): {$seatLabels}";
                                })
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('seat_config_new_count')
                                ->label('New Seat Count')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(200)
                                ->helperText('Seats will be regenerated with new count')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('seat_config_start_number')
                                ->label('Start Number')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(999)
                                ->default(1)
                                ->helperText('Seat numbering starts from this number')
                                ->columnSpan(1),

                            Forms\Components\Select::make('seat_config_numbering')
                                ->label('Numbering Direction')
                                ->options([
                                    'ltr' => 'Left to Right (1, 2, 3...)',
                                    'rtl' => 'Right to Left (...3, 2, 1)',
                                ])
                                ->default('ltr')
                                ->columnSpan(1),
                        ])
                        ->columns(3),

                    SC\Section::make('Curve Settings')
                        ->description('Curve/bulge the section and its rows')
                        ->schema([
                            Forms\Components\TextInput::make('curve_amount')
                                ->label('Curve Amount')
                                ->numeric()
                                ->minValue(-100)
                                ->maxValue(100)
                                ->default(0)
                                ->helperText('Positive = curve up, Negative = curve down. 0 = no curve.')
                                ->suffix('px')
                                ->columnSpan(1),
                        ])
                        ->collapsed()
                        ->collapsible(),
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
                    $metadata['numbering_mode'] = $data['numbering_mode'] ?? 'normal';

                    // Save curve amount to metadata
                    $metadata['curve_amount'] = (int) ($data['curve_amount'] ?? 0);
                    $updates['metadata'] = $metadata;

                    $section->update($updates);

                    // Recalculate seat positions if spacing changed
                    if ($newSeatSpacing !== $oldSeatSpacing || $newRowSpacing !== $oldRowSpacing) {
                        $this->recalculateSeatPositions($section, $newSeatSpacing, $newRowSpacing);
                    }

                    // Renumber row labels if requested
                    if (!empty($data['row_renumber_start'])) {
                        $startValue = $data['row_renumber_start'];
                        $type = $data['row_renumber_type'] ?? 'numeric';
                        $direction = $data['row_renumber_direction'] ?? 'top_to_bottom';
                        $orderDir = $direction === 'bottom_to_top' ? 'desc' : 'asc';
                        $rows = $section->rows()->orderBy('y', $orderDir)->get();

                        foreach ($rows as $index => $row) {
                            $newLabel = match ($type) {
                                'alpha_upper' => $this->numberToAlpha($startValue, $index, true),
                                'alpha_lower' => $this->numberToAlpha($startValue, $index, false),
                                default => (string) ((int) $startValue + $index),
                            };

                            $oldLabel = $row->label;
                            $row->update(['label' => $newLabel]);

                            // Update seat display names and UIDs for renamed row
                            if ($oldLabel !== $newLabel) {
                                foreach ($row->seats as $seat) {
                                    $seat->update([
                                        'display_name' => $section->generateSeatDisplayName($newLabel, $seat->label),
                                        'seat_uid' => $section->generateSeatUid($newLabel, $seat->label),
                                    ]);
                                }
                            }
                        }
                    }

                    // Handle Row Configuration actions
                    $rowAction = $data['row_action'] ?? '';
                    if ($rowAction) {
                        switch ($rowAction) {
                            case 'rename':
                                if (!empty($data['rename_row_id']) && !empty($data['rename_row_new_label'])) {
                                    $row = SeatingRow::find($data['rename_row_id']);
                                    if ($row && $row->section_id === $section->id) {
                                        $oldLabel = $row->label;
                                        $newLabel = $data['rename_row_new_label'];
                                        $row->update(['label' => $newLabel]);

                                        // Update seat display names and UIDs
                                        foreach ($row->seats as $seat) {
                                            $seat->update([
                                                'display_name' => $section->generateSeatDisplayName($newLabel, $seat->label),
                                                'seat_uid' => $section->generateSeatUid($newLabel, $seat->label),
                                            ]);
                                        }
                                    }
                                }
                                break;

                            case 'add':
                                if (!empty($data['add_row_label'])) {
                                    $seatCount = (int) ($data['add_row_seats'] ?? 10);
                                    $seatSpacing = (int) ($metadata['seat_spacing'] ?? 18);

                                    // Get the Y position for the new row (after existing rows)
                                    $lastRow = $section->rows()->orderBy('y', 'desc')->first();
                                    $newRowY = $lastRow ? $lastRow->y + ($metadata['row_spacing'] ?? 25) : 0;

                                    $newRow = SeatingRow::create([
                                        'section_id' => $section->id,
                                        'label' => $data['add_row_label'],
                                        'y' => $newRowY,
                                        'seat_count' => $seatCount,
                                    ]);

                                    // Create seats for the new row
                                    for ($s = 1; $s <= $seatCount; $s++) {
                                        SeatingSeat::create([
                                            'row_id' => $newRow->id,
                                            'label' => (string) $s,
                                            'display_name' => $section->generateSeatDisplayName($newRow->label, (string) $s),
                                            'x' => ($s - 1) * $seatSpacing,
                                            'y' => $newRowY,
                                            'angle' => 0,
                                            'shape' => $metadata['seat_shape'] ?? 'circle',
                                            'seat_uid' => $section->generateSeatUid($newRow->label, (string) $s),
                                        ]);
                                    }
                                }
                                break;

                            case 'delete':
                                if (!empty($data['delete_row_id'])) {
                                    $row = SeatingRow::find($data['delete_row_id']);
                                    if ($row && $row->section_id === $section->id) {
                                        // Delete all seats first
                                        $row->seats()->delete();
                                        // Then delete the row
                                        $row->delete();
                                    }
                                }
                                break;

                            case 'renumber':
                                if (!empty($data['renumber_start'])) {
                                    $startValue = $data['renumber_start'];
                                    $type = $data['renumber_type'] ?? 'numeric';
                                    $direction = $data['renumber_direction'] ?? 'top_to_bottom';
                                    $orderDir = $direction === 'bottom_to_top' ? 'desc' : 'asc';
                                    $rows = $section->rows()->orderBy('y', $orderDir)->get();

                                    foreach ($rows as $index => $row) {
                                        $newLabel = match ($type) {
                                            'alpha_upper' => $this->numberToAlpha($startValue, $index, true),
                                            'alpha_lower' => $this->numberToAlpha($startValue, $index, false),
                                            default => (string) ((int) $startValue + $index),
                                        };

                                        $oldLabel = $row->label;
                                        $row->update(['label' => $newLabel]);

                                        // Update seat display names and UIDs
                                        if ($oldLabel !== $newLabel) {
                                            foreach ($row->seats as $seat) {
                                                $seat->update([
                                                    'display_name' => $section->generateSeatDisplayName($newLabel, $seat->label),
                                                    'seat_uid' => $section->generateSeatUid($newLabel, $seat->label),
                                                ]);
                                            }
                                        }
                                    }
                                }
                                break;
                        }
                    }

                    // Handle Seat Configuration
                    if (!empty($data['seat_config_row_id']) && !empty($data['seat_config_new_count'])) {
                        $row = SeatingRow::find($data['seat_config_row_id']);
                        if ($row && $row->section_id === $section->id) {
                            $newCount = (int) $data['seat_config_new_count'];
                            $startNumber = (int) ($data['seat_config_start_number'] ?? 1);
                            $seatSpacing = (int) ($metadata['seat_spacing'] ?? 18);
                            $numbering = $data['seat_config_numbering'] ?? 'ltr';

                            // Delete existing seats
                            $row->seats()->delete();

                            // Create new seats
                            for ($s = 1; $s <= $newCount; $s++) {
                                // Calculate seat label based on start number and direction
                                if ($numbering === 'rtl') {
                                    $seatLabel = $startNumber + $newCount - $s;
                                } else {
                                    $seatLabel = $startNumber + $s - 1;
                                }

                                SeatingSeat::create([
                                    'row_id' => $row->id,
                                    'label' => (string) $seatLabel,
                                    'display_name' => $section->generateSeatDisplayName($row->label, (string) $seatLabel),
                                    'x' => ($s - 1) * $seatSpacing,
                                    'y' => $row->y,
                                    'angle' => 0,
                                    'shape' => $metadata['seat_shape'] ?? 'circle',
                                    'seat_uid' => $section->generateSeatUid($row->label, (string) $seatLabel),
                                ]);
                            }

                            $row->update(['seat_count' => $newCount]);
                        }
                    }

                    // Update row settings (seat_start_number and alignment)
                    if (!empty($data['rows']) && is_array($data['rows'])) {
                        foreach ($data['rows'] as $rowData) {
                            if (!empty($rowData['id'])) {
                                $row = SeatingRow::find($rowData['id']);
                                if ($row && $row->section_id === $section->id) {
                                    $row->update([
                                        'seat_start_number' => (int) ($rowData['seat_start_number'] ?? 1),
                                        'alignment' => $rowData['alignment'] ?? 'left',
                                    ]);

                                    // Renumber seats if start number changed
                                    $this->renumberSeatsInRow($row, (int) ($rowData['seat_start_number'] ?? 1));
                                }
                            }
                        }
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
        $rowSpacing = $data['row_spacing'] ?? 40;  // Gap between rows
        $seatSpacing = $data['seat_spacing'] ?? 30; // Gap between seats
        $numRows = (int) $data['num_rows'];

        // Get seat size from section metadata or use default
        $metadata = $section->metadata ?? [];
        $seatSize = $metadata['seat_size'] ?? 10;

        // Calculate step between seat/row centers (size + gap)
        $stepX = $seatSize + $seatSpacing;
        $stepY = $seatSize + $rowSpacing;

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

        // Calculate max row width for alignment purposes (using step, not just spacing)
        $maxSeats = max($seatsPerRowArray);
        $maxRowWidth = ($maxSeats - 1) * $stepX;

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
            $rowWidth = ($seatsInThisRow - 1) * $stepX;
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
                    'x'        => $startX + (($s - 1) * $stepX),
                    'y'        => 0,
                    'angle'    => 0,
                    'shape'    => 'circle',
                    'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                ]);
            }

            $row->update(['seat_count' => $seatsInThisRow]);

            $currentY += $stepY;

            if ($useGrouping && $r < $numRows && $r % $groupSize === 0) {
                $currentY += $aisleSpacing;
            }
        }

        // Dispatch update to canvas
        $this->dispatch('layout-updated', sections: $this->sections);
    }

    /**
     * Create rows and seats for a new section
     * Note: seatSpacing and rowSpacing represent the GAP between seats/rows
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

        // Calculate step between seat/row centers (size + gap)
        $stepX = $seatSize + $seatSpacing;
        $stepY = $seatSize + $rowSpacing;

        for ($r = 1; $r <= $numRows; $r++) {
            // Determine row label based on numbering direction
            $rowIndex = $rowNumbering === 'btt' ? ($numRows - $r + 1) : $r;
            $rowLabel = (string) $rowIndex;

            // Calculate Y position for this row (center of seat at padding + step)
            $rowY = $padding + (($r - 1) * $stepY);

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

                // Calculate X position for this seat (center of seat at padding + step)
                $seatX = $padding + (($s - 1) * $stepX);

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

        // Also update the local sections array to keep PHP state in sync
        // This prevents stale data from being sent back if Livewire re-renders
        foreach ($this->sections as &$s) {
            if ($s['id'] === (int) $sectionId) {
                foreach ($filteredUpdates as $field => $value) {
                    $s[$field] = (int) $value;
                }
                break;
            }
        }
        unset($s);

        // Skip render to prevent Livewire from sending back component updates
        // This is a "silent" save - the frontend already updated its local state
        $this->skipRender();
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

        // Update local sections array to keep PHP state in sync
        foreach ($this->sections as &$s) {
            if ($s['id'] === (int) $sectionId) {
                $s['x_position'] = $section->x_position;
                $s['y_position'] = $section->y_position;
                break;
            }
        }
        unset($s);

        // Dispatch event for frontend (optional, frontend already moved visually)
        $this->dispatch('section-moved', sectionId: $sectionId, x: $section->x_position, y: $section->y_position);

        // Skip render to prevent snap-back
        $this->skipRender();
    }

    /**
     * Move an entire row by delta (called from CTRL+drag on frontend)
     */
    public function moveRow($sectionId, $rowId, $deltaX, $deltaY): void
    {
        $section = SeatingSection::find($sectionId);
        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        $row = SeatingRow::find($rowId);
        if (!$row || $row->section_id !== $section->id) {
            return;
        }

        // Update Y position of the row itself
        $row->y = max(0, $row->y + (float) $deltaY);
        $row->save();

        // Update all seats in this row
        $row->seats()->each(function ($seat) use ($deltaX, $deltaY) {
            $seat->x = max(0, $seat->x + (float) $deltaX);
            $seat->y = max(0, $seat->y + (float) $deltaY);
            $seat->save();
        });

        // Update local sections array to keep PHP state in sync
        foreach ($this->sections as &$s) {
            if ($s['id'] === (int) $sectionId && isset($s['rows'])) {
                foreach ($s['rows'] as &$r) {
                    if ($r['id'] === (int) $rowId) {
                        $r['y'] = $row->y;
                        if (isset($r['seats'])) {
                            foreach ($r['seats'] as &$seat) {
                                $seat['x'] = max(0, $seat['x'] + (float) $deltaX);
                                $seat['y'] = max(0, $seat['y'] + (float) $deltaY);
                            }
                            unset($seat);
                        }
                        break;
                    }
                }
                unset($r);
                break;
            }
        }
        unset($s);

        // Skip render to prevent snap-back
        $this->skipRender();
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

    public function addDrawnShape(string $type, array $geometry, string $color, float $opacity, array $extra = []): void
    {
        $metadata = array_merge($geometry['metadata'] ?? [], [
            'shape' => $type,
            'opacity' => $opacity,
        ]);

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
     * Update section curve amount (called from frontend curve handle drag)
     */
    public function updateSectionCurve($sectionId, $curveAmount): void
    {
        $section = SeatingSection::find($sectionId);

        if (!$section || $section->layout_id !== $this->seatingLayout->id) {
            return;
        }

        // Update curve_amount in metadata
        $metadata = $section->metadata ?? [];
        $metadata['curve_amount'] = (int) $curveAmount;
        $section->update(['metadata' => $metadata]);

        // Update local sections array to keep PHP state in sync
        foreach ($this->sections as &$s) {
            if ($s['id'] === (int) $sectionId) {
                if (!isset($s['metadata'])) {
                    $s['metadata'] = [];
                }
                $s['metadata']['curve_amount'] = (int) $curveAmount;
                break;
            }
        }
        unset($s);

        // Skip render to prevent snap-back
        $this->skipRender();
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

        $sectionWidth = $section->width ?? 200;
        $padding = 10;

        foreach ($rowIds as $rowId) {
            $row = SeatingRow::with('seats')->find($rowId);
            if (!$row || $row->section_id !== $section->id) continue;

            $seats = $row->seats->sortBy('x')->values();
            if ($seats->isEmpty()) continue;

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
     * Recalculate seat positions based on new spacing values
     * Note: seatSpacing and rowSpacing represent the GAP between seats/rows, not center-to-center distance
     */
    protected function recalculateSeatPositions(SeatingSection $section, int $seatSpacing, int $rowSpacing): void
    {
        $padding = 10; // Padding from section edge

        // Get seat size from metadata (diameter/side length)
        $metadata = $section->metadata ?? [];
        $seatSize = $metadata['seat_size'] ?? 10;

        // Calculate step between seat/row centers:
        // stepX = seatSize + seatSpacing (size plus gap)
        // stepY = seatSize + rowSpacing (size plus gap)
        $stepX = $seatSize + $seatSpacing;
        $stepY = $seatSize + $rowSpacing;

        // Load rows ordered by Y position (or label)
        $rows = $section->rows()->orderBy('y', 'asc')->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Find max seats per row to calculate section width
        $maxSeatsPerRow = 0;
        $rowIndex = 0;

        foreach ($rows as $row) {
            // Calculate new row Y position (center of first seat at padding, then step by stepY)
            $newRowY = $padding + ($rowIndex * $stepY);
            $row->update(['y' => $newRowY]);

            // Get seats for this row ordered by X position
            $seats = $row->seats()->orderBy('x', 'asc')->get();

            $seatsCount = $seats->count();
            if ($seatsCount > $maxSeatsPerRow) {
                $maxSeatsPerRow = $seatsCount;
            }

            $seatIndex = 0;
            foreach ($seats as $seat) {
                // Calculate new seat X position (center of first seat at padding, then step by stepX)
                $newSeatX = $padding + ($seatIndex * $stepX);
                $newSeatY = $newRowY; // Y is same as row Y

                $seat->update([
                    'x' => $newSeatX,
                    'y' => $newSeatY,
                ]);

                $seatIndex++;
            }

            $rowIndex++;
        }

        // Only EXPAND section dimensions if seats would overflow (never shrink)
        $numRows = $rows->count();
        // Width = (seats-1) * stepX + seatSize + 2*padding (for last seat to fit)
        $requiredWidth = (($maxSeatsPerRow - 1) * $stepX) + $seatSize + (2 * $padding);
        $requiredHeight = (($numRows - 1) * $stepY) + $seatSize + (2 * $padding);

        $updates = [];
        if ($requiredWidth > $section->width) {
            $updates['width'] = $requiredWidth;
        }
        if ($requiredHeight > $section->height) {
            $updates['height'] = $requiredHeight;
        }

        if (!empty($updates)) {
            $section->update($updates);
        }
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
            // Wrap around if beyond Z (26 letters)
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
     * Renumber seats in a row starting from a given number
     */
    protected function renumberSeatsInRow(SeatingRow $row, int $startNumber = 1): void
    {
        // Get seats ordered by X position
        $seats = $row->seats()->orderBy('x', 'asc')->get();

        $seatIndex = 0;
        foreach ($seats as $seat) {
            $newLabel = (string) ($startNumber + $seatIndex);
            $seat->update([
                'label' => $newLabel,
                'display_name' => $row->section->generateSeatDisplayName($row->label, $newLabel),
                'seat_uid' => $row->section->generateSeatUid($row->label, $newLabel),
            ]);
            $seatIndex++;
        }
    }

    public function saveBackgroundColor(string $color): void
    {
        $this->seatingLayout->update(['background_color' => $color]);

        Notification::make()
            ->success()
            ->title('Background color saved')
            ->send();
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
}
