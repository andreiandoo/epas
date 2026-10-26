{{-- 1. Contul --}}
<form x-show="state.step === 'account'" @submit.prevent="send('/inregistrare/cont', account)" novalidate>
    <h1 class="text-2xl sm:text-3xl font-bold">Să-ți facem contul</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Cu el intri în panoul Tixello. După ce trimiți înscrierea, te mai întrebăm doar dacă ceva nu e clar.</p>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label" for="first_name">Prenume</label>
            <input id="first_name" x-ref="firstField" class="field" x-model="account.first_name" autocomplete="given-name" :aria-invalid="!!errors.first_name">
            <template x-if="errors.first_name"><p class="err" x-text="errors.first_name"></p></template>
        </div>
        <div>
            <label class="label" for="last_name">Nume</label>
            <input id="last_name" class="field" x-model="account.last_name" autocomplete="family-name" :aria-invalid="!!errors.last_name">
            <template x-if="errors.last_name"><p class="err" x-text="errors.last_name"></p></template>
        </div>
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="field" x-model="account.email" autocomplete="email" inputmode="email" :aria-invalid="!!errors.email">
            <template x-if="errors.email"><p class="err" x-text="errors.email"></p></template>
        </div>
        <div>
            <label class="label" for="phone">Telefon</label>
            <input id="phone" type="tel" class="field" x-model="account.phone" autocomplete="tel" inputmode="tel" placeholder="07xx xxx xxx" :aria-invalid="!!errors.phone">
            <template x-if="errors.phone"><p class="err" x-text="errors.phone"></p></template>
        </div>
        <div>
            <label class="label" for="password">Parolă</label>
            <div class="relative">
                <input id="password" :type="showPassword ? 'text' : 'password'" class="field pr-20" x-model="account.password" autocomplete="new-password" :aria-invalid="!!errors.password">
                <button type="button" @click="showPassword = !showPassword" class="absolute right-1 top-1 h-[46px] px-3 text-sm text-[color:var(--ink-2)]" x-text="showPassword ? 'Ascunde' : 'Arată'"></button>
            </div>
            <p class="hint" :class="account.password.length >= 8 && '!text-[color:var(--success)]'"><span x-text="account.password.length >= 8 ? '✓' : '•'"></span> Minim 8 caractere</p>
            <template x-if="errors.password"><p class="err" x-text="errors.password"></p></template>
        </div>
        <div>
            <label class="label" for="password_confirmation">Încă o dată parola</label>
            <input id="password_confirmation" :type="showPassword ? 'text' : 'password'" class="field" x-model="account.password_confirmation" autocomplete="new-password">
        </div>
    </div>
    <label class="flex gap-3 items-start mt-6 cursor-pointer">
        <input type="checkbox" class="mt-1 h-5 w-5 accent-[color:var(--accent)] shrink-0" x-model="account.accept_terms" :aria-invalid="!!errors.accept_terms">
        <span class="text-sm text-[color:var(--ink-2)]">Am citit și sunt de acord cu <a href="{{ config('onboarding.terms_url') }}" target="_blank" rel="noopener" class="underline text-[color:var(--ink)]">Termenii și condițiile</a> și cu <a href="{{ config('onboarding.privacy_url') }}" target="_blank" rel="noopener" class="underline text-[color:var(--ink)]">Politica de confidențialitate</a>.</span>
    </label>
    <template x-if="errors.accept_terms"><p class="err" x-text="errors.accept_terms"></p></template>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="showIntro = true">Înapoi</button>
        <button type="submit" class="btn btn-primary sm:ml-auto sm:min-w-[220px]" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Creează contul</span></button>
    </div>
</form>

