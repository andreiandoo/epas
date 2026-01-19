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

                    $section = SeatingSection::create($data);

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

            Actions\Action::make('generateSeats')
                ->label('Bulk Generate Seats')
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Generate seats for a section with rows and seat numbering.')
                ->form([
                    Forms\Components\Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => $this->seatingLayout->sections()
                            ->where('section_type', 'standard')
                            ->orderBy('display_order')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('num_rows')
                        ->label('Number of Rows')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(50)
                        ->reactive()
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('use_variable_seats')
                        ->label('Variable Seats per Row')
                        ->helperText('Set different number of seats for each row')
                        ->reactive()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('seats_per_row')
                        ->label('Seats per Row (default)')
                        ->numeric()
                        ->required(fn ($get) => !$get('use_variable_seats'))
                        ->minValue(1)
                        ->maxValue(100)
                        ->hidden(fn ($get) => $get('use_variable_seats'))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('seats_config')
                        ->label('Seats per Row Configuration')
                        ->helperText('Enter number of seats for each row, separated by commas (e.g., "10,12,14,14,12,10"). Must match number of rows.')
                        ->required(fn ($get) => $get('use_variable_seats'))
                        ->visible(fn ($get) => $get('use_variable_seats'))
                        ->columnSpanFull()
                        ->rows(3),

                    Forms\Components\TextInput::make('row_prefix')
                        ->label('Row Prefix')
                        ->default('')
                        ->helperText('e.g., "Row " or leave empty for numbers only')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('seat_prefix')
                        ->label('Seat Prefix')
                        ->default('')
                        ->helperText('e.g., "S" for S1, S2...')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('row_spacing')
                        ->label('Row Spacing (px)')
                        ->numeric()
                        ->default(40)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('seat_spacing')
                        ->label('Seat Spacing (px)')
                        ->numeric()
                        ->default(30)
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('grouping_header')
                        ->label('Row Grouping & Alignment Options')
                        ->content('')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('use_row_grouping')
                        ->label('Enable Row Grouping')
                        ->helperText('Group rows with extra spacing between groups (aisles)')
                        ->reactive()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('group_size')
                        ->label('Rows per Group')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(10)
                        ->helperText('Number of rows in each group (e.g., 3 or 4)')
                        ->required(fn ($get) => $get('use_row_grouping'))
                        ->visible(fn ($get) => $get('use_row_grouping'))
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('aisle_spacing')
                        ->label('Aisle Spacing (px)')
                        ->numeric()
                        ->default(80)
                        ->minValue(1)
                        ->helperText('Extra space between row groups')
                        ->required(fn ($get) => $get('use_row_grouping'))
                        ->visible(fn ($get) => $get('use_row_grouping'))
                        ->columnSpan(1),

                    Forms\Components\Select::make('seat_alignment')
                        ->label('Seat Alignment')
                        ->options([
                            'left' => 'Left (align to left edge)',
                            'center' => 'Center (centered in row)',
                            'right' => 'Right (align to right edge)',
                        ])
                        ->default('center')
                        ->helperText('How seats are aligned within each row')
                        ->hidden(fn ($get) => $get('use_variable_seats'))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('alignment_config')
                        ->label('Alignment per Row Configuration')
                        ->helperText('Enter alignment for each row, separated by commas (e.g., "left,center,center,right"). Options: left, center, right.')
                        ->visible(fn ($get) => $get('use_variable_seats'))
                        ->columnSpanFull()
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $this->bulkGenerateSeats($data);

                    $this->reloadSections();

                    $seatsPerRow = $data['seats_per_row'] ?? 'variable';
                    Notification::make()
                        ->success()
                        ->title('Seats generated successfully')
                        ->body("Created {$data['num_rows']} rows with {$seatsPerRow} seats each.")
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
                ->fillForm(function () {
                    // Pre-select the selected section from canvas if available
                    if ($this->selectedSection) {
                        $section = SeatingSection::find($this->selectedSection);
                        if ($section && $section->layout_id === $this->seatingLayout->id) {
                            return [
                                'section_id' => $section->id,
                                'name' => $section->name,
                                'section_code' => $section->section_code,
                                'color_hex' => $section->color_hex,
                                'seat_color' => $section->seat_color,
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
                                    $set('name', $section->name);
                                    $set('section_code', $section->section_code);
                                    $set('color_hex', $section->color_hex);
                                    $set('seat_color', $section->seat_color);
                                }
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Section Name')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('section_code')
                        ->label('Section Code')
                        ->required()
                        ->maxLength(20)
                        ->columnSpanFull(),

                    Forms\Components\ColorPicker::make('color_hex')
                        ->label('Background Color')
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('seat_color')
                        ->label('Seat Color')
                        ->columnSpan(1),
                ])
                ->action(function (array $data): void {
                    $section = SeatingSection::find($data['section_id']);
                    if (!$section) return;

                    $updates = ['name' => $data['name'], 'section_code' => $data['section_code']];
                    if (!empty($data['color_hex'])) {
                        $updates['color_hex'] = $data['color_hex'];
                    }
                    if (!empty($data['seat_color'])) {
                        $updates['seat_color'] = $data['seat_color'];
                    }

                    $section->update($updates);

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
        ];
    }
}
