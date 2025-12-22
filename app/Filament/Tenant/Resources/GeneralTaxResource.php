<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\GeneralTaxResource\Pages;
use App\Models\Tax\GeneralTax;
use App\Models\EventType;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class GeneralTaxResource extends Resource
{
    protected static ?string $model = GeneralTax::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'General Taxes';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'General Tax';

    protected static ?string $pluralModelLabel = 'General Taxes';

    protected static ?string $slug = 'general-taxes';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Tax Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tax Name')
                            ->required()
                            ->maxLength(190)
                            ->placeholder('e.g., VAT, Sales Tax, Service Fee'),

                        Forms\Components\Select::make('event_type_id')
                            ->label('Event Type')
                            ->options(function () use ($tenantLanguage) {
                                return EventType::all()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => $type->name[$tenantLanguage] ?? $type->name['en'] ?? $type->slug
                                    ]);
                            })
                            ->searchable()
                            ->placeholder('All Event Types (leave empty for global)')
                            ->helperText('Select an event type to apply this tax only to specific events, or leave empty for all events.'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->placeholder('0.00'),

                                Forms\Components\Select::make('value_type')
                                    ->label('Value Type')
                                    ->options([
                                        'percent' => 'Percentage (%)',
                                        'fixed' => 'Fixed Amount',
                                    ])
                                    ->default('percent')
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('explanation')
                            ->label('Explanation')
                            ->rows(3)
                            ->placeholder('Describe what this tax is for and when it applies...')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied to orders.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('eventType.name')
                    ->label('Event Type')
                    ->formatStateUsing(function ($state) use ($tenantLanguage) {
                        if (is_array($state)) {
                            return $state[$tenantLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? 'All Types';
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->value_type === 'percent') {
                            return number_format($state, 2) . '%';
                        }
                        return number_format($state, 2);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('value_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'primary' => 'percent',
                        'success' => 'fixed',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'percent' ? 'Percentage' : 'Fixed'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('value_type')
                    ->label('Value Type')
                    ->options([
                        'percent' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ]),
                Tables\Filters\SelectFilter::make('event_type_id')
                    ->label('Event Type')
                    ->options(function () use ($tenantLanguage) {
                        return EventType::all()
                            ->mapWithKeys(fn ($type) => [
                                $type->id => $type->name[$tenantLanguage] ?? $type->name['en'] ?? $type->slug
                            ]);
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
            'index' => Pages\ListGeneralTaxes::route('/'),
            'create' => Pages\CreateGeneralTax::route('/create'),
            'edit' => Pages\EditGeneralTax::route('/{record}/edit'),
        ];
    }
}
