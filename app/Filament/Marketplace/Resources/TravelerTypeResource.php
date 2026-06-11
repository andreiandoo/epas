<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TravelerTypeResource\Pages;
use App\Models\TravelerType;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * F3 — Traveler types taxonomy admin (who an activity is for).
 * Gated by the `discovery-module` microservice. Scoped per marketplace client.
 */
class TravelerTypeResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = TravelerType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Tipuri de călători';

    protected static ?string $modelLabel = 'Tip de călător';

    protected static ?string $pluralModelLabel = 'Tipuri de călători';

    protected static ?string $navigationParentItem = 'Activități';

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('discovery-module');
    }

    public static function getEloquentQuery(): Builder
    {
        $client = static::getMarketplaceClient();

        return parent::getEloquentQuery()->where('marketplace_client_id', $client?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')->default($marketplace?->id),

            SC\Section::make('Tip de călător')
                ->schema([
                    Forms\Components\TextInput::make("name.{$lang}")
                        ->label('Nume')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                            if ($state && ! $get('slug')) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('name.en')
                        ->label('Nume (EN)')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(191)
                        ->rule('alpha_dash')
                        ->helperText('Folosit în URL și în filtre.'),
                    SC\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('icon_emoji')->label('Emoji')->maxLength(8)->placeholder('👨‍👩‍👧'),
                        Forms\Components\TextInput::make('color')->label('Culoare (hex)')->maxLength(16)->placeholder('#1E4A3D'),
                        Forms\Components\TextInput::make('sort_order')->label('Ordine')->numeric()->default(0),
                    ]),
                    Forms\Components\Textarea::make("description.{$lang}")->label('Descriere')->rows(2),
                    Forms\Components\Toggle::make('is_visible')->label('Vizibil')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon_emoji')->label('')->width('3rem'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->getStateUsing(fn (TravelerType $r) => is_array($r->name) ? ($r->name[$lang] ?? $r->name['ro'] ?? $r->name['en'] ?? $r->slug) : $r->name)
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereRaw("LOWER(name->>'ro') LIKE ?", ['%' . mb_strtolower($search) . '%'])),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->color('gray'),
                Tables\Columns\TextColumn::make('activities_count')->label('Activități')->counts('activities')->badge(),
                Tables\Columns\TextColumn::make('sort_order')->label('Ordine')->sortable(),
                Tables\Columns\ToggleColumn::make('is_visible')->label('Vizibil'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTravelerTypes::route('/'),
            'create' => Pages\CreateTravelerType::route('/create'),
            'edit' => Pages\EditTravelerType::route('/{record}/edit'),
        ];
    }
}
