<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PageResource\Pages;
use App\Models\TenantPage;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = TenantPage::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Pages';

    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Page';

    protected static ?string $pluralModelLabel = 'Pages';

    /**
     * Hide from navigation - PageResource uses TenantPage which is not applicable to Marketplace
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
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

                SC\Section::make('Page Details')
                    ->schema([
                        SC\Tabs::make('Title')
                            ->tabs([
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Page Title (EN)')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                    ]),
                                SC\Tabs\Tab::make('Romanian')
                                    ->schema([
                                        Forms\Components\TextInput::make('title.ro')
                                            ->label('Page Title (RO)')
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The URL-friendly version of the title')
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', $tenant?->id)),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Page')
                            ->relationship('parent', 'slug', modifyQueryUsing: fn (Builder $query) => $query->where('tenant_id', $tenant?->id))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', 'en') ?? $record->slug)
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Top Level)'),
                    ])->columns(2),

                SC\Section::make('Content')
                    ->schema([
                        SC\Tabs::make('Content')
                            ->tabs([
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.en')
                                            ->label('Page Content (EN)')
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
                                SC\Tabs\Tab::make('Romanian')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.ro')
                                            ->label('Page Content (RO)')
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

                SC\Section::make('Publishing Options')
                    ->schema([
                        Forms\Components\Select::make('menu_location')
                            ->label('Menu Location')
                            ->options([
                                'header' => 'Header Menu',
                                'footer' => 'Footer Menu',
                                'none' => 'Do not show in menu',
                            ])
                            ->default('footer')
                            ->required(),

                        Forms\Components\TextInput::make('menu_order')
                            ->label('Menu Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),

                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(false)
                            ->helperText('Only published pages are visible on your website'),
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
                        return $query->whereRaw("JSON_EXTRACT(title, '$.en') LIKE ?", ["%{$search}%"]);
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
                    ->label('Published'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_location')
                    ->options([
                        'header' => 'Header Menu',
                        'footer' => 'Footer Menu',
                        'none' => 'Not in menu',
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
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
