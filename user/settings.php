<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Setari';
$currentPage = 'settings';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Container with Sidebar -->
<div class="px-4 py-6 mx-auto max-w-7xl lg:py-8">
    <div class="flex flex-col gap-6 lg:flex-row">
        <!-- Sidebar -->
        <?php require_once dirname(__DIR__) . '/includes/user-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 lg:pt-24">
        <h1 class="mb-6 text-2xl font-bold text-secondary">Setari</h1>

        <div class="space-y-6">
            <!-- Notifications -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-lg font-bold text-secondary">Notificari email</h2>

                <div class="space-y-4">
                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Confirmare comenzi</p>
                            <p class="text-sm text-muted">Primeste email cu detaliile comenzii si biletele</p>
                        </div>
                        <input type="checkbox" class="toggle" id="notif-orders" checked disabled>
                    </label>

                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Remindere evenimente</p>
                            <p class="text-sm text-muted">Primeste reminder cu 24h inainte de eveniment</p>
                        </div>
                        <input type="checkbox" class="toggle" id="notif-reminders" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Newsletter si oferte</p>
                            <p class="text-sm text-muted">Afla despre evenimente noi si oferte speciale</p>
                        </div>
                        <input type="checkbox" class="toggle" id="notif-newsletter">
                    </label>

                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Actualizari favorite</p>
                            <p class="text-sm text-muted">Primeste notificari cand evenimentele favorite se apropie</p>
                        </div>
                        <input type="checkbox" class="toggle" id="notif-favorites" checked>
                    </label>
                </div>
            </div>

            <!-- Language & Region -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-lg font-bold text-secondary">Limba si regiune</h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="label">Limba</label>
                        <select class="input" id="language">
                            <option value="ro" selected>Romana</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Moneda</label>
                        <select class="input" id="currency" disabled>
                            <option value="RON" selected>RON (Lei)</option>
                            <option value="EUR">EUR (Euro)</option>
                        </select>
                        <p class="mt-1 text-xs text-muted">Moneda este determinata automat</p>
                    </div>
                </div>
            </div>

            <!-- Privacy -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-lg font-bold text-secondary">Confidentialitate</h2>

                <div class="space-y-4">
                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Istoric de navigare</p>
                            <p class="text-sm text-muted">Salveaza evenimentele vizualizate pentru recomandari personalizate</p>
                        </div>
                        <input type="checkbox" class="toggle" id="privacy-history" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                        <div>
                            <p class="font-medium text-secondary">Cookies de marketing</p>
                            <p class="text-sm text-muted">Permite afisarea de reclame personalizate</p>
                        </div>
                        <input type="checkbox" class="toggle" id="privacy-marketing">
                    </label>
                </div>

                <div class="pt-4 mt-4 border-t border-border">
                    <button onclick="downloadData()" class="text-sm text-primary hover:underline">
                        Descarca datele mele personale
                    </button>
                </div>
            </div>

            <!-- Sessions -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-lg font-bold text-secondary">Sesiuni active</h2>

                <div class="space-y-3" id="sessions-list">
                    <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-secondary">Sesiunea curenta</p>
                                <p class="text-sm text-muted">Acest dispozitiv</p>
                            </div>
                        </div>
                        <span class="badge badge-success">Activ</span>
                    </div>
                </div>

                <button onclick="logoutAllDevices()" class="mt-4 text-sm text-error hover:underline">
                    Deconecteaza-te de pe toate dispozitivele
                </button>
            </div>

            <!-- Payment Methods Link -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Metode de plata</h2>
                        <p class="mt-1 text-sm text-muted">Gestioneaza cardurile salvate si metodele de plata</p>
                    </div>
                    <a href="/cont/plati" class="btn btn-primary">
                        Gestioneaza
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button onclick="saveSettings()" class="btn btn-primary">
                    Salveaza setarile
                </button>
            </div>
        </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const SettingsPage = {
    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/setari';
            return;
        }

        this.loadUserInfo();
        this.loadSettings();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = user.points || '0';
            }
        }
    },

    loadSettings() {
        // Load saved settings from localStorage
        const settings = JSON.parse(localStorage.getItem('ambilet_settings') || '{}');

        if (settings.reminders !== undefined) {
            document.getElementById('notif-reminders').checked = settings.reminders;
        }
        if (settings.newsletter !== undefined) {
            document.getElementById('notif-newsletter').checked = settings.newsletter;
        }
        if (settings.favorites !== undefined) {
            document.getElementById('notif-favorites').checked = settings.favorites;
        }
        if (settings.history !== undefined) {
            document.getElementById('privacy-history').checked = settings.history;
        }
        if (settings.marketing !== undefined) {
            document.getElementById('privacy-marketing').checked = settings.marketing;
        }
        if (settings.language) {
            document.getElementById('language').value = settings.language;
        }
    }
};

function saveSettings() {
    const settings = {
        reminders: document.getElementById('notif-reminders').checked,
        newsletter: document.getElementById('notif-newsletter').checked,
        favorites: document.getElementById('notif-favorites').checked,
        history: document.getElementById('privacy-history').checked,
        marketing: document.getElementById('privacy-marketing').checked,
        language: document.getElementById('language').value
    };

    localStorage.setItem('ambilet_settings', JSON.stringify(settings));

    // Try to save to API
    AmbiletAPI.put('/customer/settings', settings).catch(() => {});

    AmbiletNotifications.success('Setarile au fost salvate!');
}

function downloadData() {
    AmbiletNotifications.info('Functie in dezvoltare. Contacteaza suportul pentru a solicita datele tale.');
}

function logoutAllDevices() {
    if (confirm('Esti sigur ca vrei sa te deconectezi de pe toate dispozitivele?')) {
        AmbiletAuth.logout();
        window.location.href = '/';
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', () => SettingsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
