<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\PartnerArticleResource\Pages;
use App\Models\MarketplacePartner;
use App\Models\PartnerArticle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Articles media partners sent for artist pages. Read-only; an admin can hide one.
 */
class PartnerArticleResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = PartnerArticle::class;
    protected static ?string $slug = 'partner-articles';
    protected static ?string $navigationLabel = 'Articole parteneri';
    protected static ?string $modelLabel = 'articol partener';
    protected static ?string $pluralModelLabel = 'articole parteneri';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice(MarketplacePartner::MICROSERVICE);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', static::getMarketplaceClientId())
            ->with(['partner:id,name', 'artists:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('')
                    ->imageWidth(96)
                    ->imageHeight(54),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(70)
                    ->tooltip(fn (PartnerArticle $record) => $record->title)
                    ->url(fn (PartnerArticle $record) => $record->url, shouldOpenInNewTab: true)
                    ->description(fn (PartnerArticle $record) => $record->partner?->name),
                Tables\Columns\TextColumn::make('artists.name')
                    ->label('Artiști')
                    ->badge()
                    ->limitList(3),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicat')
                    ->dateTime('d.m.Y H:i', timezone: 'Europe/Bucharest')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_hidden')
                    ->label('Ascuns'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_partner_id')
                    ->label('Partener')
                    ->options(fn () => MarketplacePartner::where('marketplace_client_id', static::getMarketplaceClientId())
                        ->pluck('name', 'id')
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_hidden')
                    ->label('Ascuns'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerArticles::route('/'),
        ];
    }
}
