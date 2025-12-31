<?php

$filePath = 'd:/000WORK/xampp/htdocs/web/eventpilot/epas/resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($filePath);

// Replace renderOrders method
$oldOrders = <<<'OLD'
    private renderOrders(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>
                <div id="orders-list" class="space-y-4">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                </div>
            </div>
        `;
    }
OLD;

$newOrders = <<<'NEW'
    private async renderOrders(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Comenzile Mele</h1>
                <div id="orders-list" class="space-y-4">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi('/account/orders');
            const orders = response.data;

            const ordersListEl = document.getElementById('orders-list');
            if (ordersListEl) {
                if (orders && orders.length > 0) {
                    ordersListEl.innerHTML = orders.map((order: any) => `
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">${order.order_number}</h3>
                                    <p class="text-sm text-gray-600">${order.date}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-gray-900">${order.total} ${order.currency}</p>
                                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${
                                        order.status === 'paid' ? 'bg-green-100 text-green-800' :
                                        order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        order.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    }">
                                        ${order.status_label}
                                    </span>
                                </div>
                            </div>
                            <div class="border-t pt-4">
                                <p class="text-sm text-gray-600 mb-2">${order.items_count} bilet${order.items_count > 1 ? 'e' : ''}</p>
                                <div class="space-y-1">
                                    ${order.tickets.map((ticket: any) => `
                                        <p class="text-sm text-gray-700">• ${ticket.event_name} - ${ticket.ticket_type}</p>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    ordersListEl.innerHTML = `
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-600">Nu ai comenzi încă</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            const ordersListEl = document.getElementById('orders-list');
            if (ordersListEl) {
                ordersListEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea comenzilor</p>
                    </div>
                `;
            }
        }
    }
NEW;

$content = str_replace($oldOrders, $newOrders, $content);

// Replace renderTickets method
$oldTickets = <<<'OLD'
    private renderTickets(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Tickets</h1>
                <div id="tickets-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                </div>
            </div>
        `;
    }
OLD;

$newTickets = <<<'NEW'
    private async renderTickets(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Biletele Mele</h1>
                <div id="tickets-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi('/account/tickets');
            const tickets = response.data;

            const ticketsListEl = document.getElementById('tickets-list');
            if (ticketsListEl) {
                if (tickets && tickets.length > 0) {
                    ticketsListEl.innerHTML = tickets.map((ticket: any) => `
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">${ticket.event_name}</h3>
                                    <p class="text-sm text-gray-600">${ticket.ticket_type}</p>
                                </div>
                                <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${
                                    ticket.status === 'valid' ? 'bg-green-100 text-green-800' :
                                    ticket.status === 'used' ? 'bg-gray-100 text-gray-800' :
                                    'bg-red-100 text-red-800'
                                }">
                                    ${ticket.status_label}
                                </span>
                            </div>
                            ${ticket.date ? `<p class="text-sm text-gray-600 mb-2">📅 ${new Date(ticket.date).toLocaleDateString('ro-RO')}</p>` : ''}
                            ${ticket.venue ? `<p class="text-sm text-gray-600 mb-2">📍 ${ticket.venue}</p>` : ''}
                            ${ticket.seat_label ? `<p class="text-sm text-gray-600 mb-4">💺 ${ticket.seat_label}</p>` : '<div class="mb-4"></div>'}
                            <div class="border-t pt-4 text-center">
                                <img src="${ticket.qr_code}" alt="QR Code" class="w-32 h-32 mx-auto mb-2">
                                <p class="text-xs text-gray-500">${ticket.code}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    ticketsListEl.innerHTML = `
                        <div class="col-span-2 bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                            <p class="text-gray-600">Nu ai bilete încă</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            const ticketsListEl = document.getElementById('tickets-list');
            if (ticketsListEl) {
                ticketsListEl.innerHTML = `
                    <div class="col-span-2 bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea biletelor</p>
                    </div>
                `;
            }
        }
    }
NEW;

$content = str_replace($oldTickets, $newTickets, $content);

// Replace renderProfile method
$oldProfile = <<<'OLD'
    private renderProfile(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Profile</h1>
                <div id="profile-form" class="bg-white rounded-lg shadow p-6">
                    <div class="animate-pulse space-y-4">
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 w-1/3 rounded"></div>
                    </div>
                </div>
            </div>
        `;
    }
OLD;

$newProfile = <<<'NEW'
    private async renderProfile(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Profilul Meu</h1>
                <div id="profile-form" class="bg-white rounded-lg shadow p-6">
                    <div class="animate-pulse space-y-4">
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 w-1/3 rounded"></div>
                    </div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi('/account/profile');
            const profile = response.data;

            const profileFormEl = document.getElementById('profile-form');
            if (profileFormEl) {
                profileFormEl.innerHTML = `
                    <form id="update-profile-form" class="space-y-6">
                        <div id="profile-message"></div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prenume</label>
                            <input type="text" id="first_name" name="first_name" value="${profile.first_name || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nume</label>
                            <input type="text" id="last_name" name="last_name" value="${profile.last_name || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" value="${profile.email || ''}" disabled
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                            <input type="tel" id="phone" name="phone" value="${profile.phone || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div class="border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Schimbă Parola</h3>

                            <div class="space-y-4">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Parola Curentă</label>
                                    <input type="password" id="current_password" name="current_password"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Parola Nouă</label>
                                    <input type="password" id="new_password" name="new_password"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirmă Parola Nouă</label>
                                    <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition">
                            Salvează Modificările
                        </button>
                    </form>
                `;

                // Handle form submission
                const form = document.getElementById('update-profile-form') as HTMLFormElement;
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const messageEl = document.getElementById('profile-message');

                        try {
                            const formData = new FormData(form);
                            const data: any = {};
                            formData.forEach((value, key) => {
                                if (value) data[key] = value;
                            });

                            const response = await this.fetchApi('/account/profile', {}, {
                                method: 'PUT',
                                body: JSON.stringify(data)
                            });

                            if (messageEl) {
                                messageEl.innerHTML = `
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                        <p class="text-green-700">${response.message || 'Profil actualizat cu succes!'}</p>
                                    </div>
                                `;
                            }

                            // Clear password fields
                            (document.getElementById('current_password') as HTMLInputElement).value = '';
                            (document.getElementById('new_password') as HTMLInputElement).value = '';
                            (document.getElementById('new_password_confirmation') as HTMLInputElement).value = '';
                        } catch (error: any) {
                            if (messageEl) {
                                messageEl.innerHTML = `
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                        <p class="text-red-700">${error.message || 'Eroare la actualizarea profilului'}</p>
                                    </div>
                                `;
                            }
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            const profileFormEl = document.getElementById('profile-form');
            if (profileFormEl) {
                profileFormEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea profilului</p>
                    </div>
                `;
            }
        }
    }
NEW;

$content = str_replace($oldProfile, $newProfile, $content);

file_put_contents($filePath, $content);
echo "Router.ts updated successfully!\n";
