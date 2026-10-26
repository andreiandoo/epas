{{-- Lângă formular: de ce întrebăm și unde folosim datele. La „Funcții în plus”: ce plătești. --}}
<aside class="lg:sticky lg:top-24 space-y-4" aria-label="Detalii despre pasul curent">
    <template x-if="state.step !== 'extras' && why()">
        <div class="why">
            <p class="text-sm font-semibold flex items-center gap-2"><i class="ph ph-info text-lg text-[color:var(--accent)]" aria-hidden="true"></i><span x-text="why().title"></span></p>
            <ul class="mt-4 space-y-3">
                <template x-for="item in why().items" :key="item.text">
                    <li><i class="ph" :class="item.icon" aria-hidden="true"></i><span><strong x-text="item.lead"></strong> <span x-text="item.text"></span></span></li>
                </template>
            </ul>
        </div>
    </template>

    <template x-if="state.step === 'extras'">
        <section class="card p-5" aria-labelledby="cost-title">
            <h2 id="cost-title" class="text-lg font-bold">Ce plătești</h2>
            <div class="mt-4 flex items-end justify-between gap-4">
                <div>
                    <p class="font-semibold">Comision pe bilet</p>
                    <p class="text-sm text-[color:var(--ink-2)]">din biletele vândute prin Tixello</p>
                </div>
                <p class="text-right">
                    <span class="text-3xl font-bold stat-num" x-text="commissionPreview() + '%'"></span>
                    <span x-show="commissionPreview() > baseCommission()" class="block text-xs text-[color:var(--ink-3)]" x-text="'în loc de ' + baseCommission() + '%'"></span>
                </p>
            </div>
            <p x-show="commissionPreview() > baseCommission()" class="mt-3 rounded-xl bg-[color:var(--accent-soft)] px-3 py-2.5 text-sm" x-text="commissionReason()"></p>
            <p class="mt-3 text-sm text-[color:var(--ink-2)]">Factura de comision vine o dată la 30 de zile, doar dacă ai vândut ceva, cu termen de plată <span x-text="paymentTermsDays"></span> zile.</p>

            <template x-if="paidChosen('monthly').length">
                <div class="mt-4 pt-4 border-t border-[color:var(--line)] flex justify-between gap-4 text-sm">
                    <div><p class="font-semibold">Abonamente lunare</p><p class="text-[color:var(--ink-2)]" x-text="paidChosen('monthly').map(m => m.name).join(', ')"></p></div>
                    <p class="font-bold whitespace-nowrap" x-text="sumLabel('monthly') + ' / lună'"></p>
                </div>
            </template>
            <template x-if="paidChosen('one_time').length">
                <div class="mt-4 pt-4 border-t border-[color:var(--line)] flex justify-between gap-4 text-sm">
                    <div><p class="font-semibold">O singură dată</p><p class="text-[color:var(--ink-2)]" x-text="paidChosen('one_time').map(m => m.name).join(', ')"></p></div>
                    <p class="font-bold whitespace-nowrap" x-text="sumLabel('one_time')"></p>
                </div>
            </template>
            <template x-if="paidChosen('usage').length">
                <div class="mt-4 pt-4 border-t border-[color:var(--line)] text-sm">
                    <p class="font-semibold">Plătești cât folosești</p>
                    <p class="text-[color:var(--ink-2)]" x-text="paidChosen('usage').map(m => m.name + ' (' + priceLabel(m) + ')').join(', ')"></p>
                </div>
            </template>

            <p class="mt-4 pt-4 border-t border-[color:var(--line)] text-sm">
                <strong>Acum nu plătești nimic.</strong>
                <span x-show="paidChosen('monthly').length || paidChosen('one_time').some(m => m.id !== 'custom-site')" class="text-[color:var(--ink-2)]">Abonamentele le plătești cu cardul din panou, când le pornești.</span>
                <span x-show="state.template_custom" class="text-[color:var(--ink-2)] block mt-1">Site-ul făcut de noi îl plătești după ce stabilim împreună ce conține.</span>
                <span x-show="!paidChosen('monthly').length && !paidChosen('one_time').length" class="text-[color:var(--ink-2)]">Primul cost apare abia când vinzi bilete.</span>
            </p>
        </section>
    </template>
</aside>
