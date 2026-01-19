<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email_template_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('recipient_email')
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('failed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
