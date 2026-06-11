<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\LeisureCapacityResource\Pages;
use App\Models\Leisure\TicketTypeCapacity;
use App\Models\TicketType;
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

class LeisureCapacityResource extends Resource
{
    protected static ?string $model = TicketTypeCapacity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-no-symbol';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Excepții capacități';
    protected static ?string $modelLabel = 'Excepție';
    protected static ?string $pluralModelLabel = 'Excepții capacități';

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
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId)
            ->with(['ticketType:id,name,price_cents']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Excepție capacitate')
                ->description('Modifică capacitatea pentru o zi/slot specific. Restul zilelor folosesc valorile default din Produse & Bilete → Disponibilitate generală.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('ticket_type_id')
                        ->label('Tip bilet')
                        ->relationship('ticketType', 'name', function (Builder $query) {
                            $tenantId = auth()->user()?->tenant?->id;
                            return $query->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId));
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\DatePicker::make('capacity_date')
                        ->label('Data')
                        ->required(),

                    Forms\Components\TimePicker::make('time_slot_start')
                        ->label('Slot orar (start)')
                        ->seconds(false)
                        ->helperText('Lasă gol pentru capacitate pe toată ziua.'),

                    Forms\Components\TimePicker::make('time_slot_end')
                        ->label('Slot orar (sfârșit)')
                        ->seconds(false),

                    Forms\Components\TextInput::make('capacity')
                        ->label('Capacitate totală')
                        ->numeric()
                        ->minValue(0)
                        ->required(),

                    Forms\Components\TextInput::make('price_override_cents')
                        ->label('Override preț (cenți)')
                        ->numeric()
                        ->helperText('Override doar pentru această dată/slot. Lasă gol pentru a folosi prețul de bază + regulile.'),

                    Forms\Components\Toggle::make('is_closed')
                        ->label('Închis')
                        ->helperText('Marchează ziua/slotul ca indisponibil chiar dacă mai sunt locuri.'),

                    Forms\Components\TextInput::make('note')
                        ->label('Notă internă')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('capacity_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('capacity_date')
                    ->label('Data')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Tip bilet')
                    ->searchable(),

                Tables\Columns\TextColumn::make('time_slot_start')
                    ->label('Slot')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) return 'Toată ziua';
                        return $record->time_slot_start->format('H:i') . ' – ' . ($record->time_slot_end?->format('H:i') ?? '?');
                    }),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacitate')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('sold')
                    ->label('Vândut')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('Rezervat')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Rămas')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state, $record) => match ($record->status) {
                        'sold_out' => 'danger',
                        'limited' => 'warning',
                        'closed' => 'gray',
                        'unavailable' => 'gray',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'available' => 'success',
                        'limited' => 'warning',
                        'sold_out' => 'danger',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_closed')
                    ->label('Închis')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ticket_type_id')
                    ->label('Tip bilet')
                    ->relationship('ticketType', 'name', function (Builder $query) {
                        $tenantId = auth()->user()?->tenant?->id;
                        return $query->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId));
                    }),
                Tables\Filters\TernaryFilter::make('is_closed')
                    ->label('Închis'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleClosed')
                    ->label(fn ($record) => $record->is_closed ? 'Redeschide' : 'Închide')
                    ->icon('heroicon-o-lock-closed')
                    ->color(fn ($record) => $record->is_closed ? 'success' : 'warning')
                    ->action(fn ($record) => $record->update(['is_closed' => ! $record->is_closed])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('close')
                        ->label('Închide')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['is_closed' => true])),
                    BulkAction::make('reopen')
                        ->label('Redeschide')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_closed' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeisureCapacities::route('/'),
            'create' => Pages\CreateLeisureCapacity::route('/create'),
            'edit' => Pages\EditLeisureCapacity::route('/{record}/edit'),
        ];
    }
}
