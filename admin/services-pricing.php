<?php
/**
 * Admin - Services Pricing Configuration
 * Configure pricing for organizer extra services
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Configurare Preturi Servicii';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'admin-services';
require_once dirname(__DIR__) . '/includes/head.php';
?>

<div class="flex-1 flex flex-col min-h-screen">
    <!-- Admin Header -->
    <header class="bg-white border-b border-border">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/admin" class="text-muted hover:text-secondary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-secondary">Configurare Preturi Servicii</h1>
                    <p class="text-sm text-muted">Admin Marketplace - Servicii Extra Organizatori</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted" id="last-saved"></span>
                <button onclick="savePricing()" class="btn btn-primary" id="save-btn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Salveaza Modificarile
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 p-4 lg:p-8">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Success Banner -->
            <div id="success-banner" class="hidden bg-success/10 border border-success/30 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-success rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-success">Preturi actualizate!</p>
                        <p class="text-sm text-success/80">Noile preturi sunt active imediat.</p>
                    </div>
                </div>
            </div>

            <!-- Email Marketing Pricing -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border bg-gradient-to-r from-accent/10 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-accent to-orange-600 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Email Marketing</h2>
                            <p class="text-sm text-muted">Preturi per email trimis</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Pret per email - Clientii Proprii (RON)</label>
                            <input type="number" id="email-own-price" class="input w-full" step="0.01" min="0" placeholder="0.40">
                            <p class="text-xs text-muted mt-1">Organizatorul trimite catre clientii sai anteriori</p>
                        </div>
                        <div>
                            <label class="label">Pret per email - Baza Marketplace (RON)</label>
                            <input type="number" id="email-marketplace-price" class="input w-full" step="0.01" min="0" placeholder="0.50">
                            <p class="text-xs text-muted mt-1">Organizatorul trimite catre toti utilizatorii platformei</p>
                        </div>
                    </div>
                    <div>
                        <label class="label">Numar minim de emailuri per campanie</label>
                        <input type="number" id="email-minimum" class="input w-full max-w-xs" min="1" placeholder="100">
                    </div>
                </div>
            </div>

            <!-- Event Featuring Pricing -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border bg-gradient-to-r from-primary/10 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Promovare Eveniment</h2>
                            <p class="text-sm text-muted">Preturi per zi de afisare</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Pagina Principala (RON / zi)</label>
                            <input type="number" id="featuring-home" class="input w-full" min="0" placeholder="99">
                            <p class="text-xs text-muted mt-1">Afisare in sectiunea Featured pe homepage</p>
                        </div>
                        <div>
                            <label class="label">Pagina Categorie (RON / zi)</label>
                            <input type="number" id="featuring-category" class="input w-full" min="0" placeholder="69">
                            <p class="text-xs text-muted mt-1">Afisare in top pe paginile de categorie</p>
                        </div>
                        <div>
                            <label class="label">Pagina Gen Muzical (RON / zi)</label>
                            <input type="number" id="featuring-genre" class="input w-full" min="0" placeholder="59">
                            <p class="text-xs text-muted mt-1">Afisare in top pe paginile de gen</p>
                        </div>
                        <div>
                            <label class="label">Pagina Oras (RON / zi)</label>
                            <input type="number" id="featuring-city" class="input w-full" min="0" placeholder="49">
                            <p class="text-xs text-muted mt-1">Afisare in top pe paginile de oras</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ad Tracking Pricing -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border bg-gradient-to-r from-blue-100 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Tracking Campanii Ads</h2>
                            <p class="text-sm text-muted">Preturi per platforma per luna</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="label">Pret per platforma / luna (RON)</label>
                        <input type="number" id="tracking-monthly" class="input w-full max-w-xs" min="0" placeholder="49">
                        <p class="text-xs text-muted mt-1">Facebook Pixel, Google Ads, TikTok Pixel</p>
                    </div>
                    <div>
                        <p class="label mb-3">Discounturi pentru abonamente lungi</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="label text-xs">3 luni (%)</label>
                                <input type="number" id="tracking-discount-3" class="input w-full" min="0" max="100" placeholder="10">
                            </div>
                            <div>
                                <label class="label text-xs">6 luni (%)</label>
                                <input type="number" id="tracking-discount-6" class="input w-full" min="0" max="100" placeholder="15">
                            </div>
                            <div>
                                <label class="label text-xs">12 luni (%)</label>
                                <input type="number" id="tracking-discount-12" class="input w-full" min="0" max="100" placeholder="25">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Creation Pricing -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border bg-gradient-to-r from-purple-100 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Creare Campanii Ads</h2>
                            <p class="text-sm text-muted">Preturi per tip de pachet</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="label">Pachet Basic (RON)</label>
                            <input type="number" id="campaign-basic" class="input w-full" min="0" placeholder="499">
                            <p class="text-xs text-muted mt-1">1 platforma, design inclus</p>
                        </div>
                        <div>
                            <label class="label">Pachet Standard (RON)</label>
                            <input type="number" id="campaign-standard" class="input w-full" min="0" placeholder="899">
                            <p class="text-xs text-muted mt-1">2 platforme, A/B testing</p>
                        </div>
                        <div>
                            <label class="label">Pachet Premium (RON)</label>
                            <input type="number" id="campaign-premium" class="input w-full" min="0" placeholder="1499">
                            <p class="text-xs text-muted mt-1">Toate platformele, video, manager dedicat</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex justify-end gap-3 pt-4">
                <button onclick="resetToDefaults()" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reseteaza la Valori Implicite
                </button>
                <button onclick="savePricing()" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Salveaza Modificarile
                </button>
            </div>
        </div>
    </main>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
// Default pricing values
const defaultPricing = {
    email: {
        own_per_email: 0.40,
        marketplace_per_email: 0.50,
        minimum: 100
    },
    featuring: {
        home: 99,
        category: 69,
        genre: 59,
        city: 49
    },
    tracking: {
        per_platform_monthly: 49,
        discounts: { 3: 10, 6: 15, 12: 25 }
    },
    campaign: {
        basic: 499,
        standard: 899,
        premium: 1499
    }
};

document.addEventListener('DOMContentLoaded', function() {
    loadCurrentPricing();
});

async function loadCurrentPricing() {
    try {
        const response = await AmbiletAPI.get('/admin/services/pricing');
        if (response.success && response.data) {
            populateForm(response.data);
        } else {
            populateForm(defaultPricing);
        }
    } catch (e) {
        console.log('Using default pricing');
        populateForm(defaultPricing);
    }
}

function populateForm(pricing) {
    // Email Marketing
    document.getElementById('email-own-price').value = pricing.email?.own_per_email || defaultPricing.email.own_per_email;
    document.getElementById('email-marketplace-price').value = pricing.email?.marketplace_per_email || defaultPricing.email.marketplace_per_email;
    document.getElementById('email-minimum').value = pricing.email?.minimum || defaultPricing.email.minimum;

    // Featuring
    document.getElementById('featuring-home').value = pricing.featuring?.home || defaultPricing.featuring.home;
    document.getElementById('featuring-category').value = pricing.featuring?.category || defaultPricing.featuring.category;
    document.getElementById('featuring-genre').value = pricing.featuring?.genre || defaultPricing.featuring.genre;
    document.getElementById('featuring-city').value = pricing.featuring?.city || defaultPricing.featuring.city;

    // Tracking
    document.getElementById('tracking-monthly').value = pricing.tracking?.per_platform_monthly || defaultPricing.tracking.per_platform_monthly;
    document.getElementById('tracking-discount-3').value = pricing.tracking?.discounts?.[3] || defaultPricing.tracking.discounts[3];
    document.getElementById('tracking-discount-6').value = pricing.tracking?.discounts?.[6] || defaultPricing.tracking.discounts[6];
    document.getElementById('tracking-discount-12').value = pricing.tracking?.discounts?.[12] || defaultPricing.tracking.discounts[12];

    // Campaign
    document.getElementById('campaign-basic').value = pricing.campaign?.basic || defaultPricing.campaign.basic;
    document.getElementById('campaign-standard').value = pricing.campaign?.standard || defaultPricing.campaign.standard;
    document.getElementById('campaign-premium').value = pricing.campaign?.premium || defaultPricing.campaign.premium;
}

function collectFormData() {
    return {
        email: {
            own_per_email: parseFloat(document.getElementById('email-own-price').value) || defaultPricing.email.own_per_email,
            marketplace_per_email: parseFloat(document.getElementById('email-marketplace-price').value) || defaultPricing.email.marketplace_per_email,
            minimum: parseInt(document.getElementById('email-minimum').value) || defaultPricing.email.minimum
        },
        featuring: {
            home: parseInt(document.getElementById('featuring-home').value) || defaultPricing.featuring.home,
            category: parseInt(document.getElementById('featuring-category').value) || defaultPricing.featuring.category,
            genre: parseInt(document.getElementById('featuring-genre').value) || defaultPricing.featuring.genre,
            city: parseInt(document.getElementById('featuring-city').value) || defaultPricing.featuring.city
        },
        tracking: {
            per_platform_monthly: parseInt(document.getElementById('tracking-monthly').value) || defaultPricing.tracking.per_platform_monthly,
            discounts: {
                1: 0,
                3: parseFloat(document.getElementById('tracking-discount-3').value) / 100 || 0.10,
                6: parseFloat(document.getElementById('tracking-discount-6').value) / 100 || 0.15,
                12: parseFloat(document.getElementById('tracking-discount-12').value) / 100 || 0.25
            }
        },
        campaign: {
            basic: parseInt(document.getElementById('campaign-basic').value) || defaultPricing.campaign.basic,
            standard: parseInt(document.getElementById('campaign-standard').value) || defaultPricing.campaign.standard,
            premium: parseInt(document.getElementById('campaign-premium').value) || defaultPricing.campaign.premium
        }
    };
}

async function savePricing() {
    const saveBtn = document.getElementById('save-btn');
    const originalText = saveBtn.innerHTML;

    saveBtn.disabled = true;
    saveBtn.innerHTML = `
        <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Se salveaza...
    `;

    const data = collectFormData();

    try {
        const response = await AmbiletAPI.post('/admin/services/pricing', data);
        if (response.success) {
            document.getElementById('success-banner').classList.remove('hidden');
            document.getElementById('last-saved').textContent = 'Salvat la ' + new Date().toLocaleTimeString('ro-RO');
            setTimeout(() => {
                document.getElementById('success-banner').classList.add('hidden');
            }, 5000);
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la salvare');
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la salvarea preturilor');
    }

    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function resetToDefaults() {
    if (confirm('Esti sigur ca vrei sa resetezi toate preturile la valorile implicite?')) {
        populateForm(defaultPricing);
        AmbiletNotifications.info('Preturile au fost resetate. Nu uita sa salvezi!');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
