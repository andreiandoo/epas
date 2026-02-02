<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\AuthenticateMarketplaceOrSuperAdmin;
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
use App\Http\Middleware\SetLocaleFromRequest;

class MarketplacePanelProvider extends PanelProvider
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
            $input->native(false);
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('marketplace')
            ->path('marketplace')
            ->login()
            ->authGuard('marketplace_admin')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->darkMode(condition: true)

            // Discover marketplace-specific resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Marketplace/Resources'), for: 'App\\Filament\\Marketplace\\Resources')
            ->discoverPages(in: app_path('Filament/Marketplace/Pages'), for: 'App\\Filament\\Marketplace\\Pages')
            ->discoverWidgets(in: app_path('Filament/Marketplace/Widgets'), for: 'App\\Filament\\Marketplace\\Widgets')

            // Register custom routes for pages with dynamic parameters
            ->routes(function () {
                Route::get('/microservices/{slug}/settings', \App\Filament\Marketplace\Pages\MicroserviceSettings::class)
                    ->name('filament.marketplace.pages.microservice-settings');
                Route::get('/organizers/{id}/balance', \App\Filament\Marketplace\Pages\OrganizerBalance::class)
                    ->name('filament.marketplace.pages.organizer-balance');
                Route::get('/event-tax-report/{event}', \App\Filament\Marketplace\Pages\EventTaxReport::class)
                    ->name('filament.marketplace.pages.event-tax-report');
            })

            // Define navigation group order
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Organizers'),
                NavigationGroup::make('Services'),
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Reports'),
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
                SetLocaleFromRequest::class,
                AuthenticateMarketplaceOrSuperAdmin::class, // Allow super-admins from core
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            // Custom assets
            ->assets([
                Css::make('tailwind-theme', \Illuminate\Support\Facades\Vite::asset('resources/css/filament/marketplace/theme.css')),
                Css::make('epas-skin', asset('css/epas-skin.css')),
                Js::make('epas-skin', asset('js/epas-skin.js')),
            ])

            // Hide default brand logo (we use sidebar logo instead)
            ->brandLogo(fn () => '')
            ->brandLogoHeight('0')

            // Disable default global search (we have custom search)
            ->globalSearch(false)

            // Logo at the top of sidebar navigation
            ->renderHook('panels::sidebar.nav.start', fn (): string => view('filament.components.marketplace-sidebar-logo')->render())

            // Custom topbar above fi-main but inside fi-main-ctn
            ->renderHook('panels::page.start', fn (): string => view('filament.components.custom-topbar')->render())

            // Sidebar footer with support card
            ->renderHook('panels::sidebar.footer', fn (): string => view('filament.components.marketplace-support-card')->render())

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>');
    }
}
