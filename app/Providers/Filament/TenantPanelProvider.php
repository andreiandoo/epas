<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TenantPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Disable autocomplete on all form inputs
        TextInput::configureUsing(function (TextInput $input): void {
            $input->autocomplete(false);
        });

        Textarea::configureUsing(function (Textarea $input): void {
            $input->autocomplete(false);
        });

        Select::configureUsing(function (Select $input): void {
            $input->native(false); // Use custom select component
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('tenant')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->darkMode(condition: true)

            // Discover tenant-specific resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')

            // Register custom routes for pages with dynamic parameters
            ->routes(function () {
                Route::get('/microservices/{slug}/settings', \App\Filament\Tenant\Pages\MicroserviceSettings::class)
                    ->name('filament.tenant.pages.microservice-settings');
            })

            // Define navigation group order
            // Note: Groups cannot have icons if their items also have icons (Filament 4 constraint)
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Services'),
                NavigationGroup::make('Website'),
                NavigationGroup::make('Settings')
                    ->collapsed(),
                NavigationGroup::make('Help')
                    ->collapsed(),
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            // Custom assets (Tailwind utilities + custom skin)
            ->assets([
                Css::make('tailwind-theme', \Illuminate\Support\Facades\Vite::asset('resources/css/filament/tenant/theme.css')),
                Css::make('epas-skin', asset('css/epas-skin.css')),
                Js::make('epas-skin', asset('js/epas-skin.js')),
            ])

            // Custom brand with dynamic logos from settings
            ->brandLogo(fn () => view('filament.components.sidebar-brand'))
            ->brandLogoHeight('2rem')

            // Disable default topbar elements (we have custom-topbar)
            ->globalSearch(false)
            ->userMenu(false)

            ->renderHook('panels::topbar.end', fn (): string => view('filament.components.custom-topbar')->render())
            ->renderHook('panels::sidebar.footer', fn (): string => view('filament.components.tenant-support-card')->render())

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>');
    }
}