{{-- 1b. Codul din email --}}
<form x-show="state.step === 'verify'" @submit.prevent="verify()" novalidate class="max-w-md">
    <span class="icon-box mb-4" aria-hidden="true"><i class="ph ph-envelope-simple-open"></i></span>
    <h1 class="text-2xl sm:text-3xl font-bold">Verifică-ți emailul</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Ți-am trimis un cod de 6 cifre la <strong class="text-[color:var(--ink)]" x-text="state.email"></strong>. Dacă nu-l vezi în câteva secunde, uită-te și la Spam.</p>
    <label class="label" for="code">Codul din email</label>
    <input id="code" x-ref="code" class="field text-center text-3xl tracking-[0.5em] font-semibold" maxlength="6" inputmode="numeric" autocomplete="one-time-code" x-model="code"
           @input="code = code.replace(/\D/g, '').slice(0, 6); if (code.length === 6) verify()" :aria-invalid="!!errors.code">
    <template x-if="errors.code"><p class="err" x-text="errors.code"></p></template>
    <button type="submit" class="btn btn-primary w-full mt-6" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Confirmă emailul</span></button>
    <p class="mt-4 text-center text-sm text-[color:var(--ink-2)]" role="status">
        <span x-show="resendIn > 0">Poți cere alt cod în <span x-text="resendIn"></span> s</span>
        <button type="button" x-show="resendIn === 0" @click="resend()" class="link">Trimite-mi alt cod</button>
        <span x-show="notice" class="block mt-1" x-text="notice"></span>
    </p>
</form>

{{-- 2. Organizatorul --}}
<form x-show="state.step === 'organizer'" @submit.prevent="send('/inregistrare/organizator', organizer)" novalidate>
    <h1 class="text-2xl sm:text-3xl font-bold">Spune-ne despre activitatea ta</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Așa știm ce să-ți pregătim în panou. Poți schimba oricând.</p>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label" for="tenant_type">Ce faci?</label>
            <select id="tenant_type" class="field" x-model="organizer.tenant_type" :aria-invalid="!!errors.tenant_type">
                <option value="">Alege…</option>
                <template x-for="t in tenantTypes" :key="t.value"><option :value="t.value" x-text="t.label" :selected="t.value === organizer.tenant_type"></option></template>
            </select>
            <p class="hint" x-show="!errors.tenant_type">Dacă faci mai multe, alege-o pe cea principală. Ai un muzeu sau o locație de agrement? Pentru tine e <a href="https://bilete.online/autentificare?mode=register&amp;ca=venue" target="_blank" rel="noopener" class="underline text-[color:var(--ink)]">bilete.online</a>.</p>
            <template x-if="errors.tenant_type"><p class="err" x-text="errors.tenant_type"></p></template>
        </div>
        <div>
            <label class="label" for="public_name">Cum te știe publicul?</label>
            <input id="public_name" class="field" x-model="organizer.public_name" placeholder="ex. Teatrul Mic" :aria-invalid="!!errors.public_name">
            <p class="hint" x-show="!errors.public_name">Nu trebuie să fie numele firmei.</p>
            <template x-if="errors.public_name"><p class="err" x-text="errors.public_name"></p></template>
        </div>
    </div>
    <fieldset class="mt-6">
        <legend class="label">Pe unde vei vinde biletele?</legend>
        <div class="grid sm:grid-cols-2 gap-3">
            <template x-for="m in workMethods" :key="m.value">
                <label class="opt pr-12" :data-on="organizer.work_method === m.value">
                    <input type="radio" class="sr-only" name="work_method" :value="m.value" x-model="organizer.work_method">
                    <span class="check" aria-hidden="true" x-text="organizer.work_method === m.value ? '✓' : ''"></span>
                    <span class="text-2xl font-bold block" x-text="m.fee"></span>
                    <span class="font-semibold block mt-1" x-text="m.label"></span>
                    <span class="text-sm text-[color:var(--ink-2)] block mt-1" x-text="m.text"></span>
                </label>
            </template>
        </div>
        <template x-if="errors.work_method"><p class="err" x-text="errors.work_method"></p></template>
    </fieldset>
    <div class="mt-5 sm:max-w-xs">
        <label class="label" for="estimated">Cam câte bilete vinzi pe lună? <span class="text-[color:var(--ink-3)] font-normal">opțional</span></label>
        <input id="estimated" type="number" min="0" inputmode="numeric" class="field" x-model="organizer.estimated_monthly_tickets" placeholder="ex. 500">
    </div>
    <div class="flex justify-end mt-7">
        <button type="submit" class="btn btn-primary sm:min-w-[220px] w-full sm:w-auto" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</form>

