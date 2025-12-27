<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ArtistsRelationManager extends RelationManager
{
    protected static string $relationship = 'artists';
    protected static ?string $title = 'Artists';

    // Filament v4 (Schemas): folosim Schema, nu Forms\Form
    public function form(Schema $schema): Schema
    {
        // aici nu creăm artist nou; doar attach/detach
        return $schema->schema([
            Forms\Components\Select::make('artist_id')
                ->label('Artist')
                ->relationship('artists', 'name')
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'active',
                        'gray'    => 'retired',
                    ]),
            ])
            ->headerActions([
                AttachAction::make()->label('Attach Artist'),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
