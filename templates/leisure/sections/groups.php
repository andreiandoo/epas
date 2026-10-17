<?php
/**
 * Grupuri: programele pe tip de grup (din panou) și cererea de ofertă. Cererea
 * pleacă pe email (aplicația vizitatorului) către adresa din panou.
 */
require_once TEMPLATE_DIR . '/lib.php';
$email = trim((string) (($s['email'] ?? '') ?: site('footer.email', '')));
$programs = array_values(array_filter((array) ($s['programs'] ?? []), fn ($p) => trim((string) ($p['title'] ?? '')) !== ''));
$goals = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['goals'] ?? '')))));
$min = max(1, (int) ($s['min_people'] ?? 15));
$access = lv_tickets('access');
$from = $access ? min(array_map(fn ($b) => (int) $b['price_cents'], $access)) : 0;
$types = array_map(fn ($p) => (string) $p['title'], $programs) ?: ['Școală', 'Companie', 'Altceva'];
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'programe') ?>" class="bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28"
             x-data="{ types: <?= e(json_encode($types, $flags)) ?>, goals: <?= e(json_encode($goals, $flags)) ?>, type: 0, people: <?= max($min, 25) ?>, date: '', picked: [], org: '', name: '', mail: '', phone: '', note: '', sent: false, err: '',
                       toggle(g) { this.picked = this.picked.includes(g) ? this.picked.filter(x => x !== g) : [...this.picked, g]; },
                       send() {
                           this.err = '';
                           if (!this.name.trim() || !/^\S+@\S+\.\S+$/.test(this.mail)) { this.err = 'Completează numele și emailul.'; return; }
                           const lines = ['Tip grup: ' + this.types[this.type], 'Participanți: ' + this.people, 'Data dorită: ' + (this.date || 'flexibilă')];
                           if (this.picked.length) lines.push('Ne interesează: ' + this.picked.join(', '));
                           if (this.org) lines.push('Organizație: ' + this.org);
                           lines.push('', this.note, '', '—', this.name, this.mail, this.phone);
                           window.location.href = 'mailto:<?= e(rawurlencode($email)) ?>?subject=' + encodeURIComponent('Cerere ofertă grup — ' + this.types[this.type] + ', ' + this.people + ' pers.') + '&body=' + encodeURIComponent(lines.join('\n'));
                           this.sent = true;
                       } }">
        <div class="mx-auto max-w-[1460px]">
            <div data-reveal class="grid gap-6 lg:grid-cols-[1fr_.8fr] lg:items-end">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                    <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(40px,7.5vw,76px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 text-pine-900/[.62] sm:text-lg lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>

            <?php if ($programs): ?>
            <div class="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-<?= min(4, count($programs)) ?>">
                <?php foreach ($programs as $i => $p): $img = trim((string) ($p['image'] ?? '')) ?: lv_photo(LV_PHOTOS['activity'][$i % 4], 1000); ?>
                <article data-reveal class="group relative min-h-[420px] overflow-hidden rounded-[26px] border border-pine-900/10">
                    <img src="<?= e($img) ?>" alt="" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/30 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-5 text-white sm:p-6">
                        <?php if (trim((string) ($p['label'] ?? '')) !== ''): ?><span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid"><?= e($p['label']) ?></span><?php endif; ?>
                        <h3 class="mt-2 font-display text-3xl font-semibold"><?= e($p['title']) ?></h3>
                        <?php if (trim((string) ($p['text'] ?? '')) !== ''): ?><p class="mt-3 text-sm leading-6 text-white/[.66]"><?= e($p['text']) ?></p><?php endif; ?>
                        <button type="button" @click="type = <?= $i ?>; document.getElementById('cerere-oferta').scrollIntoView({ behavior: 'smooth' })" class="mt-5 inline-flex rounded-full bg-acid px-4 py-2.5 text-xs font-bold text-pine-950">Cere ofertă</button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div id="cerere-oferta" data-reveal class="mt-12 grid gap-6 rounded-[30px] bg-pine-950 p-5 text-white sm:p-9 lg:grid-cols-[.8fr_1.2fr]">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Cerere de ofertă</span>
                    <h3 class="mt-3 pb-1 font-display text-[clamp(34px,5vw,56px)] font-semibold leading-[.98] tracking-[-.04em]"><?= ts_md(($s['form_title'] ?? '') ?: 'Spune-ne cine vine.') ?></h3>
                    <p class="mt-4 text-sm leading-7 text-white/[.6]">Ofertă pentru grupuri de la <?= $min ?> persoane<?= $from ? '; biletul de acces individual pornește de la ' . e(lv_price($from)) : '' ?>.</p>
                    <?php if ($email !== ''): ?><p class="mt-4 text-sm text-white/[.6]">Sau scrie direct la <a href="mailto:<?= e($email) ?>" class="font-bold text-acid underline"><?= e($email) ?></a>.</p><?php endif; ?>
                </div>
                <?php if ($email === ''): ?>
                <p class="rounded-[20px] bg-white/[.06] p-5 text-sm text-white/60">Formularul se activează după ce adăugați adresa de email a parcului în panou.</p>
                <?php else: ?>
                <div>
                    <form x-show="!sent" @submit.prevent="send()" class="grid gap-4 sm:grid-cols-2" novalidate>
                        <label class="text-sm font-bold">Tip grup<select x-model.number="type" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal text-white outline-none"><template x-for="(t, i) in types" :key="t"><option :value="i" x-text="t" class="text-pine-950"></option></template></select></label>
                        <label class="text-sm font-bold">Participanți<input type="number" min="<?= $min ?>" max="2000" x-model.number="people" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none"></label>
                        <label class="text-sm font-bold">Data dorită<input type="date" x-model="date" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none [color-scheme:dark]"></label>
                        <label class="text-sm font-bold">Organizație <span class="font-normal text-white/45">(opțional)</span><input x-model="org" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none"></label>
                        <div class="sm:col-span-2" x-show="goals.length"><span class="text-sm font-bold">Ce vă interesează?</span><div class="mt-2 flex flex-wrap gap-2"><template x-for="g in goals" :key="g"><button type="button" @click="toggle(g)" class="rounded-full border px-3 py-2 text-xs font-bold" :class="picked.includes(g) ? 'border-acid bg-acid text-pine-950' : 'border-white/15 text-white/75'" x-text="g"></button></template></div></div>
                        <label class="text-sm font-bold">Nume<input x-model="name" autocomplete="name" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none"></label>
                        <label class="text-sm font-bold">Email<input type="email" x-model="mail" autocomplete="email" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none"></label>
                        <label class="text-sm font-bold">Telefon <span class="font-normal text-white/45">(opțional)</span><input type="tel" x-model="phone" autocomplete="tel" class="mt-2 h-12 w-full rounded-2xl border border-white/15 bg-white/[.06] px-4 font-normal outline-none"></label>
                        <label class="text-sm font-bold sm:col-span-2">Detalii<textarea x-model="note" rows="3" class="mt-2 w-full rounded-2xl border border-white/15 bg-white/[.06] p-4 font-normal outline-none"></textarea></label>
                        <p x-show="err" x-text="err" class="text-sm font-semibold text-acid sm:col-span-2"></p>
                        <button class="inline-flex min-h-[52px] items-center justify-center rounded-full bg-acid px-6 font-bold text-pine-950 sm:col-span-2 sm:justify-self-start">Trimite cererea</button>
                    </form>
                    <div x-show="sent" x-cloak class="rounded-[24px] bg-white/[.06] p-6"><p class="font-display text-2xl font-semibold">Cererea s-a deschis în aplicația de email.</p><p class="mt-2 text-sm text-white/60">Trimite-o de acolo; revenim cu oferta cât mai repede.</p><button type="button" @click="sent = false" class="mt-4 text-sm font-bold text-acid">Modifică cererea</button></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
