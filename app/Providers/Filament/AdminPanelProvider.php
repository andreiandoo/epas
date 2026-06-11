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

use Filament\Navigation\NavigationGroup;
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
            ->profile()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->darkMode(condition: true)
            ->databaseNotifications()

            // Auto-discover resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            // Default widgets disabled
            ->widgets([])

            // Define navigation group order
            ->navigationGroups([
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Tix Users'),
                NavigationGroup::make('Core'),
                NavigationGroup::make('Communications'),
                NavigationGroup::make('Platform Marketing'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Gamification'),
                NavigationGroup::make('Web Templates'),
                NavigationGroup::make('Design'),
                NavigationGroup::make('Documentation'),
                // Hidden groups (items moved to secondary sidebar)
                NavigationGroup::make('Marketplace')->collapsed(),
                NavigationGroup::make('Tenants')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
                NavigationGroup::make('Operational')->collapsed(),
                NavigationGroup::make('Taxonomies')->collapsed(),
            ])

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

            // Custom assets (Tailwind utilities + custom skin)
            ->assets([
                Css::make('tailwind-theme', \Illuminate\Support\Facades\Vite::asset('resources/css/filament/admin/theme.css')),
                Css::make('epas-skin', asset('css/epas-skin.css?v=' . @filemtime(public_path('css/epas-skin.css')))),
                Js::make('epas-skin', asset('js/epas-skin.js?v=' . @filemtime(public_path('js/epas-skin.js')))),
            ])

            // Custom brand with dynamic logos from settings
            ->brandLogo(fn () => view('filament.components.sidebar-brand'))
            ->brandLogoHeight('2rem')

            // Disable default topbar elements (we have custom-topbar)
            ->globalSearch(false)
            ->userMenu(false)

            // Custom topbar above fi-main (same placement as marketplace panel)
            ->renderHook('panels::page.start', fn (): string => view('filament.components.custom-topbar')->render())

            // Sticky / floating save button for long forms
            ->renderHook('panels::body.end', fn (): string => view('filament.sticky-actions')->render())

            // Secondary sidebar for Marketplace / Tenants / Settings / Taxonomies navigation
            ->renderHook('panels::body.end', fn (): string => view('filament.components.admin-secondary-sidebar')->render())
            ->renderHook('panels::body.end', fn () => <<<'HTML'
            <style>
            /* Admin panel: hide source groups that are shown in secondary sidebar */
            body.ep-secondary-sidebar-ready [data-group-label="Marketplace"],
            body.ep-secondary-sidebar-ready [data-group-label="Tenants"],
            body.ep-secondary-sidebar-ready [data-group-label="Settings"],
            body.ep-secondary-sidebar-ready [data-group-label="Operational"],
            body.ep-secondary-sidebar-ready [data-group-label="Taxonomies"] {
                display: none !important;
            }
            </style>
            <script>
            // Admin Secondary Sidebar – Alpine store & DOM interception
            document.addEventListener('alpine:init', () => {
                Alpine.store('secondarySidebar', {
                    open: false,
                    activePanel: null,
                    togglePanel(panel) {
                        if (this.open && this.activePanel === panel) {
                            this.close();
                        } else {
                            this.activePanel = panel;
                            this.open = true;
                            document.body.classList.add('ep-secondary-sidebar-open');
                            epAdminShowPanel(panel);
                            epAdminPositionSidebar();
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
                            epAdminShowPanel(panel);
                            epAdminPositionSidebar();
                        }
                    }
                });
            });

            const EP_ADMIN_PANELS = {
                marketplace: {
                    title: 'Marketplace',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75v-2.25a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v2.25c0 .414.336.75.75.75z"/></svg>',
                    sourceGroup: 'Marketplace',
                    triggerLabel: 'Marketplaces'
                },
                tenants: {
                    title: 'Tenants',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>',
                    sourceGroup: 'Tenants',
                    triggerLabel: 'Tenants'
                },
                settings: {
                    title: 'Settings',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    sourceGroup: 'Settings',
                    triggerLabel: 'Settings'
                },
                operational: {
                    title: 'Operational',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17l-5.1-5.1m0 0L11.42 4.97m-5.1 5.1H21M3 21h18"/></svg>',
                    sourceGroup: 'Operational',
                    triggerLabel: 'Operational'
                },
                taxonomies: {
                    title: 'Taxonomies',
                    icon: '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h.008v.008H6V6z"/></svg>',
                    sourceGroup: 'Taxonomies',
                    triggerLabel: 'Taxonomies'
                }
            };

            const EP_ADMIN_TRIGGER_ARROW = '<svg class="ep-trigger-arrow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>';

            function epAdminShowPanel(panel) {
                document.querySelectorAll('.ep-secondary-sidebar-panel').forEach(p => {
                    p.style.display = p.dataset.epPanel === panel ? '' : 'none';
                });
                const cfg = EP_ADMIN_PANELS[panel];
                if (cfg) {
                    const iconEl = document.getElementById('ep-secondary-sidebar-icon');
                    const titleEl = document.getElementById('ep-secondary-sidebar-title');
                    if (iconEl) iconEl.innerHTML = cfg.icon;
                    if (titleEl) titleEl.textContent = cfg.title;
                }
                epAdminHighlightActive();
            }

            function epAdminPositionSidebar() {
                const sidebar = document.querySelector('.fi-sidebar');
                const secondary = document.getElementById('ep-secondary-sidebar');
                if (!sidebar || !secondary) return;
                secondary.style.left = sidebar.offsetWidth + 'px';
            }

            function epAdminCloneGroupItems(groupLabel, targetId) {
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

            function epAdminGetPathname(href) {
                if (!href) return '';
                try { return new URL(href, window.location.origin).pathname; }
                catch { return href; }
            }

            function epAdminIsActivePath(href) {
                const currentPath = window.location.pathname;
                const linkPath = epAdminGetPathname(href);
                return linkPath && (currentPath === linkPath || currentPath.startsWith(linkPath + '/'));
            }

            function epAdminHighlightActive() {
                document.querySelectorAll('#ep-secondary-sidebar [data-ep-secondary-link]').forEach(link => {
                    const isActive = epAdminIsActivePath(link.getAttribute('href'));
                    link.classList.toggle('ep-active', isActive);
                    const parentItem = link.closest('.fi-sidebar-item');
                    if (parentItem) {
                        parentItem.classList.toggle('fi-active', isActive);
                    }
                });
            }

            function epAdminAutoOpenIfNeeded() {
                const store = Alpine.store('secondarySidebar');
                if (!store) return;

                // Check each panel's cloned links
                for (const panel of Object.keys(EP_ADMIN_PANELS)) {
                    const panelEl = document.querySelector('[data-ep-panel="' + panel + '"]');
                    if (!panelEl) continue;
                    const hasActive = Array.from(panelEl.querySelectorAll('[data-ep-secondary-link]'))
                        .some(link => epAdminIsActivePath(link.getAttribute('href')));
                    if (hasActive) {
                        store.openPanel(panel);
                        return;
                    }
                }

                // Also check hidden source groups directly
                for (const [panel, cfg] of Object.entries(EP_ADMIN_PANELS)) {
                    const group = document.querySelector('[data-group-label="' + cfg.sourceGroup + '"]');
                    if (!group) continue;
                    const isActive = Array.from(group.querySelectorAll('a'))
                        .some(link => epAdminIsActivePath(link.getAttribute('href')));
                    if (isActive) {
                        store.openPanel(panel);
                        return;
                    }
                }
            }

            function epAdminSetupSecondarySidebar() {
                // Clone items from source groups into secondary sidebar panels
                epAdminCloneGroupItems('Marketplace', 'ep-admin-sidebar-marketplace-clone');
                epAdminCloneGroupItems('Tenants', 'ep-admin-sidebar-tenants-clone');
                epAdminCloneGroupItems('Settings', 'ep-admin-sidebar-settings-clone');
                epAdminCloneGroupItems('Operational', 'ep-admin-sidebar-operational-clone');
                epAdminCloneGroupItems('Taxonomies', 'ep-admin-sidebar-taxonomies-clone');

                // Find trigger items only in Tix Users and Core groups (not in hidden source groups)
                const triggerItems = {};
                const triggerGroups = ['Tix Users', 'Core'];
                triggerGroups.forEach(groupLabel => {
                    const group = document.querySelector('[data-group-label="' + groupLabel + '"]');
                    if (!group) return;
                    group.querySelectorAll(':scope > .fi-sidebar-group-items > .fi-sidebar-item').forEach(item => {
                        const label = item.querySelector('.fi-sidebar-item-label');
                        if (!label) return;
                        const text = label.textContent.trim();
                        for (const [panel, cfg] of Object.entries(EP_ADMIN_PANELS)) {
                            if (text === cfg.triggerLabel && !triggerItems[panel]) {
                                triggerItems[panel] = item;
                            }
                        }
                    });
                });

                // Bind click handlers and add arrow to trigger items
                for (const [panel, item] of Object.entries(triggerItems)) {
                    item.setAttribute('data-ep-sidebar-trigger', panel);
                    const btn = item.querySelector('a.fi-sidebar-item-btn');
                    if (btn && !btn.dataset.epSecondaryBound) {
                        btn.dataset.epSecondaryBound = 'true';
                        btn.addEventListener('click', (e) => {
                            if (window.matchMedia('(max-width: 63.99rem)').matches) return;
                            e.preventDefault();
                            e.stopPropagation();
                            Alpine.store('secondarySidebar').togglePanel(panel);
                        });
                        if (!btn.querySelector('.ep-trigger-arrow')) {
                            btn.insertAdjacentHTML('beforeend', EP_ADMIN_TRIGGER_ARROW);
                        }
                    }
                }

                // Hide source groups
                document.body.classList.add('ep-secondary-sidebar-ready');

                // Close secondary sidebar when clicking any non-trigger nav item
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

                epAdminHighlightActive();
                epAdminAutoOpenIfNeeded();
            }

            // Reposition on sidebar collapse/expand
            const adminSidebarObserver = new MutationObserver(() => {
                if (Alpine.store('secondarySidebar')?.open) {
                    requestAnimationFrame(() => epAdminPositionSidebar());
                }
            });

            document.addEventListener('DOMContentLoaded', () => {
                epAdminSetupSecondarySidebar();
                const sidebar = document.querySelector('.fi-sidebar');
                if (sidebar) {
                    adminSidebarObserver.observe(sidebar, { attributes: true, attributeFilter: ['class', 'style'] });
                }
            });
            document.addEventListener('livewire:navigated', () => {
                requestAnimationFrame(() => epAdminSetupSecondarySidebar());
            });
            </script>
            HTML)

            // Set dark mode as default if not already set
            ->renderHook('panels::head.end', fn () => '<script>if(!localStorage.getItem("theme")){localStorage.setItem("theme","dark");document.documentElement.classList.add("dark");}</script>')
            ;
    }

    public function boot(): void
    {
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
}
