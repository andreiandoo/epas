<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ContactTagResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceContactTag;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactTagResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceContactTag::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Contact Tags';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Tag Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\ColorPicker::make('color')
                            ->default('#6366f1'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color'),
                Tables\Columns\TextColumn::make('customers_count')
                    ->counts('customers')
                    ->label('Contacts'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactTags::route('/'),
            'create' => Pages\CreateContactTag::route('/create'),
            'edit' => Pages\EditContactTag::route('/{record}/edit'),
        ];
    }
}
