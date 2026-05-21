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
        return parent::getEloquentQuery()->where('tenant_id', $tenantId);
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
                        ->relationship('type', 'name', fn ($q) => $q->where('tenant_id', auth()->user()?->tenant?->id))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $tenantId = auth()->user()?->tenant?->id;
                            $type = \App\Models\Leisure\PhysicalResourceType::create([
                                'tenant_id' => $tenantId,
                                'name' => $data['name'],
                                'slug' => \App\Models\Leisure\PhysicalResourceType::generateSlug($data['name'], $tenantId),
                                'is_active' => true,
                            ]);
                            return $type->id;
                        })
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            // Pre-fill the legacy resource_type string and the linked-tickets array from the type defaults.
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
            ->modifyQueryUsing(fn (Builder $q) => $q->with('type:id,name,icon,color,slug,linked_ticket_type_ids'))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('type.icon')->label('')->size('lg'),
                Tables\Columns\TextColumn::make('type.name')
                    ->label('Tip')
                    ->badge()
                    ->color(fn ($record) => $record->type?->color ? null : 'gray'),
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
                Tables\Filters\SelectFilter::make('physical_resource_type_id')
                    ->label('Tip resursă')
                    ->relationship('type', 'name', fn ($q) => $q->where('tenant_id', auth()->user()?->tenant?->id)),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Disponibilă',
                        'in_use' => 'În utilizare',
                        'maintenance' => 'Mentenanță',
                        'retired' => 'Scoasă din uz',
                    ]),
            ])
            ->headerActions([
                Action::make('bulkAdd')
                    ->label('Adaugă în bulk')
                    ->icon('heroicon-o-squares-plus')
                    ->color('success')
                    ->modalHeading('Adaugă mai multe unități deodată')
                    ->modalDescription('Generează N unități pentru un tip de resursă, cu nume auto-numerotate și QR-uri unice.')
                    ->modalSubmitActionLabel('Generează')
                    ->form([
                        Forms\Components\Select::make('type_id')
                            ->label('Tip resursă')
                            ->options(fn () => \App\Models\Leisure\PhysicalResourceType::where('tenant_id', auth()->user()?->tenant?->id)
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('count')
                            ->label('Câte unități')
                            ->numeric()->minValue(1)->maxValue(200)
                            ->default(5)
                            ->required(),
                        Forms\Components\TextInput::make('name_prefix')
                            ->label('Prefix nume')
                            ->placeholder('ex: Kayak Roșu #')
                            ->helperText('Numele final = prefix + index începând de la "start_index".')
                            ->required(),
                        Forms\Components\TextInput::make('start_index')
                            ->label('Index pornire')
                            ->numeric()->default(1)->minValue(1)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Disponibilă',
                                'maintenance' => 'Mentenanță',
                            ])
                            ->default('available'),
                    ])
                    ->action(function (array $data) {
                        $tenantId = auth()->user()?->tenant?->id;
                        $type = \App\Models\Leisure\PhysicalResourceType::find($data['type_id']);
                        if (! $type || $type->tenant_id !== $tenantId) {
                            \Filament\Notifications\Notification::make()->danger()->title('Tip invalid')->send();
                            return;
                        }
                        $created = 0;
                        for ($i = 0; $i < (int) $data['count']; $i++) {
                            $idx = (int) $data['start_index'] + $i;
                            PhysicalResource::create([
                                'tenant_id' => $tenantId,
                                'physical_resource_type_id' => $type->id,
                                'resource_type' => $type->slug,
                                'name' => $data['name_prefix'] . $idx,
                                'label' => strtoupper(\Illuminate\Support\Str::slug($type->slug)) . '-' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                                'qr_code' => PhysicalResource::generateQrCode($tenantId, $type->slug),
                                'status' => $data['status'] ?? 'available',
                                'linked_ticket_type_ids' => $type->linked_ticket_type_ids,
                            ]);
                            $created++;
                        }
                        \Filament\Notifications\Notification::make()
                            ->success()->title("Am adăugat {$created} unități")
                            ->body("Tip: {$type->name}")
                            ->send();
                    }),
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
