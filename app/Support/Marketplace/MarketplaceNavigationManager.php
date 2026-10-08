<?php

namespace App\Support\Marketplace;

use App\Support\Tenant\TenantSimpleMenu;
use Filament\Navigation\NavigationManager;

/**
 * Filament's navigation manager (bound in its place by MarketplaceMenu::boot), plus one step on the marketplace panel:
 * the entries the current marketplace hid (Setări → Meniu) are left out of the menu. On the tenant panel, a tenant that
 * turned on the simple menu (settings.panel.simple_menu) gets the reduced, Romanian-labelled menu from TenantSimpleMenu.
 * Other panels, marketplaces with nothing hidden and tenants without that setting get exactly Filament's menu.
 */
class MarketplaceNavigationManager extends NavigationManager
{
    public function mountNavigation(): void
    {
        parent::mountNavigation();

        if ($this->panel->getId() === TenantSimpleMenu::PANEL) {
            try {
                $groups = TenantSimpleMenu::apply($this->navigationItems);
                if ($groups !== null) {
                    $this->navigationGroups = $groups;
                }
            } catch (\Throwable) {
                // a menu tweak must never take the panel down: fall back to Filament's menu
            }

            return;
        }

        if ($this->panel->getId() !== MarketplaceMenu::PANEL) {
            return;
        }
        $hidden = MarketplaceMenu::hiddenClasses();
        if ($hidden) {
            MarketplaceMenu::hideItems($this->navigationItems, $hidden);
        }
    }
}
