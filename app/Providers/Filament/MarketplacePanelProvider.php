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
                Route::get('/organizers/{id}/login-as', function (int $id) {
                    $organizer = \App\Models\MarketplaceOrganizer::findOrFail($id);
                    $marketplace = \App\Models\MarketplaceClient::find($organizer->marketplace_client_id);
                    if (!$marketplace || !$marketplace->domain) {
                        abort(404, 'Marketplace domain not configured');
                    }
                    // Revoke old impersonation tokens and create a fresh one
                    $organizer->tokens()->where('name', 'admin-impersonation')->delete();
                    $token = $organizer->createToken('admin-impersonation')->plainTextToken;
                    $domain = preg_replace('#^https?://#', '', rtrim($marketplace->domain, '/'));
                    $url = 'https://' . $domain . '/organizator/panou?_admin_token=' . urlencode($token);
                    return redirect($url);
                })->name('filament.marketplace.organizer.login-as');
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

            // Sticky / floating save button for long forms
            ->renderHook('panels::body.end', fn (): string => view('filament.sticky-actions')->render())

            // Secondary sidebar for Services / Microservices navigation
            ->renderHook('panels::body.end', fn (): string => view('filament.components.marketplace-secondary-sidebar')->render())
            ->renderHook('panels::body.end', fn () => <<<'HTML'
            <script>
            // Secondary Sidebar – Alpine store & DOM interception
            document.addEventListener('alpine:init', () => {
                Alpine.store('secondarySidebar', {
                    open: false,
                    toggle() {
                        this.open = !this.open;
                        document.body.classList.toggle('ep-secondary-sidebar-open', this.open);
                    },
                    close() {
                        this.open = false;
                        document.body.classList.remove('ep-secondary-sidebar-open');
                    }
                });
            });

            function epSetupSecondarySidebar() {
                const servicesGroup = document.querySelector('[data-group-label="Services"]');
                if (!servicesGroup) return;

                const navItems = servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item');
                let microservicesItem = null;

                navItems.forEach(item => {
                    const label = item.querySelector('.fi-sidebar-item-label');
                    if (label && label.textContent.trim() === 'Microservices') {
                        microservicesItem = item;
                    }
                });

                if (microservicesItem) {
                    const btn = microservicesItem.querySelector('a.fi-sidebar-item-btn');
                    if (btn && !btn.dataset.epSecondaryBound) {
                        btn.dataset.epSecondaryBound = 'true';
                        btn.addEventListener('click', (e) => {
                            // On mobile, let normal navigation happen (secondary sidebar is hidden)
                            if (window.matchMedia('(max-width: 63.99rem)').matches) return;
                            e.preventDefault();
                            e.stopPropagation();
                            Alpine.store('secondarySidebar').toggle();
                        });
                    }
                }

                // Clone other Services group items into secondary sidebar
                epCloneServicesItems(servicesGroup, microservicesItem);

                // Close secondary sidebar when clicking any OTHER primary nav item
                document.querySelectorAll('.fi-sidebar-item a.fi-sidebar-item-btn').forEach(link => {
                    if (link.closest('.fi-sidebar-item') === microservicesItem) return;
                    if (!link.dataset.epCloseSecondaryBound) {
                        link.dataset.epCloseSecondaryBound = 'true';
                        link.addEventListener('click', () => {
                            if (Alpine.store('secondarySidebar')?.open) {
                                Alpine.store('secondarySidebar').close();
                            }
                        });
                    }
                });

                // Highlight active microservice in secondary sidebar
                epHighlightActiveSecondary();
            }

            function epCloneServicesItems(servicesGroup, microservicesItem) {
                const targetUl = document.getElementById('ep-secondary-sidebar-services-clone');
                const section = document.getElementById('ep-secondary-sidebar-services-section');
                if (!targetUl || !section) return;
                targetUl.innerHTML = '';

                const items = servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item');
                let clonedCount = 0;

                items.forEach(item => {
                    if (item === microservicesItem) return;
                    const clone = item.cloneNode(true);
                    // Remove sub-group items to keep it flat
                    clone.querySelectorAll('.fi-sidebar-sub-group-items').forEach(sub => sub.remove());
                    // Remove dropdown elements
                    clone.querySelectorAll('[x-data*="dropdown"]').forEach(dd => dd.remove());
                    targetUl.appendChild(clone);
                    clonedCount++;
                });

                section.style.display = clonedCount > 0 ? '' : 'none';
            }

            function epHighlightActiveSecondary() {
                const currentPath = window.location.pathname;
                document.querySelectorAll('#ep-secondary-sidebar .ep-secondary-sidebar-item').forEach(link => {
                    const href = link.getAttribute('href');
                    link.classList.toggle('ep-active', href && currentPath.startsWith(href));
                });
            }

            // Bind on initial load and after Livewire SPA navigation
            document.addEventListener('DOMContentLoaded', () => epSetupSecondarySidebar());
            document.addEventListener('livewire:navigated', () => {
                requestAnimationFrame(() => epSetupSecondarySidebar());
            });
            </script>
            HTML)

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>')

            // Preserve scroll position, section collapse state AND repeater collapse state during Livewire morph updates
            ->renderHook('panels::body.end', fn () => <<<'HTML'
            <script>
            document.addEventListener('livewire:init', () => {
                let savedScrollY = null;
                let isFormSubmit = false;
                let savedCollapseStates = new Map();
                let savedSectionStates = new Map();

                Livewire.hook('commit.prepare', ({ component }) => {
                    savedScrollY = window.scrollY;
                    isFormSubmit = false;

                    // Save collapse states of all repeater items
                    savedCollapseStates.clear();
                    document.querySelectorAll('.fi-fo-repeater-item').forEach(el => {
                        const key = el.getAttribute('wire:key');
                        if (key) {
                            savedCollapseStates.set(key, el.classList.contains('fi-collapsed'));
                        }
                    });

                    // Save collapse states of all collapsible sections
                    savedSectionStates.clear();
                    document.querySelectorAll('[x-data]').forEach(el => {
                        if (el._x_dataStack && typeof el._x_dataStack[0]?.isCollapsed !== 'undefined') {
                            const id = el.getAttribute('wire:key') || el.getAttribute('id') || el.dataset.epSection;
                            if (id) {
                                savedSectionStates.set(id, el._x_dataStack[0].isCollapsed);
                            }
                        }
                    });
                });

                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    // Detect form submissions (save, delete, etc.) — don't block their scroll
                    const calls = commit?.calls || [];
                    if (calls.length > 0) {
                        const methodNames = calls.map(c => c.method || '');
                        if (methodNames.some(m => m === 'save' || m === 'create' || m === 'delete' || m.startsWith('mount'))) {
                            isFormSubmit = true;
                        }
                    }

                    succeed(({ snapshot, effects }) => {
                        requestAnimationFrame(() => {
                            // Restore scroll position
                            if (savedScrollY !== null && !isFormSubmit) {
                                window.scrollTo({ top: savedScrollY, behavior: 'instant' });
                            }
                            savedScrollY = null;

                            if (!isFormSubmit) {
                                // Restore repeater collapse states
                                if (savedCollapseStates.size > 0) {
                                    document.querySelectorAll('.fi-fo-repeater-item').forEach(el => {
                                        const key = el.getAttribute('wire:key');
                                        if (key && savedCollapseStates.has(key)) {
                                            const wasCollapsed = savedCollapseStates.get(key);
                                            if (el._x_dataStack && typeof el._x_dataStack[0]?.isCollapsed !== 'undefined') {
                                                el._x_dataStack[0].isCollapsed = wasCollapsed;
                                            } else if (wasCollapsed) {
                                                el.classList.add('fi-collapsed');
                                            } else {
                                                el.classList.remove('fi-collapsed');
                                            }
                                        }
                                    });
                                }

                                // Restore section collapse states
                                if (savedSectionStates.size > 0) {
                                    document.querySelectorAll('[x-data]').forEach(el => {
                                        const id = el.getAttribute('wire:key') || el.getAttribute('id') || el.dataset?.epSection;
                                        if (id && savedSectionStates.has(id) && el._x_dataStack && typeof el._x_dataStack[0]?.isCollapsed !== 'undefined') {
                                            el._x_dataStack[0].isCollapsed = savedSectionStates.get(id);
                                        }
                                    });
                                }
                            }
                            savedCollapseStates.clear();
                            savedSectionStates.clear();
                        });
                    });
                });
            });
            </script>
            HTML);
    }
}
