<?php

namespace App\Filament\Resources\Docs;

use App\Filament\Resources\Docs\DocResource\Pages;
use App\Models\Doc;
use App\Models\DocCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocResource extends Resource
{
    protected static ?string $model = Doc::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static $navigationGroup = 'Documentation';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Content')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) =>
                                        $set('slug', \Str::slug($state))),

                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Textarea::make('excerpt')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Brief description shown in listings'),

                                Forms\Components\RichEditor::make('content')
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

                        Forms\Components\Section::make('Metadata')
                            ->schema([
                                Forms\Components\TagsInput::make('tags')
                                    ->suggestions([
                                        'api', 'component', 'guide', 'tutorial',
                                        'configuration', 'setup', 'integration',
                                    ]),

                                Forms\Components\KeyValue::make('metadata')
                                    ->keyLabel('Property')
                                    ->valueLabel('Value')
                                    ->addable()
                                    ->deletable()
                                    ->reorderable(),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options(Doc::STATUSES)
                                    ->default('draft')
                                    ->required(),

                                Forms\Components\Select::make('doc_category_id')
                                    ->label('Category')
                                    ->options(DocCategory::pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),

                                Forms\Components\Select::make('type')
                                    ->options(Doc::TYPES)
                                    ->default('general')
                                    ->required(),

                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Document')
                                    ->options(Doc::pluck('title', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Forms\Components\TextInput::make('version')
                                    ->default('1.0.0')
                                    ->required(),
                            ]),

                        Forms\Components\Section::make('Visibility')
                            ->schema([
                                Forms\Components\Toggle::make('is_public')
                                    ->label('Public')
                                    ->helperText('Make visible to public'),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Show on documentation homepage'),

                                Forms\Components\TextInput::make('order')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\TextInput::make('author')
                                    ->maxLength(255),

                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Publish Date'),
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
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Doc $record) => route('docs.show', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Doc $record) => $record->is_public && $record->status === 'published'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['status' => 'published']))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('makePublic')
                        ->label('Make Public')
                        ->icon('heroicon-o-globe-alt')
                        ->action(fn ($records) => $records->each->update(['is_public' => true]))
                        ->requiresConfirmation(),
                ]),
            ])
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
