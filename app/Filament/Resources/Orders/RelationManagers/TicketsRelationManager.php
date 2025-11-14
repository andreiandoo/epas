<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Type'),

                Tables\Columns\TextColumn::make('ticketType.event.title')
                    ->label('Event'),

                Tables\Columns\TextColumn::make('performance.starts_at')
                    ->label('Showtime')
                    ->dateTime(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'valid',
                        'gray'    => 'used',
                        'danger'  => 'void',
                    ]),
            ])
            ->headerActions([]) // no create from here
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make()
                //     ->label('Change Status')
                //     ->form([
                //         Forms\Components\Select::make('status')
                //             ->options([
                //                 'valid' => 'Valid',
                //                 'used'  => 'Used',
                //                 'void'  => 'Void',
                //             ])
                //             ->required(),
                //     ])
                //     ->using(function ($record, array $data) {
                //         $record->update(['status' => $data['status']]);
                //         return $record;
                //     }),
            ])
            ->bulkActions([]);
    }
}
