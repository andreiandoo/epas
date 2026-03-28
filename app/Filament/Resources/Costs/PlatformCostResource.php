<?php

namespace App\Filament\Resources\Costs;

use App\Models\PlatformCost;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use BackedEnum;
use UnitEnum;

class PlatformCostResource extends Resource
{
    protected static ?string $model = PlatformCost::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';
    protected static UnitEnum|string|null $navigationGroup = 'Operational';
    protected static ?int $navigationSort = 20;
    protected static ?string $modelLabel = 'Platform Cost';
    protected static ?string $pluralModelLabel = 'Platform Costs';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Cost Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Ploi Server, Cloudflare CDN'),

                    Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options(PlatformCost::CATEGORY_LABELS)
                        ->required()
                        ->searchable(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            SC\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('EUR'),

                    Forms\Components\Select::make('billing_cycle')
                        ->label('Billing Cycle')
                        ->options([
                            'monthly' => 'Monthly',
                            'yearly' => 'Yearly',
                            'one_time' => 'One-time',
                        ])
                        ->default('monthly')
                        ->required(),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->native(false)
                        ->default(now()),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->native(false)
                        ->helperText('Leave empty for ongoing costs'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(3),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'primary' => 'server',
                        'success' => 'domain',
                        'info' => 'cdn',
                        'warning' => 'service',
                        'danger' => 'marketing',
                        'gray' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_amount')
                    ->label('Monthly')
                    ->money('EUR')
                    ->getStateUsing(fn ($record) => $record->monthly_amount)
                    ->sortable(false),

                Tables\Columns\BadgeColumn::make('billing_cycle')
                    ->colors([
                        'primary' => 'monthly',
                        'success' => 'yearly',
                        'gray' => 'one_time',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('start_date')
                    ->date('d M Y')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('next_payment')
                    ->label('Next Payment')
                    ->getStateUsing(fn ($record) => $record->next_payment_date?->format('d M Y'))
                    ->sortable(false)
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->isDueSoon(5) => 'danger',
                        $record->next_payment_date && $record->next_payment_date->diffInDays(now(), absolute: true) <= 15 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordClasses(fn (PlatformCost $record) => $record->isDueSoon(5) ? 'bg-danger-50 dark:bg-danger-950/30' : '')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(PlatformCost::CATEGORY_LABELS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                        'one_time' => 'One-time',
                    ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->getTitleFromRecordUsing(fn ($record) => $record->category_label),
            ])
            ->defaultGroup('category')
            ->recordUrl(fn ($record) => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformCosts::route('/'),
            'create' => Pages\CreatePlatformCost::route('/create'),
            'edit' => Pages\EditPlatformCost::route('/{record}/edit'),
        ];
    }
}
