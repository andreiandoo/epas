<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Metode de plata';
$currentPage = 'payments';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <a href="/cont/setari" class="text-muted hover:text-primary">Setari</a>
            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="font-medium text-secondary">Metode de plata</span>
        </div>

        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Metode de plata</h1>
                <p class="mt-1 text-sm text-muted">Gestioneaza cardurile salvate</p>
            </div>
            <button onclick="openModal()" class="btn btn-primary flex items-center gap-2 px-5 py-2.5 text-white font-semibold rounded-xl text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Adauga card nou
            </button>
        </div>

        <!-- Saved Cards -->
        <div class="p-5 mb-6 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
            <h2 class="mb-4 font-bold text-secondary">Carduri salvate</h2>

            <div class="space-y-4" id="cards-list">
                <!-- Loading -->
                <div class="flex items-center gap-4 p-4 border-2 animate-pulse border-border rounded-xl">
                    <div class="w-16 h-10 rounded-lg bg-muted/20"></div>
                    <div class="flex-1">
                        <div class="w-1/3 h-4 mb-2 rounded bg-muted/20"></div>
                        <div class="w-1/4 h-3 rounded bg-muted/20"></div>
                    </div>
                </div>
            </div>

            <div id="no-cards" class="hidden py-8 text-center">
                <svg class="w-12 h-12 mx-auto mb-3 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <p class="mb-2 text-muted">Nu ai carduri salvate</p>
                <button onclick="openModal()" class="text-sm font-medium text-primary">Adauga primul card</button>
            </div>
        </div>

        <!-- Other Payment Methods -->
        <div class="p-5 mb-6 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
            <h2 class="mb-4 font-bold text-secondary">Alte metode de plata</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Apple Pay -->
                <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-12 h-12 bg-black rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-secondary">Apple Pay</p>
                            <p class="text-xs text-muted" id="apple-pay-status">Neconectat</p>
                        </div>
                    </div>
                    <button class="text-sm font-medium text-primary" id="apple-pay-btn">Conecteaza</button>
                </div>

                <!-- Google Pay -->
                <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-12 h-12 bg-white border border-border rounded-xl">
                            <svg class="w-6 h-6" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-secondary">Google Pay</p>
                            <p class="text-xs text-muted" id="google-pay-status">Neconectat</p>
                        </div>
                    </div>
                    <button class="text-sm font-medium text-primary" id="google-pay-btn">Conecteaza</button>
                </div>
            </div>
        </div>

        <!-- Billing Address -->
        <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-secondary">Adresa de facturare</h2>
                <button onclick="editAddress()" class="text-sm font-medium text-primary">Editeaza</button>
            </div>

            <div class="p-4 bg-surface rounded-xl" id="billing-address">
                <p class="text-sm text-muted">Nicio adresa salvata</p>
            </div>
        </div>

        <!-- Security Info -->
        <div class="flex items-start gap-3 p-4 mt-6 border bg-success/5 border-success/20 rounded-xl">
            <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-success/10">
                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-secondary">Datele tale sunt in siguranta</p>
                <p class="text-sm text-muted">Toate informatiile de plata sunt criptate si procesate in siguranta. Nu stocam niciodata numerele complete ale cardurilor.</p>
            </div>
        </div>

