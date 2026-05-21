<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PhysicalResourceResource\Pages;
use App\Models\Leisure\PhysicalResource;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;

class PhysicalResourceResource extends Resource
{
    protected static ?string $model = PhysicalResource::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 30;
    protected static ?string $navigationLabel = 'Inventar fizic';
    protected static ?string $modelLabel = 'Resursă fizică';
    protected static ?string $pluralModelLabel = 'Resurse fizice';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant?->id;
        return parent::getEloquentQuery()->where('tenant_id', $tenantId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Resursă fizică')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('resource_type')
                        ->label('Tip resursă')
                        ->placeholder('ex: boat, kayak, bike, sled, locker')
                        ->required(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nume (afișat operatorilor)')
                        ->placeholder('ex: Barca Roșie #3')
                        ->required(),

                    Forms\Components\TextInput::make('label')
                        ->label('Etichetă vizuală')
                        ->placeholder('ex: ROȘIE-03'),

                    Forms\Components\TextInput::make('qr_code')
                        ->label('QR Code')
                        ->disabled(fn ($context) => $context !== 'create')
                        ->helperText('Generat automat la creare. NU îl modifica după ce ai printat.'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'available' => 'Disponibilă',
                            'in_use' => 'În utilizare',
                            'maintenance' => 'Mentenanță',
                            'retired' => 'Scoasă din uz',
                        ])
                        ->default('available')
                        ->required(),

                    Forms\Components\Select::make('linked_ticket_type_ids')
                        ->label('Bilete asociate (whitelist)')
                        ->helperText('Lasă gol pentru a permite orice bilet de tip rental al acestui tenant.')
                        ->multiple()
                        ->options(function () {
                            $tenantId = auth()->user()?->tenant?->id;
                            return \App\Models\TicketType::query()
                                ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
                                ->whereIn('service_category', ['rental', 'activity'])
                                ->pluck('name', 'id');
                        })
                        ->searchable(),

                    Forms\Components\KeyValue::make('meta')
                        ->label('Atribute (size, color, condition)')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('resource_type')
            ->columns([
                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etichetă')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('qr_code')
                    ->label('QR')
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'available' => 'success',
                        'in_use' => 'info',
                        'maintenance' => 'warning',
                        'retired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('activeRental.started_at')
                    ->label('Rental început')
                    ->dateTime('d.m H:i')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('resource_type')
                    ->options(fn () => PhysicalResource::query()
                        ->where('tenant_id', auth()->user()?->tenant?->id)
                        ->distinct()
                        ->pluck('resource_type', 'resource_type')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Disponibilă',
                        'in_use' => 'În utilizare',
                        'maintenance' => 'Mentenanță',
                        'retired' => 'Scoasă din uz',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('printQr')
                    ->label('Print QR')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => route('leisure.qr-print', ['ids' => [$record->id]]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printQrBulk')
                        ->label('Print QR codes')
                        ->icon('heroicon-o-printer')
                        ->action(fn ($records) => redirect()->route('leisure.qr-print', ['ids' => $records->pluck('id')->toArray()])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhysicalResources::route('/'),
            'create' => Pages\CreatePhysicalResource::route('/create'),
            'edit' => Pages\EditPhysicalResource::route('/{record}/edit'),
        ];
    }
}
