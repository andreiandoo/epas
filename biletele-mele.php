<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Biletele mele — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="myTickets" style="min-height:80vh">
    <section class="phead phead--slim">
        <div class="wrap" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:20px">
            <div>
                <span class="label">Contul meu</span>
                <h1>Biletele mele</h1>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" x-data="siteHead" @click="logout()">Ieși din cont</button>
        </div>
    </section>

    <div class="wrap">
        <section style="padding:36px 0 96px">
            <p x-show="loading" style="color:var(--fg-2)">Se încarcă comenzile…</p>
            <div class="alert" x-show="error" x-cloak x-text="error"></div>

            <div class="panel" x-show="!loading && orders.length" x-cloak>
                <template x-for="o in orders" :key="o.id">
                    <div class="line">
                        <div>
                            <div class="line__name" x-text="o.event || 'Comandă'"></div>
                            <div class="line__unit">
                                <span class="mono" x-text="'#' + o.id"></span> ·
                                <span x-text="date(o.created_at)"></span> ·
                                <span x-text="ticketsLabel(o.tickets_count)"></span> ·
                                <span x-text="paid(o) ? 'plătită' : 'neplătită'" :style="paid(o) ? 'color:var(--ok);font-weight:700' : 'color:var(--red);font-weight:700'"></span>
                            </div>
                        </div>
                        <div class="line__tools">
                            <div class="line__sum" x-text="lei(o.total)"></div>
                            <a class="btn btn--sm" x-show="paid(o)" :href="'/confirmare?order=' + o.id">Vezi biletele</a>
                        </div>
                    </div>
                </template>
            </div>

            <div class="panel empty" x-show="!loading && !orders.length && !error" x-cloak>
                <h2>Încă nu ai bilete</h2>
                <p>Comenzile făcute cu adresa ta de email apar aici.</p>
                <a class="btn" href="/competitii">Vezi competițiile</a>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
