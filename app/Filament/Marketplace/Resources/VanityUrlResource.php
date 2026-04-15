<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\VanityUrlResource\Pages;
use App\Models\MarketplaceVanityUrl;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class VanityUrlResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceVanityUrl::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Redirecturi';
    protected static \UnitEnum|string|null $navigationGroup = 'Configurare';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'Redirect';
    protected static ?string $pluralModelLabel = 'Redirecturi';
    protected static ?string $slug = 'vanity-urls';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            SC\Section::make('Configurare redirect')
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label('URL scurt (slug)')
                        ->prefix('ambilet.ro/')
                        ->required()
                        ->maxLength(100)
                        ->regex('/^[a-z][a-z0-9-]{0,99}$/')
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('marketplace_client_id', $marketplace?->id))
                        ->helperText('Ex: qfeelploiesti'),

                    Forms\Components\Select::make('target_type')
                        ->label('Tip destinatie')
                        ->options([
                            'external_url' => 'URL redirect',
                            'artist' => 'Pagina artist',
                            'event' => 'Pagina eveniment',
                            'venue' => 'Pagina venue',
                            'organizer' => 'Pagina organizator',
                        ])
                        ->default('external_url')
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('target_url')
                        ->label('URL destinatie')
                        ->required()
                        ->maxLength(500)
                        ->helperText('Ex: https://ambilet.ro/qfeel#ploiesti')
                        ->visible(fn ($get) => ($get('target_type') ?? 'external_url') === 'external_url'),

                    Forms\Components\TextInput::make('target_id')
                        ->label('ID entitate')
                        ->numeric()
                        ->visible(fn ($get) => in_array($get('target_type'), ['artist', 'event', 'venue', 'organizer'])),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activ')
                        ->default(true),

                    Forms\Components\TextInput::make('notes')
                        ->label('Nota interna')
                        ->maxLength(255),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('URL scurt')
                    ->formatStateUsing(fn ($state) => '/' . $state)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('target_type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'external_url' => 'URL redirect',
                        'artist' => 'Artist',
                        'event' => 'Eveniment',
                        'venue' => 'Venue',
                        'organizer' => 'Organizator',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'external_url' => 'info',
                        'artist' => 'success',
                        'event' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('target_url')
                    ->label('Destinatie')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),
                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Click-uri')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Nota')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('target_type')
                    ->options([
                        'external_url' => 'URL redirect',
                        'artist' => 'Artist',
                        'event' => 'Eveniment',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activ'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVanityUrls::route('/'),
            'create' => Pages\CreateVanityUrl::route('/create'),
            'edit' => Pages\EditVanityUrl::route('/{record}/edit'),
        ];
    }
}
