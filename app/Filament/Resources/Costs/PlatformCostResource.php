<?php

namespace App\Filament\Resources\Costs;

use App\Models\PlatformCost;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use BackedEnum;

class PlatformCostResource extends Resource
{
    protected static ?string $model = PlatformCost::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static \UnitEnum|string|null $navigationGroup = 'Finance';
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
                        ->options([
                            'server' => 'Server / Hosting',
                            'domain' => 'Domain',
                            'cdn' => 'CDN',
                            'service' => 'Service / SaaS',
                            'marketing' => 'Marketing',
                            'other' => 'Other',
                        ])
                        ->required(),

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
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'server' => 'Server / Hosting',
                        'domain' => 'Domain',
                        'cdn' => 'CDN',
                        'service' => 'Service / SaaS',
                        'marketing' => 'Marketing',
                        'other' => 'Other',
                    ]),

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
