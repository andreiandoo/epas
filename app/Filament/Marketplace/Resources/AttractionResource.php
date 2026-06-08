<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\AttractionResource\Pages;
use App\Models\Attraction;
use App\Models\AttractionType;
use App\Models\MarketplaceCity;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * F4 — Attractions admin (points of interest). Gated by `discovery-module`.
 * Scoped per marketplace client.
 */
class AttractionResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Attraction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Atracții';

    protected static ?string $modelLabel = 'Atracție';

    protected static ?string $pluralModelLabel = 'Atracții';

    protected static ?string $navigationParentItem = 'Activități';

    protected static ?int $navigationSort = 22;

    protected static ?string $maxContentWidth = 'full';

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('discovery-module');
    }

    public static function getEloquentQuery(): Builder
    {
        $client = static::getMarketplaceClient();

        return parent::getEloquentQuery()->where('marketplace_client_id', $client?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        $typeOptions = AttractionType::query()
            ->where('marketplace_client_id', $marketplace?->id)
            ->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($t) => [$t->id => is_array($t->name) ? ($t->name[$lang] ?? $t->name['ro'] ?? $t->slug) : $t->name])
            ->all();

        $cityOptions = MarketplaceCity::query()
            ->where('marketplace_client_id', $marketplace?->id)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => is_array($c->name) ? ($c->name[$lang] ?? $c->name['ro'] ?? $c->slug) : $c->name])
            ->all();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')->default($marketplace?->id),

            SC\Grid::make(4)->schema([

                // ============ LEFT COLUMN (3/4) ============
                SC\Grid::make(1)->columnSpan(3)->schema([
                    SC\Section::make('Atracție')->schema([
                        Forms\Components\TextInput::make("name.{$lang}")
                            ->label('Nume')->required()->maxLength(160)->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                if ($state && ! $get('slug')) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('name.en')->label('Nume (EN)')->maxLength(160),
                        Forms\Components\TextInput::make('slug')->label('Slug')->required()->maxLength(191)->rule('alpha_dash'),
                        Forms\Components\TextInput::make("subtitle.{$lang}")->label('Subtitlu')->maxLength(200)->columnSpanFull(),
                        Forms\Components\RichEditor::make("description.{$lang}")
                            ->label('Descriere')
                            ->toolbarButtons(['bold', 'italic', 'underline', 'strike', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                            ->columnSpanFull(),
                    ])->columns(2),

                    SC\Section::make('Media')->schema([
                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Imagine cover')
                            ->image()
                            ->disk('public')
                            ->directory('attractions/covers')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('gallery')
                            ->label('Galerie')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->disk('public')
                            ->directory('attractions/gallery')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                    SC\Section::make('Locație')->schema([
                        Forms\Components\TextInput::make('address')->label('Adresă')->maxLength(255)->columnSpanFull(),
                        Forms\Components\TextInput::make('latitude')->label('Latitudine')->numeric()->step('0.0000001')->placeholder('44.4268'),
                        Forms\Components\TextInput::make('longitude')->label('Longitudine')->numeric()->step('0.0000001')->placeholder('26.1025'),
                    ])->columns(2),

                    SC\Section::make('SEO')
                        ->description('Titlu + descriere meta pentru rezultatele din motoarele de căutare și share-uri.')
                        ->schema([
                            Forms\Components\TextInput::make("seo.title_{$lang}")
                                ->label('Meta title')
                                ->maxLength(70)
                                ->helperText('Ideal 50–60 caractere.')
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make("seo.description_{$lang}")
                                ->label('Meta description')
                                ->rows(2)
                                ->maxLength(170)
                                ->helperText('Ideal 120–160 caractere.')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make("seo.keywords_{$lang}")
                                ->label('Cuvinte cheie')
                                ->helperText('Separate prin virgulă (opțional).')
                                ->columnSpanFull(),
                        ])->columns(1),
                ]),

                // ============ RIGHT COLUMN (1/4) ============
                SC\Grid::make(1)->columnSpan(1)->schema([
                    SC\Section::make('Publicare')->schema([
                        Forms\Components\Placeholder::make('view_link')
                            ->label('')
                            ->content(function ($record) use ($marketplace) {
                                if (! $record || empty($record->slug)) {
                                    return new HtmlString('<span style="color:#9a917f;font-size:.85rem">Salvează atracția ca să poți vizualiza pagina publică.</span>');
                                }
                                $domain = preg_replace('#^https?://#i', '', trim((string) ($marketplace?->domain ?? '')));
                                $domain = rtrim($domain, '/');
                                $url = ($domain !== '' ? 'https://' . $domain : '') . '/atractie/' . $record->slug;

                                return new HtmlString(
                                    '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener" '
                                    . 'style="display:inline-flex;gap:.4rem;align-items:center;justify-content:center;width:100%;padding:.6rem .9rem;border-radius:.7rem;background:#1B1714;color:#F4EFE3;font-weight:600;text-decoration:none;">Vizualizează pagina ↗</a>'
                                );
                            })
                            ->visible(fn ($record) => (bool) $record),

                        Forms\Components\Select::make('attraction_type_id')->label('Tip')->options($typeOptions)->searchable()->preload(),
                        Forms\Components\Select::make('marketplace_city_id')->label('Oraș')->options($cityOptions)->searchable()->preload(),
                        Forms\Components\TextInput::make('sort_order')->label('Ordine')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_featured')->label('Recomandată')->default(false),
                        Forms\Components\Toggle::make('is_visible')->label('Vizibilă')->default(true),
                    ]),
                ]),
            ]),
        ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_url')->label('')->disk('public')->height(40)->width(60),
                Tables\Columns\TextColumn::make('name')->label('Nume')
                    ->getStateUsing(fn (Attraction $r) => is_array($r->name) ? ($r->name[$lang] ?? $r->name['ro'] ?? $r->slug) : $r->name)
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereRaw("LOWER(name->>'ro') LIKE ?", ['%' . mb_strtolower($search) . '%'])),
                Tables\Columns\TextColumn::make('type.name')->label('Tip')
                    ->getStateUsing(fn (Attraction $r) => $r->type && is_array($r->type->name) ? ($r->type->name[$lang] ?? $r->type->name['ro'] ?? '') : ''),
                Tables\Columns\TextColumn::make('city.name')->label('Oraș')
                    ->getStateUsing(fn (Attraction $r) => $r->city && is_array($r->city->name) ? ($r->city->name[$lang] ?? $r->city->name['ro'] ?? '') : ''),
                Tables\Columns\TextColumn::make('activities_count')->label('Activități')->counts('activities')->badge(),
                Tables\Columns\IconColumn::make('is_featured')->label('Recom.')->boolean(),
                Tables\Columns\ToggleColumn::make('is_visible')->label('Vizibilă'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttractions::route('/'),
            'create' => Pages\CreateAttraction::route('/create'),
            'edit' => Pages\EditAttraction::route('/{record}/edit'),
        ];
    }
}
