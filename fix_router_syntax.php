<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Fix the broken updateCartBadge method
$search = "    // Update cart badge in header
    private updateCartBadge(): void {
        const badge = document.getElementById('cart-badge');
        if (badge) {
            const count = CartService.getItemCount();
            badge.textContent = count.toString();
            if (count > 0) {
                badge.classList.remove('hidden');
            }
    // Update header to show user name when logged in
    private updateHeaderForUser(): void {
        const accountLink = document.getElementById('account-link');
        if (accountLink && this.currentUser) {
            const firstName = this.currentUser.name?.split(' ')[0] || this.currentUser.email;
            accountLink.textContent = `Buna, \${firstName}`;
            accountLink.href = '/account';
        }
    }
 else {
                badge.classList.add('hidden');
            }
        }
    }";

$replace = "    // Update cart badge in header
    private updateCartBadge(): void {
        const badge = document.getElementById('cart-badge');
        if (badge) {
            const count = CartService.getItemCount();
            badge.textContent = count.toString();
            if (count > 0) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    // Update header to show user name when logged in
    private updateHeaderForUser(): void {
        const accountLink = document.getElementById('account-link');
        if (accountLink && this.currentUser) {
            const firstName = this.currentUser.name?.split(' ')[0] || this.currentUser.email;
            accountLink.textContent = `Buna, \${firstName}`;
            accountLink.href = '/account';
        }
    }";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "✓ Fixed Router.ts syntax error\n";
