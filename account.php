<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Contul meu — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[60vh]" x-data="accountPage()" x-init="init()">
    <div class="max-w-2xl mx-auto w-full">
        <!-- Loading -->
        <div x-show="loading" class="text-center py-16">
            <svg class="w-8 h-8 animate-spin text-gold mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>

        <!-- Not logged in -->
        <div x-show="!loading && !user" x-cloak class="text-center py-12">
            <h1 class="font-display text-4xl mb-4">Contul meu</h1>
            <p class="text-warm-gray mb-8">Autentifică-te pentru a vedea biletele și abonamentele tale.</p>
            <a href="/autentificare" class="btn-gold px-8 py-3 rounded-lg inline-block">Autentificare</a>
        </div>

        <!-- Logged in -->
        <div x-show="!loading && user" x-cloak>
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gold/15 border border-gold/30 flex items-center justify-center">
                        <span class="font-display text-gold text-xl" x-text="initials()"></span>
                    </div>
                    <div>
                        <h1 class="font-display text-2xl leading-tight" x-text="user?.name || 'Client'"></h1>
                        <p class="text-warm-gray text-sm" x-text="user?.email"></p>
                    </div>
                </div>
                <button @click="logout()" class="btn-outline px-4 py-2 rounded-lg text-sm">Ieși din cont</button>
            </div>

            <div class="grid sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-charcoal rounded-xl border border-gold/10 p-5">
                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-1">Email</p>
                    <p class="text-sm break-all" x-text="user?.email"></p>
                </div>
                <div class="bg-charcoal rounded-xl border border-gold/10 p-5">
                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-1">Telefon</p>
                    <p class="text-sm" x-text="user?.phone || '—'"></p>
                </div>
                <div class="bg-charcoal rounded-xl border border-gold/10 p-5">
                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-1">Status</p>
                    <p class="text-sm text-gold">Client activ</p>
                </div>
            </div>

            <div class="bg-charcoal rounded-2xl border border-gold/10 p-6">
                <h2 class="font-display text-xl mb-2">Biletele mele</h2>
                <p class="text-warm-gray text-sm">Biletele achiziționate îți sunt trimise pe email după fiecare comandă. Prezintă codul QR la intrare.</p>
                <a href="/repertoriu" class="btn-gold px-6 py-3 rounded-lg inline-block mt-5">Vezi spectacolele</a>
            </div>
        </div>
    </div>
</section>
<script>
function accountPage() {
    return {
        loading: true, user: null,
        auth() { try { return JSON.parse(localStorage.getItem('teatru_auth') || 'null'); } catch(e) { return null; } },
        initials() { const n = (this.user?.name || 'C').trim().split(/\s+/); return ((n[0]?.[0] || '') + (n[1]?.[0] || '')).toUpperCase() || 'C'; },
        async init() {
            const a = this.auth();
            if (!a || !a.token) { this.loading = false; return; }
            try {
                const r = await fetch('/api/proxy.php?action=me', { headers: { 'Authorization': 'Bearer ' + a.token } });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success && d.data) { this.user = d.data; }
                else { localStorage.removeItem('teatru_auth'); }
            } catch (e) {}
            this.loading = false;
        },
        async logout() {
            const a = this.auth();
            try { await fetch('/api/proxy.php?action=logout', { method: 'POST', headers: { 'Authorization': 'Bearer ' + (a?.token || '') } }); } catch (e) {}
            localStorage.removeItem('teatru_auth');
            window.location.href = '/';
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
