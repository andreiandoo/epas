<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Find and replace the renderCart method
$oldMethod = "    private renderCart(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class=\"max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8\">
                <h1 class=\"text-3xl font-bold text-gray-900 mb-8\">Shopping Cart</h1>
                <div id=\"cart-items\" class=\"space-y-4 mb-8\">
                    <div class=\"animate-pulse bg-gray-200 rounded-lg h-24\"></div>
                    <div class=\"animate-pulse bg-gray-200 rounded-lg h-24\"></div>
                </div>
                <div id=\"cart-summary\" class=\"bg-gray-50 rounded-lg p-6\">
                    <div class=\"flex justify-between items-center mb-4\">
                        <span class=\"text-lg font-medium text-gray-900\">Total</span>
                        <span class=\"text-2xl font-bold text-gray-900\" id=\"cart-total\">\$0.00</span>
                    </div>
                    <a href=\"/checkout\" class=\"block w-full text-center px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition\">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        `;
    }";

// New implementation - need to write this carefully to avoid PHP syntax issues
// I'll use a placeholder and then do a second replacement
$content = str_replace($oldMethod, "___CART_METHOD_PLACEHOLDER___", $content);

file_put_contents($file, $content);
echo "Step 1: Removed old renderCart method\n";
