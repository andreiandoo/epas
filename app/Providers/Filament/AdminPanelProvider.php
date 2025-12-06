<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use Filament\Support\Facades\FilamentView;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use App\Filament\Pages\CustomDashboard;
use App\Http\Middleware\DebugFilamentAuth;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Amber,
            ])

            // Auto-discover resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            // Default widgets disabled
            ->widgets([])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                DebugFilamentAuth::class, // Custom middleware that explicitly checks canAccessPanel()
            ])

            // Custom assets
            ->assets([
                Css::make('epas-skin', asset('css/epas-skin.css')),
                Js::make('epas-skin', asset('js/epas-skin.js')),
            ])

            // Custom brand with dynamic logos from settings
            ->brandLogo(fn () => view('filament.components.sidebar-brand'))
            ->brandLogoHeight('2rem')

            // Custom topbar in actual topbar (not in content)
            ->renderHook('panels::topbar.end', fn (): string => view('filament.components.custom-topbar')->render())

            // Event form anchor menu (only on event pages)
            ->renderHook('panels::content.start', function (): string {
                if (request()->routeIs('filament.admin.resources.events.*')) {
                    return view('filament.events.widgets.event-form-anchor-menu')->render();
                }
                return '';
            })
            ;
    }

    public function boot(): void
    {
        // Render hooks are now in panel() method above
    }
}
