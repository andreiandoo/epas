<?php
/**
 * bilete.online — /inregistrare-locatie
 *
 * Self-service onboarding form for prospective venue / activity organizers.
 * Linked from every CTA on /devino-partener with the same `?tip=…&loc=…`
 * query params propagated, so a personalized campaign URL also seeds the
 * form's first two fields.
 *
 * Submission flow: 3-step form posts to /api/proxy.php?action=public.contact
 * — the marketplace's existing contact endpoint forwards to the marketplace
 * contact email. Lead lives in the inbox until we wire up a dedicated leads
 * table (left for later, this is the MVP path).
 *
 * Visual language matches the partner landing page (Fraunces + Archivo,
 * paper/ink/rust palette) so the funnel feels continuous.
 */

$pageCacheTTL = 0; // form page — never cache (would serve someone else's prefill)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Pull categories so the prospect can pick their activity type from the
// actual marketplace taxonomy (not a hand-rolled list that can drift).
$categoriesResp = api_cached('categories_full_tree', fn () => api_get('/event-categories'), 900);
$rawCategories  = $categoriesResp['data']['categories'] ?? [];
$parentCategories = is_array($rawCategories)
    ? array_values(array_filter($rawCategories, fn ($c) => empty($c['parent_id'])))
    : [];
usort($parentCategories, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

$peel = static function ($value): string {
    if (is_string($value)) return $value;
    if (is_array($value))  return (string) ($value['ro'] ?? $value['en'] ?? reset($value) ?? '');
    return '';
};

// Build the JSON shape Alpine will consume for the category picker.
$categoryPickerData = array_values(array_map(fn ($c) => [
    'id'    => (int) ($c['id'] ?? 0),
    'slug'  => $c['slug'] ?? '',
    'name'  => $peel($c['name'] ?? ''),
    'emoji' => $c['icon_emoji'] ?? '',
], $parentCategories));

// Translate a `?tip=…` alias to one of the actual category slugs above so
// a personalized campaign URL also pre-selects the right card.
$tipToSlug = [
    'escape' => 'escape-rooms', 'escape-room' => 'escape-rooms', 'escape-rooms' => 'escape-rooms',
    'muzeu'  => 'muzee-expozitii', 'muzee' => 'muzee-expozitii',
    'parc-distractii' => 'parcuri-de-distractii', 'distractii' => 'parcuri-de-distractii',
    'parc-aventura'   => 'parcuri-de-aventura', 'aventura' => 'parcuri-de-aventura',
    'natura' => 'natura-outdoor',
    'acvarii-zoo' => 'acvarii-zoo-animale', 'zoo' => 'acvarii-zoo-animale',
    'ateliere' => 'ateliere-experiente-creative', 'atelier' => 'ateliere-experiente-creative',
    'tururi' => 'tururi-experiente-turistice', 'tur' => 'tururi-experiente-turistice',
    'educatie' => 'educatie-invatare-experientiala',
    'familie'  => 'familie-copii', 'copii' => 'familie-copii',
    'corporate' => 'corporate-grupuri', 'grupuri' => 'corporate-grupuri',
    'cultura'   => 'cultura-arta', 'cultură' => 'cultura-arta',
];
$prefillTip    = strtolower(trim((string) ($_GET['tip'] ?? '')));
$prefillSlug   = $tipToSlug[$prefillTip] ?? '';
$prefillLoc    = trim((string) ($_GET['loc'] ?? ''));
$prefillLocJs  = json_encode($prefillLoc,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$prefillSlugJs = json_encode($prefillSlug, JSON_UNESCAPED_UNICODE);

// SEO — keep this page noindex: it's a conversion-only destination, not
// something we want competing with /devino-partener in search.
$pageTitleRaw    = 'Înregistrare locație — ' . SITE_NAME;
$pageDescription = 'Începe în 5 minute. Spune-ne ce vinzi, cine ești și cum te contactăm. Te ghidăm prin restul.';
$canonicalUrl    = SITE_URL . '/inregistrare-locatie';
$noindex         = true;
$hideFromSitemap = true;

$extraHead = <<<HTML
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,400;1,9..144,600&family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      fontFamily: { display:['Fraunces','serif'], sans:['Archivo','sans-serif'], mono:['JetBrains Mono','monospace'] },
      colors: { ink:'#1a1410', paper:'#f4ecdd', cream:'#fbf6ec', rust:'#c2410c', ember:'#e8590c', forest:'#1f3d2b', gold:'#b88a2e', ticket:'#d6cab0' },
      boxShadow: { 'hard':'6px 6px 0 0 #1a1410', 'hard-sm':'3px 3px 0 0 #1a1410' },
    }}
  }
