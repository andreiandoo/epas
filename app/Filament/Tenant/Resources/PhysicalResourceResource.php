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
    protected static ?string $navigationLabel = 'Inventar (unități)';
    protected static ?string $modelLabel = 'Unitate';
    protected static ?string $pluralModelLabel = 'Unități inventar';

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
        $q = parent::getEloquentQuery()->where('tenant_id', $tenantId);
        if (\Illuminate\Support\Facades\Schema::hasColumn('physical_resources', 'physical_resource_type_id')) {
            $q->with('type:id,name,icon,color,slug,linked_ticket_type_ids');
        }
        return $q;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Unitate fizică')
                ->description('Un echipament individual din inventar. Pentru categorii (Kayak, Bicicletă etc.) folosește "Tipuri resurse".')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('physical_resource_type_id')
                        ->label('Tip')
                        ->options(fn () => \App\Models\Leisure\PhysicalResourceType::query()
                            ->where('tenant_id', auth()->user()?->tenant?->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if (! $state) return;
                            $type = \App\Models\Leisure\PhysicalResourceType::find($state);
                            if ($type) {
                                $set('resource_type', $type->slug);
                                $set('linked_ticket_type_ids', $type->linked_ticket_type_ids);
                            }
                        }),

                    Forms\Components\TextInput::make('name')
                        ->label('Nume (afișat operatorilor)')
                        ->placeholder('ex: Kayak Roșu #1')
                        ->required(),

                    Forms\Components\TextInput::make('label')
                        ->label('Etichetă vizuală')
                        ->placeholder('ex: RED-01'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'available' => 'Disponibilă',
                            'in_use' => 'În utilizare',
                            'maintenance' => 'Mentenanță',
                            'retired' => 'Scoasă din uz',
                        ])
                        ->default('available')
                        ->required(),

                    Forms\Components\TextInput::make('qr_code')
                        ->label('QR Code')
                        ->disabled(fn ($context) => $context !== 'create')
                        ->helperText('Generat automat la creare. NU îl modifica după ce ai printat.'),

                    Forms\Components\Hidden::make('resource_type'),

                    Forms\Components\Select::make('linked_ticket_type_ids')
                        ->label('Bilete asociate (override pe această unitate)')
                        ->helperText('Default-uri vin din tipul de resursă. Modifică doar dacă vrei comportament diferit pentru această unitate.')
                        ->multiple()
                        ->options(function () {
                            $tenantId = auth()->user()?->tenant?->id;
                            return \App\Models\TicketType::query()
                                ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
                                ->whereIn('service_category', ['rental', 'activity'])
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('meta')
                        ->label('Atribute (size, color, condition)')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('type.name')
                    ->label('Tip')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etichetă')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('qr_code')
                    ->label('QR')
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'in_use' => 'info',
                        'maintenance' => 'warning',
                        'retired' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('physical_resource_type_id')
                    ->label('Tip resursă')
                    ->options(fn () => \App\Models\Leisure\PhysicalResourceType::query()
                        ->where('tenant_id', auth()->user()?->tenant?->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
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
