<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ContactListResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceCustomer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ContactListResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceContactList::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Contact Lists';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('List Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                SC\Section::make('Subscribers')
                    ->schema([
                        Forms\Components\Placeholder::make('subscriber_count')
                            ->content(fn ($record) => $record ? $record->subscribers()->count() . ' subscribers' : '0 subscribers'),

                        Forms\Components\Select::make('add_subscribers')
                            ->label('Add Customers to List')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $marketplace = static::getMarketplaceClient();
                                return MarketplaceCustomer::where('marketplace_client_id', $marketplace?->id)
                                    ->where(function ($q) use ($search) {
                                        $q->where('email', 'like', "%{$search}%")
                                          ->orWhere('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name', 'like', "%{$search}%");
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->pluck('email', 'id');
                            })
                            ->getOptionLabelsUsing(function (array $values) {
                                return MarketplaceCustomer::whereIn('id', $values)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => "{$c->full_name} <{$c->email}>"]);
                            })
                            ->helperText('Search and select customers to add to this list')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->counts('subscribers')
                    ->label('Subscribers'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('manage_subscribers')
                    ->icon('heroicon-o-users')
                    ->url(fn ($record) => static::getUrl('subscribers', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactLists::route('/'),
            'create' => Pages\CreateContactList::route('/create'),
            'edit' => Pages\EditContactList::route('/{record}/edit'),
            'subscribers' => Pages\ManageSubscribers::route('/{record}/subscribers'),
        ];
    }
}