{{-- 3. Facturarea --}}
<form x-show="state.step === 'billing'" @submit.prevent="send('/inregistrare/facturare', billing)" novalidate>
    <h1 class="text-2xl sm:text-3xl font-bold">Pe cine facem contractul?</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Pe firma ta sau pe numele tău, dacă nu ai firmă.</p>
    <fieldset>
        <legend class="sr-only">Forma juridică</legend>
        <div class="grid grid-cols-3 gap-3">
            <template x-for="e in entities" :key="e.value">
                <label class="opt text-center px-3" :data-on="billing.legal_entity_type === e.value">
                    <input type="radio" class="sr-only" name="legal_entity_type" :value="e.value" x-model="billing.legal_entity_type" @change="errors = {}">
                    <i class="ph text-2xl text-[color:var(--accent)]" :class="e.icon" aria-hidden="true"></i>
                    <span class="font-semibold block mt-1" x-text="e.label"></span>
                    <span class="text-xs text-[color:var(--ink-2)] hidden sm:block" x-text="e.text"></span>
                </label>
            </template>
        </div>
        <template x-if="errors.legal_entity_type"><p class="err" x-text="errors.legal_entity_type"></p></template>
    </fieldset>

    <div x-show="billing.legal_entity_type === 'srl' || billing.legal_entity_type === 'pfa'" class="mt-6">
        <label class="label" for="cui">CUI-ul firmei</label>
        <div class="flex gap-2">
            <input id="cui" class="field" x-model="billing.cui" placeholder="ex. 12345678" inputmode="numeric" @keydown.enter.prevent="lookupCui()" @input="company = null" :aria-invalid="!!errors.cui">
            <button type="button" class="btn btn-line shrink-0" @click="lookupCui()" :aria-busy="cuiBusy"><span x-show="cuiBusy" class="spin" aria-hidden="true"></span><span>Caută</span></button>
        </div>
        <p class="hint" x-show="!errors.cui && !company && !cuiBusy">Restul datelor le luăm de la ANAF.</p>
        <template x-if="errors.cui"><p class="err" x-text="errors.cui"></p></template>

        {{-- Cât așteptăm ANAF --}}
        <div x-show="cuiBusy" class="mt-4 rounded-xl border border-[color:var(--line)] p-4 space-y-3" aria-hidden="true">
            <div class="skel h-5 w-2/3"></div><div class="skel h-4 w-1/2"></div><div class="skel h-4 w-5/6"></div>
        </div>
        <div x-show="company && !cuiBusy" class="mt-4 rounded-xl border border-[color:var(--success)]/40 bg-[color:var(--success-soft)] p-4 text-sm" role="status">
            <p class="font-semibold text-base flex items-center gap-2"><i class="ph ph-seal-check text-[color:var(--success)] text-xl" aria-hidden="true"></i><span x-text="company?.company_name"></span></p>
            <dl class="mt-3 grid grid-cols-[auto,1fr] gap-x-4 gap-y-1">
                <dt class="text-[color:var(--ink-2)]">Reg. Com.</dt><dd x-text="company?.reg_com || '—'"></dd>
                <dt class="text-[color:var(--ink-2)]">TVA</dt><dd x-text="company?.vat_payer ? 'plătitoare de TVA' : 'neplătitoare de TVA'"></dd>
                <dt class="text-[color:var(--ink-2)]">Sediu</dt><dd x-text="[company?.address, company?.city, company?.county, company?.postal_code].filter(Boolean).join(', ') || '—'"></dd>
            </dl>
            <p class="text-[color:var(--ink-2)] mt-3">Așa apare firma la ANAF. Dacă e ceva greșit, ne spui după aprobare și corectăm.</p>
        </div>
    </div>

    <div x-show="billing.legal_entity_type === 'pf'" class="mt-6 grid sm:grid-cols-[1.4fr_.6fr_1fr] gap-4">
        <div>
            <label class="label" for="cnp">CNP</label>
            <input id="cnp" class="field" inputmode="numeric" maxlength="13" x-model="billing.cnp" autocomplete="off" :aria-invalid="!!errors.cnp">
            <template x-if="errors.cnp"><p class="err" x-text="errors.cnp"></p></template>
        </div>
        <div>
            <label class="label" for="id_series">Serie CI</label>
            <input id="id_series" class="field uppercase" maxlength="2" x-model="billing.id_series" placeholder="RX" :aria-invalid="!!errors.id_series">
            <template x-if="errors.id_series"><p class="err" x-text="errors.id_series"></p></template>
        </div>
        <div>
            <label class="label" for="id_number">Număr CI</label>
            <input id="id_number" class="field" inputmode="numeric" maxlength="6" x-model="billing.id_number" placeholder="123456" :aria-invalid="!!errors.id_number">
            <template x-if="errors.id_number"><p class="err" x-text="errors.id_number"></p></template>
        </div>
    </div>

    <div x-show="needsAddress()" class="mt-5 grid sm:grid-cols-2 gap-4">
        <p x-show="billing.legal_entity_type !== 'pf'" class="sm:col-span-2 text-sm text-[color:var(--ink-2)]">ANAF nu ne-a dat adresa completă. Completeaz-o tu, te rog.</p>
        <div class="sm:col-span-2" x-show="billing.legal_entity_type === 'pf' || !company?.address">
            <label class="label" for="address" x-text="billing.legal_entity_type === 'pf' ? 'Adresa din buletin' : 'Adresa sediului'"></label>
            <input id="address" class="field" x-model="billing.address" autocomplete="street-address" placeholder="Strada, numărul, blocul, apartamentul" :aria-invalid="!!errors.address">
            <template x-if="errors.address"><p class="err" x-text="errors.address"></p></template>
        </div>
        <div x-show="billing.legal_entity_type === 'pf' || !company?.county">
            <label class="label" for="state">Județul</label>
            <select id="state" class="field" x-model="billing.state" :aria-invalid="!!errors.state">
                <option value="">Alege…</option>
                <template x-for="c in counties" :key="c"><option :value="c" x-text="c" :selected="c === billing.state"></option></template>
            </select>
            <template x-if="errors.state"><p class="err" x-text="errors.state"></p></template>
        </div>
        <div x-show="billing.legal_entity_type === 'pf' || !company?.city">
            <label class="label" for="city">Localitatea</label>
            <input id="city" class="field" x-model="billing.city" autocomplete="address-level2" :aria-invalid="!!errors.city">
            <template x-if="errors.city"><p class="err" x-text="errors.city"></p></template>
        </div>
    </div>

    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="submit" class="btn btn-primary sm:ml-auto sm:min-w-[220px]" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</form>

