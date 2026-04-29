<?php

namespace App\Filament\Resources\SystemErrors\Tables;

use App\Models\SystemError;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SystemErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('level_name')
                    ->label('Level')
                    ->badge()
                    ->color(fn (SystemError $r) => $r->severityColor()),
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('source')
                    ->label('Source'),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(120),
            ]);
    }
}
