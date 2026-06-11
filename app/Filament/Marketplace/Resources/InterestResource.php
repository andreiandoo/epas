<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\InterestResource\Pages;
use App\Models\Interest;
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
 * F3 — Interests taxonomy admin (thematic angle of an activity).
 * Gated by the `discovery-module` microservice. Scoped per marketplace client.
 */
class InterestResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Interest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Interese';

    protected static ?string $modelLabel = 'Interes';

    protected static ?string $pluralModelLabel = 'Interese';

    protected static ?string $navigationParentItem = 'Activități';

    protected static ?int $navigationSort = 20;

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

            SC\Section::make('Interes')
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
                        Forms\Components\TextInput::make('icon_emoji')->label('Emoji')->maxLength(8)->placeholder('🧭'),
                        Forms\Components\TextInput::make('color')->label('Culoare (hex)')->maxLength(16)->placeholder('#E84527'),
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
                    ->getStateUsing(fn (Interest $r) => is_array($r->name) ? ($r->name[$lang] ?? $r->name['ro'] ?? $r->name['en'] ?? $r->slug) : $r->name)
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
            'index' => Pages\ListInterests::route('/'),
            'create' => Pages\CreateInterest::route('/create'),
            'edit' => Pages\EditInterest::route('/{record}/edit'),
        ];
    }
}
