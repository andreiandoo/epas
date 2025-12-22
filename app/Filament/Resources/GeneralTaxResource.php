<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeneralTaxResource\Pages;
use App\Models\Tax\GeneralTax;
use App\Models\EventType;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

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
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
        $tenantCurrency = $tenant->currency ?? 'EUR';

        $currencies = [
            'EUR' => 'EUR - Euro',
            'USD' => 'USD - US Dollar',
            'GBP' => 'GBP - British Pound',
            'RON' => 'RON - Romanian Leu',
            'CHF' => 'CHF - Swiss Franc',
            'PLN' => 'PLN - Polish Zloty',
            'CZK' => 'CZK - Czech Koruna',
            'HUF' => 'HUF - Hungarian Forint',
            'SEK' => 'SEK - Swedish Krona',
            'NOK' => 'NOK - Norwegian Krone',
            'DKK' => 'DKK - Danish Krone',
        ];

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
                            ->placeholder('e.g., VAT, Sales Tax, Service Fee')
                            ->unique(
                                table: 'general_taxes',
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule
                                    ->where('tenant_id', $tenant?->id)
                                    ->where('event_type_id', request()->input('event_type_id'))
                            )
                            ->validationMessages([
                                'unique' => 'A tax with this name already exists for the selected event type.',
                            ]),

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

                        Forms\Components\Grid::make(3)
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
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options($currencies)
                                    ->default($tenantCurrency)
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('value_type') === 'fixed')
                                    ->required(fn (Get $get) => $get('value_type') === 'fixed')
                                    ->helperText('Required for fixed amount taxes'),
                            ]),

                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority taxes are applied first. Use this to control calculation order.'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->default(false)
                                    ->live()
                                    ->helperText('Compound taxes are calculated on the subtotal plus other non-compound taxes'),

                                Forms\Components\TextInput::make('compound_order')
                                    ->label('Compound Order')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (Get $get) => $get('is_compound'))
                                    ->helperText('Order in which compound taxes are applied (lower first)'),
                            ]),

                        Forms\Components\Textarea::make('explanation')
                            ->label('Explanation')
                            ->rows(3)
                            ->placeholder('Describe what this tax is for and when it applies...')
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Validity Period')
                    ->description('Optionally set when this tax is active. Leave empty for always active.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->placeholder('Always')
                            ->helperText('Tax becomes active on this date'),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Valid Until')
                            ->placeholder('Forever')
                            ->afterOrEqual('valid_from')
                            ->helperText('Tax expires after this date'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied to orders.')
                            ->columnSpanFull(),
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
                        return number_format($state, 2) . ' ' . ($record->currency ?? '');
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

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_compound')
                    ->label('Compound')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('validity')
                    ->label('Validity')
                    ->state(function ($record) {
                        return $record->getValidityStatus();
                    })
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'info' => 'scheduled',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('valid_from')
                    ->label('From')
                    ->date()
                    ->placeholder('Always')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Until')
                    ->date()
                    ->placeholder('Forever')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
                Tables\Filters\Filter::make('validity')
                    ->form([
                        Forms\Components\Select::make('validity_status')
                            ->options([
                                'active' => 'Currently Active',
                                'scheduled' => 'Scheduled (Future)',
                                'expired' => 'Expired',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['validity_status'], function ($query, $status) {
                            $today = now()->toDateString();
                            return match ($status) {
                                'active' => $query->where('is_active', true)
                                    ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                                    ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today)),
                                'scheduled' => $query->where('valid_from', '>', $today),
                                'expired' => $query->where('valid_until', '<', $today),
                                default => $query,
                            };
                        });
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
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