{{-- 4. Site-ul --}}
<form x-show="state.step === 'site'" @submit.prevent="saveSite()" novalidate>
    <h1 class="text-2xl sm:text-3xl font-bold">Unde vor cumpăra oamenii bilete?</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Alege adresa site-ului de bilete. Îl pornim după ce îți aprobăm contul.</p>
    <fieldset>
        <legend class="sr-only">Adresa site-ului</legend>
        <div class="grid sm:grid-cols-2 gap-3">
            <label class="opt p-5 pr-12" :data-on="site.mode === 'subdomain'">
                <input type="radio" class="sr-only" value="subdomain" x-model="site.mode">
                <span class="check" aria-hidden="true" x-text="site.mode === 'subdomain' ? '✓' : ''"></span>
                <span class="icon-box mb-3" aria-hidden="true"><i class="ph ph-cloud-check"></i></span>
                <span class="font-semibold block text-lg">Găzduit de noi</span>
                <span class="text-sm text-[color:var(--ink-2)] block mt-1">Nu ai nevoie de domeniu sau de server. Site-ul va fi la <strong class="text-[color:var(--ink)] break-all" x-text="(site.subdomain || 'numele-tau') + '.' + baseDomain"></strong>.</span>
                <span class="flex flex-wrap gap-2 mt-3"><span class="price price--commission">+1 punct la comision</span><span class="price">fără abonament</span></span>
            </label>
            <label class="opt p-5 pr-12" :data-on="site.mode === 'own'">
                <input type="radio" class="sr-only" value="own" x-model="site.mode">
                <span class="check" aria-hidden="true" x-text="site.mode === 'own' ? '✓' : ''"></span>
                <span class="icon-box mb-3" aria-hidden="true"><i class="ph ph-globe-hemisphere-east"></i></span>
                <span class="font-semibold block text-lg">Am domeniul meu</span>
                <span class="text-sm text-[color:var(--ink-2)] block mt-1">Ai deja o adresă, de exemplu <em>teatrulmeu.ro</em>? O legăm de vânzarea de bilete și îți trimitem pașii pe email.</span>
                <span class="flex flex-wrap gap-2 mt-3"><span class="price">fără cost</span></span>
            </label>
        </div>
    </fieldset>
    <div x-show="site.mode === 'subdomain'" class="mt-5">
        <label class="label" for="subdomain">Adresa site-ului</label>
        <div class="flex items-stretch">
            <input id="subdomain" class="field rounded-r-none" x-model="site.subdomain" @input.debounce.500ms="checkSubdomain()" @blur="checkSubdomain()" autocomplete="off" :aria-invalid="subdomainStatus === 'taken' || !!errors.subdomain">
            <span class="px-4 flex items-center border border-l-0 border-[color:var(--line)] rounded-r-[10px] text-[color:var(--ink-2)] bg-[color:var(--surface-2)]" x-text="'.' + baseDomain"></span>
        </div>
        <p class="hint flex items-center gap-2 min-h-[22px]" role="status">
            <span x-show="subdomainStatus === 'checking'" class="skel inline-block h-3 w-40" aria-hidden="true"></span>
            <span x-show="subdomainStatus === 'checking'" class="sr-only">Verificăm adresa</span>
            <span x-show="subdomainStatus === 'free'" class="text-[color:var(--success)]">✓ Adresa e liberă. Am propus-o din numele tău, o poți schimba.</span>
            <span x-show="subdomainStatus === 'taken'" class="text-[color:var(--error)]">Adresa e luată sau nu se poate folosi. Încearcă alta, doar cu litere mici, cifre și cratimă.</span>
            <span x-show="subdomainStatus === ''">Doar litere mici, cifre și cratimă.</span>
        </p>
        <template x-if="errors.subdomain"><p class="err" x-text="errors.subdomain"></p></template>
    </div>
    <div x-show="site.mode === 'own'" class="mt-5">
        <label class="label" for="domain">Adresa site-ului tău</label>
        <input id="domain" class="field" x-model="site.domain" placeholder="ex. bilete.teatrulmeu.ro" autocomplete="url" :aria-invalid="!!errors.domain">
        <p class="hint" x-show="!errors.domain">Poți folosi o subadresă, de exemplu <em>bilete.teatrulmeu.ro</em>. Site-ul pe care îl ai acum rămâne cum e.</p>
        <template x-if="errors.domain"><p class="err" x-text="errors.domain"></p></template>
    </div>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="button" class="link sm:ml-auto" @click="send('/inregistrare/site', { mode: 'later' })">Hotărăsc mai târziu</button>
        <button type="submit" class="btn btn-primary sm:min-w-[220px]" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</form>

