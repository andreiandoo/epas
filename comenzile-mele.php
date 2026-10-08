<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Comenzile mele — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex"><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="myTickets" style="min-height:80vh">
    <?= part_account_head('Comenzile mele', 'comenzi') ?>

    <div class="wrap">
        <section style="padding:36px 0 96px">
            <p x-show="loading" style="color:var(--fg-2)">Se încarcă comenzile…</p>
            <div class="alert" x-show="error" x-cloak x-text="error"></div>

            <div class="orders" x-show="!loading && orders.length" x-cloak>
                <template x-for="o in orders" :key="o.id">
                    <article class="order panel" :data-order="o.id">
                        <div class="order__head">
                            <div>
                                <span class="order__status" :class="o.is_paid ? 'is-paid' : 'is-off'" x-text="o.is_paid ? 'Plătită' : 'Neplătită'"></span>
                                <h2 x-text="o.event ? o.event.title : 'Comandă'"></h2>
                                <p class="order__meta">
                                    <span x-show="eventDate(o)" x-text="eventDate(o)"></span>
                                    <span x-show="place(o)" x-text="place(o)"></span>
                                </p>
                            </div>
                            <div class="order__side">
                                <b x-text="lei(o.total)"></b>
                                <span class="mono" x-text="'#' + o.id + ' · ' + date(o.created_at)"></span>
                                <span x-text="ticketsLabel(o.tickets_count)"></span>
                                <span x-show="o.discount > 0" x-text="'Reducere ' + lei(o.discount) + (o.promo_code ? ' (' + o.promo_code + ')' : '')" style="color:var(--ok);font-weight:700"></span>
                                <span x-show="o.processing_fee > 0" x-text="'Taxă de procesare ' + lei(o.processing_fee)"></span>
                            </div>
                        </div>
                        <div class="order__actions" x-show="o.is_paid">
                            <button type="button" class="btn btn--sm btn--ghost" @click="toggle(o)" :aria-expanded="open === o.id" x-text="open === o.id ? 'Ascunde biletele' : 'Vezi biletele'"></button>
                            <a class="btn btn--sm" :href="pdf(o)" x-show="o.access_token">Descarcă PDF</a>
                        </div>
                        <div class="order__tickets" x-show="open === o.id" x-cloak>
                            <p x-show="!detail[o.id]" style="color:var(--fg-2)">Se încarcă biletele…</p>
                            <div class="tickets">
                                <template x-for="t in (detail[o.id] || [])" :key="t.code">
                                    <div class="ticket">
                                        <div class="ticket__main">
                                            <div class="ticket__type" x-text="t.type || 'Bilet'"></div>
                                            <div class="ticket__title" x-text="o.event ? o.event.title : ''"></div>
                                            <div class="ticket__meta" x-text="[t.seat_label, eventDate(o), place(o)].filter(Boolean).join(' · ')"></div>
                                            <div class="ticket__code" x-text="t.code"></div>
                                            <a class="link ticket__dl" :href="pdf(o, t.code)" x-show="o.access_token">Descarcă biletul</a>
                                        </div>
                                        <div class="ticket__qr"><div :data-qr="t.code"></div></div>
                                        <div class="belt-bg"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div class="panel empty" x-show="!loading && !orders.length && !error" x-cloak>
                <h2>Încă nu ai comenzi</h2>
                <p>Aici apar comenzile făcute cu adresa de email a contului tău, inclusiv cele plasate fără autentificare.</p>
                <?= part_btn('Vezi competițiile', '/competitii') ?>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
