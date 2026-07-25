<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EventCategoryResource\Pages;
use App\Models\TenantEventCategory;
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

class EventCategoryResource extends Resource
{
    protected static ?string $model = TenantEventCategory::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Categorii evenimente';
    protected static ?string $modelLabel = 'Categorie eveniment';
    protected static ?string $pluralModelLabel = 'Categorii evenimente';

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

            SC\Section::make('Categorie')
                ->schema([
                    Forms\Components\TextInput::make('name.ro')
                        ->label('Nume (RO)')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, SSet $set, $get) {
                            if ($state && empty($get('slug'))) { $set('slug', Str::slug($state)); }
                        }),
                    Forms\Components\TextInput::make('name.en')
                        ->label('Nume (EN)')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('generat-din-nume'),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon (nume Heroicon)')
                        ->placeholder('ex: calendar, ticket, musical-note')
                        ->helperText('Numele unui icon Heroicon fără prefix.')
                        ->maxLength(64),
                ])->columns(2),

            SC\Section::make('Aspect')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('Imagine categorie')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('event-categories')
                        ->visibility('public')
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('description.ro')
                        ->label('Descriere (RO)')
                        ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'h2', 'h3', 'redo', 'undo'])
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordine')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activă')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('Imagine')->disk('public')->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', 'ro'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->toggleable(),
                Tables\Columns\TextColumn::make('icon')->label('Icon')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activă')->boolean(),
                Tables\Columns\TextColumn::make('events_count')->counts('events')->label('Evenimente')->badge()->color('info'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activă'),
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
            'index'  => Pages\ListEventCategories::route('/'),
            'create' => Pages\CreateEventCategory::route('/create'),
            'edit'   => Pages\EditEventCategory::route('/{record}/edit'),
        ];
    }
}