{{-- 5. Aspectul (larg) --}}
<div x-show="state.step === 'template'">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Cum vrei să arate site-ul?</h1>
            <p class="text-[color:var(--ink-2)] mt-2 max-w-2xl">Alege modelul cel mai apropiat de ce faci. Modelul nu costă nimic. Logo-ul, pozele, culorile și textele le pui după aprobare.</p>
        </div>
        <div class="hscroll max-w-full" x-ref="tplCats">
            <div class="hscroll__track flex gap-2 pb-1" @scroll="hscroll($event)">
                <template x-for="cat in ['Toate', ...templateCategories()]" :key="cat">
                    <button type="button" class="chip" :aria-pressed="tplCategory === cat" @click="tplCategory = cat" x-text="cat"></button>
                </template>
            </div>
            <div class="hscroll__thumb" aria-hidden="true"></div>
        </div>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6" role="radiogroup" aria-label="Modele de site">
        <template x-for="t in templates.filter(t => tplCategory === 'Toate' || t.category === tplCategory)" :key="t.slug">
            <div class="tile overflow-hidden" :data-on="template === t.slug">
                <button type="button" role="radio" :aria-checked="template === t.slug" @click="template = t.slug" class="block w-full text-left">
                    <div class="aspect-[16/10] relative bg-[color:var(--surface-2)]" x-data="{ loaded: false }">
                        <div x-show="!loaded" class="absolute inset-0 skel rounded-none" aria-hidden="true"></div>
                        <img x-show="t.preview" :src="t.preview" :alt="'Cum arată modelul ' + t.name" loading="lazy" @load="loaded = true" x-on:error="loaded = true" class="w-full h-full object-cover object-top" :class="loaded ? 'opacity-100' : 'opacity-0'" style="transition: opacity .3s">
                    </div>
                    <div class="p-4 pr-12">
                        <span class="text-xs font-medium text-[color:var(--ink-3)]" x-text="t.category"></span>
                        <span class="font-semibold block" x-text="t.name"></span>
                    </div>
                </button>
                <span class="check" style="top:auto; bottom: 22px" aria-hidden="true" x-text="template === t.slug ? '✓' : ''"></span>
                <a x-show="t.demo_url" :href="t.demo_url" target="_blank" rel="noopener" class="absolute top-3 right-3 btn btn-line !min-h-0 !py-2 !px-3 text-xs">Vezi demo <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            </div>
        </template>
        {{-- La final, după modele: site-ul făcut de la zero --}}
        <div class="tile p-5 flex flex-col justify-between" :data-on="template === '__custom'" style="background: linear-gradient(160deg, var(--accent-soft), var(--surface) 60%)">
            <button type="button" role="radio" :aria-checked="template === '__custom'" @click="template = '__custom'" class="text-left pr-10">
                <span class="icon-box mb-3" aria-hidden="true"><i class="ph ph-magic-wand"></i></span>
                <span class="text-xs font-medium text-[color:var(--ink-3)] block">Nu găsești ce cauți?</span>
                <span class="font-semibold block text-lg">Îl facem de la zero, doar pentru tine</span>
                <span class="text-sm text-[color:var(--ink-2)] block mt-1">Design propriu, pe brandul tău, cu paginile de care ai nevoie. Te sunăm după aprobare ca să stabilim ce conține.</span>
                <span class="price mt-3" x-text="money(customSite.price, customSite.currency) + ', o singură dată'"></span>
            </button>
            <span class="check" aria-hidden="true" x-text="template === '__custom' ? '✓' : ''"></span>
        </div>
    </div>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="button" class="link sm:ml-auto" @click="send('/inregistrare/template', { slug: null })">Aleg mai târziu</button>
        <button type="button" class="btn btn-primary sm:min-w-[220px]" @click="saveTemplate()" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</div>

