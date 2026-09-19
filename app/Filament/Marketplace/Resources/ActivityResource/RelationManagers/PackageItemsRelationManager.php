<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;

use App\Models\Activity;
use App\Models\ActivityVariant;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What a package holds: access tickets and services, each x quantity. The
 * package is sold at its own price (its variant); the share of that price
 * per component is optional (reports / invoices), otherwise it is split by
 * the components' own prices.
 */
class PackageItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'packageItems';

    protected static ?string $title = 'Conținut pachet';

    protected static ?string $modelLabel = 'Componentă';

    protected static ?string $pluralModelLabel = 'Componente';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->product_type === 'package';
    }

    public function form(Schema $schema): Schema
    {
        $package = $this->getOwnerRecord();

        return $schema->schema([
            Forms\Components\Select::make('component_activity_id')
                ->label('Produs')
                ->options(fn () => Activity::where('marketplace_client_id', $package->marketplace_client_id)
                    ->where('product_type', '<>', 'package')
                    ->when($package->location_id, fn ($q) => $q->where('location_id', $package->location_id))
                    ->orderBy('id')->get()
                    ->mapWithKeys(fn ($a) => [$a->id => ($a->title['ro'] ?? $a->title['en'] ?? $a->slug)])
                    ->toArray())
                ->required()
                ->searchable()
                ->live(),
            Forms\Components\Select::make('component_variant_id')
                ->label('Variantă')
                ->options(fn (Get $get) => ActivityVariant::where('activity_id', $get('component_activity_id'))
                    ->orderBy('sort_order')->get()
                    ->mapWithKeys(fn ($v) => [$v->id => ($v->name['ro'] ?? $v->name['en'] ?? '#' . $v->id) . ' — ' . number_format($v->price_cents / 100, 2, ',', '.') . ' lei'])
                    ->toArray())
                ->placeholder('Prima variantă activă'),
            Forms\Components\TextInput::make('quantity')
                ->label('Cantitate în pachet')
                ->numeric()->required()->default(1)->minValue(1)->maxValue(50),
            Forms\Components\TextInput::make('allocated_price_cents')
                ->label('Parte din prețul pachetului')
                ->numeric()->minValue(0)->step(0.01)->suffix('lei')
                ->helperText('Opțional, pentru toate bucățile din pachet. Dacă e gol la toate, se împarte după prețurile produselor.')
                ->formatStateUsing(fn ($state) => $state !== null ? round($state / 100, 2) : null)
                ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null),
            Forms\Components\TextInput::make('sort_order')
                ->label('Ordine')
                ->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('component.title')
                    ->label('Produs')
                    ->formatStateUsing(fn ($state, $record) => $record->component?->title['ro'] ?? $record->component?->slug),
                Tables\Columns\TextColumn::make('componentVariant.name')
                    ->label('Variantă')
                    ->formatStateUsing(fn ($state, $record) => $record->componentVariant?->name['ro'] ?? '—'),
                Tables\Columns\TextColumn::make('quantity')->label('Cantitate'),
                Tables\Columns\TextColumn::make('allocated_price_cents')->label('Parte din preț')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state / 100, 2, ',', '.') . ' lei' : 'automat'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
