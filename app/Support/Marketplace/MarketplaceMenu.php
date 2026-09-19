<?php

namespace App\Support\Marketplace;

use App\Filament\Marketplace\Pages\Dashboard;
use App\Filament\Marketplace\Pages\Settings;
use App\Models\MarketplaceClient;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Menu entries a marketplace hides from its admin panel (Setări → Meniu; stored in the marketplace's
 * settings.hidden_navigation as the classes of the resources and pages). A hidden entry leaves the sidebar and its
 * pages answer 403, page loads and Livewire calls alike, for that marketplace only: every other marketplace has an
 * empty list, so nothing changes for it. Hiding an entry also hides the entries nested under it in the menu (Filament
 * drops children whose parent is gone), so those are blocked too. The dashboard and the settings page, where the list
 * is edited, can't be hidden.
 *
 * Wired in AppServiceProvider::boot (boot()): the sidebar through MarketplaceNavigationManager, the block through
 * Filament's serving event, which runs on every panel request, Livewire updates included.
 */
final class MarketplaceMenu
{
    public const PANEL = 'marketplace';
    public const SETTINGS_KEY = 'hidden_navigation';
    public const ALWAYS_SHOWN = [Dashboard::class, Settings::class];

    public static function boot(): void
    {
        app()->scoped(NavigationManager::class, fn () => new MarketplaceNavigationManager());

        Filament::serving(function (): void {
            if (Filament::getCurrentPanel()?->getId() !== self::PANEL) {
                return;
            }
            $hidden = self::hiddenClasses();
            if ($hidden && self::isHiddenRoute(self::currentRouteName(), $hidden)) {
                abort(403, 'Această secțiune este ascunsă pentru acest marketplace.');
            }
        });
    }

    /** The marketplace of the signed-in admin, resolved like every marketplace page does (HasMarketplaceContext). */
    public static function client(): ?MarketplaceClient
    {
        return Settings::getMarketplaceClient();
    }

    /** What the marketplace chose to hide, as saved (only real, hideable entries of the panel). */
    public static function storedClasses(?MarketplaceClient $client = null): array
    {
        $client ??= self::client();
        $list = $client?->settings[self::SETTINGS_KEY] ?? [];

        return self::clean(is_array($list) ? $list : []);
    }

    /** What is hidden and blocked: the chosen entries plus the entries nested under them. */
    public static function hiddenClasses(?MarketplaceClient $client = null): array
    {
        $hidden = self::storedClasses($client);
        if (!$hidden) {
            return [];
        }
        $parents = [];
        foreach ($hidden as $class) {
            $parents[] = self::group($class) . '|' . self::label($class);
        }
        foreach (self::components() as $class) {
            $parent = self::parentLabel($class);
            if ($parent !== null && in_array(self::group($class) . '|' . $parent, $parents, true)) {
                $hidden[] = $class;
            }
        }

        return array_values(array_unique($hidden));
    }

    /** Keeps only resources and pages of the marketplace panel that may be hidden. */
    public static function clean(array $classes): array
    {
        $components = self::components();

        return array_values(array_unique(array_filter($classes, fn ($class) => is_string($class)
            && in_array($class, $components, true)
            && !in_array($class, self::ALWAYS_SHOWN, true))));
    }

    /** @return list<class-string> the resources and pages of the marketplace panel */
    public static function components(): array
    {
        $panel = Filament::getPanel(self::PANEL);

        return array_values(array_unique([...$panel->getResources(), ...$panel->getPages()]));
    }

