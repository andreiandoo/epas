<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeatingLayoutResource\Pages;
use App\Models\Seating\SeatingLayout;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use BackedEnum;

class SeatingLayoutResource extends Resource 
{
    protected static ?string $model = SeatingLayout::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static \UnitEnum|string|null $navigationGroup = 'Venues & Mapping';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Seating Layouts';
    protected static ?string $modelLabel = 'Seating Layout';
    protected static ?string $pluralModelLabel = 'Seating Layouts';

    //protected static ?string $navigationParentItem = 'Venues';

    // protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    // protected static ?string $navigationLabel = 'Seating Layouts';

    // protected static ?string $navigationGroup = 'Venues';

    // protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('venue_id')
                            ->relationship('venue', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(1000)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                SC\Section::make('Canvas Settings')
                    ->schema([
                        Forms\Components\TextInput::make('canvas_width')
                            ->label('Canvas Width (px)')
                            ->required()
                            ->numeric()
                            ->default(config('seating.canvas.default_width', 1920))
                            ->minValue(config('seating.canvas.min_width', 800))
                            ->maxValue(config('seating.canvas.max_width', 4096))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('canvas_height')
                            ->label('Canvas Height (px)')
                            ->required()
                            ->numeric()
                            ->default(config('seating.canvas.default_height', 1080))
                            ->minValue(config('seating.canvas.min_height', 600))
                            ->maxValue(config('seating.canvas.max_height', 4096))
                            ->columnSpan(1),

                        Forms\Components\FileUpload::make('background_image_path')
                            ->label('Background Image')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                            ->disk('public')
                            ->directory('seating/backgrounds')
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->imagePreviewHeight('250')
                            ->hint('Optional venue floor plan or background image (max 10MB, uploaded as-is without compression)')
                            ->hintIcon('heroicon-o-information-circle')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                SC\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('version')
                            ->content(fn ($record) => $record?->version ?? 1),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn ($record) => $record?->created_at?->format('M d, Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last Updated')
                            ->content(fn ($record) => $record?->updated_at?->format('M d, Y H:i') ?? '-'),
                    ])
                    ->columns(3)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->url(fn (SeatingLayout $record) => static::getUrl('designer', ['record' => $record])),

                Tables\Columns\TextColumn::make('venue.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('canvas_width')
                    ->label('Canvas')
                    ->formatStateUsing(fn ($record) => "{$record->canvas_width}x{$record->canvas_height}")
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Sections')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('version')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn (SeatingLayout $record) => static::getUrl('edit', ['record' => $record])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),

                Tables\Filters\SelectFilter::make('venue')
                    ->relationship('venue', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()) ?? $record->getTranslation('name', 'en') ?? 'Unnamed Venue')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeatingLayouts::route('/'),
            'create' => Pages\CreateSeatingLayout::route('/create'),
            'edit' => Pages\EditSeatingLayout::route('/{record}/edit'),
            'designer' => Pages\DesignerSeatingLayout::route('/{record}/designer'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['venue', 'sections']);
    }
}
