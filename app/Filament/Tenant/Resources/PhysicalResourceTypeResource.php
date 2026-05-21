<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PhysicalResourceTypeResource\Pages;
use App\Models\Leisure\PhysicalResourceType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class PhysicalResourceTypeResource extends Resource
{
    protected static ?string $model = PhysicalResourceType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 28;
    protected static ?string $navigationLabel = 'Tipuri resurse';
    protected static ?string $modelLabel = 'Tip resursă';
    protected static ?string $pluralModelLabel = 'Tipuri resurse';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()?->tenant?->id)
            ->withCount('resources');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Tip resursă')
                ->description('Catalog de produse care pot fi închiriate. Aici definești categoria, pe Inventar adaugi unitățile efective.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume tip')
                        ->placeholder('ex: Kayak, Bicicletă MTB, Barcă cu pedale')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, $get) {
                            if (! $state || $get('slug')) return;
                            $set('slug', \Illuminate\Support\Str::slug($state));
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->helperText('Identificator unic. Folosit ca prefix pentru QR codes.')
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descriere')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('icon')
                        ->label('Emoji / Icon')
                        ->placeholder('🛶'),
                    Forms\Components\ColorPicker::make('color')
                        ->label('Culoare'),

                    Forms\Components\FileUpload::make('image_url')
                        ->label('Imagine reprezentativă')
                        ->image()
                        ->directory('leisure/resource-types')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('linked_ticket_type_ids')
                        ->label('Bilete asociate (default)')
                        ->multiple()
                        ->options(function () {
                            $tenantId = auth()->user()?->tenant?->id;
                            return \App\Models\TicketType::query()
                                ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
                                ->whereIn('service_category', ['rental', 'activity'])
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->helperText('Care tipuri de bilete acceptă această resursă la rental. Se propagă automat pe unitățile noi.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activ')
                        ->default(true),

                    Forms\Components\KeyValue::make('meta')
                        ->label('Atribute (size, color, …)')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label(''),
                Tables\Columns\ImageColumn::make('image_url')->label('Img')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()
                    ->description(fn ($record) => $record->description),
                Tables\Columns\TextColumn::make('slug')->fontFamily('mono')->toggleable(),
                Tables\Columns\TextColumn::make('resources_count')
                    ->label('Unități')
                    ->alignEnd()
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Activ'),
            ])
            ->recordActions([
                Action::make('viewResources')
                    ->label('Unități')
                    ->icon('heroicon-o-cube')
                    ->url(fn ($record) => \App\Filament\Tenant\Resources\PhysicalResourceResource::getUrl(
                        'index',
                        ['tableFilters[physical_resource_type_id][value]' => $record->id]
                    )),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhysicalResourceTypes::route('/'),
            'create' => Pages\CreatePhysicalResourceType::route('/create'),
            'edit' => Pages\EditPhysicalResourceType::route('/{record}/edit'),
        ];
    }
}
