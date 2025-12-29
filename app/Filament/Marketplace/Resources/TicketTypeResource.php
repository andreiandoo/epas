<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\TicketTypeResource\Pages;
use App\Models\TicketType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class TicketTypeResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = TicketType::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Ticket Types';
    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->whereHas('event', function ($query) use ($marketplace) {
                $query->where('marketplace_client_id', $marketplace?->id);
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Ticket Type Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', modifyQueryUsing: function (Builder $query) {
                                $marketplace = static::getMarketplaceClient();
                                return $query->where('marketplace_client_id', $marketplace?->id);
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('€'),
                        Forms\Components\Select::make('currency')
                            ->options([
                                'EUR' => 'EUR',
                                'RON' => 'RON',
                                'USD' => 'USD',
                            ])
                            ->default('EUR'),
                        Forms\Components\TextInput::make('available_quantity')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('max_per_order')
                            ->numeric()
                            ->default(10),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn ($record) => $record->event?->getTranslation('title', app()->getLocale()) ?? '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Available')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTypes::route('/'),
            'create' => Pages\CreateTicketType::route('/create'),
            'edit' => Pages\EditTicketType::route('/{record}/edit'),
        ];
    }
}
