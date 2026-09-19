<?php

namespace App\Support\Marketplace;

use Filament\Navigation\NavigationManager;

/**
 * Filament's navigation manager (bound in its place by MarketplaceMenu::boot), plus one step on the marketplace panel:
 * the entries the current marketplace hid (Setări → Meniu) are left out of the menu. Other panels, and marketplaces
 * with nothing hidden, get exactly Filament's menu.
 */
class MarketplaceNavigationManager extends NavigationManager
{
    public function mountNavigation(): void
    {
        parent::mountNavigation();

        if ($this->panel->getId() !== MarketplaceMenu::PANEL) {
            return;
        }
        $hidden = MarketplaceMenu::hiddenClasses();
        if ($hidden) {
            MarketplaceMenu::hideItems($this->navigationItems, $hidden);
        }
    }
}
