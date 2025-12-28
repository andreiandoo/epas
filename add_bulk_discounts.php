<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// 1. Add bulk discounts display in ticket card (after description, before price)
$oldTicketCard = '                                                    ${ticket.description ? `<p class="text-sm text-gray-500">${ticket.description}</p>` : \'\'}
                                                </div>
                                                <div class="text-right">';

$newTicketCard = '                                                    ${ticket.description ? `<p class="text-sm text-gray-500">${ticket.description}</p>` : \'\'}
                                                </div>
                                            </div>
                                            ${ticket.bulk_discounts && ticket.bulk_discounts.length > 0 ? `
                                            <div class="mt-2 space-y-1">
                                                ${ticket.bulk_discounts.map((discount: any) => {
                                                    if (discount.rule_type === \'buy_x_get_y\') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            🎁 Cumpără ${discount.buy_qty}, primești ${discount.get_qty} GRATUIT
                                                        </div>`;
                                                    } else if (discount.rule_type === \'amount_off_per_ticket\') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            💰 ${discount.amount_off / 100} ${currency} reducere/bilet pentru ${discount.min_qty}+ bilete
                                                        </div>`;
                                                    } else if (discount.rule_type === \'percent_off\') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            📊 ${discount.percent_off}% reducere pentru ${discount.min_qty}+ bilete
                                                        </div>`;
                                                    }
                                                    return \'\';
                                                }).join(\'\')}
                                            </div>
                                            ` : \'\'}
                                            <div class="flex justify-between items-start">
                                                <div></div>
                                                <div class="text-right">';

$content = str_replace($oldTicketCard, $newTicketCard, $content);

// 2. Update setupTicketHandlers to calculate bulk discounts
$oldUpdateTotal = '        const updateTotal = () => {
            let total = 0;
            let hasSelection = false;
            let currency = \'EUR\';

            qtyDisplays.forEach((display) => {
                const ticketId = (display as HTMLElement).dataset.ticketId || \'\';
                const qty = quantities[ticketId] || 0;
                const price = parseFloat((display as HTMLElement).dataset.price || \'0\');
                const ticketCurrency = (display as HTMLElement).dataset.currency || \'EUR\';

                if (qty > 0) {
                    total += qty * price;
                    hasSelection = true;
                    currency = ticketCurrency;
                }
            });

            if (totalEl) totalEl.textContent = `${total.toFixed(2)} ${currency}`;
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };';

$newUpdateTotal = '        // Store bulk discounts per ticket
        const ticketBulkDiscounts: { [key: string]: any[] } = {};
        document.querySelectorAll(\'.ticket-qty-display\').forEach((display) => {
            const ticketId = (display as HTMLElement).dataset.ticketId || \'\';
            const discountsAttr = (display as HTMLElement).dataset.bulkDiscounts;
            if (discountsAttr) {
                try {
                    ticketBulkDiscounts[ticketId] = JSON.parse(discountsAttr);
                } catch (e) {
                    ticketBulkDiscounts[ticketId] = [];
                }
            } else {
                ticketBulkDiscounts[ticketId] = [];
            }
        });

        const calculateBulkDiscount = (qty: number, price: number, discounts: any[]): { total: number; discount: number; info: string } => {
            let bestTotal = qty * price;
            let bestDiscount = 0;
            let bestInfo = \'\';

            for (const discount of discounts) {
                let discountedTotal = qty * price;
                let discountAmount = 0;
                let info = \'\';

                if (discount.rule_type === \'buy_x_get_y\' && qty >= discount.buy_qty) {
                    // Calculate how many free tickets
                    const sets = Math.floor(qty / discount.buy_qty);
                    const freeTickets = sets * discount.get_qty;
                    const paidTickets = qty - freeTickets;
                    discountedTotal = paidTickets * price;
                    discountAmount = freeTickets * price;
                    info = `Buy ${discount.buy_qty} get ${discount.get_qty} free`;
                } else if (discount.rule_type === \'amount_off_per_ticket\' && qty >= discount.min_qty) {
                    const amountOff = discount.amount_off / 100; // Convert cents to currency
                    discountAmount = qty * amountOff;
                    discountedTotal = (qty * price) - discountAmount;
                    info = `${amountOff} off per ticket`;
                } else if (discount.rule_type === \'percent_off\' && qty >= discount.min_qty) {
                    discountAmount = (qty * price) * (discount.percent_off / 100);
                    discountedTotal = (qty * price) - discountAmount;
                    info = `${discount.percent_off}% off`;
                }

                if (discountedTotal < bestTotal) {
                    bestTotal = discountedTotal;
                    bestDiscount = discountAmount;
                    bestInfo = info;
                }
            }

            return { total: bestTotal, discount: bestDiscount, info: bestInfo };
        };

        const updateTotal = () => {
            let total = 0;
            let totalDiscount = 0;
            let hasSelection = false;
            let currency = \'EUR\';
            let discountInfos: string[] = [];

            qtyDisplays.forEach((display) => {
                const ticketId = (display as HTMLElement).dataset.ticketId || \'\';
                const qty = quantities[ticketId] || 0;
                const price = parseFloat((display as HTMLElement).dataset.price || \'0\');
                const ticketCurrency = (display as HTMLElement).dataset.currency || \'EUR\';
                const discounts = ticketBulkDiscounts[ticketId] || [];

                if (qty > 0) {
                    const result = calculateBulkDiscount(qty, price, discounts);
                    total += result.total;
                    totalDiscount += result.discount;
                    if (result.info) discountInfos.push(result.info);
                    hasSelection = true;
                    currency = ticketCurrency;
                }
            });

            if (totalEl) {
                if (totalDiscount > 0) {
                    const originalTotal = total + totalDiscount;
                    totalEl.innerHTML = `
                        <div class="text-sm text-gray-500 line-through">${originalTotal.toFixed(2)} ${currency}</div>
                        <div class="text-lg font-bold text-green-600">${total.toFixed(2)} ${currency}</div>
                        <div class="text-xs text-green-600">Economisești ${totalDiscount.toFixed(2)} ${currency}</div>
                    `;
                } else {
                    totalEl.textContent = `${total.toFixed(2)} ${currency}`;
                }
            }
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };';

$content = str_replace($oldUpdateTotal, $newUpdateTotal, $content);

// 3. Add bulk_discounts data attribute to ticket quantity display
$oldQtyDisplay = '<span class="ticket-qty-display w-12 text-center font-semibold" data-ticket-id="${ticket.id}" data-price="${ticket.sale_price || ticket.price}" data-currency="${currency}">0</span>';

$newQtyDisplay = '<span class="ticket-qty-display w-12 text-center font-semibold" data-ticket-id="${ticket.id}" data-price="${ticket.sale_price || ticket.price}" data-currency="${currency}" data-bulk-discounts=\'${JSON.stringify(ticket.bulk_discounts || [])}\'>0</span>';

$content = str_replace($oldQtyDisplay, $newQtyDisplay, $content);

file_put_contents($file, $content);

echo "Bulk discounts implemented!\n";
echo "Changes:\n";
echo "1. Display bulk discount offers on ticket cards\n";
echo "2. Calculate bulk discounts in total\n";
echo "3. Show savings amount\n";
