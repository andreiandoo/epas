<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateEventSourceResource\Pages;
use App\Models\AffiliateEventSource;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class AffiliateEventSourceResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = AffiliateEventSource::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Surse Afiliere';
    protected static ?string $modelLabel = 'Sursă Afiliere';
    protected static ?string $pluralModelLabel = 'Surse Afiliere';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            SC\Grid::make(4)->schema([
                // ========== LEFT COLUMN (3/4) ==========
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                        SC\Section::make('Detalii sursă')
                            ->schema([
                                SC\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nume sursă')
                                            ->required()
                                            ->maxLength(190)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Components\TextInput $component) {
                                                $component->getContainer()
                                                    ->getComponent('slug')
                                                    ?->state(Str::slug($state));
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(190)
                                            ->rule('alpha_dash')
                                            ->unique(ignoreRecord: true)
                                            ->key('slug'),
                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Website URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->placeholder('https://www.exemplu.ro'),
                                    ])->columns(3),
                            ]),

                        SC\Section::make('Descriere')
                            ->schema([
                                Forms\Components\Textarea::make('description')
                                    ->label('Descriere')
                                    ->rows(3)
                                    ->maxLength(1000),
                            ])
                            ->collapsible(),

                        SC\Section::make('Logo')
                            ->schema([
                                Forms\Components\TextInput::make('logo_url')
                                    ->label('URL Logo')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder('https://www.exemplu.ro/logo.png'),
                            ])
                            ->collapsible(),
                    ]),

                // ========== RIGHT COLUMN (1/4) ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        SC\Section::make('Status')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Activ',
                                        'inactive' => 'Inactiv',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ]),

                        SC\Section::make('Statistici')
                            ->schema([
                                SC\Component::make('stats-placeholder')
                                    ->view('filament.components.placeholder', [
                                        'content' => '',
                                    ])
                                    ->visible(false),
                            ])
                            ->visible(fn (?AffiliateEventSource $record) => $record && $record->exists)
                            ->headerActions([])
                            ->schema(fn (?AffiliateEventSource $record) => $record ? [
                                Forms\Components\Placeholder::make('events_count')
                                    ->label('Total evenimente')
                                    ->content($record->events_count),
                                Forms\Components\Placeholder::make('active_events_count')
                                    ->label('Evenimente active')
                                    ->content($record->active_events_count),
                            ] : []),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('website_url')
                    ->label('Website')
                    ->limit(40)
                    ->url(fn ($record) => $record->website_url, shouldOpenInNewTab: true)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('events_count')
                    ->label('Evenimente')
                    ->counts('events')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Activ',
                        'inactive' => 'Inactiv',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateEventSources::route('/'),
            'create' => Pages\CreateAffiliateEventSource::route('/create'),
            'edit' => Pages\EditAffiliateEventSource::route('/{record}/edit'),
        ];
    }
}
