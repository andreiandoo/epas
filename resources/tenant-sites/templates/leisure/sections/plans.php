<?php
/**
 * Abonamentele reale ale tenantului (/tenant-client/subscriptions), cu un mic
 * calculator „câte vizite” față de biletul de acces și cumpărarea din cont.
 */
require_once TEMPLATE_DIR . '/lib.php';
$plans = [];
foreach (tc_subscriptions() as $p) {
    if (!is_array($p) || empty($p['slug'])) { continue; }
    $plans[] = [
        'slug'     => (string) $p['slug'],
        'name'     => (string) ($p['name'] ?? ''),
        'subtitle' => (string) ($p['subtitle'] ?? ''),
        'text'     => trim(strip_tags((string) ($p['description'] ?? ''))),
        'price'    => (float) ($p['price'] ?? 0),
        'currency' => (string) ($p['currency'] ?? 'RON'),
        'benefits' => array_values(array_filter((array) ($p['benefits'] ?? []))),
        'featured' => (bool) ($p['is_featured'] ?? false),
        'visits'   => (int) ($p['tickets_included'] ?? 0) ?: (int) ($p['shows_included'] ?? 0),
        'until'    => (string) ($p['valid_until'] ?? ''),
    ];
}
$access = lv_tickets('access');
$dayPrice = $access ? ((int) $access[0]['price_cents']) / 100 : 0;
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'abonamente') ?>" class="bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28"
             x-data="{ plans: <?= e(json_encode($plans, $flags)) ?>, day: <?= json_encode($dayPrice) ?>, visits: 6, busy: '', msg: '',
                       get best() { const p = this.plans.filter(x => x.price > 0); if (!p.length) return null; const withV = p.filter(x => x.visits && x.visits >= this.visits).sort((a, b) => a.price - b.price); return withV[0] || p.slice().sort((a, b) => b.price - a.price)[0]; },
                       get save() { const b = this.best; return b && this.day ? Math.round(this.visits * this.day - b.price) : 0; },
                       async buy(slug) {
                           this.msg = '';
                           const auth = window.TS ? TS.Auth.get() : null;
                           if (!auth || !auth.token) { window.location.href = '/autentificare?next=' + encodeURIComponent(location.pathname); return; }
                           this.busy = slug;
                           try {
                               const r = await fetch('/api/proxy.php?action=subscribe', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + auth.token }, body: JSON.stringify({ plan_slug: slug, success_url: location.origin + '/cont/abonamente', cancel_url: location.href }) });
                               const d = await r.json();
                               if (d && d.success && d.redirect_url) { window.location.href = d.redirect_url; return; }
                               if (d && d.success) { window.location.href = '/cont/abonamente'; return; }
                               this.msg = (d && (d.error || d.message)) || 'Abonamentul nu a putut fi cumpărat acum.';
                           } catch (e) { this.msg = 'Conexiunea a eșuat. Încearcă din nou.'; }
                           this.busy = '';
                       } }">
        <div class="mx-auto max-w-[1460px]">
            <div data-reveal class="grid gap-6 lg:grid-cols-[1fr_.8fr] lg:items-end">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                    <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(40px,7.5vw,76px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 text-pine-900/[.62] sm:text-lg lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>

            <?php if (!$plans): ?>
            <div data-reveal class="mt-12 rounded-[28px] border border-dashed border-pine-900/20 bg-cream p-10 text-center">
                <h3 class="font-display text-3xl font-semibold text-pine-950"><?= e(trim((string) ($s['empty_title'] ?? '')) ?: 'Abonamentele se anunță în curând.') ?></h3>
                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-pine-900/[.55]"><?= e(trim((string) ($s['empty_text'] ?? '')) ?: 'Până atunci, biletele de o zi se cumpără online, cu locul rezervat.') ?></p>
                <a href="/bilete" class="mt-6 inline-flex rounded-full bg-pine-900 px-5 py-3 text-sm font-bold text-acid">Vezi biletele</a>
            </div>
            <?php else: ?>
            <?php if ($dayPrice > 0 && !empty($s['show_calculator'])): ?>
            <div data-reveal class="mt-12 grid gap-6 rounded-[30px] bg-pine-950 p-6 text-white sm:p-9 lg:grid-cols-[1fr_.9fr] lg:items-center">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Câte vizite estimezi?</span>
                    <div class="mt-5 flex items-center gap-4"><input type="range" min="2" max="20" step="1" x-model.number="visits" class="w-full accent-[rgb(var(--c-primary))]" aria-label="Număr de vizite"><strong class="w-20 text-right font-display text-3xl" x-text="visits"></strong></div>
                    <p class="mt-3 text-sm text-white/[.55]">Biletul de acces de o zi costă <?= e($fmt($dayPrice)) ?> lei.</p>
                </div>
                <div class="rounded-[24px] border border-white/10 bg-white/[.06] p-5" x-show="best">
                    <span class="text-[9px] font-bold uppercase tracking-[.2em] text-white/[.45]">Recomandarea noastră</span>
                    <p class="mt-2 font-display text-3xl font-semibold" x-text="best ? best.name : ''"></p>
                    <p class="mt-2 text-sm text-white/[.6]" x-text="save > 0 ? 'Economisești aproximativ ' + save + ' lei față de bilete separate.' : 'La puține vizite, biletul de o zi poate fi mai avantajos.'"></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-<?= min(3, count($plans)) ?>">
                <template x-for="p in plans" :key="p.slug">
                    <article class="relative flex min-h-[420px] flex-col overflow-hidden rounded-[26px] border p-6 sm:p-7" :class="p.featured ? 'border-acid bg-pine-950 text-white' : 'border-pine-900/10 bg-cream text-pine-950'">
                        <div class="flex items-center justify-between gap-3"><span class="text-[9px] font-bold uppercase tracking-[.18em] opacity-60" x-text="p.subtitle || (p.until ? 'valabil până la ' + p.until.split('-').reverse().join('.') : 'abonament')"></span><span x-show="p.featured" class="rounded-full bg-acid px-3 py-1.5 text-[8px] font-bold uppercase tracking-[.15em] text-pine-950"><?= e(trim((string) ($s['featured_label'] ?? '')) ?: 'Recomandat') ?></span></div>
                        <h3 class="mt-5 font-display text-4xl font-semibold" x-text="p.name"></h3>
                        <p class="mt-3 text-sm leading-6 opacity-65" x-show="p.text" x-text="p.text"></p>
                        <div class="mt-6 flex items-end gap-2"><span class="font-display text-5xl font-semibold" x-text="p.price.toLocaleString('ro-RO')"></span><span class="pb-2 text-sm opacity-70" x-text="p.currency === 'RON' ? 'lei' : p.currency"></span></div>
                        <ul class="mt-6 space-y-3 text-sm">
                            <template x-for="b in p.benefits" :key="b"><li class="flex items-start gap-3"><span class="mt-0.5 grid h-5 w-5 flex-none place-items-center rounded-full bg-acid text-[10px] font-bold text-pine-950">✓</span><span x-text="b"></span></li></template>
                        </ul>
                        <button type="button" @click="buy(p.slug)" :disabled="busy !== ''" class="mt-auto w-full rounded-full px-5 py-3.5 text-sm font-bold transition hover:-translate-y-1 disabled:opacity-50" :class="p.featured ? 'bg-acid text-pine-950' : 'bg-pine-900 text-white'" style="margin-top:auto" x-text="busy === p.slug ? 'Se pregătește…' : 'Alege ' + p.name"></button>
                    </article>
                </template>
            </div>
            <p x-show="msg" x-text="msg" class="mt-6 text-center text-sm font-semibold text-ember"></p>
            <p class="mt-6 text-center text-xs text-pine-900/[.45]">Abonamentul se cumpără din contul tău; îl găsești apoi în „Contul meu”.</p>
            <?php endif; ?>
        </div>
    </section>
