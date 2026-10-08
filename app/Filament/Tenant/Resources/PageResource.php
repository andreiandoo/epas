<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PageResource\Pages;
use App\Models\TenantPage;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = TenantPage::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Pages';

    public static function getNavigationLabel(): string
    {
        return __('Pages');
    }

    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Page';

    public static function getModelLabel(): string
    {
        return __('Page');
    }

    protected static ?string $pluralModelLabel = 'Pages';

    public static function getPluralModelLabel(): string
    {
        return __('Pages');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make(__('Page Details'))
                    ->schema([
                        SC\Tabs::make('Title')
                            ->tabs([
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('title.en')
                                            ->label(__('Page Title (EN)'))
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                    ]),
                                SC\Tabs\Tab::make(__('Romanian'))
                                    ->schema([
                                        Forms\Components\TextInput::make('title.ro')
                                            ->label(__('Page Title (RO)'))
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('URL Slug'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('The URL-friendly version of the title'))
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', $tenant?->id)),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('Parent Page'))
                            ->relationship('parent', 'slug', modifyQueryUsing: fn (Builder $query) => $query->where('tenant_id', $tenant?->id))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', 'en') ?? $record->slug)
                            ->searchable()
                            ->preload()
                            ->placeholder(__('None (Top Level)')),
                    ])->columns(2),

                SC\Section::make('Content')
                    ->schema([
                        SC\Tabs::make('Content')
                            ->tabs([
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.en')
                                            ->label(__('Page Content (EN)'))
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'orderedList',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'blockquote',
                                                'codeBlock',
                                                'redo',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                                SC\Tabs\Tab::make(__('Romanian'))
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.ro')
                                            ->label(__('Page Content (RO)'))
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'orderedList',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'blockquote',
                                                'codeBlock',
                                                'redo',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),

                SC\Section::make(__('Publishing Options'))
                    ->schema([
                        Forms\Components\Select::make('menu_location')
                            ->label(__('Menu Location'))
                            ->options([
                                'header' => __('Header Menu'),
                                'footer' => __('Footer Menu'),
                                'none' => __('Do not show in menu'),
                            ])
                            ->default('footer')
                            ->required(),

                        Forms\Components\TextInput::make('menu_order')
                            ->label(__('Menu Order'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('Lower numbers appear first')),

                        Forms\Components\Toggle::make('is_published')
                            ->label(__('Published'))
                            ->default(false)
                            ->helperText(__('Only published pages are visible on your website')),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', 'en') ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereRaw(
                            DB::getDriverName() === 'pgsql'
                                ? "title->>'en' LIKE ?"
                                : "JSON_EXTRACT(title, '$.en') LIKE ?",
                            ["%{$search}%"]
                        );
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('menu_location')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'header' => 'info',
                        'footer' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label(__('Published')),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_location')
                    ->options([
                        'header' => __('Header Menu'),
                        'footer' => __('Footer Menu'),
                        'none' => __('Not in menu'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label(__('Published')),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('menu_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
