<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\TenantSite;
use App\Services\TenantSites\SiteConfigBuilder;
use App\Services\TenantSites\TemplateRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;

/**
 * Personalizarea site-ului găzduit de noi pentru un tenant (resources/tenant-sites):
 * template, identitate, culori și fonturi, meniu și secțiunile fiecărei pagini.
 * Formularele secțiunilor se generează din template.json-ul template-ului ales.
 * Se lucrează pe ciornă; „Publică” o face vizibilă pe site.
 */
class ManageTenantSite extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TenantResource::class;

    protected string $view = 'filament.resources.tenants.pages.manage-tenant-site';

    /** Fonturi Google oferite în panou (oricare nume din Google Fonts merge pe site). */
    public const FONTS = [
        'Playfair Display', 'Cormorant Garamond', 'Libre Baskerville', 'Lora', 'Merriweather', 'Fraunces',
        'DM Serif Display', 'Lato', 'DM Sans', 'Inter', 'Poppins', 'Montserrat', 'Raleway', 'Work Sans',
        'Nunito', 'Open Sans', 'Roboto', 'Space Grotesk', 'Syne', 'Oswald', 'Bebas Neue', 'Archivo',
    ];

    public const COLORS = [
        'canvas' => 'Fundal pagină',
        'surface' => 'Fundal secțiuni / carduri',
        'panel' => 'Fundal panouri (cont client)',
        'fg' => 'Text',
        'muted' => 'Text secundar',
        'primary' => 'Culoare principală (butoane, accente)',
        'accent' => 'Culoare secundară',
    ];

    public ?array $data = [];

    public TenantSite $site;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var Tenant $tenant */
        $tenant = $this->record;
        $builder = app(SiteConfigBuilder::class);
        $template = config('tenant-sites.default_template');

        $this->site = TenantSite::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['template' => $template, 'draft' => $builder->defaultDraft($tenant, $template)]
        );

        $draft = $builder->fillMissing($this->site->draft ?? [], $tenant, $this->site->template);
        $draft['pages'] = $this->onlyKnownSections($draft['pages'] ?? [], $this->site->template);
        $this->form->fill([
            'template' => $this->site->template,
            'demo_checkout' => \App\Support\DemoCheckout::allowed($tenant),
        ] + $draft);
    }

    /**
     * La schimbarea template-ului: tema lui, paginile pe care nu le aveam cu
     * conținutul implicit, iar din paginile comune scot secțiunile pe care noul
     * template nu le cunoaște.
     */
    public function switchTemplate(string $template): void
    {
        if (! app(TemplateRegistry::class)->get($template)) {
            return;
        }
        $defaults = app(SiteConfigBuilder::class)->defaultDraft($this->record, $template);
        $pages = $this->data['pages'] ?? [];
        foreach ($defaults['pages'] as $key => $page) {
            if (empty($pages[$key]['sections'])) {
                $pages[$key] = $page;
            }
        }
        $this->data['theme'] = $defaults['theme'];
        $this->data['pages'] = $this->onlyKnownSections($pages, $template);
    }

    private function onlyKnownSections(array $pages, string $template): array
    {
        $types = array_keys(app(TemplateRegistry::class)->get($template)['sections'] ?? []);
        foreach ($pages as $key => $page) {
            $pages[$key]['sections'] = array_filter(
                (array) ($page['sections'] ?? []),
                fn ($item) => in_array($item['type'] ?? null, $types, true)
            );
        }

        return $pages;
    }

    public function getTitle(): string
    {
        return 'Site — ' . ($this->record->public_name ?: $this->record->name);
    }

    public function getBreadcrumb(): string
    {
        return 'Site';
    }

    public function form(Schema $schema): Schema
    {
        $template = $this->data['template'] ?? $this->site->template;
        $manifest = app(TemplateRegistry::class)->get($template) ?? [];

        $tabs = [
            SC\Tabs\Tab::make('Identitate')->icon('heroicon-o-identification')->schema([
                SC\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('branding.name')->label('Nume afișat')->required()->maxLength(120),
                    Forms\Components\TextInput::make('branding.short_name')->label('Inițiale (logo text)')
                        ->maxLength(4)->helperText('Apar în cerc când nu e urcat un logo'),
                    Forms\Components\TextInput::make('branding.tagline')->label('Sub nume')->maxLength(80)
                        ->helperText('Ex.: orașul, „Teatru de păpuși”'),
                    Forms\Components\Textarea::make('branding.description')->label('Descriere pentru Google')->rows(2)->maxLength(300),
                    $this->upload('branding.logo', 'Logo')->helperText('PNG/SVG cu fundal transparent; înlocuiește inițialele'),
                    $this->upload('branding.favicon', 'Favicon')->helperText('Pătrat, minim 64×64'),
                ]),
                SC\Section::make('Subsol')->schema([
                    Forms\Components\Textarea::make('footer.text')->label('Text scurt')->rows(2)->columnSpanFull(),
                    Forms\Components\TextInput::make('footer.email')->label('Email'),
                    Forms\Components\TextInput::make('footer.phone')->label('Telefon'),
                    Forms\Components\TextInput::make('footer.facebook')->label('Facebook (link)')->url(),
                    Forms\Components\TextInput::make('footer.instagram')->label('Instagram (link)')->url(),
                    Forms\Components\TextInput::make('footer.tiktok')->label('TikTok (link)')->url(),
                    Forms\Components\TextInput::make('footer.youtube')->label('YouTube (link)')->url(),
                ])->columns(2),
            ]),

            SC\Tabs\Tab::make('Culori și fonturi')->icon('heroicon-o-swatch')->schema([
                SC\Section::make('Culori')
                    ->description('Se aplică pe tot site-ul, inclusiv coș, checkout și contul clientului.')
                    ->headerActions([
                        Actions\Action::make('reset_colors')->label('Culorile template-ului')->link()
                            ->action(function () use ($manifest) {
                                $this->data['theme']['colors'] = $manifest['theme']['colors'] ?? [];
                            }),
                    ])
                    ->schema(collect(self::COLORS)->map(
                        fn ($label, $key) => Forms\Components\ColorPicker::make("theme.colors.$key")->label($label)
                    )->values()->all())
                    ->columns(3),
                SC\Section::make('Fonturi')->schema([
                    Forms\Components\Select::make('theme.fonts.display')->label('Titluri')
                        ->options(array_combine(self::FONTS, self::FONTS))->searchable()->required(),
                    Forms\Components\Select::make('theme.fonts.body')->label('Text')
                        ->options(array_combine(self::FONTS, self::FONTS))->searchable()->required(),
                ])->columns(2),
            ]),

            SC\Tabs\Tab::make('Meniu')->icon('heroicon-o-bars-3')->schema([
                Forms\Components\Repeater::make('nav')->label('Meniul de sus')
                    ->schema([
                        Forms\Components\TextInput::make('label')->label('Text')->required(),
                        Forms\Components\TextInput::make('url')->label('Link')->required()
                            ->helperText('Pagină a site-ului (ex. /program) sau link complet'),
                    ])
                    ->columns(2)->reorderable()->addActionLabel('Adaugă link')
                    ->itemLabel(fn (array $state) => $state['label'] ?? null)->collapsible(),
                SC\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('header_cta.label')->label('Butonul din meniu — text')
                        ->helperText('Gol = fără buton'),
                    Forms\Components\TextInput::make('header_cta.url')->label('Butonul din meniu — link'),
                ]),
                Forms\Components\Placeholder::make('fixed_pages')->label('Pagini fixe ale template-ului')
                    ->content(implode(', ', array_keys($manifest['routes'] ?? [])) . ' · plus coș, checkout, cont client'),
            ]),
        ];

        foreach ($manifest['pages'] ?? [] as $key => $page) {
            $tabs[] = SC\Tabs\Tab::make('Pagina: ' . ($page['label'] ?? $key))->icon('heroicon-o-document')->schema([
                Forms\Components\TextInput::make("pages.$key.title")->label('Titlul paginii (în tab-ul browserului)'),
                Builder::make("pages.$key.sections")
                    ->label('Secțiuni (' . ($page['path'] ?? '') . ')')
                    ->blocks($this->blocks($manifest['sections'] ?? []))
                    ->addActionLabel('Adaugă secțiune')
                    ->collapsible()->collapsed()->cloneable()->reorderableWithButtons(),
            ]);
        }

        return $schema
            ->schema([
                SC\Section::make('Template')
                    ->schema([
                        Forms\Components\Select::make('template')->label('Template')
                            ->options(fn () => app(TemplateRegistry::class)->groupedOptionsFor($this->record->getKey(), $this->site->template))
                            ->required()->live()
                            ->helperText($manifest['description'] ?? null)
                            ->hintAction(
                                Actions\Action::make('demo_link')
                                    ->label('Vezi demo live')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->visible(fn () => (bool) $this->demoUrl($template))
                                    ->url(fn () => $this->demoUrl($template))
                                    ->openUrlInNewTab()
                            )
                            ->afterStateUpdated(fn ($state) => $this->switchTemplate((string) $state)),
                        Forms\Components\Placeholder::make('status')->label('Stare')
                            ->content(fn () => $this->statusLine()),
                        Forms\Components\Toggle::make('demo_checkout')
                            ->label('Checkout demo (plată simulată)')
                            ->helperText('Plățile reale pentru site-urile de tenant nu sunt încă conectate. Pornit: comenzile se „plătesc” fără bani (doar pentru prezentări). Oprit: checkout-ul refuză comanda.')
                            ->columnSpanFull(),
                    ])->columns(2),
                SC\Tabs::make('site')->tabs($tabs)->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    /** Câte un bloc de Builder pentru fiecare secțiune din manifest. */
    private function blocks(array $sections): array
    {
        $blocks = [];
        foreach ($sections as $type => $def) {
            $blocks[] = Builder\Block::make($type)
                ->label(fn (?array $state) => trim(($def['label'] ?? $type) . (! empty($state['title']) ? ' — ' . strip_tags((string) $state['title']) : '') . (($state['enabled'] ?? true) ? '' : ' (ascunsă)')))
                ->icon($def['icon'] ?? 'heroicon-o-square-2-stack')
                ->schema(array_merge(
                    [Forms\Components\Toggle::make('enabled')->label('Vizibilă pe site')->default(true)->columnSpanFull()],
                    $this->fields($def['fields'] ?? [])
                ))
                ->columns(2);
        }

        return $blocks;
    }

    /** Câmpurile Filament pentru definițiile din manifest. */
    private function fields(array $defs): array
    {
        $out = [];
        foreach ($defs as $f) {
            $name = $f['name'];
            $label = $f['label'] ?? $name;
            $c = match ($f['type'] ?? 'text') {
                'textarea' => Forms\Components\Textarea::make($name)->rows(3)->columnSpanFull(),
                'toggle' => Forms\Components\Toggle::make($name),
                'number' => Forms\Components\TextInput::make($name)->numeric(),
                'select' => Forms\Components\Select::make($name)->options($f['options'] ?? [])->native(false),
                'image' => null,
                'repeater' => Forms\Components\Repeater::make($name)
                    ->schema($this->fields($f['fields'] ?? []))
                    ->columns(2)->collapsible()->reorderable()->columnSpanFull()
                    ->maxItems($f['max'] ?? null)
                    ->itemLabel(fn (array $state) => $state['title'] ?? $state['label'] ?? $state['value'] ?? null),
                default => Forms\Components\TextInput::make($name),
            };

            if (($f['type'] ?? '') === 'image') {
                $out[] = SC\Group::make([
                    $this->upload($name, $label),
                    Forms\Components\TextInput::make($name . '_url')->label('sau link către imagine')->url()
                        ->helperText($f['help'] ?? 'Folosit doar când nu e urcată o imagine'),
                ])->columnSpanFull()->columns(2);

                continue;
            }

            $c->label($label);
            if (! empty($f['help'])) {
                $c->helperText($f['help']);
            }
            $out[] = $c;
        }

        return $out;
    }

    private function upload(string $name, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)->label($label)
            ->image()->disk(config('tenant-sites.disk'))
            ->directory(config('tenant-sites.upload_directory') . '/' . $this->record->getKey())
            ->visibility('public')->maxSize(4096);
    }

    private function statusLine(): string
    {
        $site = $this->site->fresh();
        if (! $site?->isPublished()) {
            return 'Nepublicat — site-ul arată „în pregătire”.';
        }
        $line = 'Publicat ' . $site->published_at->timezone('Europe/Bucharest')->format('d.m.Y H:i');

        return $site->hasUnpublishedChanges() ? $line . ' · ciorna are modificări nepublicate' : $line;
    }

    /** Primul demo live al template-ului (demo-*.tics.ro), dacă există. */
    public function demoUrl(?string $template): ?string
    {
        if (! $template) {
            return null;
        }
        $demo = \App\Models\TenantSiteDemo::with('domain')->where('template', $template)
            ->where('is_listed', true)->orderBy('sort')->first();

        return $demo?->domain ? 'https://' . $demo->domain->domain : null;
    }

    /** Domeniul pe care rulează site-ul: subdomeniul nostru, altfel domeniul principal activ. */
    public function siteDomain(): ?string
    {
        $domains = $this->record->domains()->where('is_active', true)->get();

        return ($domains->firstWhere('is_managed_subdomain', true) ?? $domains->firstWhere('is_primary', true))?->domain;
    }

    public function save(bool $notify = true): void
    {
        $state = $this->form->getState();
        $template = $state['template'];
        $demoCheckout = (bool) ($state['demo_checkout'] ?? false);
        unset($state['template'], $state['demo_checkout']);

        if ($demoCheckout !== \App\Support\DemoCheckout::allowed($this->record)) {
            $settings = $this->record->settings ?? [];
            $settings['demo_checkout'] = $demoCheckout;
            $this->record->update(['settings' => $settings]);
        }

        $draft = app(SiteConfigBuilder::class)->fillMissing($state, $this->record, $template);
        $this->site->update(['template' => $template, 'draft' => $draft]);

        if ($notify) {
            Notification::make()->title('Ciorna a fost salvată')->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Previzualizează')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->disabled(fn () => ! $this->siteDomain())
                ->tooltip(fn () => $this->siteDomain() ? 'Salvează ciorna și o deschide pe site' : 'Tenantul nu are încă un subdomeniu sau domeniu activ')
                ->action(function () {
                    $this->save(false);
                    // window.open după un apel Livewire e blocat de browsere: linkul vine în notificare
                    Notification::make()
                        ->title('Ciorna a fost salvată')
                        ->body('Previzualizarea arată ciorna doar în browserul tău; site-ul public rămâne neschimbat.')
                        ->success()
                        ->actions([
                            Actions\Action::make('open')->label('Deschide previzualizarea')->button()
                                ->url('https://' . $this->siteDomain() . '/?preview=' . $this->site->preview_token)
                                ->openUrlInNewTab(),
                        ])
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('save')
                ->label('Salvează ciorna')
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
            Actions\Action::make('publish')
                ->label('Publică')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Ciorna devine vizibilă pe site în cel mult un minut.')
                ->action(function () {
                    $this->save(false);
                    $this->site->refresh()->publish();
                    Notification::make()->title('Site publicat')->success()->send();
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('reset')
                    ->label('Conținutul implicit al template-ului')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Ciorna se înlocuiește cu textele, culorile și secțiunile implicite. Versiunea publicată rămâne până la următoarea publicare.')
                    ->action(function () {
                        $draft = app(SiteConfigBuilder::class)->defaultDraft($this->record, $this->site->template);
                        $this->site->update(['draft' => $draft]);
                        $this->form->fill([
                            'template' => $this->site->template,
                            'demo_checkout' => \App\Support\DemoCheckout::allowed($this->record),
                        ] + $draft);
                        Notification::make()->title('Ciorna a fost resetată')->success()->send();
                    }),
                Actions\Action::make('edit_tenant')
                    ->label('Înapoi la tenant')
                    ->icon('heroicon-o-arrow-left')
                    ->url(fn () => TenantResource::getUrl('edit', ['record' => $this->record])),
            ]),
        ];
    }
}
