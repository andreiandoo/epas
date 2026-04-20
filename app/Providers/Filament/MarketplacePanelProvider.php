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

        \Filament\Forms\Components\DateTimePicker::configureUsing(function (\Filament\Forms\Components\DateTimePicker $picker): void {
            $picker->native(false)
                ->seconds(false)
                ->displayFormat('D, d M Y H:i')
                ->firstDayOfWeek(1)
                ->minutesStep(5);
        });

        \Filament\Forms\Components\DatePicker::configureUsing(function (\Filament\Forms\Components\DatePicker $picker): void {
            $picker->native(false)
                ->displayFormat('D, d M Y')
                ->firstDayOfWeek(1)
                ->closeOnDateSelection();
        });

        \Filament\Forms\Components\TimePicker::configureUsing(function (\Filament\Forms\Components\TimePicker $picker): void {
            $picker->native(true)
                ->seconds(false)
                ->minutesStep(5)
                ->displayFormat('H:i');
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

                Route::get('/marketplace-customers/{id}/login-as', function (int $id) {
                    $customer = \App\Models\MarketplaceCustomer::findOrFail($id);
                    $marketplace = \App\Models\MarketplaceClient::find($customer->marketplace_client_id);
                    if (!$marketplace || !$marketplace->domain) {
                        abort(404, 'Marketplace domain not configured');
                    }
                    // Revoke old impersonation tokens and create a fresh one
                    $customer->tokens()->where('name', 'admin-impersonation')->delete();
                    $token = $customer->createToken('admin-impersonation')->plainTextToken;
                    $domain = preg_replace('#^https?://#', '', rtrim($marketplace->domain, '/'));
                    $url = 'https://' . $domain . '/cont/dashboard?_admin_customer_token=' . urlencode($token);
                    return redirect($url);
                })->name('filament.marketplace.customer.login-as');
            })

            // Define navigation group order
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Organizers'),
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Tools'),
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

            // Disable HTML5 validation on Filament forms (fixes "invalid form control with name=''" errors
            // caused by hidden inputs in Filament repeater fields with custom datetime pickers)
            ->renderHook('panels::body.end', fn (): string => '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    document.querySelectorAll("form").forEach(function(form) {
                        form.setAttribute("novalidate", "novalidate");
                    });
                });
                document.addEventListener("livewire:navigated", function() {
                    document.querySelectorAll("form").forEach(function(form) {
                        form.setAttribute("novalidate", "novalidate");
                    });
                });
                document.addEventListener("livewire:initialized", function() {
                    document.querySelectorAll("form").forEach(function(form) {
                        form.setAttribute("novalidate", "novalidate");
                    });
                });
            </script>')

            // Sticky / floating save button for long forms
            ->renderHook('panels::body.end', fn (): string => view('filament.sticky-actions')->render())

            // Secondary sidebar for Tools / Microservices / Communications / Gamification navigation
            ->renderHook('panels::body.end', fn (): string => view('filament.components.marketplace-secondary-sidebar')->render())
            ->renderHook('panels::body.end', fn () => <<<'HTML'
            <script>
            // Secondary Sidebar – Alpine store & DOM interception (multi-panel)
            document.addEventListener('alpine:init', () => {
                Alpine.store('secondarySidebar', {
                    open: false,
                    activePanel: null, // 'microservices', 'communications', 'gamification'
                    togglePanel(panel) {
                        if (this.open && this.activePanel === panel) {
                            this.close();
                        } else {
                            this.activePanel = panel;
                            this.open = true;
                            document.body.classList.add('ep-secondary-sidebar-open');
                            epShowPanel(panel);
                            epPositionSecondarySidebar();
                        }
                    },
                    close() {
                        this.open = false;
                        this.activePanel = null;
                        document.body.classList.remove('ep-secondary-sidebar-open');
                    },
                    openPanel(panel) {
                        if (!this.open || this.activePanel !== panel) {
                            this.activePanel = panel;
                            this.open = true;
                            document.body.classList.add('ep-secondary-sidebar-open');
                            epShowPanel(panel);
                            epPositionSecondarySidebar();
                        }
                    }
                });
            });

            // Panel config: label, icon SVG, source group label, trigger label in Tools
            const EP_PANELS = {
                microservices: {
                    title: 'Microservices',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>',
                    sourceGroup: null, // items come from Tools group itself
                    triggerLabel: 'Microservices'
                },
                communications: {
                    title: 'Communications',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                    sourceGroup: 'Communications',
                    triggerLabel: 'Communications'
                },
                gamification: {
                    title: 'Gamification',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
                    sourceGroup: 'Gamification',
                    triggerLabel: 'Gamification'
                },
                settings: {
                    title: 'Settings',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    sourceGroup: 'Settings',
                    triggerLabel: 'Settings'
                },
                help: {
                    title: 'Help',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                    sourceGroup: 'Help',
                    triggerLabel: 'Help'
                }
            };

            // Right arrow SVG appended to trigger items
            const EP_TRIGGER_ARROW = '<svg class="ep-trigger-arrow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>';

            function epShowPanel(panel) {
                // Toggle panel visibility
                document.querySelectorAll('.ep-secondary-sidebar-panel').forEach(p => {
                    p.style.display = p.dataset.epPanel === panel ? '' : 'none';
                });
                // Update header
                const cfg = EP_PANELS[panel];
                if (cfg) {
                    const iconEl = document.getElementById('ep-secondary-sidebar-icon');
                    const titleEl = document.getElementById('ep-secondary-sidebar-title');
                    if (iconEl) iconEl.innerHTML = cfg.icon;
                    if (titleEl) titleEl.textContent = cfg.title;
                }
                epHighlightActiveSecondary();
            }

            function epPositionSecondarySidebar() {
                const sidebar = document.querySelector('.fi-sidebar');
                const secondary = document.getElementById('ep-secondary-sidebar');
                if (!sidebar || !secondary) return;
                secondary.style.left = sidebar.offsetWidth + 'px';
            }

            // Items that should stay visible in the primary Tools group (not moved to secondary sidebar)
            const EP_KEEP_IN_TOOLS = ['Media Library', 'Gift Card Designs', 'Template bilete'];

            function epSetupSecondarySidebar() {
                const servicesGroup = document.querySelector('[data-group-label="Tools"]');
                if (!servicesGroup) return;

                // Find existing trigger items by label and mark keep-items
                const navItems = servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item');
                let triggerItems = {}; // panel -> DOM element

                navItems.forEach(item => {
                    const label = item.querySelector('.fi-sidebar-item-label');
                    if (!label) return;
                    const text = label.textContent.trim();
                    // Mark items that should stay visible in primary sidebar
                    if (EP_KEEP_IN_TOOLS.includes(text)) {
                        item.setAttribute('data-ep-sidebar-keep', 'true');
                    }
                    for (const [panel, cfg] of Object.entries(EP_PANELS)) {
                        if (text === cfg.triggerLabel) {
                            triggerItems[panel] = item;
                        }
                    }
                });

                // Clone Tools items (non-trigger) into microservices panel
                epCloneServicesItems(servicesGroup, triggerItems);

                // Clone Communications, Gamification, Settings, and Help group items
                epCloneGroupItems('Communications', 'ep-secondary-sidebar-communications-clone');
                epCloneGroupItems('Gamification', 'ep-secondary-sidebar-gamification-clone');
                epCloneGroupItems('Settings', 'ep-secondary-sidebar-settings-clone');
                epCloneGroupItems('Help', 'ep-secondary-sidebar-help-clone');

                // Inject trigger items for panels that don't exist in Tools yet
                epInjectTriggerItems(servicesGroup, triggerItems);

                // Re-query trigger items after injection
                servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item').forEach(item => {
                    const label = item.querySelector('.fi-sidebar-item-label') || item.querySelector('[data-ep-trigger-label]');
                    if (!label) return;
                    const text = (label.textContent || label.getAttribute('data-ep-trigger-label') || '').trim();
                    for (const [panel, cfg] of Object.entries(EP_PANELS)) {
                        if (text === cfg.triggerLabel) {
                            triggerItems[panel] = item;
                        }
                    }
                });

                // Bind click handlers and add arrow icon to all trigger items
                for (const [panel, item] of Object.entries(triggerItems)) {
                    item.setAttribute('data-ep-sidebar-trigger', panel);

                    const btn = item.querySelector('a.fi-sidebar-item-btn') || item.querySelector('[data-ep-trigger-btn]');
                    if (btn && !btn.dataset.epSecondaryBound) {
                        btn.dataset.epSecondaryBound = 'true';
                        btn.addEventListener('click', (e) => {
                            if (window.matchMedia('(max-width: 63.99rem)').matches) return;
                            e.preventDefault();
                            e.stopPropagation();
                            Alpine.store('secondarySidebar').togglePanel(panel);
                        });
                        // Append right arrow indicator
                        if (!btn.querySelector('.ep-trigger-arrow')) {
                            btn.insertAdjacentHTML('beforeend', EP_TRIGGER_ARROW);
                        }
                    }
                }

                // Add CSS class to hide non-trigger items and Communications/Gamification groups
                document.body.classList.add('ep-secondary-sidebar-ready');

                // Close secondary sidebar when clicking any OTHER primary nav item
                document.querySelectorAll('.fi-sidebar-item a.fi-sidebar-item-btn').forEach(link => {
                    const parentItem = link.closest('.fi-sidebar-item');
                    if (parentItem && parentItem.hasAttribute('data-ep-sidebar-trigger')) return;
                    if (!link.dataset.epCloseSecondaryBound) {
                        link.dataset.epCloseSecondaryBound = 'true';
                        link.addEventListener('click', () => {
                            if (Alpine.store('secondarySidebar')?.open) {
                                Alpine.store('secondarySidebar').close();
                            }
                        });
                    }
                });

                // Highlight active item and auto-open if current page is in secondary sidebar
                epHighlightActiveSecondary();
                epAutoOpenIfNeeded();
            }

            function epCloneServicesItems(servicesGroup, triggerItems) {
                const targetUl = document.getElementById('ep-secondary-sidebar-services-clone');
                const section = document.getElementById('ep-secondary-sidebar-services-section');
                if (!targetUl || !section) return;
                targetUl.innerHTML = '';

                const items = servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item');
                let clonedCount = 0;
                const triggerSet = new Set(Object.values(triggerItems));

                items.forEach(item => {
                    // Skip trigger items, injected triggers, and keep-items
                    if (triggerSet.has(item)) return;
                    if (item.hasAttribute('data-ep-sidebar-trigger')) return;
                    if (item.hasAttribute('data-ep-injected-trigger')) return;
                    if (item.hasAttribute('data-ep-sidebar-keep')) return;
                    const clone = item.cloneNode(true);
                    // Make sub-group items always visible with indent
                    clone.querySelectorAll('.fi-sidebar-sub-group-items').forEach(sub => {
                        sub.style.display = 'flex';
                        sub.style.paddingInlineStart = '1.5rem';
                        sub.removeAttribute('x-show');
                        sub.removeAttribute('x-collapse');
                        sub.removeAttribute('x-collapse.duration.200ms');
                    });
                    clone.querySelectorAll('[x-data*="dropdown"]').forEach(dd => dd.remove());
                    clone.querySelectorAll('a').forEach(a => a.setAttribute('data-ep-secondary-link', 'true'));
                    targetUl.appendChild(clone);
                    clonedCount++;
                });

                section.style.display = clonedCount > 0 ? '' : 'none';
            }

            function epCloneGroupItems(groupLabel, targetId) {
                const group = document.querySelector('[data-group-label="' + groupLabel + '"]');
                const targetUl = document.getElementById(targetId);
                if (!targetUl) return;
                targetUl.innerHTML = '';

                if (!group) return;

                const items = group.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item');
                items.forEach(item => {
                    const clone = item.cloneNode(true);
                    clone.querySelectorAll('.fi-sidebar-sub-group-items').forEach(sub => {
                        sub.style.display = 'flex';
                        sub.style.paddingInlineStart = '1.5rem';
                        sub.removeAttribute('x-show');
                        sub.removeAttribute('x-collapse');
                        sub.removeAttribute('x-collapse.duration.200ms');
                    });
                    clone.querySelectorAll('[x-data*="dropdown"]').forEach(dd => dd.remove());
                    clone.querySelectorAll('a').forEach(a => a.setAttribute('data-ep-secondary-link', 'true'));
                    targetUl.appendChild(clone);
                });
            }

            function epInjectTriggerItems(servicesGroup, triggerItems) {
                const itemsContainer = servicesGroup.querySelector(':scope > .fi-sidebar-group-items');
                if (!itemsContainer) return;

                // SVG icons for injected triggers
                const panelIcons = {
                    communications: '<svg class="fi-sidebar-item-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>',
                    gamification: '<svg class="fi-sidebar-item-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>',
                    settings: '<svg class="fi-sidebar-item-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.204-.107-.397.165-.71.505-.78.929l-.15.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    help: '<svg class="fi-sidebar-item-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>'
                };

                ['communications', 'gamification', 'settings', 'help'].forEach(panel => {
                    if (triggerItems[panel]) return; // already exists as a Filament nav item
                    const sourceGroup = document.querySelector('[data-group-label="' + EP_PANELS[panel].sourceGroup + '"]');
                    if (!sourceGroup) return; // group doesn't exist, no items to show

                    // Check if already injected
                    if (itemsContainer.querySelector('[data-ep-injected-trigger="' + panel + '"]')) return;

                    // Create a trigger item matching Filament's sidebar item structure
                    const li = document.createElement('li');
                    li.classList.add('fi-sidebar-item');
                    li.setAttribute('data-ep-injected-trigger', panel);
                    li.setAttribute('data-ep-sidebar-trigger', panel);
                    li.innerHTML = '<a href="#" class="fi-sidebar-item-btn" data-ep-trigger-btn>' +
                        (panelIcons[panel] || '') +
                        '<span class="fi-sidebar-item-label">' + EP_PANELS[panel].triggerLabel + '</span>' +
                        '</a>';

                    itemsContainer.appendChild(li);
                    triggerItems[panel] = li;
                });
            }

            function epGetPathname(href) {
                if (!href) return '';
                try { return new URL(href, window.location.origin).pathname; }
                catch { return href; }
            }

            function epIsActivePath(href) {
                const currentPath = window.location.pathname;
                const linkPath = epGetPathname(href);
                return linkPath && (currentPath === linkPath || currentPath.startsWith(linkPath + '/'));
            }

            function epHighlightActiveSecondary() {
                document.querySelectorAll('#ep-secondary-sidebar [data-ep-secondary-link]').forEach(link => {
                    const isActive = epIsActivePath(link.getAttribute('href'));
                    link.classList.toggle('ep-active', isActive);
                    const parentItem = link.closest('.fi-sidebar-item');
                    if (parentItem) {
                        parentItem.classList.toggle('fi-active', isActive);
                    }
                });
            }

            function epAutoOpenIfNeeded() {
                const store = Alpine.store('secondarySidebar');
                if (!store) return;

                // Check each panel's links to see if current page belongs to it
                for (const panel of Object.keys(EP_PANELS)) {
                    const panelEl = document.querySelector('[data-ep-panel="' + panel + '"]');
                    if (!panelEl) continue;
                    const hasActive = Array.from(panelEl.querySelectorAll('[data-ep-secondary-link]'))
                        .some(link => epIsActivePath(link.getAttribute('href')));
                    if (hasActive) {
                        store.openPanel(panel);
                        return;
                    }
                }

                // Also check hidden primary sidebar items (Tools group non-trigger items)
                const servicesGroup = document.querySelector('[data-group-label="Tools"]');
                if (servicesGroup) {
                    const isServicePage = Array.from(
                        servicesGroup.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item:not([data-ep-sidebar-trigger]) a')
                    ).some(link => epIsActivePath(link.getAttribute('href')));
                    if (isServicePage) {
                        store.openPanel('microservices');
                        return;
                    }
                }

                // Check hidden Communications group items
                const commsGroup = document.querySelector('[data-group-label="Communications"]');
                if (commsGroup) {
                    const isCommsPage = Array.from(commsGroup.querySelectorAll('a'))
                        .some(link => epIsActivePath(link.getAttribute('href')));
                    if (isCommsPage) {
                        store.openPanel('communications');
                        return;
                    }
                }

                // Check hidden Gamification group items
                const gamifGroup = document.querySelector('[data-group-label="Gamification"]');
                if (gamifGroup) {
                    const isGamifPage = Array.from(gamifGroup.querySelectorAll('a'))
                        .some(link => epIsActivePath(link.getAttribute('href')));
                    if (isGamifPage) {
                        store.openPanel('gamification');
                        return;
                    }
                }

                // Check hidden Settings group items
                const settingsGroup = document.querySelector('[data-group-label="Settings"]');
                if (settingsGroup) {
                    const isSettingsPage = Array.from(settingsGroup.querySelectorAll('a'))
                        .some(link => epIsActivePath(link.getAttribute('href')));
                    if (isSettingsPage) {
                        store.openPanel('settings');
                        return;
                    }
                }

                // Check hidden Help group items
                const helpGroup = document.querySelector('[data-group-label="Help"]');
                if (helpGroup) {
                    const isHelpPage = Array.from(helpGroup.querySelectorAll('a'))
                        .some(link => epIsActivePath(link.getAttribute('href')));
                    if (isHelpPage) {
                        store.openPanel('help');
                        return;
                    }
                }
            }

            // Reposition on sidebar collapse/expand
            const sidebarObserver = new MutationObserver(() => {
                if (Alpine.store('secondarySidebar')?.open) {
                    requestAnimationFrame(() => epPositionSecondarySidebar());
                }
            });

            // Bind on initial load and after Livewire SPA navigation
            document.addEventListener('DOMContentLoaded', () => {
                epSetupSecondarySidebar();
                const sidebar = document.querySelector('.fi-sidebar');
                if (sidebar) {
                    sidebarObserver.observe(sidebar, { attributes: true, attributeFilter: ['class', 'style'] });
                }
            });
            document.addEventListener('livewire:navigated', () => {
                requestAnimationFrame(() => epSetupSecondarySidebar());
            });
            </script>
            HTML)

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>')

            // Prevent visual flash during Livewire morph updates + compact repeater styles
            ->renderHook('panels::styles.after', fn () => '<style>
                /* Prevent content flash during Livewire updates */
                [wire\:loading]:not(.wire\:loading) { transition: opacity 0s; }
                .fi-fo-repeater [wire\:loading] { opacity: 1 !important; }
                /* Smooth morph transitions */
                .fi-fo-repeater-item { transition: none !important; }
                .fi-section-content { transition: none !important; }
                /* Compact performance pricing repeater — header + content on single row */
                .perf-prices-compact .fi-fo-repeater-item {
                    display: flex !important;
                    flex-direction: row !important;
                    align-items: center !important;
                    gap: 8px !important;
                    padding: 6px 8px !important;
                }
                .perf-prices-compact .fi-fo-repeater-item-header {
                    order: 2 !important;
                    flex-shrink: 0 !important;
                    padding: 0 !important;
                    border: none !important;
                    background: none !important;
                    min-height: unset !important;
                }
                .perf-prices-compact .fi-fo-repeater-item-header-end-actions { gap: 0 !important; }
                .perf-prices-compact .fi-fo-repeater-item-content {
                    order: 1 !important;
                    flex: 1 !important;
                    padding: 0 !important;
                }
                .perf-prices-compact .fi-fo-repeater-item-content .fi-sc { gap: 8px !important; }
                /* Remove collapse behavior */
                .perf-prices-compact .fi-fo-repeater-item.fi-collapsed .fi-fo-repeater-item-content { display: block !important; }
                /* Disabled options in Select dropdowns — strikethrough + red text */
                .fi-select-input-option[aria-disabled="true"],
                .fi-dropdown-list-item[aria-disabled="true"] {
                    text-decoration: line-through !important;
                    opacity: 0.45 !important;
                    color: #ef4444 !important;
                }
            </style>')

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
            HTML)

            // Move tabs / header actions inside the table toolbar on list pages
            ->renderHook('panels::body.end', fn () => <<<'HTML'
            <script>
            (function() {
                // Pages where tabs should move into the table toolbar
                const TABS_INSIDE_TABLE = ['/organizers'];
                // Pages where header "New" button should move into the table toolbar
                const BUTTON_INSIDE_TABLE = ['/artists', '/venues', '/seating-layouts', '/marketplace-customers', '/gift-cards'];

                function getPath() {
                    return window.location.pathname.replace(/\/+$/, '');
                }

                function matchesAny(path, patterns) {
                    return patterns.some(p => path.endsWith(p));
                }

                function moveTabsIntoToolbar() {
                    const path = getPath();
                    if (!matchesAny(path, TABS_INSIDE_TABLE)) return;

                    const page = document.querySelector('.fi-resource-list-records-page');
                    if (!page) return;

                    const tabs = page.querySelector('.fi-tabs');
                    const toolbar = page.querySelector('.fi-ta-header-toolbar');
                    if (!tabs || !toolbar || tabs.dataset.epMoved) return;

                    tabs.dataset.epMoved = '1';
                    tabs.style.marginRight = 'auto';
                    toolbar.prepend(tabs);
                }

                function moveCreateButtonIntoToolbar() {
                    const path = getPath();
                    if (!matchesAny(path, BUTTON_INSIDE_TABLE)) return;

                    const page = document.querySelector('.fi-resource-list-records-page');
                    if (!page) return;

                    const toolbar = page.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;

                    const headerActions = page.querySelector('.fi-header .fi-header-actions-ctn');
                    if (!headerActions || headerActions.dataset.epMoved) return;

                    headerActions.dataset.epMoved = '1';
                    headerActions.style.marginRight = 'auto';
                    toolbar.prepend(headerActions);
                }

                function run() {
                    requestAnimationFrame(() => {
                        moveTabsIntoToolbar();
                        moveCreateButtonIntoToolbar();
                    });
                }

                document.addEventListener('DOMContentLoaded', run);
                document.addEventListener('livewire:navigated', run);
            })();
            </script>
            HTML);
    }
}
