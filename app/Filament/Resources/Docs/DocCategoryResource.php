<?php

namespace App\Filament\Resources\Docs;

use App\Filament\Resources\Docs\DocCategoryResource\Pages;
use App\Models\DocCategory;
use BackedEnum;
use Filament\Forms\Components as FC;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DocCategoryResource extends Resource
{
    protected static ?string $model = DocCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static UnitEnum|string|null $navigationGroup = 'Documentation';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Category Details')
                    ->schema([
                        FC\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) =>
                                $set('slug', \Str::slug($state))),

                        FC\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        FC\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500),

                        SC\Grid::make(2)
                            ->schema([
                                FC\TextInput::make('icon')
                                    ->placeholder('heroicon-o-document')
                                    ->helperText('Heroicon name'),

                                FC\ColorPicker::make('color')
                                    ->default('#6366f1'),
                            ]),

                        SC\Grid::make(2)
                            ->schema([
                                FC\TextInput::make('order')
                                    ->numeric()
                                    ->default(0),

                                FC\Toggle::make('is_public')
                                    ->label('Public')
                                    ->helperText('Make this category visible to the public'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('docs_count')
                    ->counts('docs')
                    ->label('Docs'),

                Tables\Columns\ColorColumn::make('color'),

                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),

                Tables\Columns\TextColumn::make('order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('order')
            ->reorderable('order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocCategories::route('/'),
            'create' => Pages\CreateDocCategory::route('/create'),
            'edit' => Pages\EditDocCategory::route('/{record}/edit'),
        ];
    }
}
