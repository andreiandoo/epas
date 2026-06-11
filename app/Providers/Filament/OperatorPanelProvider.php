<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
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

/**
 * Operator panel at /operator — tablet-first UI for leisure venue staff
 * (check-in, POS, rentals, inventory). Auth uses standard email+password on
 * the `web` guard; access is gated by `EnsureTenantTeamMember` middleware
 * which loads the operator's TenantTeamMember row and exposes it as
 * `auth()->user()->teamMember`.
 */
class OperatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operator')
            ->path('operator')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->darkMode(condition: true)
            ->discoverResources(in: app_path('Filament/Operator/Resources'), for: 'App\\Filament\\Operator\\Resources')
            ->discoverPages(in: app_path('Filament/Operator/Pages'), for: 'App\\Filament\\Operator\\Pages')
            ->discoverWidgets(in: app_path('Filament/Operator/Widgets'), for: 'App\\Filament\\Operator\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Operațiuni'),
                NavigationGroup::make('Rapoarte')->collapsed(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureTenantTeamMember::class,
            ])
            // Tablet-first overrides: larger touch targets, simplified topbar.
            ->renderHook('panels::styles.after', fn () => <<<'HTML'
            <style>
            /* Operator tablet skin — large touch targets, simplified controls. */
            html { font-size: 16px; }
            .fi-btn { min-height: 48px; padding: 0.6rem 1.2rem; font-size: 0.95rem; }
            .fi-input, .fi-select-input, .fi-fo-text-input input { min-height: 48px; font-size: 1rem; }
            .fi-ta-row { font-size: 0.95rem; }
            /* Make navigation labels larger and easier to tap */
            .fi-sidebar-nav .fi-sidebar-item-button { padding: 0.85rem 1rem; font-size: 0.95rem; }
            .fi-sidebar-nav .fi-sidebar-item-icon { width: 1.25rem; height: 1.25rem; }
            </style>
            HTML);
    }
}
