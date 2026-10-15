<?php
/**
 * Formular + date de contact. Formularul nu trimite prin server: deschide
 * clientul de email al vizitatorului către adresa din panou (mailto).
 */
$email = trim((string) ($s['email'] ?? '')) ?: trim((string) site('footer.email', ''));
$phone = trim((string) ($s['phone'] ?? '')) ?: trim((string) site('footer.phone', ''));
$subjects = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['subjects'] ?? '')))));
$hours = array_values(array_filter((array) ($s['hours'] ?? []), fn ($h) => !empty($h['label'])));
?>
<section class="pb-24 px-4 lg:px-8" x-data="contactForm(<?= e(json_encode($email)) ?>)">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12">
        <?php if ($email !== ''): ?>
        <div class="bg-surface rounded-xl p-8">
            <h2 class="font-display text-2xl mb-6"><?= e($s['form_title'] ?? 'Trimite-ne un mesaj') ?></h2>
            <form @submit.prevent="send()" class="space-y-5">
                <?php if ($subjects): ?>
                <div>
                    <label class="block text-sm text-muted mb-2">Subiect</label>
                    <select x-model="subject" class="input-field w-full px-4 py-3 rounded-lg text-fg">
                        <option value="">Alege un subiect</option>
                        <?php foreach ($subjects as $sub): ?><option><?= e($sub) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="grid md:grid-cols-2 gap-5">
                    <div><label class="block text-sm text-muted mb-2">Nume complet</label><input type="text" x-model="name" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                    <div><label class="block text-sm text-muted mb-2">Telefon (opțional)</label><input type="tel" x-model="phone" class="input-field w-full px-4 py-3 rounded-lg text-fg"></div>
                </div>
                <div><label class="block text-sm text-muted mb-2">Mesaj</label><textarea rows="5" x-model="message" class="input-field w-full px-4 py-3 rounded-lg text-fg resize-none" required></textarea></div>
                <button type="submit" class="btn-primary w-full py-4 rounded-lg">Scrie-ne pe email</button>
                <p class="text-xs text-muted">Se deschide aplicația ta de email cu mesajul completat.</p>
            </form>
        </div>
        <?php endif; ?>
        <div class="space-y-6">
            <?php if (!empty($s['address'])): ?>
            <div class="bg-surface rounded-xl p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div><h3 class="font-display text-lg mb-1">Adresă</h3><p class="text-fg/70"><?= e($s['address']) ?></p></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($phone !== '' || $email !== ''): ?>
            <div class="grid md:grid-cols-2 gap-4">
                <?php if ($phone !== ''): ?>
                <div class="bg-surface rounded-xl p-6">
                    <h3 class="font-display text-lg mb-1">Telefon</h3>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>" class="text-primary hover:text-primary-light"><?= e($phone) ?></a>
                    <?php if (!empty($s['phone_note'])): ?><p class="text-muted text-sm mt-1"><?= e($s['phone_note']) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                <div class="bg-surface rounded-xl p-6">
                    <h3 class="font-display text-lg mb-1">Email</h3>
                    <a href="mailto:<?= e($email) ?>" class="text-primary hover:text-primary-light break-all"><?= e($email) ?></a>
                    <?php if (!empty($s['email_note'])): ?><p class="text-muted text-sm mt-1"><?= e($s['email_note']) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($hours): ?>
            <div class="bg-surface rounded-xl p-6">
                <h3 class="font-display text-lg mb-4"><?= e($s['hours_title'] ?? 'Program') ?></h3>
                <div class="space-y-2 text-sm">
                    <?php foreach ($hours as $h): ?><div class="flex justify-between gap-4"><span class="text-muted"><?= e($h['label']) ?></span><span><?= e($h['value'] ?? '') ?></span></div><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
function contactForm(to){return{
    subject:'', name:'', phone:'', message:'',
    send(){
        var body=this.message+'\n\n— '+this.name+(this.phone?' · '+this.phone:'');
        window.location.href='mailto:'+to+'?subject='+encodeURIComponent(this.subject||'Mesaj de pe site')+'&body='+encodeURIComponent(body);
    }
};}
</script>
