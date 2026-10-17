<?php
/**
 * Contact: subiectele (din panou), formularul și canalele directe. Mesajul se
 * deschide în aplicația de email a vizitatorului, către adresa din panou
 * (secțiune sau subsol), ca la celelalte template-uri.
 */
$email = trim((string) (($s['email'] ?? '') ?: site('footer.email', '')));
$phone = trim((string) site('footer.phone', ''));
$address = trim((string) site('settings.address', ''));
$hours = trim((string) site('settings.today_hours', ''));
$topics = array_values(array_filter(array_map(fn ($t) => trim((string) ($t['label'] ?? '')), (array) ($s['topics'] ?? []))));
if (!$topics) { $topics = ['Bilete și rezervări', 'Vizita ta', 'Grupuri', 'Altceva']; }
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'mesaj') ?>" class="paper-grid bg-cream px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28"
             x-data="{ topics: <?= e(json_encode($topics, $flags)) ?>, topic: 0, name: '', mail: '', phone: '', order: '', message: '', sent: false, err: '',
                       send() {
                           this.err = '';
                           if (!this.name.trim() || !/^\S+@\S+\.\S+$/.test(this.mail) || !this.message.trim()) { this.err = 'Completează numele, emailul și mesajul.'; return; }
                           const body = this.message + '\n\n—\n' + this.name + '\n' + this.mail + (this.phone ? '\n' + this.phone : '') + (this.order ? '\nComanda: ' + this.order : '');
                           window.location.href = 'mailto:<?= e(rawurlencode($email)) ?>?subject=' + encodeURIComponent(this.topics[this.topic] + ' — mesaj de pe site') + '&body=' + encodeURIComponent(body);
                           this.sent = true;
                       } }">
        <div class="mx-auto grid max-w-[1460px] gap-8 lg:grid-cols-[1.2fr_.8fr] lg:items-start">
            <div data-reveal class="rounded-[30px] border border-pine-900/10 bg-oat p-5 shadow-card sm:p-8">
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                <h2 class="mt-3 pb-1 font-display text-[clamp(36px,5vw,56px)] font-semibold leading-[.98] tracking-[-.04em] text-pine-950"><?= ts_md(($s['title'] ?? '') ?: 'Cu ce te putem ajuta?') ?></h2>
                <div class="mt-6 flex flex-wrap gap-2">
                    <template x-for="(t, i) in topics" :key="t"><button type="button" @click="topic = i" class="rounded-full border px-4 py-2.5 text-sm font-bold transition" :class="topic === i ? 'border-pine-900 bg-pine-900 text-acid' : 'border-pine-900/10 bg-white text-pine-950'" x-text="t"></button></template>
                </div>
                <?php if ($email === ''): ?>
                <p class="mt-6 rounded-[18px] bg-cream p-4 text-sm text-pine-900/60">Formularul se activează după ce adăugați adresa de email a parcului în panou.</p>
                <?php else: ?>
                <form x-show="!sent" @submit.prevent="send()" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
                    <label class="block text-sm font-bold text-pine-950">Nume<input x-model="name" autocomplete="name" class="mt-2 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 font-normal outline-none focus:border-pine-900"></label>
                    <label class="block text-sm font-bold text-pine-950">Email<input x-model="mail" type="email" autocomplete="email" class="mt-2 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 font-normal outline-none focus:border-pine-900"></label>
                    <label class="block text-sm font-bold text-pine-950">Telefon <span class="font-normal text-pine-900/45">(opțional)</span><input x-model="phone" type="tel" autocomplete="tel" class="mt-2 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 font-normal outline-none focus:border-pine-900"></label>
                    <label class="block text-sm font-bold text-pine-950">Număr comandă <span class="font-normal text-pine-900/45">(opțional)</span><input x-model="order" class="mt-2 h-12 w-full rounded-2xl border border-pine-900/15 bg-white px-4 font-normal outline-none focus:border-pine-900"></label>
                    <label class="block text-sm font-bold text-pine-950 sm:col-span-2">Mesaj<textarea x-model="message" rows="5" class="mt-2 w-full rounded-2xl border border-pine-900/15 bg-white p-4 font-normal outline-none focus:border-pine-900"></textarea></label>
                    <p x-show="err" x-text="err" class="text-sm font-semibold text-ember sm:col-span-2"></p>
                    <button class="inline-flex min-h-[52px] items-center justify-center rounded-full bg-pine-900 px-6 font-bold text-acid transition hover:-translate-y-0.5 sm:col-span-2 sm:justify-self-start">Trimite mesajul</button>
                </form>
                <div x-show="sent" x-cloak class="mt-6 rounded-[24px] bg-pine-950 p-6 text-white">
                    <p class="font-display text-2xl font-semibold">Mesajul s-a deschis în aplicația de email.</p>
                    <p class="mt-2 text-sm text-white/60">Trimite-l de acolo. Dacă nu s-a deschis nimic, scrie-ne direct la <a class="font-bold text-acid underline" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>
                    <button type="button" @click="sent = false" class="mt-4 text-sm font-bold text-acid">Scrie alt mesaj</button>
                </div>
                <?php endif; ?>
            </div>

            <aside data-reveal class="space-y-3 lg:sticky lg:top-28">
                <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>" class="block rounded-[24px] bg-pine-950 p-5 text-white"><span class="text-[9px] font-bold uppercase tracking-[.2em] text-acid">Telefon</span><strong class="mt-2 block font-display text-2xl"><?= e($phone) ?></strong><?php if ($hours !== ''): ?><span class="mt-1 block text-xs text-white/50">Program <?= e($hours) ?></span><?php endif; ?></a><?php endif; ?>
                <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>" class="block rounded-[24px] border border-pine-900/10 bg-oat p-5 text-pine-950"><span class="text-[9px] font-bold uppercase tracking-[.2em] text-pine-700">Email</span><strong class="mt-2 block break-all font-display text-xl"><?= e($email) ?></strong></a><?php endif; ?>
                <?php if ($address !== ''): ?><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener" class="block rounded-[24px] border border-pine-900/10 bg-oat p-5 text-pine-950"><span class="text-[9px] font-bold uppercase tracking-[.2em] text-pine-700">Adresă</span><strong class="mt-2 block text-base"><?= e($address) ?></strong><span class="mt-1 block text-xs text-pine-900/50">Deschide în hărți ↗</span></a><?php endif; ?>
                <?php if (trim((string) ($s['note'] ?? '')) !== ''): ?><div class="rounded-[24px] bg-ember/15 p-5 text-sm leading-6 text-pine-950"><?= nl2br(e($s['note'])) ?></div><?php endif; ?>
            </aside>
        </div>
    </section>
