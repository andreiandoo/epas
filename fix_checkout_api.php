<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Fix 1: Replace fetchApi with postApi in checkout handler
$content = str_replace(
    "const response = await this.fetchApi('/orders', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            customer_name: formData.get('customer_name'),
                            customer_email: formData.get('customer_email'),
                            customer_phone: formData.get('customer_phone'),
                            cart: cart.map(item => ({
                                eventId: item.eventId,
                                ticketTypeId: item.ticketTypeId,
                                quantity: item.quantity,
                            })),
                        }),
                    });",
    "const response = await this.postApi('/orders', {
                        customer_name: formData.get('customer_name'),
                        customer_email: formData.get('customer_email'),
                        customer_phone: formData.get('customer_phone'),
                        cart: cart.map(item => ({
                            eventId: item.eventId,
                            ticketTypeId: item.ticketTypeId,
                            quantity: item.quantity,
                        })),
                    });",
    $content
);

file_put_contents($file, $content);

echo "✓ Fixed checkout POST request to use postApi\n";
