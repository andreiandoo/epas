<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// 1. Add artist links
$content = str_replace(
    "                                    <div class=\"flex items-center bg-gray-100 rounded-lg p-3\">
                                        \${artist.image
                                            ? `<img src=\"\${artist.image}\" alt=\"\${artist.name}\" class=\"w-10 h-10 rounded-full object-cover mr-3\">`
                                            : `<div class=\"w-10 h-10 rounded-full bg-gray-300 mr-3\"></div>`
                                        }
                                        <span class=\"font-medium\">\${artist.name}</span>
                                    </div>",
    "                                    <a href=\"https://core.tixello.com/artist/\${artist.slug}?locale=en\" target=\"_blank\" class=\"flex items-center bg-gray-100 rounded-lg p-3 hover:bg-gray-200 transition\">
                                        \${artist.image
                                            ? `<img src=\"\${artist.image}\" alt=\"\${artist.name}\" class=\"w-10 h-10 rounded-full object-cover mr-3\">`
                                            : `<div class=\"w-10 h-10 rounded-full bg-gray-300 mr-3\"></div>`
                                        }
                                        <span class=\"font-medium\">\${artist.name}</span>
                                    </a>",
    $content
);

// 2. Add venue link and Google Maps link
$content = str_replace(
    "                            \${event.venue ? `
                            <div class=\"flex items-center\">
                                <svg class=\"w-5 h-5 mr-2\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                    <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z\"/>
                                    <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 11a3 3 0 11-6 0 3 3 0 016 0z\"/>
                                </svg>
                                \${event.venue.name}\${event.venue.city ? `, \${event.venue.city}` : ''}
                            </div>
                            ` : ''}",
    "                            \${event.venue ? `
                            <div class=\"flex items-center flex-wrap gap-2\">
                                <div class=\"flex items-center\">
                                    <svg class=\"w-5 h-5 mr-2\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z\"/>
                                        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 11a3 3 0 11-6 0 3 3 0 016 0z\"/>
                                    </svg>
                                    <a href=\"https://core.tixello.com/venue/\${event.venue.slug}?locale=en\" target=\"_blank\" class=\"hover:underline text-blue-600\">\${event.venue.name}</a>
                                    \${event.venue.city ? `, \${event.venue.city}` : ''}
                                </div>
                                \${event.venue.latitude && event.venue.longitude ? `
                                <a href=\"https://www.google.com/maps?q=\${event.venue.latitude},\${event.venue.longitude}\" target=\"_blank\" class=\"text-sm text-blue-600 hover:underline\">
                                    Vezi pe Google Maps
                                </a>
                                ` : event.venue.address ? `
                                <a href=\"https://www.google.com/maps/search/?api=1&query=\${encodeURIComponent(event.venue.address)}\" target=\"_blank\" class=\"text-sm text-blue-600 hover:underline\">
                                    Vezi pe Google Maps
                                </a>
                                ` : ''}
                            </div>
                            ` : ''}",
    $content
);

// 3. Replace dropdown with +/- buttons for ticket quantities
$content = str_replace(
    "                                            \${ticket.status === 'active' && available > 0 ? `
                                            <div class=\"flex items-center justify-between mt-3\">
                                                <select class=\"ticket-qty px-3 py-1 border border-gray-300 rounded text-sm\" data-ticket-id=\"\${ticket.id}\" data-price=\"\${ticket.sale_price || ticket.price}\" data-currency=\"\${currency}\">
                                                    \${Array.from({length: maxQty + 1}, (_, i) => `<option value=\"\${i}\">\${i}</option>`).join('')}
                                                </select>
                                                <span class=\"text-sm text-gray-500\">\${available} disponibile</span>
                                            </div>
                                            ` : `
                                                <p class=\"text-sm text-gray-500 mt-2\">\${ticket.status !== 'active' ? 'Indisponibil' : 'Stoc epuizat'}</p>
                                            `}",
    "                                            \${ticket.status === 'active' && available > 0 ? `
                                            <div class=\"flex items-center justify-between mt-3\">
                                                <div class=\"flex items-center gap-2\">
                                                    <button class=\"ticket-minus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100\" data-ticket-id=\"\${ticket.id}\">
                                                        <svg class=\"w-4 h-4\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M20 12H4\"/>
                                                        </svg>
                                                    </button>
                                                    <span class=\"ticket-qty-display w-12 text-center font-semibold\" data-ticket-id=\"\${ticket.id}\" data-price=\"\${ticket.sale_price || ticket.price}\" data-currency=\"\${currency}\">0</span>
                                                    <button class=\"ticket-plus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100\" data-ticket-id=\"\${ticket.id}\" data-max=\"\${maxQty}\">
                                                        <svg class=\"w-4 h-4\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 4v16m8-8H4\"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <span class=\"text-sm text-gray-500\">\${available} disponibile</span>
                                            </div>
                                            ` : `
                                                <p class=\"text-sm text-gray-500 mt-2\">\${ticket.status !== 'active' ? 'Indisponibil' : 'Stoc epuizat'}</p>
                                            `}",
    $content
);

// 4. Update setupTicketHandlers to work with +/- buttons
$content = str_replace(
    "    private setupTicketHandlers(): void {
        const qtySelects = document.querySelectorAll('.ticket-qty');
        const totalEl = document.getElementById('cart-total-price');
        const addBtn = document.getElementById('add-to-cart-btn');

        const updateTotal = () => {
            let total = 0;
            let hasSelection = false;
            let currency = 'EUR';

            qtySelects.forEach((select) => {
                const qty = parseInt((select as HTMLSelectElement).value);
                const price = parseFloat((select as HTMLSelectElement).dataset.price || '0');
                const ticketCurrency = (select as HTMLSelectElement).dataset.currency || 'EUR';
                if (qty > 0) {
                    total += qty * price;
                    hasSelection = true;
                    currency = ticketCurrency; // Use first selected ticket's currency
                }
            });

            if (totalEl) totalEl.textContent = `\${total.toFixed(2)} \${currency}`;
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };

        qtySelects.forEach((select) => {
            select.addEventListener('change', updateTotal);
        });

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                // TODO: Add to cart functionality
                alert('Funcționalitate în dezvoltare. Biletele vor fi adăugate în coș.');
                this.navigate('/cart');
            });
        }
    }",
    "    private setupTicketHandlers(): void {
        const qtyDisplays = document.querySelectorAll('.ticket-qty-display');
        const totalEl = document.getElementById('cart-total-price');
        const addBtn = document.getElementById('add-to-cart-btn');

        // Store quantities in memory
        const quantities: { [key: string]: number } = {};

        const updateTotal = () => {
            let total = 0;
            let hasSelection = false;
            let currency = 'EUR';

            qtyDisplays.forEach((display) => {
                const ticketId = (display as HTMLElement).dataset.ticketId || '';
                const qty = quantities[ticketId] || 0;
                const price = parseFloat((display as HTMLElement).dataset.price || '0');
                const ticketCurrency = (display as HTMLElement).dataset.currency || 'EUR';

                if (qty > 0) {
                    total += qty * price;
                    hasSelection = true;
                    currency = ticketCurrency;
                }
            });

            if (totalEl) totalEl.textContent = `\${total.toFixed(2)} \${currency}`;
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };

        // Setup + buttons
        document.querySelectorAll('.ticket-plus').forEach((btn) => {
            btn.addEventListener('click', () => {
                const ticketId = (btn as HTMLElement).dataset.ticketId || '';
                const max = parseInt((btn as HTMLElement).dataset.max || '10');
                const current = quantities[ticketId] || 0;

                if (current < max) {
                    quantities[ticketId] = current + 1;
                    const display = document.querySelector(`.ticket-qty-display[data-ticket-id=\"\${ticketId}\"]`);
                    if (display) display.textContent = quantities[ticketId].toString();
                    updateTotal();
                }
            });
        });

        // Setup - buttons
        document.querySelectorAll('.ticket-minus').forEach((btn) => {
            btn.addEventListener('click', () => {
                const ticketId = (btn as HTMLElement).dataset.ticketId || '';
                const current = quantities[ticketId] || 0;

                if (current > 0) {
                    quantities[ticketId] = current - 1;
                    const display = document.querySelector(`.ticket-qty-display[data-ticket-id=\"\${ticketId}\"]`);
                    if (display) display.textContent = quantities[ticketId].toString();
                    updateTotal();
                }
            });
        });

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                // TODO: Add to cart functionality
                alert('Funcționalitate în dezvoltare. Biletele vor fi adăugate în coș.');
                this.navigate('/cart');
            });
        }
    }",
    $content
);

