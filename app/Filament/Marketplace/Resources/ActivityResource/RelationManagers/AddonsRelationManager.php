<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Extras on a product (life jacket, photo pack…): a number included per unit
 * bought, then paid ones up to a cap per unit.
 */
class AddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'addons';

    protected static ?string $title = 'Suplimente';

    protected static ?string $modelLabel = 'Supliment';

    protected static ?string $pluralModelLabel = 'Suplimente';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->product_type !== 'package';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name.ro')
                ->label(__('Nume'))
                ->required()
                ->maxLength(120)
                ->placeholder(__('ex: Vestă de salvare, Pachet foto')),
            Forms\Components\TextInput::make('price_cents')
                ->label(__('Preț'))
                ->numeric()->required()->default(0)->minValue(0)->step(0.01)->suffix('lei')
                ->formatStateUsing(fn ($state) => $state !== null ? round($state / 100, 2) : null)
                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
            Forms\Components\TextInput::make('included_qty')
                ->label(__('Incluse gratuit / unitate'))
                ->helperText(__('Câte primește clientul fără să plateasca, la fiecare bilet.'))
                ->numeric()->default(0)->minValue(0)->maxValue(50),
            Forms\Components\TextInput::make('max_per_unit')
                ->label(__('Plătite, maxim / unitate'))
                // The customer's ceiling is the sum of the two, and the site now prints it next to
                // the stepper; 0 here means "nothing to buy beyond what is already included".
                ->helperText(__('Câte mai poate cumpăra peste cele incluse. Total maxim pe bilet = incluse + acest număr.'))
                ->numeric()->default(0)->minValue(0)->maxValue(50),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('Ordine'))
                ->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Activ'))
                ->default(true)
                ->inline(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name.ro')->label(__('Supliment')),
                Tables\Columns\TextColumn::make('price_cents')->label(__('Preț'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2, ',', '.') . ' lei'),
                Tables\Columns\TextColumn::make('included_qty')->label(__('Incluse')),
                Tables\Columns\TextColumn::make('max_per_unit')->label(__('Max. plătite')),
                Tables\Columns\IconColumn::make('is_active')->label(__('Activ'))->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
