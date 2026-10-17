<?php

namespace App\Filament\Resources\TenantSiteDemos;

use App\Models\Tenant;
use App\Models\TenantSiteDemo;
use App\Services\TenantSites\TemplateRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Demo-urile live ale template-urilor: demo-{slug}.tics.ro, pe datele unui
 * tenant demo, listate în galeria demo.tics.ro. Se trimit direct prospecților.
 */
class TenantSiteDemoResource extends Resource
{
    protected static ?string $model = TenantSiteDemo::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static UnitEnum|string|null $navigationGroup = 'Tenants';

    protected static ?string $navigationLabel = 'Demo-uri site';

    protected static ?string $modelLabel = 'demo de site';

    protected static ?string $pluralModelLabel = 'Demo-uri site';

    protected static ?string $slug = 'site-demos';

    protected static ?int $navigationSort = 30;

    public static function demoBaseDomain(): string
    {
        return (string) config('tenant-sites.demo_base_domain', 'tics.ro');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Demo')
                ->description('Demo-ul rulează pe datele tenantului ales, cu template-ul ales și conținutul lui implicit. Tenantul primește checkout demo (plată simulată).')
                ->schema([
                    Forms\Components\Select::make('template')
                        ->label('Template')
                        ->options(fn () => array_map(fn ($m) => $m['name'] ?? '', app(TemplateRegistry::class)->demoable()))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $m = app(TemplateRegistry::class)->get($state);
                            if (! $get('title') && $m) {
                                $set('title', $m['name'] ?? $state);
                            }
                            if (! $get('description') && $m) {
                                $set('description', $m['description'] ?? null);
                            }
                            if (! $get('slug') && $state) {
                                $set('slug', $state);
                            }
                        }),
                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant demo (datele)')
                        ->helperText('Un tenant demo pe verticală (Demo Teatru, Demo Festival ...). Evenimentele, artiștii și sălile lui apar în demo.')
                        ->options(fn () => Tenant::query()->orderBy('name')->get()
                            ->mapWithKeys(fn (Tenant $t) => [$t->id => '#' . $t->id . ' ' . ($t->public_name ?: $t->name)]))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('slug')
                        ->label('Adresă')
                        ->prefix('demo-')
                        ->suffix(fn () => '.' . static::demoBaseDomain())
                        ->required()
                        ->regex('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
                        ->maxLength(40)
                        ->visibleOn('create')
                        ->helperText('Litere mici, cifre, cratimă. Ex.: teatru → demo-teatru.' . static::demoBaseDomain()),
                    Forms\Components\Placeholder::make('host')
                        ->label('Adresă')
                        ->content(fn (?TenantSiteDemo $record) => $record?->domain?->domain)
                        ->visibleOn('edit'),
                    Forms\Components\TextInput::make('title')->label('Titlu în galerie')->required()->maxLength(120),
                    Forms\Components\Textarea::make('description')->label('Descriere în galerie')->rows(2)->maxLength(400),
                    Forms\Components\Toggle::make('is_listed')->label('Apare în galeria demo.' . static::demoBaseDomain())->default(true),
                    Forms\Components\TextInput::make('sort')->label('Ordine')->numeric()->default(0),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Titlu')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('template')->label('Template')->badge(),
                Tables\Columns\TextColumn::make('domain.domain')->label('Adresă')
                    ->url(fn (TenantSiteDemo $r) => $r->domain ? 'https://' . $r->domain->domain : null, true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('tenant.name')->label('Tenant demo'),
                Tables\Columns\IconColumn::make('is_listed')->label('În galerie')->boolean(),
                Tables\Columns\TextColumn::make('sort')->label('Ordine')->sortable(),
            ])
            ->defaultSort('sort')
            ->headerActions([
                Action::make('gallery')
                    ->label('Deschide galeria')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => 'https://demo.' . static::demoBaseDomain(), true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Se șterge și domeniul demo-ului. Datele tenantului demo rămân.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantSiteDemos::route('/'),
            'create' => Pages\CreateTenantSiteDemo::route('/create'),
            'edit' => Pages\EditTenantSiteDemo::route('/{record}/edit'),
        ];
    }
}
