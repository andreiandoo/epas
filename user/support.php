<?php
/**
 * bilete.online — /cont/tichete-support (Tichete suport, v2 design)
 *
 * Customer-facing support inbox: list of tickets opened by the current
 * customer (GET /customer/support-tickets) + a create form
 * (POST /customer/support-tickets) and per-ticket thread view with reply
 * (GET /customer/support-tickets/{id}, POST .../messages).
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Tichete suport — ' . SITE_NAME;
$pageDescription = 'Trimite și urmărește solicitările tale către echipa bilete.online.';
$canonicalUrl    = SITE_URL . '/cont/tichete-support';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'support'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientSupportPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">SUPORT CLIENT</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Tichete suport</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            Trimite o solicitare către echipă și urmărește statusul fiecărui tichet într-un singur loc.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <button @click="openCreate=true" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Tichet nou</button>
                            <a href="/ajutor" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Vezi FAQ</a>
                        </div>
                    </div>
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">DESCHISE</p>
                        <p class="mt-2 font-display text-7xl font-bold leading-none" x-text="counts.open || 0">0</p>
                        <p class="mt-1 text-sm text-ink-soft">solicitări active</p>
                        <p class="mt-3 text-xs text-ink-soft" x-text="counts.total + ' tichete în total'"></p>
                    </div>
                </div>
            </section>

            <!-- LIST -->
            <section class="mt-6">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SOLICITĂRI</p>
                        <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="tickets.length + ' tichete'"></h2>
                    </div>
                    <button @click="openCreate=true" class="rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition hidden sm:inline-flex">+ Tichet nou</button>
                </div>

                <div x-show="loading" class="space-y-3">
                    <div class="h-24 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-24 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && tickets.length > 0" class="space-y-3">
                    <template x-for="t in tickets" :key="t.id">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                            <button @click="open(t)" class="w-full grid sm:grid-cols-[1fr_auto] gap-3 items-start text-left">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(t.status)" x-text="statusLabel(t.status)"></span>
                                        <span x-show="t.priority && t.priority !== 'normal'" class="rounded-full bg-rose text-vermilion px-3 py-1 text-xs font-bold" x-text="t.priority"></span>
                                        <span x-show="t.department && t.department.name" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="t.department && t.department.name"></span>
                                    </div>
                                    <h3 class="mt-3 font-display text-2xl font-bold leading-none" x-text="t.subject"></h3>
                                    <p class="mt-1 text-ink-soft text-sm font-mono" x-text="t.ticket_number + ' · ' + formatDate(t.last_activity)"></p>
                                </div>
                                <span class="text-vermilion font-bold text-sm">Deschide →</span>
                            </button>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && tickets.length === 0" class="rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">💬</p>
                    <p class="mt-4 font-display text-3xl font-bold">Niciun tichet deschis</p>
                    <p class="mt-2 text-ink-soft">Trimite o solicitare echipei dacă ai nevoie de ajutor.</p>
                    <button @click="openCreate=true" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Tichet nou</button>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="isAuth === false" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/tichete-support" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>

            <!-- CREATE MODAL -->
            <div x-show="openCreate" x-cloak class="fixed inset-0 z-[100] bg-ink/70 backdrop-blur-sm p-4 grid place-items-center" @click.self="openCreate=false">
                <div class="max-w-xl w-full rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-deep">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <h2 class="font-display text-3xl font-bold leading-none">Tichet nou</h2>
                        <button @click="openCreate=false" class="grid place-items-center w-10 h-10 rounded-full bg-ink text-paper font-bold">×</button>
                    </div>
                    <form @submit.prevent="submitCreate()" class="grid gap-4">
                        <label x-show="meta.departments && meta.departments.length">
                            <span class="block mb-1.5 text-sm font-bold">Departament</span>
                            <select class="field" x-model="form.support_department_id">
                                <option value="">— alege —</option>
                                <template x-for="d in meta.departments" :key="d.id">
                                    <option :value="d.id" x-text="d.name"></option>
                                </template>
                            </select>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Subiect</span>
                            <input class="field" x-model="form.subject" maxlength="200" required>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Mesaj</span>
                            <textarea class="field min-h-40" x-model="form.message" maxlength="5000" required></textarea>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-bold">Prioritate</span>
                            <select class="field" x-model="form.priority">
                                <option value="normal">Normală</option>
                                <option value="high">Ridicată</option>
                                <option value="urgent">Urgentă</option>
                                <option value="low">Scăzută</option>
                            </select>
                        </label>

                        <div x-show="error" x-cloak class="rounded-2xl border-2 border-vermilion bg-vermilion/10 px-4 py-3 text-sm font-bold text-vermilion" x-text="error"></div>
                        <button type="submit" :disabled="submitting" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!submitting">Trimite tichetul</span>
                            <span x-show="submitting" x-cloak>Se trimite…</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- DETAIL MODAL -->
            <div x-show="selected" x-cloak class="fixed inset-0 z-[100] bg-ink/70 backdrop-blur-sm p-4 grid place-items-center" @click.self="selected=null">
                <div class="max-w-2xl w-full rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-deep max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div class="min-w-0">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft" x-text="selected && selected.ticket_number"></p>
                            <h2 class="mt-2 font-display text-3xl font-bold leading-none" x-text="selected && selected.subject"></h2>
                            <span x-show="selected" class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="selected && statusClass(selected.status)" x-text="selected && statusLabel(selected.status)"></span>
                        </div>
                        <button @click="selected=null" class="grid place-items-center w-10 h-10 rounded-full bg-ink text-paper font-bold">×</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="m in messages" :key="m.id">
                            <div class="rounded-2xl p-4" :class="m.is_staff ? 'bg-mint border border-forest/20' : 'bg-paper-2 border border-ink/10'">
                                <p class="text-sm whitespace-pre-wrap" x-text="m.body"></p>
                                <p class="mt-2 text-xs text-ink-soft font-mono" x-text="(m.is_staff ? 'Echipa · ' : 'Tu · ') + formatDate(m.created_at)"></p>
                            </div>
                        </template>
                        <div x-show="!selected || (selected && selected.is_closed)" class="text-sm text-ink-soft text-center py-3" x-text="(selected && selected.is_closed) ? 'Tichet închis. Deschide unul nou pentru o solicitare nouă.' : ''"></div>
                    </div>

                    <form x-show="selected && !selected.is_closed" @submit.prevent="reply()" class="mt-5 grid gap-3">
                        <textarea class="field min-h-24" x-model="replyText" maxlength="5000" placeholder="Răspuns…" required></textarea>
                        <button type="submit" :disabled="submitting || !replyText.trim()" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-50">
                            <span x-show="!submitting">Trimite răspunsul</span>
                            <span x-show="submitting" x-cloak>Se trimite…</span>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function clientSupportPage() {
    return {
        loading: true,
        isAuth: null,
        submitting: false,
        tickets: [],
        counts: { open: 0, closed: 0, total: 0 },
        meta: { departments: [], problem_types: [] },
        openCreate: false,
        selected: null,
        messages: [],
        replyText: '',
        error: '',
        form: { subject: '', message: '', support_department_id: '', priority: 'normal' },

        init() {
            try { if (window.BileteOnlineAuth && BileteOnlineAuth.getToken && BileteOnlineAuth.getToken()) this.isAuth = true; } catch (e) {}
            if (this.isAuth === false) { this.loading = false; return; }
            this.load();
            this.loadMeta();
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.get('/customer/support-tickets');
                if (r && r.data) {
                    this.tickets = r.data.tickets || [];
                    this.counts = r.data.counts || this.counts;
                }
            } catch (e) {}
            this.loading = false;
        },

        async loadMeta() {
            try {
                const r = await BileteOnlineAPI.get('/customer/support-meta');
                if (r && r.data) this.meta = r.data;
            } catch (e) {}
        },

        async submitCreate() {
            if (! this.form.subject.trim() || ! this.form.message.trim()) {
                this.error = 'Completează subiectul și mesajul.';
                return;
            }
            this.submitting = true;
            this.error = '';
            try {
                const payload = {
                    subject:  this.form.subject.trim(),
                    message:  this.form.message.trim(),
                    priority: this.form.priority,
                };
                if (this.form.support_department_id) payload.support_department_id = Number(this.form.support_department_id);
                const r = await BileteOnlineAPI.post('/customer/support-tickets', payload);
                if (r && r.success) {
                    this.openCreate = false;
                    this.form = { subject: '', message: '', support_department_id: '', priority: 'normal' };
                    this.load();
                } else {
                    this.error = (r && r.message) || 'Nu am putut trimite tichetul.';
                }
            } catch (e) { this.error = 'Eroare la trimitere.'; }
            this.submitting = false;
        },

        async open(ticket) {
            this.selected = ticket;
            this.messages = [];
            try {
                const r = await BileteOnlineAPI.get('/customer/support-tickets/' + ticket.id);
                if (r && r.data) {
                    this.selected = r.data.ticket;
                    this.messages = r.data.messages || [];
                }
            } catch (e) {}
        },

        async reply() {
            if (! this.selected || ! this.replyText.trim()) return;
            this.submitting = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/support-tickets/' + this.selected.id + '/messages', { message: this.replyText.trim() });
                if (r && r.success) {
                    if (r.data && r.data.message) this.messages.push(r.data.message);
                    if (r.data && r.data.ticket) this.selected = r.data.ticket;
                    this.replyText = '';
                    this.load();
                }
            } catch (e) {}
            this.submitting = false;
        },

        statusLabel(s) {
            const map = { open: 'deschis', in_progress: 'în lucru', awaiting_organizer: 'în așteptare', resolved: 'rezolvat', closed: 'închis' };
            return map[s] || s;
        },
        statusClass(s) {
            if (s === 'resolved' || s === 'closed') return 'bg-paper-2 text-ink-soft';
            if (s === 'open') return 'bg-rose text-vermilion';
            return 'bg-mint text-forest';
        },
        formatDate(d) {
            if (! d) return '';
            try {
                const dt = new Date(d);
                if (isNaN(dt)) return d;
                const months = ['ian','feb','mar','apr','mai','iun','iul','aug','sep','oct','noi','dec'];
                return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear() + ' · ' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
            } catch (e) { return d; }
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
