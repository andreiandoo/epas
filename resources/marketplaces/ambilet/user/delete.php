<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Sterge contul';
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
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6 text-sm">
                <a href="/cont" class="text-muted hover:text-primary">Contul meu</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/cont/setari" class="text-muted hover:text-primary">Setari</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-medium text-secondary">Sterge contul</span>
            </nav>

            <!-- Delete Card -->
            <div class="max-w-xl mx-auto bg-white border rounded-2xl border-border overflow-hidden">
                <!-- Header -->
                <div class="p-6 text-center text-white bg-gradient-to-r from-red-600 to-red-700">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-white/20">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <h1 class="mb-1 text-xl font-bold">Sterge contul definitiv</h1>
                    <p class="text-sm opacity-90">Aceasta actiune este ireversibila</p>
                </div>

                <!-- Body -->
                <div class="p-6">
                    <!-- Warning Box -->
                    <div class="p-4 mb-6 border rounded-xl bg-red-50 border-red-200">
                        <h3 class="flex items-center gap-2 mb-3 text-sm font-semibold text-red-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Vei pierde permanent
                        </h3>
                        <ul class="space-y-2 text-sm text-red-700">
                            <li class="flex items-center gap-2">
                                <span class="font-bold text-red-600">✕</span>
                                Toate biletele si istoricul comenzilor
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="font-bold text-red-600">✕</span>
                                Creditul si recompensele acumulate
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="font-bold text-red-600">✕</span>
                                Recenziile si preferintele salvate
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="font-bold text-red-600">✕</span>
                                Accesul la evenimente viitoare achizitionate
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="font-bold text-red-600">✕</span>
                                Link-urile de referral si bonusurile asociate
                            </li>
                        </ul>
                    </div>

                    <!-- Account Summary -->
                    <div class="p-4 mb-6 rounded-xl bg-surface">
                        <h3 class="mb-3 text-xs font-semibold tracking-wider uppercase text-muted">Ce vei pierde</h3>
                        <div class="grid grid-cols-2 gap-3" id="delete-summary">
                            <div class="p-3 text-center bg-white rounded-lg">
                                <div class="text-xl font-bold text-secondary" id="summary-tickets">--</div>
                                <div class="text-xs text-muted">Bilete achizitionate</div>
                            </div>
                            <div class="p-3 text-center bg-white rounded-lg">
                                <div class="text-xl font-bold text-secondary" id="summary-credit">-- RON</div>
                                <div class="text-xs text-muted">Credit disponibil</div>
                            </div>
                            <div class="p-3 text-center bg-white rounded-lg">
                                <div class="text-xl font-bold text-secondary" id="summary-reviews">--</div>
                                <div class="text-xs text-muted">Recenzii scrise</div>
                            </div>
                            <div class="p-3 text-center bg-white rounded-lg">
                                <div class="text-xl font-bold text-secondary" id="summary-active">--</div>
                                <div class="text-xs text-muted">Bilete active</div>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="deleteAccountForm">
                        <div class="mb-4">
                            <label class="label">Introdu parola pentru confirmare</label>
                            <input type="password" class="input" id="delete-password" placeholder="Parola curenta" required>
                            <p class="mt-1 text-xs text-muted">Pentru securitate, confirma identitatea ta</p>
                        </div>

                        <div class="mb-4">
                            <label class="label">De ce pleci? (optional)</label>
                            <select class="input" id="delete-reason">
                                <option value="">Selecteaza un motiv</option>
                                <option value="not-using">Nu mai folosesc serviciul</option>
                                <option value="privacy">Motive de confidentialitate</option>
                                <option value="alternative">Am gasit o alternativa</option>
                                <option value="experience">Experienta nesatisfacatoare</option>
                                <option value="other">Alt motiv</option>
                            </select>
                        </div>

                        <div class="space-y-3 mb-6">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" class="mt-1 checkbox" id="confirm1" required>
                                <span class="text-sm text-muted">Inteleg ca toate datele mele vor fi sterse permanent si nu pot fi recuperate</span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" class="mt-1 checkbox" id="confirm2" required>
                                <span class="text-sm text-muted">Confirm ca vreau sa sterg definitiv contul meu AmBilet</span>
                            </label>
                        </div>

                        <div class="flex gap-3">
                            <a href="/cont/setari" class="flex-1 text-center btn btn-secondary">Anuleaza</a>
                            <button type="submit" class="flex-1 text-white bg-red-600 btn hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed" id="deleteBtn" disabled>
                                Sterge contul
                            </button>
                        </div>
                    </form>

                    <!-- Alternative -->
                    <div class="pt-4 mt-6 text-center border-t border-border">
                        <p class="mb-2 text-sm text-muted">Nu esti sigur? Poti dezactiva temporar contul in schimb.</p>
                        <a href="/cont/setari" class="text-sm font-semibold text-primary hover:underline">Dezactiveaza contul temporar &rarr;</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const DeleteAccountPage = {
    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/sterge-cont';
            return;
        }

        this.loadUserInfo();
        this.setupFormValidation();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            document.getElementById('summary-tickets').textContent = user.total_tickets || '0';
            document.getElementById('summary-credit').textContent = (user.credit || 0) + ' RON';
            document.getElementById('summary-reviews').textContent = user.reviews_count || '0';
            document.getElementById('summary-active').textContent = user.active_tickets || '0';
        }
    },

    setupFormValidation() {
        const form = document.getElementById('deleteAccountForm');
        const checkboxes = document.querySelectorAll('#confirm1, #confirm2');
        const deleteBtn = document.getElementById('deleteBtn');

        const updateButton = () => {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            deleteBtn.disabled = !allChecked;
        };

        checkboxes.forEach(cb => cb.addEventListener('change', updateButton));

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleDelete();
        });
    },

    async handleDelete() {
        const password = document.getElementById('delete-password').value;
        const reason = document.getElementById('delete-reason').value;

        if (!password) {
            AmbiletNotifications.error('Te rugam sa introduci parola.');
            return;
        }

        if (!confirm('Esti absolut sigur? Aceasta actiune nu poate fi anulata.')) {
            return;
        }

        try {
            const response = await AmbiletAPI.delete('/customer/account', { password, reason });
            if (response.success) {
                AmbiletNotifications.success('Contul a fost sters. Ne pare rau sa te vedem plecand.');
                AmbiletAuth.logout();
                setTimeout(() => window.location.href = '/', 2000);
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la stergerea contului.');
            }
        } catch (error) {
            AmbiletNotifications.error('Parola este incorecta sau a aparut o eroare.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => DeleteAccountPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
