<?php
/** Verificarea unui card cadou după cod: starea și soldul rămas (/gift-cards/{code}). */
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'verifica') ?>" class="paper-grid bg-cream px-4 py-20 sm:px-6 sm:py-24 lg:px-10"
             x-data="{ code: new URLSearchParams(location.search).get('cod') || '', busy: false, res: null, err: '',
                       async check() {
                           this.err = ''; this.res = null;
                           const c = this.code.trim().toUpperCase();
                           if (c.length < 4) { this.err = 'Introdu codul de pe card.'; return; }
                           this.busy = true;
                           try {
                               const r = await fetch('/api/proxy.php?action=voucher-verify&code=' + encodeURIComponent(c));
                               const d = await r.json();
                               if (d && d.success) { this.res = d.data; } else { this.err = r.status === 429 ? 'Prea multe încercări. Mai încearcă peste un minut.' : 'Codul nu a fost găsit. Verifică literele și cifrele.'; }
                           } catch (e) { this.err = 'Conexiunea a eșuat. Încearcă din nou.'; }
                           this.busy = false;
                       },
                       init() { if (this.code) this.check(); } }">
        <div class="mx-auto max-w-3xl">
            <div data-reveal class="rounded-[30px] border border-pine-900/10 bg-oat p-6 shadow-card sm:p-10">
                <?php if (trim((string) ($s['title'] ?? '')) !== ''): ?><h2 class="pb-1 font-display text-[clamp(34px,5vw,54px)] font-semibold leading-[.98] tracking-[-.04em] text-pine-950"><?= ts_md($s['title']) ?></h2><?php endif; ?>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="mt-3 text-sm leading-7 text-pine-900/60"><?= ts_md($s['lead']) ?></p><?php endif; ?>
                <form @submit.prevent="check()" class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <input x-model="code" placeholder="Ex.: TC-GIFT-AB12CD" autocomplete="off" spellcheck="false" class="h-14 flex-1 rounded-full border border-pine-900/15 bg-white px-5 font-mono text-base uppercase tracking-wider outline-none focus:border-pine-900">
                    <button :disabled="busy" class="inline-flex h-14 items-center justify-center rounded-full bg-pine-900 px-7 font-bold text-acid disabled:opacity-50" x-text="busy ? 'Se verifică…' : 'Verifică'"></button>
                </form>
                <p x-show="err" x-text="err" class="mt-4 text-sm font-semibold text-ember"></p>
                <div x-show="res" x-cloak class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-pine-950 p-5 text-white sm:col-span-2"><span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid">Sold disponibil</span><strong class="mt-1 block font-display text-4xl" x-text="res ? res.balance + ' lei' : ''"></strong><span class="mt-1 block text-xs text-white/50" x-text="res ? 'din ' + res.initial + ' lei · ' + res.code : ''"></span></div>
                    <div class="rounded-2xl border border-pine-900/10 bg-white p-5"><span class="text-[9px] font-bold uppercase tracking-[.2em] text-pine-700">Stare</span><strong class="mt-1 block font-display text-2xl text-pine-950" x-text="res ? ({ active: 'Activ', used: 'Folosit', expired: 'Expirat', cancelled: 'Anulat' }[res.status] || res.status) : ''"></strong></div>
                </div>
                <div x-show="res && res.status === 'active' && res.balance > 0" x-cloak class="mt-6 flex flex-wrap gap-3"><a href="/bilete" class="rounded-full bg-acid px-5 py-3 text-sm font-bold text-pine-950">Alege biletele</a><a href="/cont/carduri-cadou" class="rounded-full border border-pine-900/15 px-5 py-3 text-sm font-bold text-pine-950">Adaugă în cont</a></div>
            </div>
        </div>
    </section>