{{-- 6. Plățile --}}
<div x-show="state.step === 'payments'">
    <h1 class="text-2xl sm:text-3xl font-bold">Cum ajung banii din bilete la tine?</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Plata cu cardul trece printr-un procesator de plăți. El încasează banii de la cumpărător și ți-i virează în cont. Lucrezi deja cu unul dintre ei? Alege-l. Dacă nu, nicio grijă, te ajutăm după aprobare.</p>
    <div class="grid sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Procesator de plăți">
        <button type="button" role="radio" :aria-checked="processor === 'none'" class="opt text-left pr-12 sm:col-span-2" :data-on="processor === 'none'" @click="processor = 'none'">
            <span class="check" aria-hidden="true" x-text="processor === 'none' ? '✓' : ''"></span>
            <span class="font-semibold block">Nu am încă un procesator</span>
            <span class="text-sm text-[color:var(--ink-2)]">Îți spunem ce variante ai și cum îți faci cont, după ce te aprobăm.</span>
        </button>
        <template x-for="p in processors" :key="p.value">
            <button type="button" role="radio" :aria-checked="processor === p.value" class="opt text-left pr-12" :data-on="processor === p.value" @click="processor = p.value">
                <span class="check" aria-hidden="true" x-text="processor === p.value ? '✓' : ''"></span>
                <span class="font-semibold block" x-text="p.label"></span>
                <span class="text-sm text-[color:var(--ink-2)]" x-text="processorText[p.value] || ''"></span>
            </button>
        </template>
    </div>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="button" class="btn btn-primary sm:ml-auto sm:min-w-[220px]" @click="processor ? send('/inregistrare/plati', { processor: processor === 'none' ? null : processor }) : (flash = 'Alege o variantă. Dacă nu ai un procesator, alege prima.')" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</div>

