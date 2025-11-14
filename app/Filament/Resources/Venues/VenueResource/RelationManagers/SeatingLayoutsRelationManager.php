<?php

namespace App\Filament\Resources\Venues\VenueResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SeatingLayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'seatingLayouts';

    protected static ?string $title = 'Seating Layouts';

    protected static ?string $modelLabel = 'Seating Layout';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3),

                Forms\Components\TextInput::make('canvas_width')
                    ->label('Canvas Width (px)')
                    ->required()
                    ->numeric()
                    ->default(config('seating.canvas.default_width', 800)),

                Forms\Components\TextInput::make('canvas_height')
                    ->label('Canvas Height (px)')
                    ->required()
                    ->numeric()
                    ->default(config('seating.canvas.default_height', 600)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => \App\Filament\Resources\SeatingLayoutResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('canvas_width')
                    ->label('Canvas')
                    ->formatStateUsing(fn ($record) => "{$record->canvas_width}x{$record->canvas_height}"),

                Tables\Columns\TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Sections'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ]);
    }
}
