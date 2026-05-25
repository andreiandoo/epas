<?php
/**
 * /activitate/{slug} — single Activity detail + booking sidebar.
 *
 * Server-rendered shell with Alpine.js handling date + slot picker and
 * variant quantity inputs. Booking submission is deferred to A5 — the
 * "Rezervă" button currently triggers a placeholder modal that signals
 * the booking pipeline isn't live yet.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// ============================================================
// SLUG RESOLUTION
// ============================================================
$slug = $_GET['slug'] ?? null;
if (! $slug || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

// ============================================================
// FETCH DETAIL
// ============================================================
$activityResp = api_cached("activity_detail_{$slug}", fn () => api_get('/activities/' . $slug), 60);
if (! ($activityResp['success'] ?? false) || empty($activityResp['data']['activity'])) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

$activity = $activityResp['data']['activity'];

// ============================================================
// HELPERS (page-local — keep activitate.php self-contained)
// ============================================================
$pageT = function ($key, $default = '') use ($activity) {
    return $activity[$key] ?? $default;
};

$pricedFromCents = function (?int $cents): string {
    if (! $cents || $cents <= 0) return '—';
    return number_format($cents / 100, 0, ',', '.') . ' lei';
};

$dayLabels = [
    1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi',
    5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică',
];

$difficultyLabels = [
    'easy' => 'Ușor', 'medium' => 'Mediu', 'hard' => 'Greu', 'expert' => 'Expert',
];

// ============================================================
// PAGE METADATA
// ============================================================
$pageTitleRaw = ($activity['seo']['title'] ?? null)
    ?: ($activity['title'] . ' — ' . SITE_NAME);
$pageDescription = $activity['seo']['description']
    ?? $activity['short_description']
    ?? ($activity['title'] . ' pe ' . SITE_NAME);

$canonicalUrl = SITE_URL . '/activitate/' . $activity['slug'];
$currentPage = 'activitate';
$cssBundle = 'single';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
];
if (! empty($activity['category'])) {
    $breadcrumbs[] = [
        'name' => $activity['category']['name'],
        'url'  => SITE_URL . '/' . $activity['category']['slug'],
    ];
}
$breadcrumbs[] = ['name' => $activity['title'], 'url' => $canonicalUrl];

// JSON-LD Product + AggregateOffer (covers all variant prices in one structured offer).
$variantPrices = array_filter(array_column($activity['variants'] ?? [], 'price_cents'));
$lowPriceCents = $variantPrices ? min($variantPrices) : null;
$highPriceCents = $variantPrices ? max($variantPrices) : null;

$structuredData = [
    [
        '@context'   => 'https://schema.org',
        '@type'      => 'Product',
        'name'       => $activity['title'],
        'description'=> $activity['short_description'] ?? '',
        'image'      => $activity['hero_image_url'] ?: $activity['cover_image_url'] ?: null,
        'category'   => $activity['category']['name'] ?? null,
        'offers'     => $lowPriceCents ? [
            '@type'         => 'AggregateOffer',
            'priceCurrency' => 'RON',
            'lowPrice'      => number_format($lowPriceCents / 100, 2, '.', ''),
            'highPrice'     => number_format($highPriceCents / 100, 2, '.', ''),
            'offerCount'    => count($activity['variants'] ?? []),
            'availability'  => 'https://schema.org/InStock',
        ] : null,
    ],
];

// FAQ JSON-LD when admin populated FAQs.
if (! empty($activity['faqs']) && is_array($activity['faqs'])) {
    $structuredData[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'] ?? '',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a'] ?? ''],
        ], $activity['faqs']),
    ];
}

// Bootstrap Alpine state from server data — slug + variants + booking constraints.
$bookingBootstrap = [
    'slug'              => $activity['slug'],
    'variants'          => array_map(fn ($v) => [
        'id'             => $v['id'],
        'name'           => $v['name'],
        'description'    => $v['description'] ?? null,
        'price_cents'    => (int) $v['price_cents'],
        'capacity_share' => (int) $v['capacity_share'],
        'min_per_order'  => (int) $v['min_per_order'],
        'max_per_order'  => (int) $v['max_per_order'],
        'min_age'        => $v['min_age'],
        'max_age'        => $v['max_age'],
    ], $activity['variants'] ?? []),
    'window'            => $activity['booking_window'] ?? [
        'lead_time_hours'  => 2,
        'max_advance_days' => 60,
        'min_participants' => 1,
        'max_participants' => 10,
    ],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="activityPage(<?= htmlspecialchars(json_encode($bookingBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>)">

<!-- ============================================================ -->
<!-- HERO                                                            -->
<!-- ============================================================ -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_15%,rgba(232,69,39,.22),transparent_30%),radial-gradient(circle_at_18%_70%,rgba(30,74,61,.20),transparent_34%)]" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-14 pb-10">
        <nav class="flex items-center gap-2 text-sm text-ink-soft mb-6 flex-wrap" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a>
            <?php if (! empty($activity['category'])): ?>
                <span aria-hidden="true">/</span>
                <a href="/<?= htmlspecialchars($activity['category']['slug']) ?>" class="hover:text-vermilion"><?= htmlspecialchars($activity['category']['name']) ?></a>
            <?php endif; ?>
            <span aria-hidden="true">/</span>
            <span class="text-ink truncate"><?= htmlspecialchars($activity['title']) ?></span>
        </nav>

        <div class="grid lg:grid-cols-[1.4fr_.6fr] gap-10 items-start">
            <div>
                <?php if (! empty($activity['category'])): ?>
                    <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">
                        <?= htmlspecialchars(strtoupper($activity['category']['name'])) ?>
                        <?php if (! empty($activity['city'])): ?>
                            · <?= htmlspecialchars($activity['city']['name']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-700 leading-[.92]">
                    <?= htmlspecialchars($activity['title']) ?>
                </h1>

                <?php if (! empty($activity['subtitle'])): ?>
                    <p class="mt-3 text-2xl text-ink-soft"><?= htmlspecialchars($activity['subtitle']) ?></p>
                <?php endif; ?>

                <dl class="mt-6 flex flex-wrap gap-3">
                    <?php if ($activity['duration_minutes']): ?>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 px-4 py-2">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">DURATĂ</dt>
                            <dd class="font-700"><?= (int) $activity['duration_minutes'] ?> min</dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($activity['capacity_per_slot']): ?>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 px-4 py-2">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">MAX. / SLOT</dt>
                            <dd class="font-700"><?= (int) $activity['capacity_per_slot'] ?> persoane</dd>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($activity['difficulty_level'])): ?>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 px-4 py-2">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">DIFICULTATE</dt>
                            <dd class="font-700"><?= htmlspecialchars($difficultyLabels[$activity['difficulty_level']] ?? $activity['difficulty_level']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($activity['age_min'] !== null || $activity['age_max'] !== null): ?>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 px-4 py-2">
                            <dt class="font-mono text-[10px] tracking-[.18em] text-ink-soft">VÂRSTĂ</dt>
                            <dd class="font-700">
                                <?php
                                if ($activity['age_min'] !== null && $activity['age_max'] !== null) {
                                    echo (int) $activity['age_min'] . '–' . (int) $activity['age_max'] . ' ani';
                                } elseif ($activity['age_min'] !== null) {
                                    echo '≥ ' . (int) $activity['age_min'] . ' ani';
                                } else {
                                    echo '≤ ' . (int) $activity['age_max'] . ' ani';
                                }
                                ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if (! empty($activity['short_description'])): ?>
                    <p class="mt-6 text-lg text-ink-soft leading-relaxed max-w-3xl">
                        <?= htmlspecialchars($activity['short_description']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="relative min-h-[360px] order-first lg:order-last">
                <?php
                $heroSrc = $activity['hero_image_url'] ?? $activity['cover_image_url'] ?? null;
                if ($heroSrc):
                    ?>
                    <div class="absolute inset-0 rounded-[2rem] overflow-hidden shadow-deep border-2 border-ink">
                        <img src="<?= htmlspecialchars($heroSrc) ?>" alt="<?= htmlspecialchars($activity['title']) ?>"
                             class="w-full h-full object-cover" loading="eager">
                    </div>
                <?php else: ?>
                    <div class="absolute inset-0 rounded-[2rem] bg-paper-2 border-2 border-ink grid place-items-center text-ink-soft font-display text-2xl">
                        <?= htmlspecialchars($activity['title']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- BODY + BOOKING SIDEBAR                                          -->
<!-- ============================================================ -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
    <div class="grid lg:grid-cols-[1.4fr_.6fr] gap-10">

        <!-- LEFT: long-form content -->
        <div>
            <?php if (! empty($activity['description'])): ?>
                <article class="prose-custom max-w-none">
                    <?= $activity['description'] ?>
                </article>
            <?php endif; ?>

            <?php if (! empty($activity['included_items']) || ! empty($activity['not_included']) || ! empty($activity['requirements'])): ?>
                <div class="mt-10 grid sm:grid-cols-3 gap-4">
                    <?php if (! empty($activity['included_items'])): ?>
                        <div class="soft-card p-5">
                            <h3 class="font-display text-xl font-700">Inclus</h3>
                            <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                <?php foreach ($activity['included_items'] as $item): ?>
                                    <li>✓ <?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($activity['not_included'])): ?>
                        <div class="soft-card p-5">
                            <h3 class="font-display text-xl font-700">Neinclus</h3>
                            <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                <?php foreach ($activity['not_included'] as $item): ?>
                                    <li>× <?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($activity['requirements'])): ?>
                        <div class="soft-card p-5">
                            <h3 class="font-display text-xl font-700">Cerințe</h3>
                            <ul class="mt-3 space-y-1 text-sm text-ink-soft">
                                <?php foreach ($activity['requirements'] as $item): ?>
                                    <li>• <?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($activity['schedule'])): ?>
                <div class="mt-10 rounded-3xl border-2 border-ink bg-paper p-6">
                    <h3 class="font-display text-2xl font-700">Program</h3>
                    <dl class="mt-4 grid sm:grid-cols-2 gap-2 text-sm">
                        <?php
                        $byDay = [];
                        foreach ($activity['schedule'] as $row) {
                            $byDay[$row['day_of_week']][] = $row['open_time'] . '–' . $row['close_time'];
                        }
                        for ($d = 1; $d <= 7; $d++):
                            $intervals = $byDay[$d] ?? null;
                            ?>
                            <div class="flex justify-between gap-3 px-3 py-2 rounded-xl <?= $intervals ? 'bg-paper-2' : 'opacity-50' ?>">
                                <dt class="font-700"><?= $dayLabels[$d] ?></dt>
                                <dd class="font-mono"><?= $intervals ? implode(' · ', $intervals) : 'Închis' ?></dd>
                            </div>
                        <?php endfor; ?>
                    </dl>
                </div>
            <?php endif; ?>

            <?php if (! empty($activity['venue'])): ?>
                <div class="mt-10 rounded-3xl border-2 border-ink bg-paper-2/60 p-6">
                    <h3 class="font-display text-2xl font-700">Unde ne întâlnim</h3>
                    <p class="mt-2 font-700"><?= htmlspecialchars($activity['venue']['name'] ?? '') ?></p>
                    <?php if (! empty($activity['venue']['address'])): ?>
                        <p class="text-ink-soft"><?= htmlspecialchars($activity['venue']['address']) ?>
                            <?php if (! empty($activity['venue']['city'])): ?>, <?= htmlspecialchars($activity['venue']['city']) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (! empty($activity['meeting_point'])): ?>
                        <p class="mt-3 text-sm text-ink-soft"><?= htmlspecialchars($activity['meeting_point']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($activity['cancellation_policy'])): ?>
                <div class="mt-6 rounded-3xl border border-ink/15 bg-paper p-6">
                    <h3 class="font-display text-xl font-700">Politică de anulare</h3>
                    <p class="mt-2 text-ink-soft"><?= htmlspecialchars($activity['cancellation_policy']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (! empty($activity['seo']['body'])): ?>
                <div class="mt-12 proseish max-w-none">
                    <?php if (! empty($activity['seo']['body_title'])): ?>
                        <h2><?= htmlspecialchars($activity['seo']['body_title']) ?></h2>
                    <?php endif; ?>
                    <?= strip_tags($activity['seo']['body'], '<p><h2><h3><ul><ol><li><a><strong><em><blockquote><br>') ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($activity['faqs'])): ?>
                <div class="mt-12" x-data="{ open: 0 }">
                    <h2 class="font-display text-3xl font-700">Întrebări frecvente</h2>
                    <div class="mt-6 space-y-3">
                        <?php foreach ($activity['faqs'] as $idx => $faq): ?>
                            <article class="rounded-2xl border-2 border-ink bg-paper overflow-hidden">
                                <button @click="open = open === <?= $idx ?> ? null : <?= $idx ?>"
                                        class="w-full text-left px-5 py-4 flex items-center justify-between gap-4">
                                    <span class="font-display text-lg font-700"><?= htmlspecialchars($faq['q'] ?? '') ?></span>
                                    <span class="text-2xl font-700" x-text="open === <?= $idx ?> ? '−' : '+'"></span>
                                </button>
                                <div x-show="open === <?= $idx ?>" x-collapse class="px-5 pb-5 text-ink-soft">
                                    <?= htmlspecialchars($faq['a'] ?? '') ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: booking sidebar -->
        <aside class="lg:sticky lg:top-24 h-fit">
            <div class="rounded-[2rem] border-2 border-ink bg-paper shadow-deep overflow-hidden">
                <div class="px-6 pt-6 pb-4 border-b-2 border-dashed border-ink/15">
                    <p class="font-mono text-[11px] tracking-[.18em] text-ink-soft">REZERVĂ ONLINE</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="font-display text-4xl font-700">
                            <?php if (! empty($activity['cheapest_price_cents'])): ?>
                                de la <?= $pricedFromCents($activity['cheapest_price_cents']) ?>
                            <?php else: ?>
                                <span class="text-ink-soft">—</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <p class="text-sm text-ink-soft">Plătești online, primești QR instant.</p>
                </div>

                <div class="px-6 py-5 space-y-5">
                    <!-- Date picker -->
                    <div>
                        <label for="booking-date" class="block text-sm font-700 mb-2">Alege data</label>
                        <input id="booking-date" type="date" class="field"
                               x-model="selectedDate" @change="loadSlots()"
                               :min="minDate" :max="maxDate">
                    </div>

                    <!-- Slot picker -->
                    <div>
                        <p class="text-sm font-700 mb-2">Alege ora</p>
                        <div x-show="loadingSlots" class="text-ink-soft text-sm py-3">Se încarcă sloturile…</div>
                        <div x-show="! loadingSlots && slots.length === 0 && selectedDate" class="text-ink-soft text-sm py-3">
                            Nu sunt sloturi disponibile în această zi.
                        </div>
                        <div x-show="! loadingSlots && slots.length > 0" class="grid grid-cols-3 gap-2">
                            <template x-for="slot in slots" :key="slot.start_time">
                                <button type="button"
                                        @click="slot.is_bookable && (selectedSlot = slot.start_time)"
                                        :disabled="! slot.is_bookable"
                                        :class="{
                                            'bg-vermilion text-paper border-vermilion': selectedSlot === slot.start_time,
                                            'bg-paper-2 border-ink/15 hover:border-vermilion/60': selectedSlot !== slot.start_time && slot.is_bookable,
                                            'bg-paper-2/40 border-ink/10 text-ink-soft cursor-not-allowed line-through': ! slot.is_bookable,
                                        }"
                                        class="rounded-xl border-2 px-2 py-2 text-sm font-mono"
                                        x-text="slot.start_time.substring(0,5)">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Variants picker (only after slot selected) -->
                    <div x-show="selectedSlot" x-collapse>
                        <p class="text-sm font-700 mb-2">Bilete</p>
                        <div class="space-y-3">
                            <template x-for="variant in variants" :key="variant.id">
                                <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-paper-2/60 border border-ink/10">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-700 truncate" x-text="variant.name"></p>
                                        <p class="text-sm text-ink-soft" x-text="money(variant.price_cents)"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="decrement(variant.id)"
                                                class="w-8 h-8 rounded-full border-2 border-ink/20 grid place-items-center hover:bg-ink hover:text-paper transition"
                                                aria-label="Mai puține">−</button>
                                        <span class="w-6 text-center font-mono font-700" x-text="quantities[variant.id] || 0"></span>
                                        <button type="button" @click="increment(variant.id)"
                                                class="w-8 h-8 rounded-full border-2 border-ink/20 grid place-items-center hover:bg-ink hover:text-paper transition"
                                                aria-label="Mai multe">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Total + CTA -->
                        <div class="mt-5 pt-4 border-t-2 border-dashed border-ink/15">
                            <div class="flex justify-between items-baseline">
                                <span class="font-display text-lg">Total</span>
                                <span class="font-display text-3xl font-700" x-text="money(totalCents)"></span>
                            </div>
                            <p class="mt-1 text-xs text-ink-soft" x-text="participantsLabel"></p>

                            <button type="button" @click="submitBooking()"
                                    :disabled="! canSubmit"
                                    :class="canSubmit ? 'bg-vermilion text-paper hover:bg-vermilion-d' : 'bg-paper-2 text-ink-soft cursor-not-allowed'"
                                    class="mt-4 w-full rounded-full px-6 py-4 font-700 text-lg transition">
                                Rezervă acum
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-3 px-2 text-xs text-ink-soft text-center">
                După plată primești biletele cu QR pe email și în contul tău <?= htmlspecialchars(SITE_NAME) ?>.
            </p>
        </aside>
    </div>
</section>

</main>

<!-- ============================================================ -->
<!-- ALPINE.JS COMPONENT                                             -->
<!-- ============================================================ -->
<script>
function activityPage(bootstrap) {
    const today = new Date();
    const todayStr = today.toISOString().substring(0, 10);
    const maxDate = new Date(today);
    maxDate.setDate(maxDate.getDate() + (bootstrap.window.max_advance_days || 60));

    return {
        slug: bootstrap.slug,
        variants: bootstrap.variants,
        window: bootstrap.window,

        // state
        selectedDate: todayStr,
        selectedSlot: null,
        slots: [],
        loadingSlots: false,
        quantities: {},

        // constants
        minDate: todayStr,
        maxDate: maxDate.toISOString().substring(0, 10),

        init() {
            this.loadSlots();
        },

        async loadSlots() {
            this.selectedSlot = null;
            this.slots = [];
            this.loadingSlots = true;
            try {
                const url = `/api/proxy.php?action=activity.slots&slug=${encodeURIComponent(this.slug)}&date=${encodeURIComponent(this.selectedDate)}`;
                const r = await fetch(url);
                const j = await r.json();
                this.slots = (j?.data?.slots) || [];
            } catch (e) {
                console.error('slots load failed', e);
                this.slots = [];
            } finally {
                this.loadingSlots = false;
            }
        },

        increment(variantId) {
            const variant = this.variants.find(v => v.id === variantId);
            if (! variant) return;
            const current = this.quantities[variantId] || 0;
            const max = Math.max(1, variant.max_per_order || 10);
            // Capacity check (across all variants × their capacity_share):
            const remainingSeats = this.currentSlotRemaining - this.totalSeatsUsed + (current * (variant.capacity_share || 1));
            const maxByCapacity = Math.floor(remainingSeats / (variant.capacity_share || 1));
            this.quantities[variantId] = Math.min(current + 1, max, Math.max(0, maxByCapacity));
        },

        decrement(variantId) {
            const current = this.quantities[variantId] || 0;
            this.quantities[variantId] = Math.max(0, current - 1);
        },

        get currentSlot() {
            return this.slots.find(s => s.start_time === this.selectedSlot);
        },

        get currentSlotRemaining() {
            return this.currentSlot ? (this.currentSlot.capacity_remaining || 0) : 0;
        },

        get totalSeatsUsed() {
            return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.capacity_share || 1)), 0);
        },

        get totalCents() {
            return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.price_cents || 0)), 0);
        },

        get totalQuantity() {
            return Object.values(this.quantities).reduce((a, b) => a + b, 0);
        },

        get participantsLabel() {
            const n = this.totalSeatsUsed;
            if (! n) return 'Selectează biletele';
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            if (n < min) return `Minim ${min} participanți`;
            if (n > max) return `Maxim ${max} participanți`;
            return `${n} ${n === 1 ? 'participant' : 'participanți'}`;
        },

        get canSubmit() {
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            return this.selectedSlot
                && this.totalSeatsUsed >= min
                && this.totalSeatsUsed <= max
                && this.totalSeatsUsed <= this.currentSlotRemaining
                && this.totalCents > 0;
        },

        money(cents) {
            const v = (cents || 0) / 100;
            return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 0 }).format(v);
        },

        submitBooking() {
            // A5 wires this to the cart + checkout pipeline. For now we surface a
            // friendly notice so beta testers know exactly what's missing.
            alert('Rezervările online pentru activități vor fi disponibile în curând.\n\nData: ' + this.selectedDate + '\nSlot: ' + this.selectedSlot + '\nTotal: ' + this.money(this.totalCents));
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
