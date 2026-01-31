<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// 1. After adding items to cart (before navigate)
$content = str_replace(
    "ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');
                    this.navigate('/cart');",
    "ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');
                    this.updateCartBadge();
                    this.navigate('/cart');",
    $content
);

// 2. After removing item from cart
$content = str_replace(
    "CartService.removeItem(eventId, ticketId);
                this.renderCart();",
    "CartService.removeItem(eventId, ticketId);
                this.updateCartBadge();
                this.renderCart();",
    $content
);

// 3. After updating quantity (increment)
$content = str_replace(
    "CartService.updateQuantity(eventId, ticketId, item.quantity + 1);
                    this.renderCart();",
    "CartService.updateQuantity(eventId, ticketId, item.quantity + 1);
                    this.updateCartBadge();
                    this.renderCart();",
    $content
);

// 4. After updating quantity (decrement)
$content = str_replace(
    "CartService.updateQuantity(eventId, ticketId, item.quantity - 1);
                    this.renderCart();",
    "CartService.updateQuantity(eventId, ticketId, item.quantity - 1);
                    this.updateCartBadge();
                    this.renderCart();",
    $content
);

// 5. After clearing cart from cart page
$content = str_replace(
    "CartService.clearCart();
                    this.renderCart();",
    "CartService.clearCart();
                    this.updateCartBadge();
                    this.renderCart();",
    $content
);

// 6. Call on page init - find the init() method and add updateCartBadge after router()
if (preg_match('/(private\s+init\(\):\s+void\s*\{[^}]*this\.router\(\);)/s', $content, $matches)) {
    $replacement = str_replace('this.router();', 'this.router();
        this.updateCartBadge();', $matches[1]);
    $content = str_replace($matches[1], $replacement, $content);
}

file_put_contents($file, $content);

echo "✓ Added all missing updateCartBadge() calls\n";
