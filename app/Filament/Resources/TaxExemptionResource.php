<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxExemptionResource\Pages;
use App\Models\Tax\TaxExemption;
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

class TaxExemptionResource extends Resource
{
    protected static ?string $model = TaxExemption::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Tax Exemptions';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Tax Exemption';

    protected static ?string $pluralModelLabel = 'Tax Exemptions';

    protected static ?string $slug = 'tax-exemptions';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    protected static function getExemptionTypeOptions(): array
    {
        return [
            'customer' => 'Customer',
            'ticket_type' => 'Ticket Type',
            'event' => 'Event',
            'product' => 'Product',
            'category' => 'Category',
        ];
    }

    protected static function getScopeOptions(): array
    {
        return [
            'all' => 'All Taxes',
            'general' => 'General Taxes Only',
            'local' => 'Local Taxes Only',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Exemption Details')
                    ->description('Configure the tax exemption')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Exemption Name')
                            ->required()
                            ->maxLength(190)
                            ->placeholder('e.g., Non-Profit Organization Exemption')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('exemption_type')
                            ->label('Exemption Type')
                            ->options(static::getExemptionTypeOptions())
                            ->required()
                            ->live()
                            ->helperText('Select what this exemption applies to'),

                        Forms\Components\Select::make('scope')
                            ->label('Tax Scope')
                            ->options(static::getScopeOptions())
                            ->default('all')
                            ->required()
                            ->helperText('Which types of taxes this exemption affects'),

                        Forms\Components\TextInput::make('exemption_percent')
                            ->label('Exemption Percentage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(100)
                            ->suffix('%')
                            ->required()
                            ->helperText('100% means fully exempt, 50% means half taxes')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->placeholder('Explain why this exemption is granted...')
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Target Selection')
                    ->description('Select the specific item this exemption applies to')
                    ->schema([
                        Forms\Components\Select::make('exemptable_id')
                            ->label(fn (Get $get) => match ($get('exemption_type')) {
                                'customer' => 'Select Customer',
                                'ticket_type' => 'Select Ticket Type',
                                'event' => 'Select Event',
                                'product' => 'Select Product',
                                'category' => 'Select Category',
                                default => 'Select Target',
                            })
                            ->options(function (Get $get) use ($tenant) {
                                $type = $get('exemption_type');
                                if (!$type) return [];

                                return match ($type) {
                                    'customer' => \App\Models\Customer::where('tenant_id', $tenant?->id)
                                        ->pluck('name', 'id')
                                        ->toArray(),
                                    'ticket_type' => \App\Models\TicketType::where('tenant_id', $tenant?->id)
                                        ->pluck('name', 'id')
                                        ->toArray(),
                                    'event' => \App\Models\Event::where('tenant_id', $tenant?->id)
                                        ->orderByDesc('start_date')
                                        ->pluck('name', 'id')
                                        ->toArray(),
                                    'product' => \App\Models\Product::where('tenant_id', $tenant?->id)
                                        ->pluck('name', 'id')
                                        ->toArray(),
                                    'category' => \App\Models\Category::where('tenant_id', $tenant?->id)
                                        ->pluck('name', 'id')
                                        ->toArray(),
                                    default => [],
                                };
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => !empty($get('exemption_type')))
                            ->helperText('Leave empty to apply to all items of the selected type'),

                        Forms\Components\Hidden::make('exemptable_type')
                            ->default(function (Get $get) {
                                return match ($get('exemption_type')) {
                                    'customer' => 'App\\Models\\Customer',
                                    'ticket_type' => 'App\\Models\\TicketType',
                                    'event' => 'App\\Models\\Event',
                                    'product' => 'App\\Models\\Product',
                                    'category' => 'App\\Models\\Category',
                                    default => null,
                                };
                            }),
                    ]),

                SC\Section::make('Validity Period')
                    ->description('Optionally set when this exemption is active')
                    ->collapsed()
                    ->schema([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->placeholder('Always'),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Valid Until')
                            ->placeholder('Forever')
                            ->afterOrEqual('valid_from'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive exemptions will not be applied')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('exemption_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->colors([
                        'primary' => 'customer',
                        'success' => 'ticket_type',
                        'warning' => 'event',
                        'danger' => 'product',
                        'gray' => 'category',
                    ]),

                Tables\Columns\TextColumn::make('scope')
                    ->label('Scope')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'all' => 'All Taxes',
                        'general' => 'General Only',
                        'local' => 'Local Only',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('exemption_percent')
                    ->label('Exemption')
                    ->formatStateUsing(fn ($state) => number_format($state, 0) . '%')
                    ->badge()
                    ->color(fn ($state) => $state >= 100 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('exemptable_id')
                    ->label('Target')
                    ->formatStateUsing(function ($record) {
                        if (!$record->exemptable_id) {
                            return 'All ' . ucfirst(str_replace('_', ' ', $record->exemption_type)) . 's';
                        }
                        return $record->exemptable?->name ?? "ID: {$record->exemptable_id}";
                    }),

                Tables\Columns\TextColumn::make('validity')
                    ->label('Status')
                    ->state(fn ($record) => $record->getValidityStatus())
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'info' => 'scheduled',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('exemption_type')
                    ->label('Exemption Type')
                    ->options(static::getExemptionTypeOptions()),
                Tables\Filters\SelectFilter::make('scope')
                    ->label('Scope')
                    ->options(static::getScopeOptions()),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxExemptions::route('/'),
            'create' => Pages\CreateTaxExemption::route('/create'),
            'edit' => Pages\EditTaxExemption::route('/{record}/edit'),
        ];
    }
}
