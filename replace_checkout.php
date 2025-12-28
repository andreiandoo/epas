<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Find and replace the renderCheckout method
$pattern = '/private renderCheckout\(\): void \{.*?^\    \}/ms';

// Read the new implementation
$newImplementation = file_get_contents('implement_checkout.txt');

// Do the replacement
$content = preg_replace($pattern, $newImplementation, $content);

file_put_contents($file, $content);

echo "Checkout method replaced!\n";
