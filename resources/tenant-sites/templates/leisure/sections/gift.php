<?php
/**
 * Card cadou valoric: previzualizare, valoare, destinatar, mesaj; se emite din
 * contul cumpărătorului (/account/gift-cards/purchase). Codul apare la final și
 * în „Contul meu → Carduri cadou”.
 */
$amounts = array_values(array_filter(array_map('intval', preg_split('/[\s,;]+/', (string) ($s['amounts'] ?? '100, 200, 300, 500')))));
if (!$amounts) { $amounts = [100, 200, 300, 500]; }
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'configureaza') ?>" class="paper-grid bg-cream px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28"
             x-data="{ amounts: <?= e(json_encode($amounts, $flags)) ?>, amount: <?= (int) ($amounts[1] ?? $amounts[0]) ?>, custom: '', to: '', msg: '', theme: 0, busy: false, err: '', code: '', balance: 0,
                       themes: ['rgb(var(--c-pine-900))', '#d76e42', 'rgb(var(--c-primary))'],
                       get value() { const c = Number(this.custom); return this.custom !== '' ? c : this.amount; },
                       async buy() {
                           this.err = '';
                           if (!(this.value >= 25 && this.value <= 5000)) { this.err = 'Alege o valoare între 25 și 5.000 lei.'; return; }
                           const auth = window.TS ? TS.Auth.get() : null;
                           if (!auth || !auth.token) { window.location.href = '/autentificare?next=' + encodeURIComponent(location.pathname); return; }
                           this.busy = true;
                           try {
                               const r = await fetch('/api/proxy.php?action=acc-gift-purchase', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + auth.token }, body: JSON.stringify({ amount: this.value, recipient_name: this.to, message: this.msg }) });
                               const d = await r.json();
                               if (d && d.success && d.data) { this.code = d.data.code; this.balance = d.data.balance; }
                               else { this.err = (d && (d.error || d.message)) || 'Cardul nu a putut fi emis acum.'; }
                           } catch (e) { this.err = 'Conexiunea a eșuat. Încearcă din nou.'; }
                           this.busy = false;
                       } }">
        <div class="mx-auto max-w-[1460px]">
            <div data-reveal class="grid gap-6 lg:grid-cols-[1fr_.8fr] lg:items-end">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                    <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(40px,7.5vw,76px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 text-pine-900/[.62] sm:text-lg lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>

            <div class="mt-12 grid gap-8 lg:grid-cols-[1fr_1fr] lg:items-start">
                <div data-reveal class="lg:sticky lg:top-28">
                    <article class="relative aspect-[1.6/1] overflow-hidden rounded-[28px] p-6 text-white shadow-lift sm:p-8" :style="'background:' + themes[theme]" :class="theme === 2 ? '!text-pine-950' : ''">
                        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full border border-current opacity-20"></div>
                        <div class="absolute -bottom-20 -left-10 h-64 w-64 rounded-full border border-current opacity-10"></div>
                        <div class="relative flex h-full flex-col">
                            <div class="flex items-start justify-between gap-4"><strong class="font-display text-2xl"><?= e(SITE_NAME) ?></strong><span class="text-[10px] font-bold uppercase tracking-[.2em] opacity-60">card cadou</span></div>
                            <div class="mt-auto">
                                <p class="font-display text-3xl font-semibold sm:text-4xl" x-text="to ? 'Pentru ' + to : 'Pentru următoarea ieșire'"></p>
                                <p class="mt-2 line-clamp-2 max-w-sm text-sm opacity-70" x-text="msg || 'O zi în aer liber, aleasă de tine.'"></p>
                            </div>
                            <div class="mt-5 flex items-end justify-between"><span class="font-display text-4xl font-semibold" x-text="(value || 0) + ' lei'"></span><span class="font-mono text-xs opacity-70" x-text="code || 'COD LA EMITERE'"></span></div>
                        </div>
                    </article>
                    <div class="mt-4 flex gap-2"><template x-for="(t, i) in themes" :key="i"><button type="button" @click="theme = i" class="h-9 w-9 rounded-full border-2 transition" :class="theme === i ? 'border-pine-950 scale-110' : 'border-transparent'" :style="'background:' + t" :aria-label="'Stil ' + (i + 1)"></button></template></div>
                </div>

                <div data-reveal class="rounded-[30px] border border-pine-900/10 bg-oat p-5 shadow-card sm:p-8">
                    <div x-show="!code">
                        <span class="text-sm font-bold text-pine-950">1. Valoarea</span>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4"><template x-for="v in amounts" :key="v"><button type="button" @click="amount = v; custom = ''" class="rounded-2xl border px-3 py-4 font-display text-xl font-semibold transition" :class="custom === '' && amount === v ? 'border-pine-900 bg-pine-900 text-acid' : 'border-pine-900/10 bg-white text-pine-950'" x-text="v + ' lei'"></button></template></div>
                        <label class="mt-3 block text-xs font-semibold text-pine-900/55">sau altă sumă (25–5.000 lei)<input type="number" min="25" max="5000" step="5" x-model="custom" class="mt-2 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 text-base outline-none focus:border-pine-900"></label>
                        <span class="mt-7 block text-sm font-bold text-pine-950">2. Pentru cine</span>
                        <input x-model="to" maxlength="60" placeholder="Numele destinatarului" class="mt-3 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 outline-none focus:border-pine-900">
                        <textarea x-model="msg" maxlength="300" rows="3" placeholder="Mesajul tău (opțional)" class="mt-3 w-full rounded-2xl border border-pine-900/15 bg-white p-4 outline-none focus:border-pine-900"></textarea>
                        <div class="mt-7 flex items-center justify-between gap-4 border-t border-pine-900/10 pt-6"><div><span class="block text-xs text-pine-900/50">Total</span><strong class="font-display text-3xl text-pine-950" x-text="(value || 0) + ' lei'"></strong></div><button type="button" @click="buy()" :disabled="busy" class="inline-flex min-h-[52px] items-center justify-center rounded-full bg-pine-900 px-6 font-bold text-acid transition hover:-translate-y-0.5 disabled:opacity-50" x-text="busy ? 'Se emite…' : 'Cumpără cardul'"></button></div>
                        <p x-show="err" x-text="err" class="mt-4 text-sm font-semibold text-ember"></p>
                        <p class="mt-4 text-xs leading-5 text-pine-900/50">Cardul se cumpără din contul tău. Codul îl primești imediat și îl găsești oricând în „Contul meu”.</p>
                    </div>
                    <div x-show="code" x-cloak class="text-pine-950">
                        <span class="grid h-12 w-12 place-items-center rounded-full bg-acid text-xl font-bold">✓</span>
                        <h3 class="mt-4 font-display text-3xl font-semibold">Cardul e gata.</h3>
                        <p class="mt-2 text-sm text-pine-900/60">Codul de mai jos se folosește la plată, pentru <span x-text="balance"></span> lei.</p>
                        <div class="mt-5 flex items-center justify-between gap-3 rounded-2xl border border-dashed border-pine-900/25 bg-white p-4"><strong class="font-mono text-lg" x-text="code"></strong><button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(code); window.TS && TS.toast('Cod copiat.')" class="rounded-full bg-pine-900 px-4 py-2 text-xs font-bold text-acid">Copiază</button></div>
                        <div class="mt-6 flex flex-wrap gap-3"><a href="/cont/carduri-cadou" class="rounded-full bg-pine-900 px-5 py-3 text-sm font-bold text-white">Vezi în cont</a><button type="button" @click="code = ''; to = ''; msg = ''" class="rounded-full border border-pine-900/15 px-5 py-3 text-sm font-bold">Încă un card</button></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