</script>
<style>
  body { background-color:#f4ecdd; font-family:'Archivo',sans-serif; color:#1a1410; }
  [x-cloak]{display:none!important;}
  ::selection{background:#e8590c; color:#fbf6ec;}
  .grain::before { content:""; position:fixed; inset:0; pointer-events:none; z-index:50; opacity:0.04;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<div class="grain font-sans text-ink antialiased min-h-screen flex flex-col">

<!-- Light nav: just a logo back to home + the landing page link -->
<header class="border-b-2 border-ink/10 bg-cream/60 backdrop-blur">
  <nav class="max-w-5xl mx-auto px-5 sm:px-8 flex items-center justify-between h-16">
    <a href="/" class="flex items-center gap-2 font-display font-black text-xl tracking-tight">
      <span class="inline-grid place-items-center w-8 h-8 bg-ink text-cream rounded-sm font-mono text-sm rotate-[-6deg]">b.</span>
      bilete<span class="text-rust">.online</span>
    </a>
    <a href="/devino-partener" class="text-sm font-semibold hover:text-rust transition">← Înapoi la prezentare</a>
  </nav>
</header>

<main class="flex-1 py-12 sm:py-16">
  <div class="max-w-3xl mx-auto px-5 sm:px-8"
       x-data='onboardingForm(<?= htmlspecialchars(json_encode($categoryPickerData, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= $prefillLocJs ?>, <?= $prefillSlugJs ?>)'>

    <div class="text-center mb-10">
      <p class="font-mono text-sm uppercase tracking-widest text-rust mb-3">Onboarding · 5 minute</p>
      <h1 class="font-display font-black text-4xl sm:text-5xl leading-[0.95]">Hai să-ți punem<br>locația online.</h1>
      <p class="mt-5 text-ink/70 max-w-xl mx-auto">Spune-ne ce vinzi, cine ești și cum te contactăm. Vorbim cu tine în următoarea zi lucrătoare și te ghidăm prin restul.</p>
    </div>

    <!-- Progress -->
    <div class="mb-10">
      <div class="flex items-center justify-between mb-3">
        <span class="font-mono text-xs uppercase tracking-widest text-ink/50" x-text="`Pasul ${step} din 3`">Pasul 1 din 3</span>
        <span class="font-mono text-xs uppercase tracking-widest text-ink/50" x-text="['Activitate','Tu','Locație'][step-1]">Activitate</span>
      </div>
      <div class="h-1.5 bg-ticket rounded-full overflow-hidden">
        <div class="h-full bg-rust transition-all duration-400" :style="`width:${(step/3)*100}%`"></div>
      </div>
    </div>

    <!-- Card -->
    <div class="bg-cream border-2 border-ink rounded-md shadow-hard p-6 sm:p-10" x-cloak>

      <!-- STEP 1 — what they sell -->
      <div x-show="step===1" x-transition.opacity.duration.300ms>
        <h2 class="font-display font-bold text-2xl sm:text-3xl mb-1">Ce vinzi?</h2>
        <p class="text-ink/65 mb-6 text-sm">Alege categoria care se potrivește cel mai bine. O folosim doar ca să-ți pregătim setup-ul.</p>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <template x-for="cat in categories" :key="cat.slug">
            <button type="button"
              @click="form.category_slug = cat.slug; form.category_name = cat.name"
              class="text-left p-4 rounded-md border-2 transition-all duration-150"
              :class="form.category_slug === cat.slug
                ? 'border-rust bg-rust/5 shadow-hard-sm -translate-y-0.5'
                : 'border-ink/20 hover:border-ink hover:-translate-y-0.5'">
              <div class="text-2xl mb-2" x-text="cat.emoji || '🎫'"></div>
              <p class="font-display font-bold text-sm leading-tight" x-text="cat.name"></p>
            </button>
          </template>
        </div>

        <label class="block mt-6">
          <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Sau descrie scurt (opțional)</span>
          <input type="text" x-model="form.category_other" placeholder="ex. Centru de echitație, planetariu, observator"
            class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition" maxlength="120">
        </label>

        <div class="mt-8 flex justify-end">
          <button type="button" @click="goNext()" :disabled="!canGoFromStep1()"
            class="inline-flex items-center gap-2 bg-rust text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">
            Continuă →
          </button>
        </div>
      </div>

      <!-- STEP 2 — who they are -->
      <div x-show="step===2" x-transition.opacity.duration.300ms>
        <h2 class="font-display font-bold text-2xl sm:text-3xl mb-1">Cine ești?</h2>
        <p class="text-ink/65 mb-6 text-sm">Datele tale de contact. Le folosim doar ca să te sunăm și să-ți răspundem.</p>

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Nume și prenume *</span>
            <input type="text" x-model="form.contact_name" required maxlength="120"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
          <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Email *</span>
            <input type="email" x-model="form.email" required maxlength="160"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
          <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Telefon</span>
            <input type="tel" x-model="form.phone" maxlength="40" placeholder="07xx xxx xxx"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
        </div>

        <div class="mt-8 flex items-center justify-between">
          <button type="button" @click="goPrev()" class="text-sm font-semibold text-ink/60 hover:text-ink">← Înapoi</button>
          <button type="button" @click="goNext()" :disabled="!canGoFromStep2()"
            class="inline-flex items-center gap-2 bg-rust text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">
            Continuă →
          </button>
        </div>
      </div>

      <!-- STEP 3 — the location -->
      <div x-show="step===3" x-transition.opacity.duration.300ms>
        <h2 class="font-display font-bold text-2xl sm:text-3xl mb-1">Despre locație</h2>
        <p class="text-ink/65 mb-6 text-sm">Detaliile despre locația ta. Câteva minute și am terminat.</p>

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Numele locației / organizației *</span>
            <input type="text" x-model="form.location_name" required maxlength="160"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
          <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Oraș *</span>
            <input type="text" x-model="form.city" required maxlength="80" placeholder="ex. Cluj-Napoca"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
          <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Site web (opțional)</span>
            <input type="url" x-model="form.website" maxlength="200" placeholder="https://…"
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
          </label>
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Volum estimat de bilete / lună</span>
            <select x-model="form.volume" class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition">
              <option value="">Alege un interval</option>
              <option value="0-100">Până la 100 bilete</option>
              <option value="100-500">Între 100 și 500</option>
              <option value="500-2000">Între 500 și 2.000</option>
              <option value="2000-10000">Între 2.000 și 10.000</option>
              <option value="10000+">Peste 10.000</option>
            </select>
          </label>
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink/60">Spune-ne ce e important (opțional)</span>
            <textarea x-model="form.notes" rows="3" maxlength="800"
              placeholder="ex. avem sloturi la 30 min, vrem să integrăm cu casa de marcat existentă, etc."
              class="mt-1.5 w-full border-2 border-ink/20 rounded-sm px-4 py-3 bg-paper focus:outline-none focus:border-ink focus:bg-cream transition resize-none"></textarea>
          </label>
        </div>

        <div class="mt-5 flex items-start gap-2 text-xs text-ink/60">
          <input type="checkbox" id="gdpr" x-model="form.gdpr" required class="mt-0.5 w-4 h-4 accent-rust">
          <label for="gdpr">Sunt de acord cu prelucrarea datelor în scopul contactării — datele sunt folosite doar pentru a-ți răspunde.</label>
        </div>

        <div class="mt-8 flex items-center justify-between">
          <button type="button" @click="goPrev()" :disabled="submitting" class="text-sm font-semibold text-ink/60 hover:text-ink disabled:opacity-40">← Înapoi</button>
          <button type="button" @click="submit()" :disabled="!canSubmit() || submitting"
            class="inline-flex items-center gap-2 bg-ink text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">
            <span x-show="!submitting">Trimite cererea →</span>
            <span x-show="submitting">Se trimite…</span>
          </button>
        </div>

        <p x-show="errorMessage" x-text="errorMessage" class="mt-4 text-sm text-rust font-semibold"></p>
      </div>

      <!-- DONE -->
      <div x-show="step==='done'" x-transition.opacity.duration.300ms class="text-center py-8">
        <div class="w-16 h-16 rounded-full bg-forest text-cream grid place-items-center text-3xl mx-auto mb-4">✓</div>
        <h2 class="font-display font-black text-3xl sm:text-4xl">Mulțumim, <span x-text="form.contact_name.split(' ')[0] || 'partenere'"></span>!</h2>
        <p class="mt-4 text-ink/70 max-w-md mx-auto">Cererea ta a ajuns la echipa bilete.online. Te contactăm în următoarea zi lucrătoare pe <strong x-text="form.email"></strong>.</p>
        <a href="/devino-partener" class="mt-8 inline-flex items-center gap-2 border-2 border-ink font-bold px-7 py-3 rounded-sm hover:bg-ink hover:text-cream transition">← Înapoi la prezentare</a>
      </div>
    </div>

    <p class="mt-6 text-center text-xs text-ink/50">
      Fără cost de pornire · Activități nelimitate · Comision 2%* plătit de cumpărător
    </p>
  </div>
</main>

<footer class="border-t-2 border-ink/10 py-8 text-center text-xs text-ink/50">
  <p>© <?= date('Y') ?> bilete.online · Construit pe <strong class="text-ink/70">Tixello</strong></p>
</footer>

</div>

<script>
function onboardingForm(categories, prefillLoc, prefillSlug) {
  return {
    step: 1,
    submitting: false,
    errorMessage: '',
    categories: categories || [],
    form: {
      // Step 1
      category_slug: prefillSlug || '',
      category_name: '',
      category_other: '',
      // Step 2
      contact_name: '',
      email: '',
      phone: '',
      // Step 3
      location_name: prefillLoc || '',
      city: '',
      website: '',
      volume: '',
      notes: '',
      gdpr: false,
    },

    init() {
      // If `?tip=` matched a category slug, hydrate the display name too so
      // the user sees the card highlighted on first render.
      if (this.form.category_slug) {
        const cat = this.categories.find(c => c.slug === this.form.category_slug);
        if (cat) this.form.category_name = cat.name;
      }
    },

    canGoFromStep1() {
      return !!(this.form.category_slug || (this.form.category_other && this.form.category_other.trim().length > 1));
    },
    canGoFromStep2() {
      return this.form.contact_name.trim().length > 1
          && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.form.email.trim());
    },
    canSubmit() {
      return this.form.location_name.trim().length > 1
          && this.form.city.trim().length > 1
          && this.form.gdpr === true;
    },

    goNext() {
      if (this.step === 1 && !this.canGoFromStep1()) return;
      if (this.step === 2 && !this.canGoFromStep2()) return;
      this.step++;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    goPrev() {
      if (typeof this.step === 'number' && this.step > 1) {
        this.step--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    async submit() {
      if (!this.canSubmit() || this.submitting) return;
      this.submitting = true;
      this.errorMessage = '';

      const categoryLabel = this.form.category_name
        || this.form.category_other
        || this.form.category_slug
        || '(necunoscut)';

      // Reuse the existing /contact endpoint — body is a single message
      // string with all the fields formatted so the recipient (the
      // marketplace contact inbox) reads a complete lead profile in one go.
      const lines = [
        `🏢 CERERE PARTENERIAT — bilete.online`,
        ``,
        `Locație: ${this.form.location_name}`,
        `Oraș: ${this.form.city}`,
        `Categorie: ${categoryLabel}`,
        this.form.website ? `Website: ${this.form.website}` : null,
        this.form.volume ? `Volum estimat bilete/lună: ${this.form.volume}` : null,
        ``,
        `Contact: ${this.form.contact_name}`,
        `Email: ${this.form.email}`,
        this.form.phone ? `Telefon: ${this.form.phone}` : null,
        ``,
        this.form.notes ? `Mesaj:\n${this.form.notes}` : null,
      ].filter(Boolean);

      const payload = {
        name: this.form.contact_name,
        email: this.form.email,
        phone: this.form.phone || '',
        subject: `Cerere parteneriat — ${this.form.location_name}`,
        message: lines.join('\n'),
        // Tag the source so the marketplace team can filter these leads in
        // the inbox vs generic contact-form messages.
        source: 'partner-signup',
        meta: {
          location_name: this.form.location_name,
          city: this.form.city,
          website: this.form.website || null,
          category_slug: this.form.category_slug || null,
          category_name: this.form.category_name || null,
          category_other: this.form.category_other || null,
          volume_estimate: this.form.volume || null,
          referrer: document.referrer || null,
          utm: Object.fromEntries(new URLSearchParams(window.location.search)),
        },
      };

      try {
        const res = await fetch('/api/proxy.php?action=public.contact', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));

        // Treat both 2xx and "success": true as success — the upstream
        // contact endpoint returns 200 even on success, but some proxies
        // sit in the chain and wrap things differently. Be lenient.
        if (!res.ok && !data.success) {
          throw new Error(data.message || data.error || 'A apărut o problemă la trimitere.');
        }

        this.step = 'done';
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Optional analytics ping if EPAS tracking is loaded.
        try {
          if (window.EPASTracking && typeof EPASTracking.trackLead === 'function') {
            EPASTracking.trackLead('partner-signup', { category: categoryLabel, city: this.form.city });
          }
        } catch (_) {}
      } catch (e) {
        this.errorMessage = e.message || 'Trimiterea nu a reușit. Te rugăm să încerci din nou sau să ne scrii la contact@bilete.online.';
      } finally {
        this.submitting = false;
      }
    }
  }
}
</script>

<?php include __DIR__ . '/includes/cookie-consent.php'; ?>
</body>
</html>
