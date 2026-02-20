<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PartnerArtistResource\Pages;
use App\Models\Artist;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class PartnerArtistResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Artist::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Partner Artists';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Partner Artist';

    protected static ?string $pluralModelLabel = 'Partner Artists';

    protected static ?string $slug = 'partner-artists';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Artist Info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->disabled(),

                        Forms\Components\Toggle::make('is_partner')
                            ->label('Is Partner Artist')
                            ->helperText('Partner artists are featured prominently on the marketplace.'),

                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Partner Notes')
                            ->rows(4)
                            ->helperText('Internal notes about this partnership.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl('/images/placeholder-artist.jpg'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_partner')
                    ->label('Partner')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('partner_notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label('Partner Artists Only'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPartnerArtists::route('/'),
            'edit'   => Pages\EditPartnerArtist::route('/{record}/edit'),
        ];
    }
}
