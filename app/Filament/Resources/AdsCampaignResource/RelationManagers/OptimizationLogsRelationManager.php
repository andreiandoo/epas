<?php

namespace App\Filament\Resources\AdsCampaignResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OptimizationLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'optimizationLogs';

    protected static ?string $title = 'Optimization History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'budget_increase', 'campaign_resume', 'creative_activate' => 'success',
                        'budget_decrease', 'campaign_pause', 'creative_pause' => 'warning',
                        'ab_test_winner' => 'info',
                        'audience_expansion', 'audience_narrowing' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('description')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->description)
                    ->wrap(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'auto' => 'info',
                        'manual' => 'gray',
                        'ai_suggested' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('performer.name')
                    ->label('By')
                    ->default('System'),

                Tables\Columns\TextColumn::make('expected_improvement')
                    ->label('Expected')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 1) . '%' : '-'),

                Tables\Columns\TextColumn::make('actual_improvement')
                    ->label('Actual')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 1) . '%' : 'Pending')
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
