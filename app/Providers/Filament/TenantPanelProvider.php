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

        \Filament\Forms\Components\DateTimePicker::configureUsing(function (\Filament\Forms\Components\DateTimePicker $picker): void {
            $picker->native(false)
                ->seconds(false)
                ->displayFormat('D, d M Y H:i')
                ->firstDayOfWeek(1)
                ->closeOnDateSelection()
                ->minutesStep(5);
        });

        \Filament\Forms\Components\DatePicker::configureUsing(function (\Filament\Forms\Components\DatePicker $picker): void {
            $picker->native(false)
                ->displayFormat('D, d M Y')
                ->firstDayOfWeek(1)
                ->closeOnDateSelection();
        });

        \Filament\Forms\Components\TimePicker::configureUsing(function (\Filament\Forms\Components\TimePicker $picker): void {
            $picker->seconds(false)
                ->minutesStep(5);
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

            // Hide default brand logo (we use sidebar logo instead, like Marketplace)
            ->brandLogo(fn () => '')
            ->brandLogoHeight('0')

            // Disable default topbar elements (we have custom-topbar)
            ->globalSearch(false)
            ->userMenu(false)

            // Logo at the top of sidebar navigation (same approach as Marketplace)
            ->renderHook('panels::sidebar.nav.start', fn (): string => view('filament.components.sidebar-brand')->render())

            // Custom topbar above fi-main but inside fi-main-ctn (same hook as Marketplace)
            ->renderHook('panels::page.start', fn (): string => view('filament.components.custom-topbar')->render())

            // Sidebar footer with support card
            ->renderHook('panels::sidebar.footer', fn (): string => view('filament.components.tenant-support-card')->render())

            // Sticky / floating save button for long forms (same as Marketplace)
            ->renderHook('panels::body.end', fn (): string => view('filament.sticky-actions')->render())

            // Inline CSS: hide default topbar + compact repeaters (same as Marketplace)
            ->renderHook('panels::styles.after', fn () => <<<'HTML'
            <style>
            /* Hide default Filament topbar (we use custom-topbar via panels::page.start) */
            .fi-topbar, .fi-topbar-ctn { display: none !important; }
            /* Fix sidebar height/top when topbar is hidden */
            .fi-body-has-topbar .fi-sidebar { height: 100dvh !important; top: 0 !important; }
            .fi-body-has-topbar .fi-main-ctn { min-height: 100dvh !important; padding-top: 0 !important; }
            /* Sidebar nav: remove row-gap */
            .fi-sidebar-nav { row-gap: 0 !important; }
            /* Page content: no padding on main, padding on header */
            .fi-main { padding: 0 !important; }
            .fi-page-header-main-ctn { padding: 1rem !important; }
            /* Prevent flash of unstyled content during Livewire morph */
            [wire\:loading] { opacity: 1 !important; }
            /* Compact repeater item content */
            .fi-fo-repeater-item-content { padding: 0 !important; }
            .fi-fo-repeater-item-content > .fi-sc.fi-sc-has-gap { gap: 0 !important; }
            .fi-fo-repeater .fi-section.fi-section-compact { padding: 0 !important; }
            .fi-fo-repeater .fi-section .fi-section-header-ctn { padding: 0.5rem 1rem !important; }
            .fi-fo-repeater .fi-section .fi-section-header-heading { font-size: 0.875rem !important; }
            .fi-fo-repeater-item-header-label { font-size: 1.125rem !important; }
            /* Allow dropdowns to overflow inside repeaters */
            .fi-fo-repeater-item-content,
            .fi-fo-repeater-item-content .fi-section,
            .fi-fo-repeater-item-content .fi-section-content-ctn { overflow: visible !important; }
            </style>
            HTML)

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>');
    }
}
