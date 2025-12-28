<?php
// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->handle($request);

header('Content-Type: text/plain');
echo "=== FILAMENT 403 DEBUG REPORT ===\n\n";
echo "Generated: " . now() . "\n\n";

// Get authenticated user
$user = auth()->user();

if (!$user) {
    echo "ERROR: No authenticated user found!\n";
    echo "You must be logged in to access this debug page.\n";
    exit;
}

echo "AUTHENTICATED USER: {$user->name} ({$user->email}) - Role: {$user->role}\n\n";

// ===== TEST 1: Routes =====
echo "===== TEST 1: FILAMENT ROUTES =====\n";
try {
    $routes = collect(Route::getRoutes())->filter(function($route) {
        return str_starts_with($route->uri(), 'admin');
    });

    echo "Total admin routes found: " . $routes->count() . "\n";
    echo "\nFirst 10 admin routes:\n";
    foreach ($routes->take(10) as $route) {
        echo "  {$route->methods()[0]} {$route->uri()} -> {$route->getName()}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 2: Panel Registration =====
echo "===== TEST 2: FILAMENT PANEL =====\n";
try {
    $panel = \Filament\Facades\Filament::getPanel('admin');
    echo "Panel ID: " . $panel->getId() . "\n";
    echo "Panel path: " . $panel->getPath() . "\n";
    echo "Panel login enabled: " . ($panel->hasLogin() ? 'YES' : 'NO') . "\n";

    // Get middleware
    $middleware = $panel->getMiddleware();
    echo "Panel middleware (" . count($middleware) . "):\n";
    foreach ($middleware as $m) {
        echo "  - " . (is_string($m) ? $m : get_class($m)) . "\n";
    }

    // Get auth middleware
    $authMiddleware = $panel->getAuthMiddleware();
    echo "\nAuth middleware (" . count($authMiddleware) . "):\n";
    foreach ($authMiddleware as $m) {
        echo "  - " . (is_string($m) ? $m : get_class($m)) . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 3: Authorization =====
echo "===== TEST 3: AUTHORIZATION CHECKS =====\n";
try {
    $panel = \Filament\Facades\Filament::getPanel('admin');
    $canAccess = $user->canAccessPanel($panel);
    echo "canAccessPanel(): " . ($canAccess ? 'YES ✓' : 'NO ✗') . "\n";

    // Check if there's a global gate
    $gates = app(\Illuminate\Contracts\Auth\Access\Gate::class);
    echo "\nGlobal gates/policies registered:\n";

    // Try to access gates via reflection
    $reflection = new ReflectionClass($gates);
    $abilitiesProperty = $reflection->getProperty('abilities');
    $abilitiesProperty->setAccessible(true);
    $abilities = $abilitiesProperty->getValue($gates);

    echo "Total gates registered: " . count($abilities) . "\n";
    if (count($abilities) > 0) {
        echo "First 10 gates:\n";
        $count = 0;
        foreach ($abilities as $ability => $callback) {
            if ($count++ >= 10) break;
            echo "  - {$ability}\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 4: Environment =====
echo "===== TEST 4: ENVIRONMENT =====\n";
echo "APP_ENV: " . config('app.env') . "\n";
echo "APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . "\n";
echo "APP_URL: " . config('app.url') . "\n";
echo "SESSION_DRIVER: " . config('session.driver') . "\n";
echo "CACHE_DRIVER: " . config('cache.default') . "\n";
echo "\n";

// ===== TEST 5: Middleware Stack Test =====
echo "===== TEST 5: MIDDLEWARE STACK =====\n";
try {
    $routeMiddleware = Route::getMiddleware();
    echo "Global middleware groups:\n";
    foreach (app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups() as $group => $middleware) {
        echo "  {$group}: " . count($middleware) . " middleware\n";
    }

    echo "\nRoute middleware aliases:\n";
    $aliases = app(\Illuminate\Contracts\Http\Kernel::class)->getRouteMiddleware();
    foreach (array_slice($aliases, 0, 10, true) as $alias => $class) {
        echo "  {$alias} -> {$class}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 6: Session Data =====
echo "===== TEST 6: SESSION =====\n";
echo "Session ID: " . session()->getId() . "\n";
echo "Session has auth: " . (session()->has('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d') ? 'YES' : 'NO') . "\n";
echo "Session auth ID: " . session()->get('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', 'N/A') . "\n";
echo "\n";

// ===== TEST 7: Filament Resources Discovery =====
echo "===== TEST 7: FILAMENT RESOURCES =====\n";
try {
    $panel = \Filament\Facades\Filament::getPanel('admin');

    // Try to get resources
    echo "Attempting to discover resources...\n";
    $resources = $panel->getResources();
    echo "Resources registered: " . count($resources) . "\n";

    if (count($resources) > 0) {
        echo "First 5 resources:\n";
        foreach (array_slice($resources, 0, 5) as $resource) {
            echo "  - {$resource}\n";
        }
    }

    $pages = $panel->getPages();
    echo "\nPages registered: " . count($pages) . "\n";

    $widgets = $panel->getWidgets();
    echo "Widgets registered: " . count($widgets) . "\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 8: Direct Route Access Test =====
echo "===== TEST 8: ROUTE ACCESS TEST =====\n";
try {
    // Try to manually resolve the admin route
    $route = Route::getRoutes()->getByName('filament.admin.pages.dashboard');
    if ($route) {
        echo "Dashboard route found: YES\n";
        echo "  URI: {$route->uri()}\n";
        echo "  Action: {$route->getActionName()}\n";
    } else {
        echo "Dashboard route: NOT FOUND\n";

        // Try to find ANY admin route
        $adminRoute = Route::getRoutes()->getByName('filament.admin.home');
        if ($adminRoute) {
            echo "Admin home route found: YES\n";
            echo "  URI: {$adminRoute->uri()}\n";
        } else {
            echo "Admin home route: NOT FOUND\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 9: Provider Check =====
echo "===== TEST 9: SERVICE PROVIDERS =====\n";
try {
    $providers = app()->getLoadedProviders();
    $filamentProviders = array_filter(array_keys($providers), function($provider) {
        return str_contains($provider, 'Filament');
    });

    echo "Filament providers loaded:\n";
    foreach ($filamentProviders as $provider) {
        echo "  - {$provider}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== TEST 10: Vendor Check =====
echo "===== TEST 10: VENDOR FILES =====\n";
$filamentPath = base_path('vendor/filament/filament');
echo "Filament vendor path exists: " . (is_dir($filamentPath) ? 'YES' : 'NO') . "\n";
if (is_dir($filamentPath)) {
    echo "Filament version: ";
    $composerFile = $filamentPath . '/composer.json';
    if (file_exists($composerFile)) {
        $composer = json_decode(file_get_contents($composerFile), true);
        echo $composer['version'] ?? 'unknown';
    } else {
        echo "composer.json not found";
    }
    echo "\n";
}
echo "\n";

echo "=== END DEBUG REPORT ===\n";
echo "\nTo fix the 403 issue, review the above output and identify:\n";
echo "1. Are routes registered? (TEST 1)\n";
echo "2. Is the panel configured correctly? (TEST 2)\n";
echo "3. Do authorization checks pass? (TEST 3)\n";
echo "4. Are resources being discovered when they shouldn't be? (TEST 7)\n";
echo "5. Is there a middleware blocking access? (TEST 5)\n";