<!-- Add Card Modal -->
    <div id="cardModal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal bg-black/50">
        <div class="modal-content bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b lg:p-6 border-border">
                <h2 class="text-lg font-bold text-secondary">Adauga card nou</h2>
                <button onclick="closeModal()" class="p-2 rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 lg:p-6">
                <!-- Card Preview -->
                <div class="p-5 mb-6 text-white credit-card" style="background: linear-gradient(135deg, #1E293B 0%, #334155 100%);    border-radius: 16px;    aspect-ratio: 1.586;    max-width: 400px;">
                    <div class="flex items-start justify-between mb-8">
                        <div class="w-12 h-10 rounded bg-gradient-to-br from-yellow-200 to-yellow-400"></div>
                        <svg class="w-12 h-8" viewBox="0 0 60 20" fill="white">
                            <text x="5" y="15" font-size="12" font-weight="bold" font-family="Arial">VISA</text>
                        </svg>
                    </div>
                    <p class="mb-4 font-mono text-xl tracking-widest" id="cardNumberPreview">**** **** **** ****</p>
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xs uppercase text-white/90">Titular</p>
                            <p class="font-medium" id="cardNamePreview">NUME PRENUME</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase text-white/90">Expira</p>
                            <p class="font-medium" id="cardExpiryPreview">MM/YY</p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form id="card-form" class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Numar card</label>
                        <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCardNumber(this)" class="w-full px-4 py-3 font-mono text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Numele de pe card</label>
                        <input type="text" id="cardName" placeholder="ANDREI POPESCU" oninput="updateCardPreview()" class="w-full px-4 py-3 text-sm uppercase border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary">Data expirarii</label>
                            <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)" class="w-full px-4 py-3 font-mono text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary">CVV</label>
                            <input type="text" id="cardCvv" placeholder="123" maxlength="4" class="w-full px-4 py-3 font-mono text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-surface rounded-xl">
                        <input type="checkbox" id="defaultCard" class="w-4 h-4 rounded text-primary border-border focus:ring-primary">
                        <label for="defaultCard" class="text-sm text-secondary">Seteaza ca metoda principala de plata</label>
                    </div>
                </form>
            </div>

            <div class="flex gap-3 p-5 border-t lg:p-6 border-border">
                <button onclick="closeModal()" class="flex-1 py-3 text-sm font-semibold transition-colors bg-surface text-secondary rounded-xl hover:bg-primary/10 hover:text-primary">
                    Anuleaza
                </button>
                <button onclick="saveCard()" class="flex-1 py-3 text-sm font-semibold text-white btn btn-primary rounded-xl">
                    Salveaza cardul
                </button>
            </div>
        </div>
    </div>

