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

            SC\Section::make('Banner')
                ->description('Apare în caruselul de sus al homepage-ului, printre evenimentele recomandate.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume intern')
                        ->helperText('Doar pentru admin, nu apare pe site.')
                        ->required()->maxLength(191),
                    Forms\Components\TextInput::make('url')
                        ->label('Link (URL)')
                        ->placeholder('https://ambilet.ro/... sau /bilete/...')
                        ->required()->maxLength(1000)
                        ->rule('regex:/^(https?:\/\/|\/)/i')
                        ->validationMessages(['regex' => 'Linkul trebuie să înceapă cu https:// sau /']),
                    Forms\Components\TextInput::make('title')
                        ->label('Titlu (opțional)')
                        ->helperText('Gol = bannerul se afișează fără text peste imagine.')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('subtitle')
                        ->label('Subtitlu (opțional)')
                        ->maxLength(191),
                    Forms\Components\Toggle::make('open_in_new_tab')
                        ->label('Deschide în tab nou'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activ')
                        ->default(true),
                ])->columns(2),

            SC\Section::make('Imagini')
                ->description('Orizontal = desktop/tabletă, vertical = mobil. Dacă lipsește una, bannerul nu apare pe acel tip de ecran.')
                ->schema([
                    Forms\Components\FileUpload::make('image_horizontal')
                        ->label('Imagine orizontală')
                        ->helperText('Recomandat 2058 × 1080 px (raport ~1.9:1).')
                        ->image()
                        ->directory('hero-banners')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->requiredWithout('image_vertical'),
                    Forms\Components\FileUpload::make('image_vertical')
                        ->label('Imagine verticală (mobil)')
                        ->helperText('Recomandat 1080 × 1620 px (raport 2:3, ca posterele).')
                        ->image()
                        ->directory('hero-banners')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->requiredWithout('image_horizontal'),
                ])->columns(2),

            SC\Section::make('Afișare')
                ->schema([
                    Forms\Components\TextInput::make('position')
                        ->label('Poziția în carusel')
                        ->helperText('1 = primul slide. Dacă sunt mai puține evenimente, bannerul ajunge la final.')
                        ->numeric()->minValue(1)->maxValue(50)->default(1)->required(),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Afișează de la (opțional)')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Afișează până la (opțional)')
                        ->seconds(false)
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_horizontal')->label('Orizontal')->disk('public')->height(40),
                Tables\Columns\ImageColumn::make('image_vertical')->label('Vertical')->disk('public')->height(40),
                Tables\Columns\TextColumn::make('name')->label('Nume')->searchable()
                    ->description(fn (MarketplaceHeroBanner $r) => $r->title),
                Tables\Columns\TextColumn::make('url')->label('Link')->limit(40)->color('gray'),
                Tables\Columns\TextColumn::make('position')->label('Poziție')->sortable()->badge(),
                Tables\Columns\TextColumn::make('starts_at')->label('De la')->dateTime('d.m.Y H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('ends_at')->label('Până la')->dateTime('d.m.Y H:i')->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Activ'),
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
