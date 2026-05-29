<?php
/**
 * bilete.online — /cont/setari (Setări cont, v2 design)
 *
 * Three blocks: profile + contact (PUT /customer/profile), password
 * change (PUT /customer/password), notification + preference toggles
 * (PUT /customer/settings), plus a danger zone with account deletion.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Setări cont — ' . SITE_NAME;
$pageDescription = 'Gestionează datele tale de contact, parola, preferințele de notificare și opțiunile contului bilete.online.';
$canonicalUrl    = SITE_URL . '/cont/setari';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'settings'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientSettingsPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">SETĂRI CONT</p>
                <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Setări</h1>
                <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Datele tale de contact, parola, preferințele de notificare și controlul contului.</p>
            </section>

            <!-- TABS -->
            <nav class="mt-6 flex flex-wrap gap-2">
                <button @click="activeTab='profile'" :class="activeTab==='profile' ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-full px-5 py-2 font-bold border border-ink/10">Profil</button>
                <button @click="activeTab='password'" :class="activeTab==='password' ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-full px-5 py-2 font-bold border border-ink/10">Parolă</button>
                <button @click="activeTab='preferences'" :class="activeTab==='preferences' ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-full px-5 py-2 font-bold border border-ink/10">Preferințe</button>
                <button @click="activeTab='danger'" :class="activeTab==='danger' ? 'bg-vermilion text-paper' : 'bg-rose text-vermilion'" class="rounded-full px-5 py-2 font-bold border border-vermilion/30">Zona periculoasă</button>
            </nav>

            <!-- Save banner -->
            <div x-show="message" x-cloak class="mt-5 rounded-2xl border-2 px-4 py-3 text-sm font-bold" :class="messageType === 'error' ? 'border-vermilion bg-vermilion/10 text-vermilion' : 'border-forest bg-mint text-forest'" x-text="message"></div>

            <!-- PROFILE -->
            <section x-show="activeTab === 'profile'" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                <h2 class="font-display text-3xl font-bold leading-none">Date personale</h2>
                <p class="mt-2 text-ink-soft text-sm">Aceste informații apar pe bilete și pe email-ul de confirmare.</p>

                <form @submit.prevent="saveProfile()" class="mt-6 grid sm:grid-cols-2 gap-4">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Prenume</span>
                        <input class="field" x-model="profile.first_name" autocomplete="given-name">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Nume</span>
                        <input class="field" x-model="profile.last_name" autocomplete="family-name">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="block mb-1.5 text-sm font-bold">Email</span>
                        <input class="field" type="email" x-model="profile.email" autocomplete="email">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="block mb-1.5 text-sm font-bold">Telefon</span>
                        <input class="field" type="tel" x-model="profile.phone" autocomplete="tel" placeholder="0722 123 456">
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="saving" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!saving">Salvează modificările</span>
                            <span x-show="saving" x-cloak>Se salvează…</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- PASSWORD -->
            <section x-show="activeTab === 'password'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                <h2 class="font-display text-3xl font-bold leading-none">Schimbă parola</h2>
                <p class="mt-2 text-ink-soft text-sm">Vei rămâne autentificat pe acest dispozitiv după schimbare.</p>

                <form @submit.prevent="savePassword()" class="mt-6 grid gap-4 max-w-lg">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Parolă curentă</span>
                        <input class="field" type="password" x-model="pw.current_password" autocomplete="current-password" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Parolă nouă</span>
                        <input class="field" type="password" x-model="pw.password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Confirmă parola nouă</span>
                        <input class="field" type="password" x-model="pw.password_confirmation" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button type="submit" :disabled="saving" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                        <span x-show="!saving">Schimbă parola</span>
                        <span x-show="saving" x-cloak>Se salvează…</span>
                    </button>
                </form>
            </section>

            <!-- PREFERENCES -->
            <section x-show="activeTab === 'preferences'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                <h2 class="font-display text-3xl font-bold leading-none">Notificări & preferințe</h2>
                <p class="mt-2 text-ink-soft text-sm">Alege ce vrei să primești și cum.</p>

                <div class="mt-6 space-y-3">
                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-paper-2 border border-ink/10 cursor-pointer">
                        <input type="checkbox" x-model="prefs.email_newsletter" class="mt-1 w-5 h-5 accent-vermilion">
                        <div>
                            <p class="font-bold">Newsletter</p>
                            <p class="text-sm text-ink-soft">Recomandări, oferte și activități noi în orașul tău.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-paper-2 border border-ink/10 cursor-pointer">
                        <input type="checkbox" x-model="prefs.email_reminders" class="mt-1 w-5 h-5 accent-vermilion">
                        <div>
                            <p class="font-bold">Reminder evenimente</p>
                            <p class="text-sm text-ink-soft">Primește un email cu 24h înainte de fiecare bilet activ.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-paper-2 border border-ink/10 cursor-pointer">
                        <input type="checkbox" x-model="prefs.email_recommendations" class="mt-1 w-5 h-5 accent-vermilion">
                        <div>
                            <p class="font-bold">Recomandări personalizate</p>
                            <p class="text-sm text-ink-soft">Sugestii bazate pe orașul tău și activitățile vizualizate.</p>
                        </div>
                    </label>
                </div>

                <button @click="savePreferences()" :disabled="saving" class="mt-6 rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                    <span x-show="!saving">Salvează preferințele</span>
                    <span x-show="saving" x-cloak>Se salvează…</span>
                </button>
            </section>

            <!-- DANGER ZONE -->
            <section x-show="activeTab === 'danger'" x-cloak class="mt-6 rounded-[2rem] border-2 border-vermilion bg-rose p-6 sm:p-8 shadow-ticket">
                <h2 class="font-display text-3xl font-bold leading-none text-vermilion">Zona periculoasă</h2>
                <p class="mt-2 text-ink-soft text-sm">Acțiunile de aici nu pot fi anulate. Citește cu atenție înainte să continui.</p>

                <div class="mt-6 rounded-2xl border-2 border-ink bg-paper p-5">
                    <p class="font-bold">Șterge contul permanent</p>
                    <p class="mt-1 text-sm text-ink-soft">Datele personale vor fi anonimizate. Comenzile rămân în istoricul fiscal. Nu poți șterge contul dacă ai bilete viitoare.</p>
                    <form @submit.prevent="confirmDelete()" class="mt-4 grid gap-3 max-w-md">
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Parola curentă (pentru confirmare)</span>
                            <input class="field" type="password" x-model="del.password" required>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Motivul ștergerii (opțional)</span>
                            <textarea class="field min-h-24" x-model="del.reason"></textarea>
                        </label>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" x-model="del.confirmed" class="mt-1 w-5 h-5 accent-vermilion" required>
                            <span>Înțeleg că datele mele vor fi anonimizate și că această acțiune este definitivă.</span>
                        </label>
                        <button type="submit" :disabled="!del.confirmed || saving" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-40">
                            <span x-show="!saving">Șterge contul</span>
                            <span x-show="saving" x-cloak>Se șterge…</span>
                        </button>
                    </form>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/setari" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientSettingsPage() {
    return {
        loading: true,
        isAuth: true,
        saving: false,
        message: '',
        messageType: 'success',
        activeTab: 'profile',
        profile: { first_name: '', last_name: '', email: '', phone: '' },
        pw: { current_password: '', password: '', password_confirmation: '' },
        prefs: { email_newsletter: true, email_reminders: true, email_recommendations: true },
        del: { password: '', reason: '', confirmed: false },

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }
            this.load();

            // jump to specific tab via hash (#profil-preferinte etc.)
            const h = (location.hash || '').replace('#', '');
            if (h.includes('preferinte')) this.activeTab = 'preferences';
            else if (h.includes('parol')) this.activeTab = 'password';
            else if (h.includes('danger') || h.includes('sterg')) this.activeTab = 'danger';
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getProfile();
                const u = (r && r.data) || {};
                this.profile = {
                    first_name: u.first_name || '',
                    last_name:  u.last_name || '',
                    email:      u.email || '',
                    phone:      u.phone || '',
                };
                const s = u.settings || u.preferences || {};
                this.prefs = {
                    email_newsletter:      s.email_newsletter      ?? s.newsletter      ?? true,
                    email_reminders:       s.email_reminders       ?? true,
                    email_recommendations: s.email_recommendations ?? true,
                };
            } catch (e) {}
            this.loading = false;
        },

        flash(msg, type) { this.message = msg; this.messageType = type || 'success'; setTimeout(() => { this.message = ''; }, 4500); },

        async saveProfile() {
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.customer.updateProfile(this.profile);
                if (r && r.success) this.flash('Datele au fost salvate.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva datele.', 'error');
            } catch (e) { this.flash('Eroare la salvare.', 'error'); }
            this.saving = false;
        },

        async savePassword() {
            if (this.pw.password !== this.pw.password_confirmation) { this.flash('Parolele nu coincid.', 'error'); return; }
            if ((this.pw.password || '').length < 8) { this.flash('Parola nouă trebuie să aibă minim 8 caractere.', 'error'); return; }
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.put('/customer/password', {
                    current_password: this.pw.current_password,
                    password: this.pw.password,
                    password_confirmation: this.pw.password_confirmation,
                });
                if (r && r.success) {
                    this.flash('Parola a fost schimbată.', 'success');
                    this.pw = { current_password: '', password: '', password_confirmation: '' };
                } else {
                    this.flash((r && r.message) || 'Parola curentă nu este corectă.', 'error');
                }
            } catch (e) { this.flash('Eroare la schimbarea parolei.', 'error'); }
            this.saving = false;
        },

        async savePreferences() {
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.put('/customer/settings', this.prefs);
                if (r && r.success) this.flash('Preferințele au fost salvate.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva preferințele.', 'error');
            } catch (e) { this.flash('Eroare la salvare.', 'error'); }
            this.saving = false;
        },

        async confirmDelete() {
            if (! confirm('Ești sigur că vrei să-ți ștergi contul? Acțiunea NU poate fi anulată.')) return;
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.customer.deleteAccount(this.del.password, this.del.reason);
                if (r && r.success) {
                    this.flash('Contul a fost șters.', 'success');
                    setTimeout(() => {
                        try { BileteOnlineAuth.logoutCustomer && BileteOnlineAuth.logoutCustomer(); } catch (e) {}
                        location.href = '/';
                    }, 1200);
                } else {
                    this.flash((r && r.message) || 'Nu am putut șterge contul.', 'error');
                }
            } catch (e) { this.flash('Eroare la ștergere.', 'error'); }
            this.saving = false;
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
