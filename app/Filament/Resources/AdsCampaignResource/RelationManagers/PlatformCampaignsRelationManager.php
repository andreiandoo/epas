<?php

namespace App\Filament\Resources\AdsCampaignResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformCampaignsRelationManager extends RelationManager
{
    protected static string $relationship = 'platformCampaigns';

    protected static ?string $title = 'Platform Performance';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'google' => 'Google Ads',
                        'tiktok' => 'TikTok',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'facebook' => 'info',
                        'instagram' => 'warning',
                        'google' => 'success',
                        'tiktok' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variant')
                    ->badge(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => fn ($state) => in_array($state, ['pending_creation', 'creating']),
                        'success' => 'active',
                        'danger' => fn ($state) => in_array($state, ['failed', 'deleted']),
                        'info' => fn ($state) => in_array($state, ['paused', 'ended']),
                    ]),

                Tables\Columns\TextColumn::make('budget_allocated')
                    ->label('Budget')
                    ->money('eur'),

                Tables\Columns\TextColumn::make('impressions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reach')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state) => number_format((float)$state, 2) . '%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cpc')
                    ->label('CPC')
                    ->money('eur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('spend')
                    ->label('Spend')
                    ->money('eur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversions')
                    ->label('Conv.')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('revenue')
                    ->money('eur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('roas')
                    ->label('ROAS')
                    ->formatStateUsing(fn ($state) => number_format((float)$state, 2) . 'x')
                    ->color(fn ($state) => (float)$state >= 2 ? 'success' : ((float)$state >= 1 ? 'warning' : 'danger'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->since()
                    ->toggleable(),
            ])
            ->defaultSort('spend', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
