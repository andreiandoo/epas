<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\BlogArticleResource\Pages;
use App\Models\Activity;
use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogCategory;
use App\Models\Event;
use Illuminate\Support\HtmlString;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BlogArticleResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = BlogArticle::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Blog';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'Blog Articles';

    protected static ?string $slug = 'blog-articles';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('blog');
    }

    /** Resolve a translatable name (json array or string) to a display string. */
    public static function transName($name, string $fallback = ''): string
    {
        if (is_array($name)) {
            return (string) ($name['ro'] ?? $name['en'] ?? (reset($name) ?: $fallback));
        }

        return (string) ($name !== null && $name !== '' ? $name : $fallback);
    }

    /** Build the [activities ...] shortcode string from the builder helper fields. */
    public static function buildActivitiesShortcode(\Filament\Schemas\Components\Utilities\Get $get): string
    {
        $style = in_array($get('_ab_style'), ['small', 'large', 'long'], true) ? $get('_ab_style') : 'small';

        if ($get('_ab_mode') === 'manual') {
            $ids = $get('_ab_ids');
            $ids = is_array($ids) ? implode(',', array_map('intval', array_filter($ids))) : '';
            if ($ids === '') {
                return '[activities …] — alege una sau mai multe activități';
            }

            return '[activities ids="' . $ids . '" style="' . $style . '"]';
        }

        $city = trim((string) $get('_ab_city'));
        $cat = trim((string) $get('_ab_category'));
        $limit = (int) ($get('_ab_limit') ?: 6);

        if ($city === '' && $cat === '') {
            return '[activities …] — alege oraș și/sau categorie';
        }

        $out = '[activities';
        if ($city !== '') {
            $out .= ' city="' . $city . '"';
        }
        if ($cat !== '') {
            $out .= ' category="' . $cat . '"';
        }
        $out .= ' limit="' . max(1, min(24, $limit)) . '" style="' . $style . '"]';

        return $out;
    }

    /** Yoast-style live SEO analysis for the focus keyword. Returns rendered HTML. */
    public static function seoAnalysis(\Filament\Schemas\Components\Utilities\Get $get, string $lang): HtmlString
    {
        $kw = trim((string) $get('focus_keyword'));
        $title = (string) $get("title.{$lang}");
        $metaTitle = (string) ($get("meta_title.{$lang}") ?: $title);
        $metaDesc = (string) $get("meta_description.{$lang}");
        $slug = (string) $get('slug');
        $contentHtml = (string) $get("content.{$lang}");
        $excerpt = (string) $get("excerpt.{$lang}");
        $secondary = array_values(array_filter(array_map('trim', explode(',', (string) $get('secondary_keywords')))));

        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($contentHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $words = $text === '' ? [] : preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        $lc = fn ($s) => mb_strtolower((string) $s, 'UTF-8');
        $has = fn ($hay, $needle) => $needle !== '' && mb_strpos($lc($hay), $lc($needle), 0, 'UTF-8') !== false;

        $checks = [];

        if ($kw === '') {
            $checks[] = ['warn', 'Setează un keyword principal pentru a porni analiza.'];
        } else {
            $checks[] = [$has($metaTitle, $kw) ? 'ok' : 'bad', 'Keyword principal în titlul SEO'];
            $checks[] = [($has($slug, str_replace(' ', '-', $kw)) || $has($slug, $kw)) ? 'ok' : 'warn', 'Keyword principal în slug'];
            $checks[] = [$has($metaDesc, $kw) ? 'ok' : 'bad', 'Keyword principal în meta description'];
            $intro = mb_substr($text, 0, 250, 'UTF-8') . ' ' . $excerpt;
            $checks[] = [$has($intro, $kw) ? 'ok' : 'warn', 'Keyword principal în introducere'];

            $occ = $wordCount ? substr_count($lc($text), $lc($kw)) : 0;
            $density = $wordCount ? round($occ / $wordCount * 100, 2) : 0.0;
            $densOk = $density >= 0.5 && $density <= 2.5;
            $checks[] = [$occ === 0 ? 'bad' : ($densOk ? 'ok' : 'warn'), "Densitate keyword: {$density}% ({$occ} apariții, ideal 0.5–2.5%)"];

            $inHeading = (bool) preg_match('/<h[2-4][^>]*>(?:(?!<\/h[2-4]>).)*' . preg_quote($kw, '/') . '/isu', $contentHtml);
            $checks[] = [$inHeading ? 'ok' : 'warn', 'Keyword principal într-un subtitlu (H2–H4)'];
        }

        $checks[] = [$wordCount >= 300 ? 'ok' : 'warn', "Lungime conținut: {$wordCount} cuvinte (ideal ≥300)"];

        $mtLen = mb_strlen($metaTitle, 'UTF-8');
        $checks[] = [($mtLen >= 30 && $mtLen <= 60) ? 'ok' : 'warn', "Titlu SEO: {$mtLen} caractere (ideal 30–60)"];

        $mdLen = mb_strlen($metaDesc, 'UTF-8');
        $checks[] = [($mdLen >= 120 && $mdLen <= 160) ? 'ok' : 'warn', "Meta description: {$mdLen} caractere (ideal 120–160)"];

        if ($secondary) {
            $found = count(array_filter($secondary, fn ($s) => $has($text, $s)));
            $checks[] = [$found === count($secondary) ? 'ok' : 'warn', "Keywords secundare în conținut: {$found}/" . count($secondary)];
        }

        $okN = count(array_filter($checks, fn ($c) => $c[0] === 'ok'));
        $total = max(1, count($checks));
        $score = (int) round($okN / $total * 100);
        $barColor = $score >= 80 ? '#1E4A3D' : ($score >= 50 ? '#DA9A33' : '#E84527');
        $iconFor = ['ok' => ['✓', '#1E4A3D'], 'warn' => ['!', '#DA9A33'], 'bad' => ['✗', '#E84527']];

        $rows = '';
        foreach ($checks as [$st, $label]) {
            [$glyph, $col] = $iconFor[$st];
            $rows .= '<div style="display:flex;gap:.5rem;align-items:flex-start;padding:.3rem 0;border-top:1px solid #f0eee9;">'
                . '<span style="flex:0 0 1.1rem;color:' . $col . ';font-weight:700;">' . $glyph . '</span>'
                . '<span style="color:#3f3a33;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></div>';
        }

        $html = '<div style="border:1px solid #e7e2d6;border-radius:.85rem;padding:1rem 1.1rem;background:#fcfbf7;">'
            . '<div style="display:flex;align-items:center;gap:.85rem;margin-bottom:.5rem;">'
            . '<span style="font-size:1.6rem;font-weight:800;line-height:1;color:' . $barColor . ';">' . $score . '<span style="font-size:.85rem;color:#9a917f;">/100</span></span>'
            . '<span style="font-weight:600;color:#5A4F41;">Scor SEO</span></div>'
            . '<div style="height:.5rem;border-radius:99px;background:#ece7da;overflow:hidden;margin-bottom:.5rem;">'
            . '<div style="height:100%;width:' . $score . '%;background:' . $barColor . ';"></div></div>'
            . $rows . '</div>';

        return new HtmlString($html);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                // Two-column layout: Left (2/3) and Right (1/3)
                SC\Grid::make(3)
                    ->schema([
                        // LEFT COLUMN (2/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                // Article Content Section
                                SC\Section::make('Article Content')
                                    ->schema([
                                        Forms\Components\TextInput::make("title.{$marketplaceLanguage}")
                                            ->label('Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) use ($marketplaceLanguage) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                    // Auto-populate SEO if empty
                                                    if (!$get("meta_title.{$marketplaceLanguage}")) {
                                                        $set("meta_title.{$marketplaceLanguage}", Str::limit($state, 60));
                                                    }
                                                    if (!$get("og_title.{$marketplaceLanguage}")) {
                                                        $set("og_title.{$marketplaceLanguage}", Str::limit($state, 60));
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->rule('alpha_dash'),

                                        Forms\Components\TextInput::make("subtitle.{$marketplaceLanguage}")
                                            ->label('Subtitle')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make("excerpt.{$marketplaceLanguage}")
                                            ->label('Excerpt')
                                            ->rows(2)
                                            ->helperText('Short summary for previews and SEO')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) use ($marketplaceLanguage) {
                                                if ($state) {
                                                    // Auto-populate meta description if empty
                                                    if (!$get("meta_description.{$marketplaceLanguage}")) {
                                                        $set("meta_description.{$marketplaceLanguage}", Str::limit($state, 155));
                                                    }
                                                    if (!$get("og_description.{$marketplaceLanguage}")) {
                                                        $set("og_description.{$marketplaceLanguage}", Str::limit($state, 155));
                                                    }
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\RichEditor::make("content.{$marketplaceLanguage}")
                                            ->label('Content')
                                            ->required()
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
                                            ])
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                // SEO Section
                                SC\Section::make('SEO & Meta Tags')
                                    ->description('Search engine optimization settings')
                                    ->schema([
                                        SC\Tabs::make('SEO Tabs')
                                            ->tabs([
                                                SC\Tabs\Tab::make('Cuvinte cheie & analiză')
                                                    ->icon('heroicon-o-magnifying-glass-circle')
                                                    ->schema([
                                                        Forms\Components\TextInput::make('focus_keyword')
                                                            ->label('Keyword principal')
                                                            ->live(onBlur: true)
                                                            ->maxLength(255)
                                                            ->helperText('Un singur cuvânt/frază pe care vrei să o ranking-uiești (ex: „escape room București”).'),

                                                        Forms\Components\Textarea::make('secondary_keywords')
                                                            ->label('Keywords secundare')
                                                            ->live(onBlur: true)
                                                            ->rows(2)
                                                            ->helperText('Mai multe cuvinte/fraze, separate prin virgulă.'),

                                                        Forms\Components\Textarea::make('longtail_phrases')
                                                            ->label('Long-tail phrases')
                                                            ->live(onBlur: true)
                                                            ->rows(2)
                                                            ->helperText('Fraze lungi (întrebări, intenții), separate prin virgulă.'),

                                                        Forms\Components\Placeholder::make('seo_analysis')
                                                            ->label('Analiză SEO (live)')
                                                            ->content(fn (\Filament\Schemas\Components\Utilities\Get $get) => static::seoAnalysis($get, $marketplaceLanguage)),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Basic SEO')
                                                    ->schema([
                                                        Forms\Components\TextInput::make("meta_title.{$marketplaceLanguage}")
                                                            ->label('Meta Title')
                                                            ->live(onBlur: true)
                                                            ->maxLength(60)
                                                            ->helperText('Recommended: 50-60 characters. Auto-fills from title.'),

                                                        Forms\Components\Textarea::make("meta_description.{$marketplaceLanguage}")
                                                            ->label('Meta Description')
                                                            ->live(onBlur: true)
                                                            ->rows(2)
                                                            ->maxLength(160)
                                                            ->helperText('Recommended: 150-160 characters. Auto-fills from excerpt.'),

                                                        Forms\Components\TextInput::make('canonical_url')
                                                            ->label('Canonical URL')
                                                            ->url()
                                                            ->maxLength(500)
                                                            ->helperText('Leave empty to use the article URL'),

                                                        Forms\Components\Toggle::make('no_index')
                                                            ->label('Hide from Search Engines')
                                                            ->helperText('When enabled, search engines will not index this article'),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Open Graph')
                                                    ->schema([
                                                        Forms\Components\TextInput::make("og_title.{$marketplaceLanguage}")
                                                            ->label('OG Title')
                                                            ->maxLength(60)
                                                            ->helperText('Title for social media sharing'),

                                                        Forms\Components\Textarea::make("og_description.{$marketplaceLanguage}")
                                                            ->label('OG Description')
                                                            ->rows(2)
                                                            ->maxLength(200)
                                                            ->helperText('Description for social media sharing'),

                                                        Forms\Components\FileUpload::make('og_image_url')
                                                            ->label('OG Image')
                                                            ->image()
                                                            ->disk('public')
                                                            ->directory('blog-og-images')
                                                            ->imageResizeMode('cover')
                                                            ->imageCropAspectRatio('1.91:1')
                                                            ->imageResizeTargetWidth('1200')
                                                            ->imageResizeTargetHeight('630')
                                                            ->helperText('Image for social sharing (1200x630px recommended)'),

                                                        Forms\Components\Select::make('twitter_card')
                                                            ->label('Twitter Card Type')
                                                            ->options([
                                                                'summary' => 'Summary',
                                                                'summary_large_image' => 'Summary with Large Image',
                                                            ])
                                                            ->default('summary_large_image'),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Schema.org')
                                                    ->schema([
                                                        Forms\Components\Select::make('schema_markup.type')
                                                            ->label('Schema Type')
                                                            ->options([
                                                                'Article' => 'Article',
                                                                'BlogPosting' => 'Blog Posting',
                                                                'NewsArticle' => 'News Article',
                                                                'TechArticle' => 'Tech Article',
                                                            ])
                                                            ->default('BlogPosting'),

                                                        Forms\Components\TextInput::make('schema_markup.author_name')
                                                            ->label('Author Name')
                                                            ->maxLength(100),

                                                        Forms\Components\TextInput::make('schema_markup.author_url')
                                                            ->label('Author URL')
                                                            ->url()
                                                            ->maxLength(255),

                                                        Forms\Components\TextInput::make('schema_markup.publisher_name')
                                                            ->label('Publisher Name')
                                                            ->maxLength(100)
                                                            ->default(fn () => $marketplace->name ?? ''),

                                                        Forms\Components\TextInput::make('schema_markup.publisher_logo')
                                                            ->label('Publisher Logo URL')
                                                            ->url()
                                                            ->maxLength(500),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Advanced')
                                                    ->schema([
                                                        Forms\Components\Select::make('language')
                                                            ->label('Content Language')
                                                            ->options([
                                                                'en' => 'English',
                                                                'ro' => 'Romanian',
                                                                'de' => 'German',
                                                                'fr' => 'French',
                                                                'es' => 'Spanish',
                                                                'it' => 'Italian',
                                                            ])
                                                            ->default($marketplaceLanguage),

                                                        Forms\Components\TextInput::make('reading_time_minutes')
                                                            ->label('Reading Time (minutes)')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->helperText('Leave empty for auto-calculation'),
                                                    ])->columns(1),
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                // FAQs Section — moved last, after SEO. Optional
                                // per-guide FAQ list; if empty the public guide
                                // renders a fallback set. Emitted as FAQPage JSON-LD.
                                SC\Section::make('Întrebări frecvente (FAQ)')
                                    ->description('Opțional. Dacă lași gol, ghidul afișează un set FAQ de fallback. Apar și ca FAQPage JSON-LD pentru SEO.')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Repeater::make('faqs')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\TextInput::make('q')
                                                    ->label('Întrebare')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Forms\Components\Textarea::make('a')
                                                    ->label('Răspuns')
                                                    ->required()
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                                            ->addActionLabel('Adaugă întrebare')
                                            ->reorderable()
                                            ->collapsible()
                                            ->defaultItems(0),
                                    ]),
                            ]),

                        // RIGHT COLUMN (1/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // Activities block builder — sticky right rail,
                                // always visible while editing. Generates an
                                // [activities ...] shortcode to paste into Content.
                                // Helper fields are dehydrated(false) — not saved.
                                SC\Section::make('Bloc de activități (shortcode)')
                                    ->description('Configurează un bloc, copiază shortcode-ul și lipește-l în Content.')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->extraAttributes(['style' => 'position: sticky; top: 5rem; z-index: 5;'])
                                    ->schema([
                                        Forms\Components\Select::make('_ab_mode')
                                            ->label('Mod')
                                            ->dehydrated(false)
                                            ->live()
                                            ->default('auto')
                                            ->selectablePlaceholder(false)
                                            ->options([
                                                'auto'   => 'Automat (categorie + oraș)',
                                                'manual' => 'Manual (activități alese)',
                                            ]),
                                        Forms\Components\Select::make('_ab_style')
                                            ->label('Stil carduri')
                                            ->dehydrated(false)
                                            ->live()
                                            ->default('small')
                                            ->selectablePlaceholder(false)
                                            ->options([
                                                'small' => 'Small — verticale',
                                                'large' => 'Large — pătrate',
                                                'long'  => 'Long — orizontale',
                                            ]),

                                        Forms\Components\Select::make('_ab_city')
                                            ->label('Oraș')
                                            ->dehydrated(false)
                                            ->live()
                                            ->searchable()
                                            ->placeholder('Toate orașele')
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('_ab_mode') !== 'manual')
                                            ->options(function () use ($marketplace) {
                                                try {
                                                    return Activity::query()
                                                        ->where('marketplace_client_id', $marketplace?->id)
                                                        ->whereNotNull('marketplace_city_id')
                                                        ->with('city:id,slug,name')
                                                        ->get()
                                                        ->pluck('city')
                                                        ->filter()
                                                        ->unique('id')
                                                        ->sortBy('id')
                                                        ->mapWithKeys(fn ($c) => [$c->slug => static::transName($c->name, $c->slug)])
                                                        ->all();
                                                } catch (\Throwable $e) {
                                                    return [];
                                                }
                                            }),
                                        Forms\Components\Select::make('_ab_category')
                                            ->label('Categorie')
                                            ->dehydrated(false)
                                            ->live()
                                            ->searchable()
                                            ->placeholder('Toate categoriile')
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('_ab_mode') !== 'manual')
                                            ->options(function () use ($marketplace) {
                                                try {
                                                    return Activity::query()
                                                        ->where('marketplace_client_id', $marketplace?->id)
                                                        ->whereNotNull('marketplace_category_id')
                                                        ->with('category:id,slug,name')
                                                        ->get()
                                                        ->pluck('category')
                                                        ->filter()
                                                        ->unique('id')
                                                        ->sortBy('id')
                                                        ->mapWithKeys(fn ($c) => [$c->slug => static::transName($c->name, $c->slug)])
                                                        ->all();
                                                } catch (\Throwable $e) {
                                                    return [];
                                                }
                                            }),
                                        Forms\Components\TextInput::make('_ab_limit')
                                            ->label('Număr carduri')
                                            ->dehydrated(false)
                                            ->live(onBlur: true)
                                            ->numeric()
                                            ->default(6)
                                            ->minValue(1)
                                            ->maxValue(24)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('_ab_mode') !== 'manual'),

                                        Forms\Components\Select::make('_ab_ids')
                                            ->label('Activități alese (în ordine)')
                                            ->dehydrated(false)
                                            ->live()
                                            ->multiple()
                                            ->searchable()
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('_ab_mode') === 'manual')
                                            ->options(function () use ($marketplace) {
                                                try {
                                                    return Activity::query()
                                                        ->where('marketplace_client_id', $marketplace?->id)
                                                        ->orderByDesc('id')
                                                        ->limit(300)
                                                        ->get(['id', 'title'])
                                                        ->mapWithKeys(fn ($a) => [$a->id => static::transName($a->title, 'Activitate #' . $a->id)])
                                                        ->all();
                                                } catch (\Throwable $e) {
                                                    return [];
                                                }
                                            }),

                                        Forms\Components\Placeholder::make('_ab_shortcode')
                                            ->label('Shortcode generat')
                                            ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                                $code = static::buildActivitiesShortcode($get);
                                                $js = htmlspecialchars(json_encode($code), ENT_QUOTES, 'UTF-8');

                                                return new HtmlString(
                                                    '<div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">'
                                                    . '<code style="flex:1 1 100%;padding:.55rem .7rem;border-radius:.6rem;background:#1B1714;color:#F4EFE3;font-size:.8rem;word-break:break-all;">'
                                                    . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</code>'
                                                    . '<button type="button" onclick="navigator.clipboard.writeText(' . $js . ');this.textContent=\'Copiat!\';setTimeout(()=>this.textContent=\'Copiază shortcode\',1500);" '
                                                    . 'style="flex:1 1 100%;padding:.55rem .9rem;border-radius:.6rem;background:#E84527;color:#fff;font-weight:600;border:0;cursor:pointer;">Copiază shortcode</button>'
                                                    . '</div>'
                                                    . '<p style="margin-top:.5rem;color:#7a7164;font-size:.78rem;">Lipește-l pe o linie separată în Content. Poți insera oricâte blocuri.</p>'
                                                );
                                            }),
                                    ]),

                                // Organization Section
                                SC\Section::make('Organization')
                                    ->schema([
                                        Forms\Components\Select::make('category_id')
                                            ->label('Category')
                                            ->options(function () use ($marketplace, $marketplaceLanguage) {
                                                return BlogCategory::where('marketplace_client_id', $marketplace?->id)
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) use ($marketplaceLanguage) {
                                                        $name = $cat->name[$marketplaceLanguage] ?? $cat->name['en'] ?? $cat->name[array_key_first($cat->name ?? [])] ?? 'Unnamed';
                                                        return [$cat->id => $name];
                                                    });
                                            })
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'published' => 'Published',
                                                'scheduled' => 'Scheduled',
                                                'archived' => 'Archived',
                                            ])
                                            ->default('draft')
                                            ->required(),

                                        Forms\Components\DateTimePicker::make('published_at')
                                            ->label('Publish Date')
                                            ->helperText('Leave empty to publish immediately'),

                                        Forms\Components\Toggle::make('is_featured')
                                            ->label('Featured Article')
                                            ->helperText('Show on homepage/featured section'),

                                        Forms\Components\Select::make('event_id')
                                            ->label('Linked Event')
                                            ->placeholder('Select an event to promote...')
                                            ->options(function () use ($marketplace, $marketplaceLanguage) {
                                                return Event::where('marketplace_client_id', $marketplace?->id)
                                                    ->orderBy('created_at', 'desc')
                                                    ->limit(300)
                                                    ->get()
                                                    ->mapWithKeys(function ($event) use ($marketplaceLanguage) {
                                                        $titleData = $event->title;
                                                        $title = is_array($titleData)
                                                            ? ($titleData[$marketplaceLanguage] ?? $titleData['en'] ?? collect($titleData)->first() ?? 'Unnamed')
                                                            : ($titleData ?? 'Unnamed');
                                                        return [$event->id => $title];
                                                    });
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Promotes this event in the article (shows a CTA button)'),

                                        Forms\Components\Select::make('activity_ids')
                                            ->label('Linked Activities')
                                            ->placeholder('Selectează activități de promovat...')
                                            ->multiple()
                                            ->options(function () use ($marketplace, $marketplaceLanguage) {
                                                if (! class_exists(\App\Models\Activity::class)) {
                                                    return [];
                                                }
                                                return \App\Models\Activity::where('marketplace_client_id', $marketplace?->id)
                                                    ->orderBy('created_at', 'desc')
                                                    ->limit(300)
                                                    ->get()
                                                    ->mapWithKeys(function ($activity) use ($marketplaceLanguage) {
                                                        $titleData = $activity->title;
                                                        $title = is_array($titleData)
                                                            ? ($titleData[$marketplaceLanguage] ?? $titleData['en'] ?? collect($titleData)->first() ?? 'Fără titlu')
                                                            : ($titleData ?? 'Fără titlu');
                                                        return [$activity->id => $title];
                                                    });
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Activitățile apar ca recomandări (carduri „Vezi bilete") în ghid'),
                                    ]),

                                // Featured Image Section
                                SC\Section::make('Featured Image')
                                    ->schema([
                                        Forms\Components\FileUpload::make('featured_image_url')
                                            ->label('Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('blog-images')
                                            ->imageEditor()
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('16:9')
                                            ->imageResizeTargetWidth('1200')
                                            ->imageResizeTargetHeight('630')
                                            ->helperText('Drag & drop or click to upload. Recommended: 1200x630px')
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('featured_image_alt')
                                            ->label('Alt Text')
                                            ->maxLength(255)
                                            ->helperText('Describe the image for accessibility'),
                                    ]),
                            ]),
                    ]),
            ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image_url')
                    ->label('Image')
                    ->circular(false)
                    ->size(50),

                Tables\Columns\TextColumn::make("title.{$marketplaceLanguage}")
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state) use ($marketplaceLanguage) {
                        if (is_array($state)) {
                            return $state[$marketplaceLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'published',
                        'warning' => 'scheduled',
                        'danger' => 'archived',
                    ]),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->recordActions([
                EditAction::make(),
                Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BlogArticle $record) => $record->status !== 'published')
                    ->action(fn (BlogArticle $record) => $record->publish()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogArticles::route('/'),
            'create' => Pages\CreateBlogArticle::route('/create'),
            'edit' => Pages\EditBlogArticle::route('/{record}/edit'),
        ];
    }
}