{{-- 7. Funcții în plus (larg, cu rezumatul alături) --}}
<div x-show="state.step === 'extras'">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Ai nevoie de ceva în plus?</h1>
            <p class="text-[color:var(--ink-2)] mt-2 max-w-2xl">Nu e obligatoriu, ai deja tot ce trebuie ca să vinzi. Unele funcții au abonament. Cele marcate cu <span class="price price--commission">+1 punct</span> nu au: dacă alegi una, comisionul crește cu un punct, oricâte ai alege.</p>
        </div>
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Caută o funcție</span>
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[color:var(--ink-3)]" aria-hidden="true"></i>
            <input type="search" class="field !rounded-full pl-10" placeholder="Caută: facturi, SMS, hartă…" x-model="msSearch" @input="if (msSearch) showAllMicroservices = true">
        </label>
    </div>
    <div class="hscroll mt-5" x-ref="msCats">
        <div class="hscroll__track flex gap-2 pb-1" @scroll="hscroll($event)" role="group" aria-label="Categorii">
            <button type="button" class="chip" :aria-pressed="!showAllMicroservices" @click="showAllMicroservices = false; msCategory = 'Toate'; msSearch = ''">Cele mai folosite</button>
            <template x-for="cat in ['Toate', ...msCategories()]" :key="cat">
                <button type="button" class="chip" :aria-pressed="showAllMicroservices && msCategory === cat" @click="showAllMicroservices = true; msCategory = cat" x-text="cat === 'Toate' ? 'Toate (' + microservices.length + ')' : cat"></button>
            </template>
        </div>
        <div class="hscroll__thumb" aria-hidden="true"></div>
    </div>
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3 mt-5">
        <template x-for="m in visibleMicroservices()" :key="m.id">
            <div class="tile flex flex-col" :data-on="isChosen(m)">
                <button type="button" class="text-left p-4 pr-12 flex gap-3 items-start flex-1" @click="toggleMicroservice(m)" :aria-pressed="isChosen(m)">
                    <span class="icon-box !w-10 !h-10 !text-lg" aria-hidden="true">
                        <img x-show="m.icon" :src="m.icon" alt="" class="w-6 h-6 object-contain">
                        <i x-show="!m.icon" class="ph" :class="categoryIcon(m.category)"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="font-semibold block leading-snug" x-text="m.name"></span>
                        <span class="text-sm text-[color:var(--ink-2)] block mt-0.5 line-clamp-2" x-text="m.text"></span>
                        <span class="price mt-2" :class="m.raises_commission && 'price--commission'" x-text="priceLabel(m)"></span>
                    </span>
                </button>
                <span class="check" aria-hidden="true" x-text="isChosen(m) ? '✓' : ''"></span>
                <button type="button" class="border-t border-[color:var(--line)] px-4 py-2.5 text-sm text-[color:var(--ink-2)] flex items-center justify-between" @click="openDetails(m)">
                    <span>Ce primești</span><i class="ph ph-caret-right" aria-hidden="true"></i>
                </button>
            </div>
        </template>
    </div>
    <p x-show="!visibleMicroservices().length" class="text-sm text-[color:var(--ink-2)] text-center py-10">Nu am găsit nimic. Încearcă alt cuvânt sau altă categorie.</p>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="button" class="link sm:ml-auto" @click="send('/inregistrare/functii', { microservices: [] })">Nu am nevoie acum</button>
        <button type="button" class="btn btn-primary sm:min-w-[220px]" @click="send('/inregistrare/functii', extras)" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
    </div>
