<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Add updateCartBadge method after getUser method
$searchFor = '    public getUser(): any {
        return this.currentUser;
    }';

$replacement = '    public getUser(): any {
        return this.currentUser;
    }

    // Update cart badge in header
    private updateCartBadge(): void {
        const badge = document.getElementById(\'cart-badge\');
        if (badge) {
            const count = CartService.getItemCount();
            badge.textContent = count.toString();
            if (count > 0) {
                badge.classList.remove(\'hidden\');
            } else {
                badge.classList.add(\'hidden\');
            }
        }
    }';

$content = str_replace($searchFor, $replacement, $content);

// Call updateCartBadge after adding items to cart (in setupTicketHandlers)
$content = str_replace(
    "ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');
                        this.navigate('/cart');",
    "ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');
                        this.updateCartBadge();
                        this.navigate('/cart');",
    $content
);

// Call updateCartBadge after clearing cart
$content = str_replace(
    "CartService.clearCart();
                        ToastNotification.show('✓ Comanda a fost plasată cu succes!', 'success');",
    "CartService.clearCart();
                        this.updateCartBadge();
                        ToastNotification.show('✓ Comanda a fost plasată cu succes!', 'success');",
    $content
);

// Call updateCartBadge after clearing cart from cart page
$content = str_replace(
    "CartService.clearCart();
                    this.renderCart();
                    ToastNotification.show('Coșul a fost golit', 'success');",
    "CartService.clearCart();
                    this.updateCartBadge();
                    this.renderCart();
                    ToastNotification.show('Coșul a fost golit', 'success');",
    $content
);

// Call updateCartBadge after updating quantity or removing item
$content = str_replace(
    "CartService.updateQuantity(eventId, ticketTypeId, newQty);
                        this.renderCart();",
    "CartService.updateQuantity(eventId, ticketTypeId, newQty);
                        this.updateCartBadge();
                        this.renderCart();",
    $content
);

$content = str_replace(
    "CartService.removeItem(eventId, ticketTypeId);
                    this.renderCart();
                    ToastNotification.show('Produs șters din coș', 'success');",
    "CartService.removeItem(eventId, ticketTypeId);
                    this.updateCartBadge();
                    this.renderCart();
                    ToastNotification.show('Produs șters din coș', 'success');",
    $content
);

// Call updateCartBadge on page load (in init method)
$content = preg_replace(
    '/(private init\(\): void \{[^}]+)(this\.router\(\);)/s',
    '$1this.updateCartBadge();
        $2',
    $content
);

file_put_contents($file, $content);

echo "✓ Added updateCartBadge() method and calls\n";
