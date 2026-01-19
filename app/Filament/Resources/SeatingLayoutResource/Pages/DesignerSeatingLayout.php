<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingSection;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Models\Seating\PriceTier;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;

class DesignerSeatingLayout extends Page
{
    protected static string $resource = SeatingLayoutResource::class;

    protected string $view = 'filament.resources.seating-layout-resource.pages.designer-konva';

    protected static ?string $title = 'Seating Designer';

    // ATENȚIE: redenumit ca să nu intre în conflict cu Filament\Pages\Page::$layout (static).
    public SeatingLayout $seatingLayout;

    public array $sections = [];

    public ?int $selectedSection = null;

    public array $priceTiers = [];

    public function mount(SeatingLayout $record): void
    {
        $this->seatingLayout = $record;

        $this->reloadSections();

        // Load price tiers pentru tenant
        $this->priceTiers = PriceTier::query()
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
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

                    Forms\Components\Select::make('price_tier_id')
                        ->label('Default Price Tier')
                        ->options(fn () => $this->priceTiers)
                        ->searchable()
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('section_type')
                        ->options([
                            'standard' => 'Standard (rows & seats)',
                            'general_admission' => 'General Admission (capacity only)',
                        ])
                        ->default('standard')
                        ->required()
                        ->columnSpanFull(),

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
                ->fillForm(function (): array {
                    // Check if there's geometry data from drawing tools
                    return [];
                })
                ->action(function (array $data): void {
                    $data['layout_id'] = $this->seatingLayout->id;
                    $data['tenant_id'] = $this->seatingLayout->tenant_id;

                    // Ensure display_order has a default value
                    if (!isset($data['display_order']) || $data['display_order'] === null || $data['display_order'] === '') {
                        $data['display_order'] = 0;
                    }

                    // Parse metadata if it's a JSON string
                    if (isset($data['metadata']) && is_string($data['metadata'])) {
                        $data['metadata'] = json_decode($data['metadata'], true);
                    }

                    $section = SeatingSection::create($data);

                    $this->reloadSections();

                    // Dispatch event to add to canvas
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

                    // Ensure display_order has a default value
                    if (!isset($data['display_order']) || $data['display_order'] === null || $data['display_order'] === '') {
                        $data['display_order'] = 0;
                    }

                    // Parse metadata if it's a JSON string
                    if (isset($data['metadata']) && is_string($data['metadata'])) {
                        $data['metadata'] = json_decode($data['metadata'], true);
                    }

                    $zone = SeatingSection::create($data);

                    $this->reloadSections();

                    // Dispatch event to add to canvas
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
                        ->options(fn () => $this->seatingLayout->sections()->orderBy('display_order')->pluck('name', 'id'))
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
                        ->default('Row ')
                        ->helperText('e.g., "Row " or "R"')
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
                        ->helperText('Enter alignment for each row, separated by commas (e.g., "left,center,center,right"). Options: left, center, right. Must match number of rows.')
                        ->visible(fn ($get) => $get('use_variable_seats'))
                        ->columnSpanFull()
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $this->bulkGenerateSeats($data);

                    $this->reloadSections();

                    Notification::make()
                        ->success()
                        ->title('Seats generated successfully')
                        ->body("Created {$data['num_rows']} rows with {$data['seats_per_row']} seats each.")
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

        $rowPrefix  = $data['row_prefix']  ?? 'Row ';
        $seatPrefix = $data['seat_prefix'] ?? '';
        $rowSpacing = $data['row_spacing'] ?? 40;
        $seatSpacing = $data['seat_spacing'] ?? 30;
        $numRows = (int) $data['num_rows'];

        // Parse variable seats configuration
        $seatsPerRowArray = [];
        if (!empty($data['use_variable_seats']) && !empty($data['seats_config'])) {
            $seatsPerRowArray = array_map('trim', explode(',', $data['seats_config']));
            $seatsPerRowArray = array_map('intval', $seatsPerRowArray);

            // Validate count matches
            if (count($seatsPerRowArray) !== $numRows) {
                Notification::make()
                    ->danger()
                    ->title('Configuration Error')
                    ->body("Seats configuration must have exactly {$numRows} values (one per row)")
                    ->send();
                return;
            }
        } else {
            // Use same seats per row for all
            $defaultSeats = (int) ($data['seats_per_row'] ?? 10);
            $seatsPerRowArray = array_fill(0, $numRows, $defaultSeats);
        }

        // Parse alignment configuration
        $alignmentArray = [];
        if (!empty($data['use_variable_seats']) && !empty($data['alignment_config'])) {
            $alignmentArray = array_map('trim', explode(',', $data['alignment_config']));

            // Validate count matches
            if (count($alignmentArray) !== $numRows) {
                Notification::make()
                    ->danger()
                    ->title('Configuration Error')
                    ->body("Alignment configuration must have exactly {$numRows} values (one per row)")
                    ->send();
                return;
            }

            // Validate alignment values
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
            // Use same alignment for all rows
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
                    'x'        => $startX + (($s - 1) * $seatSpacing),
                    'y'        => 0,
                    'angle'    => 0,
                    'shape'    => 'circle',
                    'seat_uid' => SeatingSeat::generateSeatUid($section->section_code ?? $section->name, $rowLabel, $seatLabel),
                ]);
            }

            // Update row seat count
            $row->update(['seat_count' => $seatsInThisRow]);

            // Calculate next row Y position with grouping
            $currentY += $rowSpacing;

            // Add aisle spacing after each group
            if ($useGrouping && $r < $numRows && $r % $groupSize === 0) {
                $currentY += $aisleSpacing;
            }
        }
    }

    /**
     * Reîncarcă secțiunile pentru layout-ul curent.
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

        $section->update($updates);
        $this->reloadSections();

        Notification::make()
            ->success()
            ->title('Section updated')
            ->body('Position and dimensions saved')
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

        // Dispatch event to remove from canvas
        $this->dispatch('section-deleted', sectionId: $sectionId);

        Notification::make()
            ->success()
            ->title('Section deleted')
            ->body("Section '{$sectionName}' has been deleted")
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

        // Find or create a "Manual" row for manually placed seats
        $row = SeatingRow::firstOrCreate(
            [
                'section_id' => $section->id,
                'label' => 'Manual',
            ],
            [
                'y' => 0,
                'rotation' => 0,
                'seat_count' => 0,
            ]
        );

        // Create the seat
        $seat = SeatingSeat::create([
            'row_id' => $row->id,
            'label' => $data['label'],
            'x' => $data['x'],
            'y' => $data['y'],
            'angle' => $data['angle'] ?? 0,
            'shape' => $data['shape'] ?? 'circle',
            'seat_uid' => SeatingSeat::generateSeatUid(
                $section->section_code ?? $section->name,
                'Manual',
                $data['label']
            ),
        ]);

        // Update row seat count
        $row->increment('seat_count');

        $this->reloadSections();

        // Dispatch event to add to canvas
        $this->dispatch('seat-added', seat: $seat->toArray(), sectionId: $section->id);

        Notification::make()
            ->success()
            ->title('Seat added')
            ->body("Seat '{$data['label']}' added to section")
            ->send();
    }

    /**
     * Date trimise către view.
     */
    protected function getViewData(): array
    {
        return [
            // Păstrăm cheia 'layout' dacă Blade-ul tău se bazează pe ea.
            'layout'       => $this->seatingLayout,
            'sections'     => $this->sections,
            'canvasWidth'  => $this->seatingLayout->canvas_width,
            'canvasHeight' => $this->seatingLayout->canvas_height,
            'backgroundUrl' => $this->seatingLayout->background_image_path
                ? \Storage::disk('public')->url($this->seatingLayout->background_image_path)
                : null,
        ];
    }
}