// 5. Add ticket_terms display after description
$content = str_replace(
    "                        \${event.description ? `
                        <div class=\"prose max-w-none mb-8\">
                            <h2 class=\"text-xl font-semibold text-gray-900 mb-4\">Descriere</h2>
                            <div class=\"text-gray-700\">\${event.description}</div>
                        </div>
                        ` : ''}",
    "                        \${event.description ? `
                        <div class=\"prose max-w-none mb-8\">
                            <h2 class=\"text-xl font-semibold text-gray-900 mb-4\">Descriere</h2>
                            <div class=\"text-gray-700\">\${event.description}</div>
                        </div>
                        ` : ''}

                        \${event.ticket_terms ? `
                        <div class=\"prose max-w-none mb-8\">
                            <h2 class=\"text-xl font-semibold text-gray-900 mb-4\">Termeni și condiții bilete</h2>
                            <div class=\"text-gray-700 text-sm\">\${event.ticket_terms}</div>
                        </div>
                        ` : ''}

                        \${event.event_website_url || event.facebook_url ? `
                        <div class=\"mb-8\">
                            <h2 class=\"text-xl font-semibold text-gray-900 mb-4\">Link-uri</h2>
                            <div class=\"flex flex-wrap gap-4\">
                                \${event.event_website_url ? `<a href=\"\${event.event_website_url}\" target=\"_blank\" class=\"text-blue-600 hover:underline\">Website eveniment</a>` : ''}
                                \${event.facebook_url ? `<a href=\"\${event.facebook_url}\" target=\"_blank\" class=\"text-blue-600 hover:underline\">Facebook</a>` : ''}
                                \${event.website_url ? `<a href=\"\${event.website_url}\" target=\"_blank\" class=\"text-blue-600 hover:underline\">Website</a>` : ''}
                            </div>
                        </div>
                        ` : ''}",
    $content
);

file_put_contents($file, $content);

echo "Router.ts updated successfully!\n";
echo "Changes made:\n";
echo "1. Artist links to tixello.com added\n";
echo "2. Venue link to tixello.com added\n";
echo "3. Google Maps links added (lat/long or address)\n";
echo "4. Ticket quantity dropdown replaced with +/- buttons\n";
echo "5. ticket_terms and social links sections added\n";
