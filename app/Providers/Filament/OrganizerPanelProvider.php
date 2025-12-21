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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OrganizerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('organizer')
            ->path('organizer')
            ->login()
            ->authGuard('organizer')
            ->colors([
                'primary' => Color::Emerald,
            ])

            // Discover organizer-specific resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Organizer/Resources'), for: 'App\\Filament\\Organizer\\Resources')
            ->discoverPages(in: app_path('Filament/Organizer/Pages'), for: 'App\\Filament\\Organizer\\Pages')
            ->discoverWidgets(in: app_path('Filament/Organizer/Widgets'), for: 'App\\Filament\\Organizer\\Widgets')

            // Define navigation group order
            ->navigationGroups([
                NavigationGroup::make('Events'),
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Settings')
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

            // Custom brand
            ->brandName('Organizer Dashboard')
            ->brandLogo(fn () => view('filament.organizer.components.brand-logo'))
            ->brandLogoHeight('2rem')

            // Custom Topbar
            ->renderHook('panels::topbar.end', fn (): string => view('filament.organizer.components.topbar')->render());
    }
}
