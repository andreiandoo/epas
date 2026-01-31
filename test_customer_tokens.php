<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Database Connection Test ===\n";
echo "Connection: " . config('database.default') . "\n";
echo "Database: " . config('database.connections.' . config('database.default') . '.database') . "\n\n";

// Test 1: Check if table exists via raw query
echo "Test 1: Check table existence via raw SQL\n";
try {
    $result = DB::select("SHOW TABLES LIKE 'customer_tokens'");
    echo "Result: " . (count($result) > 0 ? "✓ Table EXISTS\n" : "✗ Table NOT FOUND\n");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Try to query the table
echo "Test 2: Query the table\n";
try {
    $count = DB::table('customer_tokens')->count();
    echo "Result: ✓ Table has {$count} records\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Try using the CustomerToken model
echo "Test 3: Use CustomerToken model\n";
try {
    $count = App\Models\CustomerToken::count();
    echo "Result: ✓ Model works, {$count} records found\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Try to insert a test record
echo "Test 4: Insert test record\n";
try {
    $token = App\Models\CustomerToken::create([
        'customer_id' => 1,
        'token' => hash('sha256', 'test-token-' . time()),
        'name' => 'diagnostic-test',
        'abilities' => ['*'],
        'expires_at' => now()->addDays(30),
    ]);
    echo "Result: ✓ Insert successful, ID: {$token->id}\n";

    // Clean up
    $token->delete();
    echo "Result: ✓ Test record deleted\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Schema cache info
echo "Test 5: Clear schema cache\n";
try {
    Artisan::call('schema:clear');
    echo "Result: ✓ Schema cache cleared\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
