<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// 1. Add cart management interface at the top of the Router class (after imports/before class)
$cartInterface = "
// Cart item interface
interface CartItem {
    eventId: number;
    eventTitle: string;
    eventSlug: string;
    eventDate: string;
    ticketTypeId: number;
    ticketTypeName: string;
    price: number;
    salePrice: number | null;
    quantity: number;
    currency: string;
    bulkDiscounts: any[];
}

class CartService {
    private static STORAGE_KEY = 'tixello_cart';

    static getCart(): CartItem[] {
        const cartJson = localStorage.getItem(this.STORAGE_KEY);
        return cartJson ? JSON.parse(cartJson) : [];
    }

    static addItem(item: CartItem): void {
        const cart = this.getCart();
        const existingIndex = cart.findIndex(
            i => i.eventId === item.eventId && i.ticketTypeId === item.ticketTypeId
        );

        if (existingIndex >= 0) {
            cart[existingIndex].quantity += item.quantity;
        } else {
            cart.push(item);
        }

        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
    }

    static updateQuantity(eventId: number, ticketTypeId: number, quantity: number): void {
        const cart = this.getCart();
        const item = cart.find(i => i.eventId === eventId && i.ticketTypeId === ticketTypeId);
        if (item) {
            item.quantity = quantity;
            if (item.quantity <= 0) {
                this.removeItem(eventId, ticketTypeId);
            } else {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
            }
        }
    }

    static removeItem(eventId: number, ticketTypeId: number): void {
        let cart = this.getCart();
        cart = cart.filter(i => !(i.eventId === eventId && i.ticketTypeId === ticketTypeId));
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
    }

    static clearCart(): void {
        localStorage.removeItem(this.STORAGE_KEY);
    }

    static getItemCount(): number {
        return this.getCart().reduce((sum, item) => sum + item.quantity, 0);
    }

    static calculateBulkDiscount(qty: number, price: number, discounts: any[]): { total: number; discount: number } {
        let bestTotal = qty * price;
        let bestDiscount = 0;

        for (const discount of discounts) {
            let discountedTotal = qty * price;
            let discountAmount = 0;

            if (discount.rule_type === 'buy_x_get_y' && qty >= discount.buy_qty) {
                const sets = Math.floor(qty / discount.buy_qty);
                const freeTickets = sets * discount.get_qty;
                const paidTickets = qty - freeTickets;
                discountedTotal = paidTickets * price;
                discountAmount = freeTickets * price;
            } else if (discount.rule_type === 'amount_off_per_ticket' && qty >= discount.min_qty) {
                const amountOff = discount.amount_off / 100;
                discountAmount = qty * amountOff;
                discountedTotal = (qty * price) - discountAmount;
            } else if (discount.rule_type === 'percent_off' && qty >= discount.min_qty) {
                discountAmount = (qty * price) * (discount.percent_off / 100);
                discountedTotal = (qty * price) - discountAmount;
            }

            if (discountedTotal < bestTotal) {
                bestTotal = discountedTotal;
                bestDiscount = discountAmount;
            }
        }

        return { total: bestTotal, discount: bestDiscount };
    }

    static getTotal(): { subtotal: number; discount: number; total: number; currency: string } {
        const cart = this.getCart();
        let subtotal = 0;
        let totalDiscount = 0;
        let currency = 'EUR';

        for (const item of cart) {
            const itemPrice = item.salePrice || item.price;
            const result = this.calculateBulkDiscount(item.quantity, itemPrice, item.bulkDiscounts);
            subtotal += item.quantity * itemPrice;
            totalDiscount += result.discount;
            currency = item.currency;
        }

        return {
            subtotal,
            discount: totalDiscount,
            total: subtotal - totalDiscount,
            currency
        };
    }
}

";

// Insert CartService before the Router class
$content = str_replace(
    'export class Router {',
    $cartInterface . 'export class Router {',
    $content
);

// 2. Update "Add to cart" button handler to actually add to cart
$oldAddToCart = '        if (addBtn) {
            addBtn.addEventListener(\'click\', () => {
                // TODO: Add to cart functionality
                alert(\'Funcționalitate în dezvoltare. Biletele vor fi adăugate în coș.\');
                this.navigate(\'/cart\');
            });
        }';

$newAddToCart = '        if (addBtn) {
            addBtn.addEventListener(\'click\', () => {
                // Get current event data
                const eventTitle = document.querySelector(\'#event-detail h1\')?.textContent || \'\';
                const eventData = (window as any).currentEventData; // Store event data globally

                // Collect selected tickets
                let hasItems = false;
                qtyDisplays.forEach((display) => {
                    const ticketId = parseInt((display as HTMLElement).dataset.ticketId || \'0\');
                    const qty = quantities[ticketId] || 0;

                    if (qty > 0 && eventData) {
                        const ticketType = eventData.ticket_types.find((t: any) => t.id === ticketId);
                        if (ticketType) {
                            CartService.addItem({
                                eventId: eventData.id,
                                eventTitle: eventData.title,
                                eventSlug: eventData.slug,
                                eventDate: eventData.start_date,
                                ticketTypeId: ticketType.id,
                                ticketTypeName: ticketType.name,
                                price: ticketType.price,
                                salePrice: ticketType.sale_price,
                                quantity: qty,
                                currency: ticketType.currency || \'EUR\',
                                bulkDiscounts: ticketType.bulk_discounts || []
                            });
                            hasItems = true;
                        }
                    }
                });

                if (hasItems) {
                    alert(\'Biletele au fost adăugate în coș!\');
                    this.navigate(\'/cart\');
                } else {
                    alert(\'Te rog selectează cel puțin un bilet.\');
                }
            });
        }';

$content = str_replace($oldAddToCart, $newAddToCart, $content);

// 3. Store event data globally in renderEventDetail
$oldEventDetail = '            if (!event) {
                this.render404();
                return;
            }

            const date = event.start_date';

$newEventDetail = '            if (!event) {
                this.render404();
                return;
            }

            // Store event data globally for cart functionality
            (window as any).currentEventData = event;

            const date = event.start_date';

$content = str_replace($oldEventDetail, $newEventDetail, $content);

file_put_contents($file, $content);

echo "Step 1: Cart service and add-to-cart implemented!\n";
