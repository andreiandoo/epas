<?php
// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->handle($request);

header('Content-Type: text/plain');
echo "=== FILAMENT ADMIN DEBUG ===\n\n";

// Get authenticated user
$user = auth()->user();

if (!$user) {
    echo "ERROR: No authenticated user found!\n";
    echo "You must be logged in to access this debug page.\n";
    exit;
}

echo "1. AUTHENTICATED USER:\n";
echo "   ID: " . $user->id . "\n";
echo "   Name: " . $user->name . "\n";
echo "   Email: " . $user->email . "\n";
echo "   Role: " . ($user->role ?? 'NULL') . "\n\n";

echo "2. ROLE METHODS:\n";
echo "   isSuperAdmin(): " . ($user->isSuperAdmin() ? 'YES' : 'NO') . "\n";
echo "   isAdmin(): " . ($user->isAdmin() ? 'YES' : 'NO') . "\n";
echo "   isEditor(): " . ($user->isEditor() ? 'YES' : 'NO') . "\n";
echo "   isTenant(): " . ($user->isTenant() ? 'YES' : 'NO') . "\n\n";

echo "3. PANEL ACCESS:\n";
try {
    $panel = \Filament\Facades\Filament::getPanel('admin');
    echo "   Panel found: YES\n";
    echo "   Panel ID: " . $panel->getId() . "\n";

    $canAccess = $user->canAccessPanel($panel);
    echo "   canAccessPanel(): " . ($canAccess ? 'YES' : 'NO') . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "4. USER POLICY CHECK:\n";
try {
    $policy = app(\App\Policies\UserPolicy::class);
    $canViewAny = $policy->viewAny($user);
    echo "   UserPolicy->viewAny(): " . ($canViewAny ? 'YES' : 'NO') . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "5. GATE CHECK:\n";
try {
    $canViewUsers = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\User::class);
    echo "   Gate::allows('viewAny', User::class): " . ($canViewUsers ? 'YES' : 'NO') . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "6. USER ATTRIBUTES (full dump):\n";
print_r($user->getAttributes());
echo "\n";

echo "7. SESSION DATA:\n";
print_r(session()->all());
echo "\n";

echo "=== END DEBUG ===\n";