    /**
     * The entries to choose from, as the signed-in admin sees the menu, plus whatever is already hidden (so a saved
     * choice never falls out of the list): [class => "Grup · Intrare"] in menu order.
     *
     * @return array<class-string, string>
     */
    public static function options(?MarketplaceClient $client = null): array
    {
        $stored = self::storedClasses($client);
        $groupOrder = array_values(array_map(
            fn ($group, $key) => is_string($key) ? $key : (is_string($group) ? $group : $group->getLabel()),
            Filament::getPanel(self::PANEL)->getNavigationGroups(),
            array_keys(Filament::getPanel(self::PANEL)->getNavigationGroups()),
        ));

        $rows = [];
        foreach (self::components() as $class) {
            if (in_array($class, self::ALWAYS_SHOWN, true)) {
                continue;
            }
            if (!in_array($class, $stored, true) && !self::inMenu($class)) {
                continue;
            }
            $group = self::group($class);
            $parent = self::parentLabel($class);
            $position = array_search($group, $groupOrder, true);
            $rows[] = [
                'class' => $class,
                'label' => ($group !== '' ? $group : 'Principal') . ' · ' . ($parent !== null ? $parent . ' › ' : '') . self::label($class),
                'sort' => [$position === false ? PHP_INT_MAX : $position, $parent ?? self::label($class), $parent === null ? 0 : 1, self::sort($class)],
            ];
        }
        usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_column($rows, 'label', 'class');
    }

    /** True when the route belongs to one of the hidden resources (any of its pages) or pages. */
    public static function isHiddenRoute(string $route, array $hidden): bool
    {
        if ($route === '') {
            return false;
        }
        $panel = Filament::getPanel(self::PANEL);
        foreach ($hidden as $class) {
            if (is_subclass_of($class, Resource::class)) {
                $base = $class::getRouteBaseName($panel);
                if ($route === $base || str_starts_with($route, $base . '.')) {
                    return true;
                }
            } elseif (is_subclass_of($class, Page::class) && $route === $class::getRouteName($panel)) {
                return true;
            }
        }

        return false;
    }

    /** Marks invisible the menu items that lead to a hidden entry (by path, the query string doesn't matter). */
    public static function hideItems(array $items, array $hidden): void
    {
        $paths = [];
        foreach ($hidden as $class) {
            try {
                $paths[] = self::path($class::getNavigationUrl());
            } catch (\Throwable) {
                // an entry without a menu link has nothing to hide here; its route is still blocked
            }
        }
        if (!$paths) {
            return;
        }
        foreach ($items as $item) {
            if ($item instanceof NavigationItem && in_array(self::path($item->getUrl()), $paths, true)) {
                $item->visible(false);
            }
        }
    }

    /** The page being shown: for a Livewire call, the page it came from (the path in its signed snapshot). */
    protected static function currentRouteName(): string
    {
        try {
            if (Livewire::isLivewireRequest()) {
                return (string) app('router')->getRoutes()->match(Request::create(Livewire::originalUrl(), 'GET'))->getName();
            }

            return (string) request()->route()?->getName();
        } catch (\Throwable) {
            return '';
        }
    }

    protected static function inMenu(string $class): bool
    {
        try {
            if (is_subclass_of($class, Resource::class) && $class::getParentResourceRegistration()) {
                return false;
            }

            return $class::shouldRegisterNavigation() && $class::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function label(string $class): string
    {
        try {
            return (string) $class::getNavigationLabel();
        } catch (\Throwable) {
            return class_basename($class);
        }
    }

    protected static function group(string $class): string
    {
        try {
            $group = $class::getNavigationGroup();
        } catch (\Throwable) {
            return '';
        }

        return $group instanceof \UnitEnum ? $group->name : (string) $group;
    }

    protected static function parentLabel(string $class): ?string
    {
        try {
            $parent = $class::getNavigationParentItem();
        } catch (\Throwable) {
            return null;
        }

        return filled($parent) ? (string) $parent : null;
    }

    protected static function sort(string $class): int
    {
        try {
            return (int) ($class::getNavigationSort() ?? PHP_INT_MAX);
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    protected static function path(?string $url): string
    {
        return rtrim((string) parse_url((string) $url, PHP_URL_PATH), '/');
    }
}
