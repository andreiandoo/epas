<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\MerchandiseAllocationResource\Pages;
use App\Models\MerchandiseAllocation;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class MerchandiseAllocationResource extends Resource
{
    protected static ?string $model = MerchandiseAllocation::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Alocare marfa';
    protected static ?string $modelLabel = 'Alocare';
    protected static ?string $pluralModelLabel = 'Alocari marfa';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant && $tenant->tenant_type === TenantType::Festival;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Alocare marfa catre vendor')
                    ->schema([
                        Forms\Components\Select::make('festival_edition_id')
                            ->label('Editie festival')
                            ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('merchandise_item_id')
                            ->label('Produs')
                            ->relationship('item', 'name', modifyQueryUsing: function (Builder $query, \Filament\Schemas\Components\Utilities\Get $get) {
                                $tenant = auth()->user()->tenant;
                                $query->where('tenant_id', $tenant?->id);
                                if ($editionId = $get('festival_edition_id')) {
                                    $query->where('festival_edition_id', $editionId);
                                }
                                return $query;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->quantity} {$record->unit} disponibil)"),
                        Forms\Components\Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('quantity_allocated')
                            ->label('Cantitate alocata')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\DateTimePicker::make('allocated_at')
                            ->label('Data alocare')
                            ->default(now()),
                    ])->columns(2),

                SC\Section::make('Retur')
                    ->schema([
                        Forms\Components\TextInput::make('quantity_returned')
                            ->label('Cantitate returnata')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\DateTimePicker::make('returned_at')
                            ->label('Data retur'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'allocated'      => 'Alocat',
                                'partial_return'  => 'Retur partial',
                                'returned'        => 'Returnat complet',
                            ])
                            ->default('allocated')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observatii')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('edition.name')
                    ->label('Editie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Produs')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_allocated')
                    ->label('Alocat')
                    ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . ' ' . ($record->item?->unit ?? 'buc'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_returned')
                    ->label('Returnat')
                    ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . ' ' . ($record->item?->unit ?? 'buc'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Ramas')
                    ->getStateUsing(fn ($record) => $record->quantityOutstanding() . ' ' . ($record->item?->unit ?? 'buc')),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'info'    => 'allocated',
                        'warning' => 'partial_return',
                        'success' => 'returned',
                    ]),
                Tables\Columns\TextColumn::make('allocated_at')
                    ->label('Data alocare')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('festival_edition_id')
                    ->label('Editie')
                    ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                        $tenant = auth()->user()->tenant;
                        return $query->where('tenant_id', $tenant?->id);
                    }),
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name', modifyQueryUsing: function (Builder $query) {
                        $tenant = auth()->user()->tenant;
                        return $query->where('tenant_id', $tenant?->id);
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'allocated'      => 'Alocat',
                        'partial_return'  => 'Retur partial',
                        'returned'        => 'Returnat complet',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('markReturn')
                    ->label('Marcheaza retur')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status !== 'returned')
                    ->form([
                        Forms\Components\TextInput::make('return_qty')
                            ->label('Cantitate retur')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                    ])
                    ->action(function ($record, array $data) {
                        $maxReturn = $record->quantityOutstanding();
                        if ($data['return_qty'] > $maxReturn) {
                            Notification::make()
                                ->title("Cantitatea maxima de retur este {$maxReturn}")
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->markReturned((float) $data['return_qty']);

                        Notification::make()
                            ->title('Retur inregistrat')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMerchandiseAllocations::route('/'),
            'create' => Pages\CreateMerchandiseAllocation::route('/create'),
            'edit'   => Pages\EditMerchandiseAllocation::route('/{record}/edit'),
        ];
    }
}
