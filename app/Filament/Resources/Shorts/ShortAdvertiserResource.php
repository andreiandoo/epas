<?php

namespace App\Filament\Resources\Shorts;

use App\Filament\Resources\Shorts\ShortAdvertiserResource\Pages\CreateShortAdvertiser;
use App\Filament\Resources\Shorts\ShortAdvertiserResource\Pages\EditShortAdvertiser;
use App\Filament\Resources\Shorts\ShortAdvertiserResource\Pages\ListShortAdvertisers;
use App\Models\ShortAdvertiser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Who pays for ads in the feed (D3).
 *
 * Core admin only: an external brand has no tenant to administer it from, and a
 * tenant must not be able to edit its own balance.
 */
class ShortAdvertiserResource extends Resource
{
    protected static ?string $model = ShortAdvertiser::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Ad advertisers';


    /* Submeniu sub Shorts: cele patru resurse sunt un singur feature,

       iar in bara laterala aratau ca patru lucruri fara legatura. */

    protected static ?string $navigationParentItem = 'Shorts';

    protected static ?string $modelLabel = 'Advertiser';

    protected static ?string $pluralModelLabel = 'Ad advertisers';

    protected static ?int $navigationSort = 53;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Advertiser')
                ->description('Credit is prepaid and is never edited by hand — use the "Add credit" action so every movement lands in the ledger.')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(160),

                    Forms\Components\Select::make('type')
                        ->options([
                            ShortAdvertiser::TYPE_TENANT => 'Tenant — an organiser boosting its own catalogue',
                            ShortAdvertiser::TYPE_HOUSE => 'House — our own inventory, never billed',
                            ShortAdvertiser::TYPE_EXTERNAL => 'External — a brand with no tenant account',
                        ])
                        ->default(ShortAdvertiser::TYPE_EXTERNAL)
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('tenant_id')
                        ->label('Tenant ID')
                        ->numeric()
                        ->visible(fn (Get $get) => $get('type') === ShortAdvertiser::TYPE_TENANT),

                    Forms\Components\TextInput::make('contact_email')->email()->maxLength(190),

                    Forms\Components\TextInput::make('website')->url()->maxLength(190),

                    Forms\Components\Select::make('status')
                        ->options([
                            ShortAdvertiser::STATUS_ACTIVE => 'Active',
                            ShortAdvertiser::STATUS_BLOCKED => 'Blocked',
                        ])
                        ->default(ShortAdvertiser::STATUS_ACTIVE)
                        ->required(),

                    Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ShortAdvertiser::TYPE_HOUSE => 'info',
                        ShortAdvertiser::TYPE_EXTERNAL => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('credit_cents')
                    ->label('Credit')
                    ->state(fn (ShortAdvertiser $record) => $record->isHouse()
                        ? '—'
                        : number_format($record->credit_cents / 100, 2))
                    ->color(fn (ShortAdvertiser $record) => ! $record->isHouse() && $record->credit_cents <= 0 ? 'danger' : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('promotions_count')
                    ->label('Campaigns')
                    ->counts('promotions'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === ShortAdvertiser::STATUS_ACTIVE ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(array_combine(
                    ShortAdvertiser::TYPES,
                    ShortAdvertiser::TYPES,
                )),
            ])
            ->recordActions([
                Action::make('topUp')
                    ->label('Add credit')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (ShortAdvertiser $record) => ! $record->isHouse())
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (major units, e.g. 250 = 250 RON)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->helperText('Invoice number, payment intent id — whatever ties this to the money.')
                            ->maxLength(190),
                    ])
                    ->action(function (ShortAdvertiser $record, array $data) {
                        $record->topUp((int) round(((float) $data['amount']) * 100), $data['reference'] ?? null);

                        Notification::make()
                            ->title('Credit added')
                            ->body($record->name.' now holds '.number_format($record->fresh()->credit_cents / 100, 2).'.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortAdvertisers::route('/'),
            'create' => CreateShortAdvertiser::route('/create'),
            'edit' => EditShortAdvertiser::route('/{record}/edit'),
        ];
    }
}
