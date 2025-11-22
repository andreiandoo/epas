<?php

namespace App\Filament\Resources\Docs;

use App\Filament\Resources\Docs\DocResource\Pages;
use App\Models\Doc;
use App\Models\DocCategory;
use BackedEnum;
use Filament\Forms\Components as FC;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocResource extends Resource
{
    protected static ?string $model = Doc::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Group::make()
                    ->schema([
                        SC\Section::make('Content')
                            ->schema([
                                FC\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) =>
                                        $set('slug', \Str::slug($state))),

                                FC\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                FC\Textarea::make('excerpt')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Brief description shown in listings'),

                                FC\RichEditor::make('content')
                                    ->required()
                                    ->columnSpanFull()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('docs')
                                    ->toolbarButtons([
                                        'blockquote',
                                        'bold',
                                        'bulletList',
                                        'codeBlock',
                                        'h2',
                                        'h3',
                                        'italic',
                                        'link',
                                        'orderedList',
                                        'redo',
                                        'strike',
                                        'underline',
                                        'undo',
                                        'attachFiles',
                                    ]),
                            ]),

                        SC\Section::make('Metadata')
                            ->schema([
                                FC\TagsInput::make('tags')
                                    ->suggestions([
                                        'api', 'component', 'guide', 'tutorial',
                                        'configuration', 'setup', 'integration',
                                    ]),

                                FC\KeyValue::make('metadata')
                                    ->keyLabel('Property')
                                    ->valueLabel('Value')
                                    ->addable()
                                    ->deletable()
                                    ->reorderable(),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                SC\Group::make()
                    ->schema([
                        SC\Section::make('Status')
                            ->schema([
                                FC\Select::make('status')
                                    ->options(Doc::STATUSES)
                                    ->default('draft')
                                    ->required(),

                                FC\Select::make('doc_category_id')
                                    ->label('Category')
                                    ->options(DocCategory::pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),

                                FC\Select::make('type')
                                    ->options(Doc::TYPES)
                                    ->default('general')
                                    ->required(),

                                FC\Select::make('parent_id')
                                    ->label('Parent Document')
                                    ->options(Doc::pluck('title', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                FC\TextInput::make('version')
                                    ->default('1.0.0')
                                    ->required(),
                            ]),

                        SC\Section::make('Visibility')
                            ->schema([
                                FC\Toggle::make('is_public')
                                    ->label('Public')
                                    ->helperText('Make visible to public'),

                                FC\Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Show on documentation homepage'),

                                FC\TextInput::make('order')
                                    ->numeric()
                                    ->default(0),

                                FC\TextInput::make('author')
                                    ->maxLength(255),

                                FC\DateTimePicker::make('published_at')
                                    ->label('Publish Date')
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Doc $record) => \Str::limit($record->excerpt, 50)),

                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'api' => 'info',
                        'component' => 'success',
                        'module' => 'warning',
                        'microservice' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),

                Tables\Columns\TextColumn::make('version')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Doc::STATUSES),

                Tables\Filters\SelectFilter::make('type')
                    ->options(Doc::TYPES),

                Tables\Filters\SelectFilter::make('doc_category_id')
                    ->label('Category')
                    ->options(DocCategory::pluck('name', 'id')),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocs::route('/'),
            'create' => Pages\CreateDoc::route('/create'),
            'edit' => Pages\EditDoc::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'content', 'excerpt'];
    }
}
