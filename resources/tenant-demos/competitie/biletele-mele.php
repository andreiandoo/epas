<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Biletele mele — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex"><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="ticketsList" style="min-height:80vh">
    <?= part_account_head('Biletele mele', 'bilete') ?>

    <div class="wrap">
        <section style="padding:36px 0 96px">
            <p x-show="loading" style="color:var(--fg-2)">Se încarcă biletele…</p>
            <div class="alert" x-show="error" x-cloak x-text="error"></div>

            <template x-for="group in groups" :key="group.key">
                <div x-show="group.items.length" style="margin-bottom:44px">
                    <h2 class="acc-title" x-text="group.title"></h2>
                    <div class="tickets">
                        <template x-for="t in group.items" :key="t.code">
                            <div class="ticket" :class="group.key === 'past' && 'is-past'">
                                <div class="ticket__main">
                                    <div class="ticket__type" x-text="t.type || 'Bilet'"></div>
                                    <div class="ticket__title" x-text="t.event ? t.event.title : ''"></div>
                                    <div class="ticket__meta" x-text="[t.seat_label, eventDate(t), place(t)].filter(Boolean).join(' · ')"></div>
                                    <div class="ticket__code" x-text="t.code"></div>
                                    <a class="link ticket__dl" :href="pdf(t)">Descarcă biletul</a>
                                </div>
                                <div class="ticket__qr"><div :data-qr="t.code"></div></div>
                                <div class="belt-bg"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="panel empty" x-show="!loading && !tickets.length && !error" x-cloak>
                <h2>Încă nu ai bilete</h2>
                <p>Biletele din comenzile plătite cu adresa de email a contului tău apar aici, cu cod QR și descărcare în PDF.</p>
                <?= part_btn('Vezi competițiile', '/competitii') ?>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