</div>

{{-- 8. Verifică și trimite --}}
<div x-show="state.step === 'review'">
    <h1 class="text-2xl sm:text-3xl font-bold">Totul arată bine?</h1>
    <p class="text-[color:var(--ink-2)] mt-2 mb-6">Uită-te o dată peste date. Le poți schimba aici sau după aprobare.</p>
    <dl class="rounded-2xl border border-[color:var(--line)] divide-y divide-[color:var(--line)] text-sm">
        <template x-for="row in reviewRows()" :key="row.label">
            <div class="px-4 py-3 grid grid-cols-[minmax(0,140px)_1fr_auto] gap-4 items-center">
                <dt class="text-[color:var(--ink-2)]" x-text="row.label"></dt>
                <dd class="min-w-0 break-words" x-text="row.value"></dd>
                <button type="button" class="link !py-1 text-sm" @click="goTo(row.step)" :aria-label="'Modifică ' + row.label">Modifică</button>
            </div>
        </template>
    </dl>
    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-7">
        <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
        <button type="button" class="btn btn-primary sm:ml-auto sm:min-w-[240px]" @click="send('/inregistrare/trimite', {})" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Trimite înscrierea</span></button>
    </div>
</div>

{{-- Trimisă --}}
<div x-show="state.step === 'submitted'" class="text-center py-6 max-w-lg mx-auto">
    <div class="mx-auto mb-5 h-16 w-16 rounded-full bg-[color:var(--success-soft)] text-[color:var(--success)] grid place-items-center text-3xl ob-pop" aria-hidden="true"><i class="ph-bold ph-check"></i></div>
    <h1 class="text-2xl sm:text-3xl font-bold">Am primit înscrierea</h1>
    <p class="text-[color:var(--ink-2)] mt-3">Ne uităm peste date și îți scriem pe <strong class="text-[color:var(--ink)]" x-text="state.email"></strong> imediat ce contul e aprobat. Până atunci poți intra în panou să pregătești evenimentele, logo-ul și culorile.</p>
    <a href="/tenant" class="btn btn-primary mt-7">Intră în panou <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
</div>

{{-- Respinsă --}}
<div x-show="state.step === 'rejected'" class="text-center py-6 max-w-lg mx-auto">
    <h1 class="text-2xl sm:text-3xl font-bold">Nu am putut aproba înscrierea</h1>
    <p x-show="state.rejected_reason" class="rounded-xl bg-[color:var(--surface-2)] p-4 text-sm my-5" x-text="state.rejected_reason"></p>
    <p class="text-[color:var(--ink-2)]">Răspunde la emailul primit de la noi și vedem împreună ce lipsește.</p>
</div>
