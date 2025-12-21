<?php

namespace App\Filament\Organizer\Resources\EventResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Ticket Types';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name.ro')
                    ->label('Ticket Name (RO)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('name.en')
                    ->label('Ticket Name (EN)')
                    ->maxLength(255),

                Forms\Components\TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Forms\Components\TextInput::make('quantity')
                    ->label('Available Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Forms\Components\Textarea::make('description.ro')
                    ->label('Description (RO)')
                    ->rows(2),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\DateTimePicker::make('sale_start')
                    ->label('Sale Starts'),

                Forms\Components\DateTimePicker::make('sale_end')
                    ->label('Sale Ends'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name.ro')
            ->columns([
                Tables\Columns\TextColumn::make('name.ro')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Available')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Sold')
                    ->counts('tickets')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sale_start')
                    ->label('Sale Start')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sale_end')
                    ->label('Sale End')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Ticket Type'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
