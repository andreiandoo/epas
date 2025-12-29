<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Ajutor & Suport';
$currentPage = 'help';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 py-6 lg:py-8">
        <!-- Search Header -->
        <div class="bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl p-6 lg:p-10 mb-8 text-white text-center relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="relative">
                <h1 class="text-2xl lg:text-3xl font-bold mb-2">Cum te putem ajuta?</h1>
                <p class="text-white/70 mb-6">Cauta raspunsuri sau contacteaza echipa de suport</p>
                <div class="max-w-lg mx-auto relative">
                    <input type="text" id="search-input" placeholder="Cauta un subiect..." class="w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 focus:outline-none focus:bg-white/20 focus:border-white/40">
                    <svg class="w-5 h-5 text-white/50 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>

        <!-- Quick Help Cards -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="#bilete" class="help-card bg-white rounded-xl border border-border p-5 text-center hover:border-primary">
                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <h3 class="font-semibold text-secondary mb-1">Bilete</h3>
                <p class="text-xs text-muted">Descarca, transfera, anuleaza</p>
            </a>
            <a href="#plati" class="help-card bg-white rounded-xl border border-border p-5 text-center hover:border-primary">
                <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <h3 class="font-semibold text-secondary mb-1">Plati</h3>
                <p class="text-xs text-muted">Facturi, rambursari, erori</p>
            </a>
            <a href="#cont" class="help-card bg-white rounded-xl border border-border p-5 text-center hover:border-primary">
                <div class="w-12 h-12 bg-accent/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="font-semibold text-secondary mb-1">Cont</h3>
                <p class="text-xs text-muted">Profil, securitate, date</p>
            </a>
            <a href="#puncte" class="help-card bg-white rounded-xl border border-border p-5 text-center hover:border-primary">
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="font-semibold text-secondary mb-1">Puncte</h3>
                <p class="text-xs text-muted">Recompense, badge-uri</p>
            </a>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-5 lg:p-6 mb-8">
            <h2 class="text-lg font-bold text-secondary mb-6">Intrebari frecvente</h2>

            <div class="space-y-3">
                <!-- FAQ 1 -->
                <div class="faq-item active border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Cum imi descarc biletele?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">Poti descarca biletele din sectiunea "Biletele mele" din contul tau. Fiecare bilet poate fi descarcat in format PDF sau Apple Wallet. De asemenea, iti trimitem biletele si pe email dupa achizitie.</p>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="faq-item border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Pot solicita o rambursare?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">Politica de rambursare variaza in functie de eveniment si organizator. In general, rambursarile sunt posibile cu cel putin 7 zile inainte de eveniment. Daca evenimentul este anulat, vei primi automat rambursarea completa.</p>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="faq-item border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Cum functioneaza punctele?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">Castigi puncte pentru fiecare achizitie (2 puncte / leu), check-in la evenimente (+50 puncte), si pentru badge-uri obtinute. Punctele pot fi folosite pentru reduceri: 100 puncte = 1 leu reducere.</p>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="faq-item border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Pot transfera un bilet altcuiva?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">Da, poti transfera biletele unei alte persoane din sectiunea "Biletele mele". Selecteaza biletul si apasa "Transfera". Persoana va primi biletul pe email si il va putea accesa din contul sau <?= SITE_NAME ?>.</p>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="faq-item border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Ce fac daca am uitat parola?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">Pe pagina de autentificare, apasa "Am uitat parola" si introdu adresa de email. Vei primi un link pentru resetarea parolei. Link-ul este valid 24 de ore.</p>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="faq-item border border-border rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-secondary">Cum functioneaza check-in-ul la eveniment?</span>
                        <svg class="faq-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-answer px-4 pb-4">
                        <p class="text-sm text-muted">La intrarea in locatie, prezinta codul QR de pe bilet (din aplicatie sau PDF). Staff-ul va scana codul pentru a valida biletul. Asigura-te ca ai telefonul incarcat sau biletul printat.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="grid md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-border p-5 text-center">
                <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h3 class="font-bold text-secondary mb-1">Chat live</h3>
                <p class="text-sm text-muted mb-3">Raspuns in sub 5 minute</p>
                <button onclick="openChat()" class="w-full btn btn-primary py-2.5 text-white font-semibold rounded-xl text-sm">
                    Incepe conversatia
                </button>
            </div>

            <div class="bg-white rounded-xl border border-border p-5 text-center">
                <div class="w-14 h-14 bg-success/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="font-bold text-secondary mb-1">Email</h3>
                <p class="text-sm text-muted mb-3"><?= defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'suport@ambilet.ro' ?></p>
                <a href="mailto:<?= defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'suport@ambilet.ro' ?>" class="block w-full py-2.5 bg-surface text-secondary font-semibold rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                    Trimite email
                </a>
            </div>

            <div class="bg-white rounded-xl border border-border p-5 text-center">
                <div class="w-14 h-14 bg-accent/10 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <h3 class="font-bold text-secondary mb-1">Telefon</h3>
                <p class="text-sm text-muted mb-3"><?= defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '+40 21 234 5678' ?></p>
                <a href="tel:<?= defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '+40212345678' ?>" class="block w-full py-2.5 bg-surface text-secondary font-semibold rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                    Suna acum
                </a>
            </div>
        </div>

        <!-- Help Articles -->
        <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-5 lg:p-6">
            <h2 class="text-lg font-bold text-secondary mb-4">Articole populare</h2>

            <div class="grid sm:grid-cols-2 gap-4">
                <a href="#" class="flex items-center gap-3 p-4 bg-surface rounded-xl hover:bg-primary/5 transition-colors">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary text-sm">Cum cumpar bilete</p>
                        <p class="text-xs text-muted">Ghid pas cu pas</p>
                    </div>
                </a>

                <a href="#" class="flex items-center gap-3 p-4 bg-surface rounded-xl hover:bg-primary/5 transition-colors">
                    <div class="w-10 h-10 bg-success/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary text-sm">Politica de rambursare</p>
                        <p class="text-xs text-muted">Cand si cum</p>
                    </div>
                </a>

                <a href="#" class="flex items-center gap-3 p-4 bg-surface rounded-xl hover:bg-primary/5 transition-colors">
                    <div class="w-10 h-10 bg-accent/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary text-sm">Securitatea contului</p>
                        <p class="text-xs text-muted">2FA si parole</p>
                    </div>
                </a>

                <a href="#" class="flex items-center gap-3 p-4 bg-surface rounded-xl hover:bg-primary/5 transition-colors">
                    <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary text-sm">Sistemul de puncte</p>
                        <p class="text-xs text-muted">Cum functioneaza</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Support Hours -->
        <div class="mt-8 text-center">
            <p class="text-sm text-muted">Program suport: Luni - Vineri 09:00 - 20:00 - Sambata 10:00 - 16:00</p>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    item.classList.toggle('active');
}

function openChat() {
    alert('Chat-ul live va fi disponibil in curand!');
}

document.addEventListener('DOMContentLoaded', () => {
    // Load user info if authenticated
    if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isAuthenticated()) {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = (user.points || 0).toLocaleString();
            }
        }
    }

    // Search functionality
    document.getElementById('search-input').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    });
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
