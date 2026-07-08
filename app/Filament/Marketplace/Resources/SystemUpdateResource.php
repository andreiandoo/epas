<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\SystemUpdateResource\Pages;
use App\Models\SystemUpdate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Marketplace-scoped changelog / product-announcement resource
 * (Noutăți). Each marketplace admin publishes their own set — public
 * site (ambilet.ro/noutati etc.) shows only entries authored inside
 * that marketplace.
 */
class SystemUpdateResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = SystemUpdate::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Noutăți';
    protected static ?string $modelLabel = 'Noutate';
    protected static ?string $pluralModelLabel = 'Noutăți';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Grid::make(3)->schema([
                    // ─── LEFT (2/3) — main content ───────────────────
                    SC\Group::make([
                        SC\Section::make('Conținut')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Titlu')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get, ?SystemUpdate $record) {
                                        // Auto-slug ONLY when creating (record is null)
                                        // or when the slug is still empty. Never
                                        // rewrite an existing slug — that would
                                        // break already-shared /noutati/{slug}
                                        // links.
                                        if ($record === null && empty($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug (URL)')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Se auto-completează din titlu. Poate fi editat înainte de publicare — după publish, nu mai schimba slug-ul ca să nu strici link-urile deja distribuite.')
                                    ->rule(function (?SystemUpdate $record) {
                                        // Uniqueness scoped per marketplace so
                                        // two marketplaces can each own a
                                        // slug like "release-v3".
                                        return \Illuminate\Validation\Rule::unique('system_updates', 'slug')
                                            ->where('marketplace_client_id', static::getMarketplaceClientId())
                                            ->ignore($record?->id);
                                    }),
                                Forms\Components\Textarea::make('excerpt')
                                    ->label('Rezumat (excerpt)')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->helperText('Max. 300 caractere. Apare pe cardul din pagina de listă /noutati.')
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('body')
                                    ->label('Conținut complet')
                                    ->helperText('Suportă text formatat, linkuri, imagini, embed video YouTube/Vimeo. Sanitizat automat pe salvare.')
                                    ->columnSpanFull()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('system-updates')
                                    ->fileAttachmentsVisibility('public'),
                            ])->columns(2),

                        SC\Section::make('SEO')
                            ->description('Opțional. Dacă rămân goale, se folosesc titlul și rezumatul.')
                            ->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta title')
                                    ->maxLength(255)
                                    ->helperText('Titlul afișat în tab-ul de browser + rezultate Google.'),
                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta description')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Descriere sub rezultatul Google. 150-160 caractere e ideal.'),
                            ]),
                    ])->columnSpan(2),

                    // ─── RIGHT (1/3) — sidebar ───────────────────────
                    SC\Group::make([
                        SC\Section::make('Publicare')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft (invizibil pe site)',
                                        'published' => 'Publicat',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->live(),
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Data publicării')
                                    ->native(false)
                                    ->seconds(false)
                                    ->helperText('Se completează automat când salvezi cu status "Publicat". Poți edita manual pentru a antedata sau programa.')
                                    ->visible(fn (Get $get) => $get('status') === 'published'),
                                Forms\Components\Select::make('category')
                                    ->label('Categorie')
                                    ->options([
                                        'interfata' => 'Interfață',
                                        'organizator' => 'Organizator',
                                        'client' => 'Client',
                                    ])
                                    ->required()
                                    ->native(false),
                            ]),
                        SC\Section::make('Imagine reprezentativă')
                            ->schema([
                                Forms\Components\FileUpload::make('featured_image')
                                    ->label('')
                                    ->image()
                                    ->disk('public')
                                    ->directory('system-updates/featured')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->imageCropAspectRatio('16:9')
                                    ->imageResizeTargetWidth(1200)
                                    ->imageResizeTargetHeight(675)
                                    ->helperText('Recomandat: 1200×675 (16:9). Apare ca thumbnail pe /noutati și ca hero pe pagina de detail.'),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl(fn () => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f3f4f6"/><text x="50" y="55" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#9ca3af">no image</text></svg>')),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (SystemUpdate $r) => $r->slug),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'interfata' => 'Interfață',
                        'organizator' => 'Organizator',
                        'client' => 'Client',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'interfata' => 'info',
                        'organizator' => 'warning',
                        'client' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? 'Publicat' : 'Draft')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicat')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizat')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Publicat',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'interfata' => 'Interfață',
                        'organizator' => 'Organizator',
                        'client' => 'Client',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSystemUpdates::route('/'),
            'create' => Pages\CreateSystemUpdate::route('/create'),
            'edit'   => Pages\EditSystemUpdate::route('/{record}/edit'),
        ];
    }
}
