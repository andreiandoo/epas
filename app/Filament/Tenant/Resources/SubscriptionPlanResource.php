<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SubscriptionPlanResource\Pages;
use App\Models\TenantSubscriptionPlan;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = TenantSubscriptionPlan::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Abonamente';
    protected static ?string $modelLabel = 'Abonament';
    protected static ?string $pluralModelLabel = 'Abonamente';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()?->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()?->tenant;

        return $schema->schema([
            Forms\Components\Hidden::make('tenant_id')->default($tenant?->id),

            SC\Section::make('Detalii abonament')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, SSet $set, $get) {
                            if ($state && empty($get('slug'))) { $set('slug', Str::slug($state)); }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('generat-din-nume'),
                    Forms\Components\TextInput::make('subtitle')
                        ->label('Subtitlu')
                        ->placeholder('ex: 5 spectacole • Staluri')
                        ->maxLength(190)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('price_cents')
                        ->label('Preț (RON)')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                    Forms\Components\Select::make('currency')
                        ->label('Monedă')
                        ->options(['RON' => 'RON', 'EUR' => 'EUR'])
                        ->default('RON'),
                ])->columns(2),

            SC\Section::make('Beneficii & Aspect')
                ->schema([
                    Forms\Components\TagsInput::make('benefits')
                        ->label('Beneficii')
                        ->placeholder('Adaugă un beneficiu și apasă Enter')
                        ->helperText('Fiecare beneficiu apare ca punct în listă pe site.')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('Descriere')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Imagine (opțional)')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('subscription-plans')
                        ->visibility('public'),
                    SC\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_featured')->label('Evidențiat (recomandat)')->default(false),
                        Forms\Components\TextInput::make('sort_order')->label('Ordine')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_active')->label('Activ')->default(true),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subtitle')->label('Subtitlu')->toggleable(),
                Tables\Columns\TextColumn::make('price_cents')->label('Preț')
                    ->formatStateUsing(fn ($state, $record) => number_format(($state ?? 0) / 100, 0, ',', '.') . ' ' . ($record->currency ?? 'RON'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label('Evidențiat')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activ'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit'   => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
