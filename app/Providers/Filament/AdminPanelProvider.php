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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // TESTING: Disable ALL resources to isolate 403 issue
            // ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // ->pages([
            //     Dashboard::class,
            // ])
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // ->widgets([
            //     AccountWidget::class,
            //     FilamentInfoWidget::class,
            // ])
            ->middleware([
                // \App\Http\Middleware\DebugCookieSession::class, // DISABLED - was calling session() too early
                // \App\Http\Middleware\DebugAdminAccess::class, // DISABLED - not needed
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // AuthenticateSession::class, // DISABLED - causes session regeneration issues
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->maxContentWidth('full')

            // === Assets: CSS + JS - TEMPORARILY DISABLED (files missing) ===
            // ->assets([
            //     Css::make('epas-skin', asset('admin/epas-skin.css')),
            //     Js::make('epas-skin', asset('admin/epas-skin.js')),
            // ])

            // Render hooks for custom layout elements - TEMPORARILY DISABLED
            // ->renderHook('panels::sidebar.header', fn (): string => view('filament.components.sidebar-brand')->render())

            // Custom topbar inside main content + event form menu - TEMPORARILY DISABLED
            // ->renderHook('panels::content.start', function (): string {
            //     $html = view('filament.components.custom-topbar')->render();

            //     // Add event form anchor menu if on event pages
            //     if (request()->routeIs('filament.admin.resources.events.*')) {
            //         $html .= view('filament.events.widgets.event-form-anchor-menu')->render();
            //     }

            //     return $html;
            // })
            ;
    }

    public function boot(): void
    {
        // All render hooks disabled
    }
}

