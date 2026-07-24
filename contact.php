<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Contact — ' . SITE_NAME;
$activeNav = 'contact';
$pageExtraStyles = '
    .input-field { background: #0A0A0A; border: 1px solid rgba(212,175,55,.2); transition: all .2s ease; }
    .input-field:focus { border-color: #D4AF37; outline: none; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-12 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Suntem aici pentru tine</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-6">Contact</h1>
        <p class="text-ivory/70 text-lg max-w-2xl">Ai întrebări despre bilete, abonamente sau spectacole? Echipa noastră îți stă la dispoziție.</p>
    </div>
</section>

<section class="pb-24 px-4 lg:px-8" x-data="{ subject:'', sent:false, loading:false, submit(){ this.loading=true; setTimeout(()=>{ this.loading=false; this.sent=true; }, 700); } }">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12">
        <!-- Form -->
        <div class="bg-charcoal rounded-xl p-8">
            <h2 class="font-display text-2xl mb-6">Trimite-ne un mesaj</h2>
            <form @submit.prevent="submit()" x-show="!sent">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm text-warm-gray mb-2">Subiect</label>
                        <select x-model="subject" class="input-field w-full px-4 py-3 rounded-lg text-ivory">
                            <option value="">Alege un subiect</option>
                            <option value="tickets">Bilete și rezervări</option>
                            <option value="subscriptions">Abonamente</option>
                            <option value="groups">Grupuri și școli</option>
                            <option value="press">Presă și parteneriate</option>
                            <option value="other">Altele</option>
                        </select>
                    </div>
                    <div class="grid md:grid-cols-2 gap-5">
                        <div><label class="block text-sm text-warm-gray mb-2">Nume complet</label><input type="text" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Ion Popescu" required></div>
                        <div><label class="block text-sm text-warm-gray mb-2">Email</label><input type="email" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="ion@email.com" required></div>
                    </div>
                    <div><label class="block text-sm text-warm-gray mb-2">Telefon (opțional)</label><input type="tel" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="07xx xxx xxx"></div>
                    <template x-if="subject === 'groups'">
                        <div class="grid md:grid-cols-2 gap-5 p-4 bg-midnight/50 rounded-lg">
                            <div><label class="block text-sm text-warm-gray mb-2">Data dorită</label><input type="date" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                            <div><label class="block text-sm text-warm-gray mb-2">Număr persoane</label><input type="number" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="20" min="10"></div>
                        </div>
                    </template>
                    <div><label class="block text-sm text-warm-gray mb-2">Mesaj</label><textarea rows="5" class="input-field w-full px-4 py-3 rounded-lg text-ivory resize-none" placeholder="Scrie mesajul tău aici..." required></textarea></div>
                    <button type="submit" :disabled="loading" class="btn-gold w-full py-4 rounded-lg"><span x-text="loading ? 'Se trimite...' : 'Trimite mesajul'"></span></button>
                </div>
            </form>
            <div x-show="sent" class="text-center py-12">
                <div class="w-20 h-20 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="font-display text-2xl mb-3">Mesaj trimis!</h3>
                <p class="text-ivory/70 mb-6">Îți mulțumim. Te vom contacta în cel mai scurt timp.</p>
                <button @click="sent=false" class="text-gold hover:underline">Trimite alt mesaj</button>
            </div>
        </div>

        <!-- Info -->
        <div class="space-y-6">
            <div class="bg-charcoal rounded-xl p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-gold/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div><h3 class="font-display text-lg mb-1">Adresă</h3><p class="text-ivory/70">Piața Teatrului, București</p></div>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-charcoal rounded-xl p-6">
                    <h3 class="font-display text-lg mb-1">Telefon</h3>
                    <a href="tel:+40210000000" class="text-gold hover:text-gold-light">021 000 0000</a>
                    <p class="text-warm-gray text-sm mt-1">Casa de bilete</p>
                </div>
                <div class="bg-charcoal rounded-xl p-6">
                    <h3 class="font-display text-lg mb-1">Email</h3>
                    <a href="mailto:bilete@teatru.ro" class="text-gold hover:text-gold-light">bilete@teatru.ro</a>
                    <p class="text-warm-gray text-sm mt-1">Răspundem în 24h</p>
                </div>
            </div>
            <div class="bg-charcoal rounded-xl p-6">
                <h3 class="font-display text-lg mb-4">Program casă de bilete</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-warm-gray">Luni – Vineri</span><span>10:00 – 19:00</span></div>
                    <div class="flex justify-between"><span class="text-warm-gray">Sâmbătă – Duminică</span><span>10:00 – 14:00</span></div>
                    <div class="flex justify-between"><span class="text-warm-gray">Înainte de spectacole</span><span>până la începere</span></div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
