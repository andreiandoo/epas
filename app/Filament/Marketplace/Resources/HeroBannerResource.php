<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\HeroBannerResource\Pages;
use App\Models\MarketplaceHeroBanner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bannere custom în caruselul hero de pe homepage (3D Poster Stack),
 * afișate împreună cu evenimentele marcate „homepage featured”.
 */
class HeroBannerResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceHeroBanner::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Bannere homepage';

    protected static ?string $modelLabel = 'Banner homepage';

    protected static ?string $pluralModelLabel = 'Bannere homepage';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        $client = static::getMarketplaceClient();

        return parent::getEloquentQuery()->where('marketplace_client_id', $client?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')->default($marketplace?->id),

            SC\Section::make(__('Banner'))
                ->description(__('Apare în caruselul de sus al homepage-ului, printre evenimentele recomandate.'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Nume intern'))
                        ->helperText(__('Doar pentru admin, nu apare pe site.'))
                        ->required()->maxLength(191),
                    Forms\Components\TextInput::make('url')
                        ->label(__('Link (URL)'))
                        ->placeholder('https://ambilet.ro/... sau /bilete/...')
                        ->required()->maxLength(1000)
                        ->rule('regex:/^(https?:\/\/|\/)/i')
                        ->validationMessages(['regex' => 'Linkul trebuie să înceapă cu https:// sau /']),
                    Forms\Components\TextInput::make('title')
                        ->label(__('Titlu (opțional)'))
                        ->helperText(__('Gol = bannerul se afișează fără text peste imagine.'))
                        ->maxLength(191),
                    Forms\Components\TextInput::make('subtitle')
                        ->label(__('Subtitlu (opțional)'))
                        ->maxLength(191),
                    Forms\Components\Toggle::make('open_in_new_tab')
                        ->label(__('Deschide în tab nou')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Activ'))
                        ->default(true),
                ])->columns(2),

            SC\Section::make(__('Imagini'))
                ->description(__('Orizontal = desktop/tabletă, vertical = mobil. Dacă lipsește una, bannerul nu apare pe acel tip de ecran.'))
                ->schema([
                    Forms\Components\FileUpload::make('image_horizontal')
                        ->label(__('Imagine orizontală'))
                        ->helperText(__('Recomandat 2058 × 1080 px (raport ~1.9:1).'))
                        ->image()
                        ->disk('public')
                        ->directory('hero-banners')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->requiredWithout('image_vertical'),
                    Forms\Components\FileUpload::make('image_vertical')
                        ->label(__('Imagine verticală (mobil)'))
                        ->helperText(__('Recomandat 1080 × 1620 px (raport 2:3, ca posterele).'))
                        ->image()
                        ->disk('public')
                        ->directory('hero-banners')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->requiredWithout('image_horizontal'),
                ])->columns(2),

            SC\Section::make(__('Afișare'))
                ->schema([
                    Forms\Components\TextInput::make('position')
                        ->label(__('Poziția în carusel'))
                        ->helperText(__('1 = primul slide. Dacă sunt mai puține evenimente, bannerul ajunge la final.'))
                        ->numeric()->minValue(1)->maxValue(50)->default(1)->required(),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('Afișează de la (opțional)'))
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('Afișează până la (opțional)'))
                        ->seconds(false)
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_horizontal')->label(__('Orizontal'))->disk('public')->height(40),
                Tables\Columns\ImageColumn::make('image_vertical')->label(__('Vertical'))->disk('public')->height(40),
                Tables\Columns\TextColumn::make('name')->label(__('Nume'))->searchable()
                    ->description(fn (MarketplaceHeroBanner $r) => $r->title),
                Tables\Columns\TextColumn::make('url')->label(__('Link'))->limit(40)->color('gray'),
                Tables\Columns\TextColumn::make('position')->label(__('Poziție'))->sortable()->badge(),
                Tables\Columns\TextColumn::make('starts_at')->label(__('De la'))->dateTime('d.m.Y H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('ends_at')->label(__('Până la'))->dateTime('d.m.Y H:i')->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Activ')),
            ])
            ->defaultSort('position')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeroBanners::route('/'),
            'create' => Pages\CreateHeroBanner::route('/create'),
            'edit' => Pages\EditHeroBanner::route('/{record}/edit'),
        ];
    }
}
