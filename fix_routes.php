<?php

$file = 'routes/api.php';
$content = file_get_contents($file);

// Remove the messed up routes
$content = preg_replace('/\/\/ Orders.*?Route::prefix\(\'orders\'\)->group\(function \(\) \{.*?\}\);/s', '', $content);

// Find the checkout section and add orders after it
$checkoutSection = "    // Checkout\n    Route::prefix('checkout')->group(function () {\n    });";

$replacement = "    // Checkout\n    Route::prefix('checkout')->group(function () {\n        Route::post('/init', [CheckoutController::class, 'init'])\n            ->name('api.tenant-client.checkout.init');\n        Route::post('/submit', [CheckoutController::class, 'submit'])\n            ->name('api.tenant-client.checkout.submit');\n        Route::get('/order/{orderId}', [CheckoutController::class, 'orderStatus'])\n            ->name('api.tenant-client.checkout.order-status');\n        Route::post('/insurance-quote', [CheckoutController::class, 'insuranceQuote'])\n            ->name('api.tenant-client.checkout.insurance-quote');\n    });\n\n    // Orders\n    Route::prefix('orders')->group(function () {\n        Route::post('/', [\\App\\Http\\Controllers\\Api\\TenantClient\\OrderController::class, 'store'])\n            ->name('api.tenant-client.orders.store');\n        Route::get('/{orderId}', [\\App\\Http\\Controllers\\Api\\TenantClient\\OrderController::class, 'show'])\n            ->name('api.tenant-client.orders.show');\n    });";

$content = str_replace($checkoutSection, $replacement, $content);

file_put_contents($file, $content);

echo "Routes fixed!\n";