<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const PaymentsPage = {
    cards: [],

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/plati';
            return;
        }

        this.loadUserInfo();
        await this.loadPaymentMethods();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                const initials = ((user.first_name?.[0] || '') + (user.last_name?.[0] || '')).toUpperCase() ||
                    user.name?.substring(0, 2).toUpperCase() || '--';
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = user.points || '0';
            }

            // Load billing address
            if (user.address || user.city) {
                document.getElementById('billing-address').innerHTML = `
                    <p class="font-medium text-secondary">${user.first_name || ''} ${user.last_name || ''}</p>
                    <p class="mt-1 text-sm text-muted">${user.address || ''}</p>
                    <p class="text-sm text-muted">${user.city || ''} ${user.postal_code || ''}</p>
                    <p class="text-sm text-muted">${user.country || 'Romania'}</p>
                `;
            }
        }
    },

    async loadPaymentMethods() {
        try {
            const response = await AmbiletAPI.get('/customer/payment-methods');
            if (response.success && response.data?.cards?.length > 0) {
                this.cards = response.data.cards;
                this.renderCards();
            } else {
                this.showNoCards();
            }
        } catch (error) {
            console.error('Error loading payment methods:', error);
            // Show demo cards for testing
            this.cards = [
                { id: 1, type: 'visa', last4: '4532', expiry: '08/26', is_default: true },
                { id: 2, type: 'mastercard', last4: '8891', expiry: '12/25', is_default: false }
            ];
            this.renderCards();
        }
    },

    renderCards() {
        const container = document.getElementById('cards-list');
        const noCards = document.getElementById('no-cards');

        if (this.cards.length === 0) {
            this.showNoCards();
            return;
        }

        noCards.classList.add('hidden');
        container.innerHTML = this.cards.map(card => `
            <div class="card-item ${card.is_default ? 'selected' : ''} border-2 ${card.is_default ? 'border-primary' : 'border-border'} rounded-xl p-4 cursor-pointer" onclick="PaymentsPage.selectCard(${card.id})">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-10 ${card.type === 'visa' ? 'bg-gradient-to-r from-[#1a1f71] to-[#2d4aa8]' : 'bg-gradient-to-r from-[#eb001b] to-[#f79e1b]'} rounded-lg flex items-center justify-center">
                        ${card.type === 'visa'
                            ? '<svg class="w-10 h-6" viewBox="0 0 60 20" fill="white"><text x="5" y="15" font-size="12" font-weight="bold" font-family="Arial">VISA</text></svg>'
                            : '<div class="flex"><div class="w-4 h-4 bg-[#eb001b] rounded-full"></div><div class="w-4 h-4 bg-[#f79e1b] rounded-full -ml-1.5"></div></div>'
                        }
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-secondary">**** **** **** ${card.last4}</p>
                            ${card.is_default ? '<span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-semibold rounded">Principal</span>' : ''}
                        </div>
                        <p class="text-sm text-muted">Expira ${card.expiry}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="event.stopPropagation(); PaymentsPage.editCard(${card.id})" class="p-2 transition-colors rounded-lg text-muted hover:text-secondary hover:bg-surface" title="Editeaza">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button onclick="event.stopPropagation(); PaymentsPage.deleteCard(${card.id})" class="p-2 transition-colors rounded-lg text-muted hover:text-error hover:bg-error/10" title="Sterge">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    },

    showNoCards() {
        document.getElementById('cards-list').innerHTML = '';
        document.getElementById('no-cards').classList.remove('hidden');
    },

    selectCard(id) {
        this.cards.forEach(card => card.is_default = (card.id === id));
        this.renderCards();
        AmbiletNotifications.success('Card principal actualizat!');
    },

    editCard(id) {
        AmbiletNotifications.info('Functie in dezvoltare');
    },

    async deleteCard(id) {
        if (!confirm('Esti sigur ca vrei sa stergi acest card?')) return;

        try {
            await AmbiletAPI.delete(`/customer/payment-methods/${id}`);
            this.cards = this.cards.filter(c => c.id !== id);
            this.renderCards();
            AmbiletNotifications.success('Card sters cu succes!');
        } catch (error) {
            // Demo mode
            this.cards = this.cards.filter(c => c.id !== id);
            this.renderCards();
            AmbiletNotifications.success('Card sters cu succes!');
        }
    }
};

function openModal() {
    const modal = document.getElementById('cardModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('cardModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
    // Reset form
    document.getElementById('card-form').reset();
    document.getElementById('cardNumberPreview').textContent = '**** **** **** ****';
    document.getElementById('cardNamePreview').textContent = 'NUME PRENUME';
    document.getElementById('cardExpiryPreview').textContent = 'MM/YY';
}

function formatCardNumber(input) {
    let value = input.value.replace(/\s/g, '').replace(/\D/g, '');
    let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
    input.value = formatted;
    document.getElementById('cardNumberPreview').textContent = formatted || '**** **** **** ****';
}

function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2);
    }
    input.value = value;
    document.getElementById('cardExpiryPreview').textContent = value || 'MM/YY';
}

function updateCardPreview() {
    const name = document.getElementById('cardName').value;
    document.getElementById('cardNamePreview').textContent = name.toUpperCase() || 'NUME PRENUME';
}

async function saveCard() {
    const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
    const cardName = document.getElementById('cardName').value;
    const cardExpiry = document.getElementById('cardExpiry').value;
    const cardCvv = document.getElementById('cardCvv').value;
    const isDefault = document.getElementById('defaultCard').checked;

    if (!cardNumber || cardNumber.length < 16) {
        AmbiletNotifications.error('Numar card invalid');
        return;
    }
    if (!cardName) {
        AmbiletNotifications.error('Introdu numele de pe card');
        return;
    }
    if (!cardExpiry || cardExpiry.length < 5) {
        AmbiletNotifications.error('Data expirarii invalida');
        return;
    }
    if (!cardCvv || cardCvv.length < 3) {
        AmbiletNotifications.error('CVV invalid');
        return;
    }

    try {
        await AmbiletAPI.post('/customer/payment-methods', {
            card_number: cardNumber,
            card_name: cardName,
            expiry: cardExpiry,
            cvv: cardCvv,
            is_default: isDefault
        });
        closeModal();
        AmbiletNotifications.success('Card adaugat cu succes!');
        PaymentsPage.loadPaymentMethods();
    } catch (error) {
        // Demo mode - add locally
        const newCard = {
            id: Date.now(),
            type: cardNumber.startsWith('4') ? 'visa' : 'mastercard',
            last4: cardNumber.slice(-4),
            expiry: cardExpiry,
            is_default: isDefault
        };
        if (isDefault) {
            PaymentsPage.cards.forEach(c => c.is_default = false);
        }
        PaymentsPage.cards.push(newCard);
        PaymentsPage.renderCards();
        closeModal();
        AmbiletNotifications.success('Card adaugat cu succes!');
    }
}

function editAddress() {
    window.location.href = '/cont/profil';
}

function logout() {
    AmbiletAuth.logout();
    window.location.href = '/';
}

// Close modal on backdrop click
document.getElementById('cardModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Initialize page
document.addEventListener('DOMContentLoaded', () => PaymentsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
