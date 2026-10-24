<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Înscriere organizator - Tixello</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.5"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } } };
    </script>
    <style>
        :root {
            --c-bg: #0b0b12; --c-surface: #14141f; --c-surface-2: #1c1c2b; --c-border: #2a2a3d;
            --c-text: #f2f2f7; --c-muted: #a1a1b5; --c-primary: #7c5cff; --c-primary-hover: #6a4af0;
            --c-success: #34d399; --c-error: #f87171; --radius: 12px;
        }
        [x-cloak] { display: none !important; }
        body { background: var(--c-bg); color: var(--c-text); }
        .card { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: 16px; }
        .field { width: 100%; min-height: 48px; padding: 12px 14px; background: var(--c-surface-2); border: 1px solid var(--c-border); border-radius: var(--radius); color: var(--c-text); font-size: 16px; }
        .field::placeholder { color: #6b6b80; }
        .field:focus { outline: 2px solid var(--c-primary); outline-offset: 2px; border-color: var(--c-primary); }
        .field[aria-invalid="true"] { border-color: var(--c-error); }
        .label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: var(--c-text); }
        .hint { margin-top: 6px; font-size: 13px; color: var(--c-muted); }
        .err { margin-top: 6px; font-size: 13px; color: var(--c-error); display: flex; gap: 6px; align-items: flex-start; }
        .btn { min-height: 48px; padding: 12px 20px; border-radius: var(--radius); font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: background-color .15s ease-out; }
        .btn-primary { background: var(--c-primary); color: #fff; }
        .btn-ghost { color: var(--c-muted); }
        @media (hover: hover) { .btn-primary:hover { background: var(--c-primary-hover); } .btn-ghost:hover { color: var(--c-text); } .opt:hover { border-color: #4a4a66; } }
        .btn:focus-visible, .opt:focus-within { outline: 2px solid var(--c-primary); outline-offset: 2px; }
        .opt { display: block; border: 1px solid var(--c-border); border-radius: var(--radius); padding: 14px 16px; cursor: pointer; background: var(--c-surface-2); transition: border-color .15s ease-out; }
        .opt[data-on="true"] { border-color: var(--c-primary); box-shadow: inset 0 0 0 1px var(--c-primary); }
        .spin { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spin { animation-duration: 2s; } * { transition: none !important; } }
    </style>
</head>
<body class="font-sans antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 btn btn-primary">Sari la formular</a>

<div x-data="signup()" x-init="init()" x-cloak class="min-h-screen flex flex-col">
    <header class="max-w-2xl w-full mx-auto px-4 pt-8 pb-4 flex items-center justify-between">
        <a href="/" class="text-xl font-bold tracking-tight">Tixello</a>
        <a x-show="state.step === 'account'" href="/tenant/login" class="text-sm text-[color:var(--c-muted)] underline underline-offset-4 py-3">Ai deja cont? Intră</a>
    </header>

    {{-- Progres --}}
    <nav x-show="stepIndex() >= 0" aria-label="Pașii înscrierii" class="max-w-2xl w-full mx-auto px-4">
        <p class="text-sm text-[color:var(--c-muted)] mb-2">
            Pasul <span x-text="stepIndex() + 1"></span> din <span x-text="steps.length"></span>:
            <span class="text-[color:var(--c-text)] font-medium" x-text="steps[stepIndex()]?.label"></span>
        </p>
        <div class="h-1.5 rounded-full bg-[color:var(--c-surface-2)] overflow-hidden" role="progressbar" :aria-valuenow="stepIndex() + 1" aria-valuemin="1" :aria-valuemax="steps.length">
            <div class="h-full bg-[color:var(--c-primary)] transition-all duration-300" :style="`width: ${((stepIndex() + 1) / steps.length) * 100}%`"></div>
        </div>
    </nav>

    <main id="main" class="max-w-2xl w-full mx-auto px-4 py-6 flex-1">
        <div class="card p-6 sm:p-8">

            {{-- Eroare generală --}}
            <div x-show="flash" role="alert" class="mb-6 rounded-xl border border-[color:var(--c-error)]/40 bg-[color:var(--c-error)]/10 p-4 text-sm" x-text="flash"></div>

            {{-- 1. Cont --}}
            <form x-show="state.step === 'account'" @submit.prevent="send('/inregistrare/cont', account)" novalidate>
                <h1 class="text-2xl font-bold mb-2">Creează-ți contul de organizator</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Durează cam 5 minute. Contul îl verificăm noi înainte să începi să vinzi.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="first_name">Prenume</label>
                        <input id="first_name" class="field" x-model="account.first_name" autocomplete="given-name" :aria-invalid="!!errors.first_name">
                        <template x-if="errors.first_name"><p class="err" x-text="errors.first_name"></p></template>
                    </div>
                    <div>
                        <label class="label" for="last_name">Nume</label>
                        <input id="last_name" class="field" x-model="account.last_name" autocomplete="family-name" :aria-invalid="!!errors.last_name">
                        <template x-if="errors.last_name"><p class="err" x-text="errors.last_name"></p></template>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="email">Email</label>
                        <input id="email" type="email" class="field" x-model="account.email" autocomplete="email" inputmode="email" :aria-invalid="!!errors.email">
                        <template x-if="errors.email"><p class="err" x-text="errors.email"></p></template>
                        <p class="hint" x-show="!errors.email">Îți trimitem aici un cod de confirmare.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="phone">Telefon</label>
                        <input id="phone" type="tel" class="field" x-model="account.phone" autocomplete="tel" inputmode="tel" placeholder="07xx xxx xxx" :aria-invalid="!!errors.phone">
                        <template x-if="errors.phone"><p class="err" x-text="errors.phone"></p></template>
                    </div>
                    <div>
                        <label class="label" for="password">Parolă</label>
                        <div class="relative">
                            <input id="password" :type="showPassword ? 'text' : 'password'" class="field pr-14" x-model="account.password" autocomplete="new-password" :aria-invalid="!!errors.password">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-1 top-1 h-[46px] w-12 text-sm text-[color:var(--c-muted)]" :aria-label="showPassword ? 'Ascunde parola' : 'Arată parola'" x-text="showPassword ? 'Ascunde' : 'Arată'"></button>
                        </div>
                        <p class="hint" :class="account.password.length >= 8 && 'text-[color:var(--c-success)]'">
                            <span x-text="account.password.length >= 8 ? '✓' : '•'"></span> Minim 8 caractere
                        </p>
                        <template x-if="errors.password"><p class="err" x-text="errors.password"></p></template>
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Confirmă parola</label>
                        <input id="password_confirmation" :type="showPassword ? 'text' : 'password'" class="field" x-model="account.password_confirmation" autocomplete="new-password">
                    </div>
                </div>
                <label class="flex gap-3 items-start mt-6 cursor-pointer py-2">
                    <input type="checkbox" class="mt-1 h-5 w-5 accent-[color:var(--c-primary)]" x-model="account.accept_terms" :aria-invalid="!!errors.accept_terms">
                    <span class="text-sm text-[color:var(--c-muted)]">
                        Am citit și accept <a href="{{ config('onboarding.terms_url') }}" target="_blank" rel="noopener" class="underline text-[color:var(--c-text)]">Termenii și condițiile</a>
                        și <a href="{{ config('onboarding.privacy_url') }}" target="_blank" rel="noopener" class="underline text-[color:var(--c-text)]">Politica de confidențialitate</a>.
                    </span>
                </label>
                <template x-if="errors.accept_terms"><p class="err" x-text="errors.accept_terms"></p></template>
                <button type="submit" class="btn btn-primary w-full mt-6" :aria-busy="busy">
                    <span x-show="busy" class="spin" aria-hidden="true"></span>
                    <span>Creează contul</span>
                </button>
            </form>

            {{-- 1b. Verificare email --}}
            <form x-show="state.step === 'verify'" @submit.prevent="verify()" novalidate>
                <h1 class="text-2xl font-bold mb-2">Confirmă adresa de email</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Am trimis un cod de 6 cifre la <strong class="text-[color:var(--c-text)]" x-text="state.email"></strong>. Verifică și folderul Spam.</p>
                <label class="label" for="code">Codul din email</label>
                <input id="code" x-ref="code" class="field text-center text-2xl tracking-[0.5em] font-semibold" maxlength="6"
                       inputmode="numeric" autocomplete="one-time-code" x-model="code"
                       @input="code = code.replace(/\D/g, '').slice(0, 6); if (code.length === 6) verify()" :aria-invalid="!!errors.code">
                <template x-if="errors.code"><p class="err" x-text="errors.code"></p></template>
                <button type="submit" class="btn btn-primary w-full mt-6" :aria-busy="busy">
                    <span x-show="busy" class="spin" aria-hidden="true"></span><span>Confirmă</span>
                </button>
                <div class="mt-4 text-center text-sm text-[color:var(--c-muted)]">
                    <span x-show="resendIn > 0">Poți cere alt cod în <span x-text="resendIn"></span> s</span>
                    <button type="button" x-show="resendIn === 0" @click="resend()" class="btn btn-ghost underline underline-offset-4">Trimite din nou codul</button>
                    <p x-show="notice" class="mt-2" role="status" x-text="notice"></p>
                </div>
            </form>

            {{-- 2. Ce organizezi --}}
            <form x-show="state.step === 'organizer'" @submit.prevent="send('/inregistrare/organizator', organizer)" novalidate>
                <h1 class="text-2xl font-bold mb-2">Spune-ne ce organizezi</h1>
                <p class="text-[color:var(--c-muted)] mb-6">În funcție de răspuns îți pregătim panoul: un teatru are nevoie de alte lucruri decât un festival sau un parc de distracții. Poți schimba oricând.</p>
                <label class="label" for="tenant_type">Ce fel de evenimente faci?</label>
                <select id="tenant_type" class="field" x-model="organizer.tenant_type" :aria-invalid="!!errors.tenant_type">
                    <option value="">Alege…</option>
                    <template x-for="t in tenantTypes" :key="t.value"><option :value="t.value" x-text="t.label" :selected="t.value === organizer.tenant_type"></option></template>
                </select>
                <p class="hint" x-show="!errors.tenant_type">Dacă faci mai multe, alege-l pe cel principal.</p>
                <template x-if="errors.tenant_type"><p class="err" x-text="errors.tenant_type"></p></template>

                <label class="label mt-5" for="public_name">Cum te știe publicul?</label>
                <input id="public_name" class="field" x-model="organizer.public_name" placeholder="ex. Teatrul Mic, Festivalul Vară în Oraș" :aria-invalid="!!errors.public_name">
                <p class="hint" x-show="!errors.public_name">Numele ăsta apare pe bilete și pe site. Nu trebuie să fie numele firmei.</p>
                <template x-if="errors.public_name"><p class="err" x-text="errors.public_name"></p></template>

                <fieldset class="mt-6">
                    <legend class="label">Pe unde vei vinde biletele?</legend>
                    <p class="hint mt-0 mb-3">De asta depinde comisionul nostru pe fiecare bilet vândut prin Tixello.</p>
                    <div class="grid gap-3">
                        <template x-for="m in workMethods" :key="m.value">
                            <label class="opt" :data-on="organizer.work_method === m.value">
                                <input type="radio" class="sr-only" name="work_method" :value="m.value" x-model="organizer.work_method">
                                <span class="flex justify-between gap-4">
                                    <span><span class="font-semibold block" x-text="m.label"></span><span class="text-sm text-[color:var(--c-muted)]" x-text="m.text"></span></span>
                                    <span class="font-semibold whitespace-nowrap" x-text="m.fee"></span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <template x-if="errors.work_method"><p class="err" x-text="errors.work_method"></p></template>
                </fieldset>

                <label class="label mt-5" for="estimated">Cam câte bilete vinzi într-o lună? <span class="text-[color:var(--c-muted)] font-normal">(opțional)</span></label>
                <input id="estimated" type="number" min="0" inputmode="numeric" class="field" x-model="organizer.estimated_monthly_tickets" placeholder="ex. 500">
                <p class="hint">O estimare e suficientă. Ne ajută să te ajutăm mai bine la început.</p>

                <button type="submit" class="btn btn-primary w-full mt-6" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
            </form>

            {{-- 3. Cine facturează --}}
            <form x-show="state.step === 'billing'" @submit.prevent="send('/inregistrare/facturare', billing)" novalidate>
                <h1 class="text-2xl font-bold mb-2">Pe cine facem contractul?</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Datele astea apar pe contract și pe documentele prin care îți trimitem banii din bilete.</p>
                <fieldset>
                    <legend class="label">Vinzi biletele ca:</legend>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <template x-for="e in entities" :key="e.value">
                            <label class="opt" :data-on="billing.legal_entity_type === e.value">
                                <input type="radio" class="sr-only" name="legal_entity_type" :value="e.value" x-model="billing.legal_entity_type" @change="errors = {}">
                                <span class="font-semibold block" x-text="e.label"></span>
                                <span class="text-sm text-[color:var(--c-muted)]" x-text="e.text"></span>
                            </label>
                        </template>
                    </div>
                    <template x-if="errors.legal_entity_type"><p class="err" x-text="errors.legal_entity_type"></p></template>
                </fieldset>

                {{-- Firmă: doar CUI-ul, restul vine de la ANAF --}}
                <div x-show="billing.legal_entity_type === 'srl' || billing.legal_entity_type === 'pfa'" class="mt-6">
                    <label class="label" for="cui">CUI-ul firmei</label>
                    <div class="flex gap-2">
                        <input id="cui" class="field" x-model="billing.cui" placeholder="ex. 12345678" inputmode="numeric" @keydown.enter.prevent="lookupCui()" @input="company = null" :aria-invalid="!!errors.cui">
                        <button type="button" class="btn btn-primary shrink-0" @click="lookupCui()" :aria-busy="cuiBusy">
                            <span x-show="cuiBusy" class="spin" aria-hidden="true"></span><span>Caută</span>
                        </button>
                    </div>
                    <p class="hint" x-show="!errors.cui && !company">Scrie doar CUI-ul. Numele firmei, adresa și restul datelor le luăm automat de la ANAF.</p>
                    <template x-if="errors.cui"><p class="err" x-text="errors.cui"></p></template>

                    <div x-show="company" class="mt-4 rounded-xl border border-[color:var(--c-success)]/40 bg-[color:var(--c-success)]/10 p-4 text-sm" role="status">
                        <p class="font-semibold text-base"><span aria-hidden="true">✓</span> <span x-text="company?.company_name"></span></p>
                        <dl class="mt-3 grid grid-cols-[auto,1fr] gap-x-4 gap-y-1">
                            <dt class="text-[color:var(--c-muted)]">Reg. Com.</dt><dd x-text="company?.reg_com || '—'"></dd>
                            <dt class="text-[color:var(--c-muted)]">TVA</dt><dd x-text="company?.vat_payer ? 'plătitor de TVA' : 'neplătitor de TVA'"></dd>
                            <dt class="text-[color:var(--c-muted)]">Adresa</dt><dd x-text="[company?.address, company?.city, company?.county, company?.postal_code].filter(Boolean).join(', ') || '—'"></dd>
                        </dl>
                        <p class="text-[color:var(--c-muted)] mt-3">Datele sunt cele de la ANAF. Dacă e ceva greșit, ne spui după aprobare și le corectăm.</p>
                    </div>
                </div>

                {{-- Persoană fizică --}}
                <div x-show="billing.legal_entity_type === 'pf'" class="mt-6">
                    <p class="text-sm text-[color:var(--c-muted)] mb-4">Fără firmă, contractul se face pe numele tău. Pentru el avem nevoie de CNP și de datele din buletin. Le păstrăm criptat și nu apar nicăieri public.</p>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-3">
                            <label class="label" for="cnp">CNP</label>
                            <input id="cnp" class="field" inputmode="numeric" maxlength="13" x-model="billing.cnp" autocomplete="off" :aria-invalid="!!errors.cnp">
                            <template x-if="errors.cnp"><p class="err" x-text="errors.cnp"></p></template>
                        </div>
                        <div>
                            <label class="label" for="id_series">Seria buletinului</label>
                            <input id="id_series" class="field uppercase" maxlength="2" x-model="billing.id_series" placeholder="ex. RX" :aria-invalid="!!errors.id_series">
                            <template x-if="errors.id_series"><p class="err" x-text="errors.id_series"></p></template>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="id_number">Numărul buletinului</label>
                            <input id="id_number" class="field" inputmode="numeric" maxlength="6" x-model="billing.id_number" placeholder="ex. 123456" :aria-invalid="!!errors.id_number">
                            <template x-if="errors.id_number"><p class="err" x-text="errors.id_number"></p></template>
                        </div>
                    </div>
                </div>

                {{-- Adresa: la persoană fizică mereu; la firmă doar ce nu are ANAF --}}
                <div x-show="needsAddress()" class="mt-6 grid sm:grid-cols-2 gap-4">
                    <p x-show="billing.legal_entity_type !== 'pf'" class="sm:col-span-2 text-sm text-[color:var(--c-muted)]">ANAF nu ne-a dat adresa completă. Te rugăm s-o completezi tu.</p>
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

                <div class="flex flex-col-reverse sm:flex-row gap-3 mt-6">
                    <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
                    <button type="submit" class="btn btn-primary flex-1" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
                </div>
            </form>

            {{-- 4. Site --}}
            <form x-show="state.step === 'site'" @submit.prevent="saveSite()" novalidate>
                <h1 class="text-2xl font-bold mb-2">Unde vor cumpăra oamenii bilete?</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Primești un site de bilete gata făcut, inclus în preț. Trebuie doar să alegi la ce adresă să fie. Îl pornim imediat după ce îți aprobăm contul.</p>
                <fieldset>
                    <legend class="sr-only">Adresa site-ului</legend>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="opt p-5" :data-on="site.mode === 'subdomain'">
                            <input type="radio" class="sr-only" value="subdomain" x-model="site.mode">
                            <span class="text-2xl block mb-2" aria-hidden="true">🎁</span>
                            <span class="font-semibold block text-lg">Vreau o adresă gratuită</span>
                            <span class="text-sm text-[color:var(--c-muted)] block mt-1">N-ai nevoie de nimic în plus. Site-ul tău va fi la <strong class="text-[color:var(--c-text)]" x-text="(site.subdomain || 'numele-tau') + '.' + baseDomain"></strong>.</span>
                            <span class="inline-block mt-3 text-xs font-semibold rounded-full px-2 py-1 bg-[color:var(--c-primary)]/20">Cel mai simplu</span>
                        </label>
                        <label class="opt p-5" :data-on="site.mode === 'own'">
                            <input type="radio" class="sr-only" value="own" x-model="site.mode">
                            <span class="text-2xl block mb-2" aria-hidden="true">🌐</span>
                            <span class="font-semibold block text-lg">Am deja un domeniu</span>
                            <span class="text-sm text-[color:var(--c-muted)] block mt-1">Ai deja o adresă de site, de exemplu <em>teatrulmeu.ro</em>? O legăm de site-ul de bilete. Îți explicăm pas cu pas ce ai de făcut, după aprobare.</span>
                        </label>
                    </div>
                </fieldset>

                <div x-show="site.mode === 'subdomain'" class="mt-5">
                    <label class="label" for="subdomain">Alege adresa</label>
                    <div class="flex items-stretch">
                        <input id="subdomain" class="field rounded-r-none" x-model="site.subdomain" @input.debounce.500ms="checkSubdomain()" @blur="checkSubdomain()" autocomplete="off" :aria-invalid="subdomainStatus === 'taken' || !!errors.subdomain">
                        <span class="px-3 flex items-center border border-l-0 border-[color:var(--c-border)] rounded-r-[12px] text-[color:var(--c-muted)] bg-[color:var(--c-surface)]" x-text="'.' + baseDomain"></span>
                    </div>
                    <p class="hint" role="status">
                        <span x-show="subdomainStatus === '' || subdomainStatus === 'free'">Am propus-o din numele tău. O poți schimba.</span>
                        <span x-show="subdomainStatus === 'checking'">Verificăm dacă e liberă…</span>
                        <span x-show="subdomainStatus === 'free'" class="text-[color:var(--c-success)]"> ✓ E liberă.</span>
                        <span x-show="subdomainStatus === 'taken'" class="text-[color:var(--c-error)]">✕ Adresa e deja luată sau nu se poate folosi. Încearcă alta, doar cu litere mici, cifre și cratimă.</span>
                    </p>
                    <template x-if="errors.subdomain"><p class="err" x-text="errors.subdomain"></p></template>
                </div>
                <div x-show="site.mode === 'own'" class="mt-5">
                    <label class="label" for="domain">Adresa site-ului tău</label>
                    <input id="domain" class="field" x-model="site.domain" placeholder="ex. bilete.teatrulmeu.ro" autocomplete="url" :aria-invalid="!!errors.domain">
                    <p class="hint" x-show="!errors.domain">Poți folosi și o subadresă, de exemplu <em>bilete.teatrulmeu.ro</em>, ca să-ți păstrezi site-ul de acum neatins.</p>
                    <template x-if="errors.domain"><p class="err" x-text="errors.domain"></p></template>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-6">
                    <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
                    <button type="button" class="btn btn-ghost underline underline-offset-4" @click="send('/inregistrare/site', { mode: 'later' })">Nu știu încă, aleg mai târziu</button>
                    <button type="submit" class="btn btn-primary flex-1" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
                </div>
            </form>

            {{-- 5. Template --}}
            <div x-show="state.step === 'template'">
                <h1 class="text-2xl font-bold mb-2">Cum vrei să arate site-ul?</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Alege modelul care seamănă cel mai mult cu ce organizezi. Cu „Vezi demo” deschizi un site complet, ca să vezi cum arată. Nu te încurca acum cu detaliile: logo-ul, pozele, culorile și textele le schimbi tu după aprobare.</p>
                <div class="grid sm:grid-cols-2 gap-4" role="radiogroup" aria-label="Modele de site">
                    <template x-for="t in templates" :key="t.slug">
                        <div class="opt p-0 overflow-hidden" :data-on="template === t.slug">
                            <button type="button" role="radio" :aria-checked="template === t.slug" @click="template = t.slug" class="block w-full text-left">
                                <div class="aspect-[16/10] bg-[color:var(--c-surface)]">
                                    <img x-show="t.preview" :src="t.preview" :alt="'Cum arată modelul ' + t.name" loading="lazy" class="w-full h-full object-cover">
                                </div>
                                <div class="p-4">
                                    <span class="text-xs uppercase tracking-wide text-[color:var(--c-muted)]" x-text="t.category"></span>
                                    <span class="font-semibold block" x-text="t.name"></span>
                                </div>
                            </button>
                            <a x-show="t.demo_url" :href="t.demo_url" target="_blank" rel="noopener" class="block px-4 pb-4 text-sm underline underline-offset-4 text-[color:var(--c-muted)]">Vezi demo ↗</a>
                        </div>
                    </template>
                </div>
                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mt-6">
                    <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
                    <button type="button" class="btn btn-ghost underline underline-offset-4" @click="send('/inregistrare/template', { slug: null })">Aleg mai târziu</button>
                    <button type="button" class="btn btn-primary flex-1" @click="template ? send('/inregistrare/template', { slug: template }) : (flash = 'Alege un model sau apasă „Aleg mai târziu”.')" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
                </div>
            </div>

            {{-- 6. Plăți --}}
            <div x-show="state.step === 'payments'">
                <h1 class="text-2xl font-bold mb-2">Cum ajung la tine banii din bilete?</h1>
                <p class="text-[color:var(--c-muted)] mb-4">Când cineva plătește un bilet cu cardul, plata trece printr-o firmă specializată, numită procesator de plăți. Ea încasează banii de la cumpărător și ți-i virează în contul tău din bancă.</p>
                <p class="text-[color:var(--c-muted)] mb-6">Lucrezi deja cu una dintre firmele de mai jos? Alege-o. Dacă nu, alege „Nu am încă” și te ajutăm noi după aprobare. Nu trebuie să rezolvi nimic acum.</p>
                <div class="grid gap-3" role="radiogroup" aria-label="Procesator de plăți">
                    <button type="button" role="radio" :aria-checked="processor === 'none'" class="opt text-left" :data-on="processor === 'none'" @click="processor = 'none'">
                        <span class="font-semibold block">Nu am încă / nu știu</span>
                        <span class="text-sm text-[color:var(--c-muted)]">Îți explicăm noi variantele și te ajutăm să-ți faci cont.</span>
                    </button>
                    <template x-for="p in processors" :key="p.value">
                        <button type="button" role="radio" :aria-checked="processor === p.value" class="opt text-left" :data-on="processor === p.value" @click="processor = p.value">
                            <span class="font-semibold block" x-text="p.label"></span>
                            <span class="text-sm text-[color:var(--c-muted)]" x-text="processorText[p.value] || ''"></span>
                        </button>
                    </template>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3 mt-6">
                    <button type="button" class="btn btn-ghost" @click="back()">Înapoi</button>
                    <button type="button" class="btn btn-primary flex-1" @click="processor ? send('/inregistrare/plati', { processor: processor === 'none' ? null : processor }) : (flash = 'Alege o variantă. Dacă nu știi, alege „Nu am încă”.')" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Continuă</span></button>
                </div>
            </div>

            {{-- 7. Verifică și trimite --}}
            <div x-show="state.step === 'review'">
                <h1 class="text-2xl font-bold mb-2">Verifică și trimite</h1>
                <p class="text-[color:var(--c-muted)] mb-6">După ce trimiți, verificăm datele și îți scriem pe email, de obicei în aceeași zi lucrătoare.</p>
                <dl class="divide-y divide-[color:var(--c-border)] text-sm">
                    <template x-for="row in reviewRows()" :key="row.label">
                        <div class="py-3 flex justify-between gap-4">
                            <dt class="text-[color:var(--c-muted)]" x-text="row.label"></dt>
                            <dd class="text-right flex items-center gap-3">
                                <span x-text="row.value"></span>
                                <button type="button" class="underline underline-offset-4 text-[color:var(--c-muted)] py-2" @click="goTo(row.step)" :aria-label="'Modifică ' + row.label">Modifică</button>
                            </dd>
                        </div>
                    </template>
                </dl>
                <button type="button" class="btn btn-primary w-full mt-6" @click="send('/inregistrare/trimite', {})" :aria-busy="busy"><span x-show="busy" class="spin" aria-hidden="true"></span><span>Trimite înscrierea</span></button>
            </div>

            {{-- Trimisă --}}
            <div x-show="state.step === 'submitted'" class="text-center py-6">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-[color:var(--c-success)]/15 text-[color:var(--c-success)] flex items-center justify-center text-2xl" aria-hidden="true">✓</div>
                <h1 class="text-2xl font-bold mb-2">Gata, am primit înscrierea!</h1>
                <p class="text-[color:var(--c-muted)] mb-6">Îți verificăm datele și îți scriem pe <strong class="text-[color:var(--c-text)]" x-text="state.email"></strong>. Până atunci poți intra în panou să pregătești evenimentele, logo-ul și culorile.</p>
                <a href="/tenant" class="btn btn-primary">Intră în panou</a>
            </div>

            {{-- Respinsă --}}
            <div x-show="state.step === 'rejected'" class="text-center py-6">
                <h1 class="text-2xl font-bold mb-2">Nu am putut aproba înscrierea</h1>
                <p x-show="state.rejected_reason" class="rounded-xl bg-[color:var(--c-surface-2)] p-4 text-sm my-4" x-text="state.rejected_reason"></p>
                <p class="text-[color:var(--c-muted)]">Dacă vrei să lămurim ce lipsește, răspunde la emailul primit de la noi.</p>
            </div>
        </div>
    </main>
</div>

<script>
    function signup() {
        return {
            state: @json($state),
            tenantTypes: @json($tenantTypes),
            counties: @json(array_values($counties)),
            templates: @json($templates),
            processors: @json($processors),
            baseDomain: @json($baseDomain),
            steps: [
                { key: 'account', label: 'Cont' }, { key: 'organizer', label: 'Ce organizezi' }, { key: 'billing', label: 'Cine facturează' },
                { key: 'site', label: 'Site' }, { key: 'template', label: 'Template' }, { key: 'payments', label: 'Plăți' }, { key: 'review', label: 'Trimite' },
            ],
            workMethods: [
                { value: 'exclusive', label: 'Doar prin Tixello', text: 'Toate biletele se vând prin noi: pe site-ul tău, la casă și la intrare.', fee: '1%' },
                { value: 'mixed', label: 'Prin Tixello și în alte locuri', text: 'Vinzi și prin alte platforme de bilete sau agenții, pe lângă Tixello.', fee: '2%' },
            ],
            entities: [
                { value: 'srl', label: 'SRL', text: 'Ai o firmă' },
                { value: 'pfa', label: 'PFA', text: 'Ești persoană fizică autorizată' },
                { value: 'pf', label: 'Persoană fizică', text: 'Nu ai firmă' },
            ],
            processorText: {
                netopia: 'Procesator românesc, folosit de multe magazine online din țară.',
                stripe: 'Firmă internațională. Contul se face online, în câteva minute.',
                euplatesc: 'Procesator românesc. Contul se face prin bancă.',
                payu: 'Procesator internațional, prezent și în România.',
            },
            account: { first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '', accept_terms: false },
            organizer: { tenant_type: '', public_name: '', work_method: 'mixed', estimated_monthly_tickets: '' },
            billing: { legal_entity_type: '', cui: '', cnp: '', id_series: '', id_number: '', address: '', city: '', state: '' },
            site: { mode: 'subdomain', subdomain: '', domain: '' },
            template: null,
            processor: null,
            company: null,
            code: '',
            errors: {},
            flash: '',
            notice: '',
            busy: false,
            cuiBusy: false,
            showPassword: false,
            subdomainStatus: '',
            resendIn: 0,
            viewing: null,

            init() {
                this.hydrate();
                if (this.state.step === 'verify') this.startResendTimer();
                window.addEventListener('beforeunload', (e) => {
                    if (this.state.step === 'account' && !this.busy && (this.account.email || this.account.first_name)) e.preventDefault();
                });
            },

            // Câmpurile pornesc de la ce e salvat pe server
            hydrate() {
                const s = this.state;
                if (s.tenant_type) this.organizer.tenant_type = s.tenant_type;
                if (s.public_name) this.organizer.public_name = s.public_name;
                if (s.work_method) this.organizer.work_method = s.work_method;
                if (s.estimated_monthly_tickets) this.organizer.estimated_monthly_tickets = s.estimated_monthly_tickets;
                if (s.legal_entity_type) this.billing.legal_entity_type = s.legal_entity_type;
                if (s.cui) this.billing.cui = s.cui;
                ['address', 'city'].forEach(k => { if (s[k]) this.billing[k] = s[k]; });
                if (s.county) this.billing.state = s.county;
                if (s.company_name) this.company = { company_name: s.company_name, reg_com: s.reg_com, vat_payer: s.vat_payer, address: s.address, city: s.city, county: s.county, postal_code: s.postal_code };
                if (s.site) Object.assign(this.site, s.site.mode === 'later' ? { mode: 'subdomain' } : s.site);
                if (!this.site.subdomain && s.suggested_subdomain) { this.site.subdomain = s.suggested_subdomain; this.subdomainStatus = 'free'; }
                if (s.template) this.template = s.template;
                if (s.processor) this.processor = s.processor;
                else if (s.step === 'review') this.processor = 'none';
            },

            stepIndex() {
                const key = this.state.step === 'verify' ? 'account' : this.state.step;
                return this.steps.findIndex(s => s.key === key);
            },

            async request(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, status: res.status, data };
            },

            async send(url, body) {
                if (this.busy) return;
                this.busy = true; this.errors = {}; this.flash = '';
                try {
                    const { ok, status, data } = await this.request(url, body);
                    if (ok && data.state) {
                        // După primul pas sesiunea e nouă: luăm tokenul CSRF proaspăt
                        if (data.csrf) document.querySelector('meta[name=csrf-token]').content = data.csrf;
                        this.state = data.state;
                        this.hydrate();
                        if (this.state.step === 'verify') { this.startResendTimer(); this.$nextTick(() => this.$refs.code.focus()); }
                        window.scrollTo({ top: 0 });
                        return;
                    }
                    this.showErrors(status, data);
                } catch (e) {
                    this.flash = 'Nu am putut contacta serverul. Verifică conexiunea și încearcă din nou.';
                } finally {
                    this.busy = false;
                }
            },

            showErrors(status, data) {
                if (status === 422 && data.errors) {
                    this.errors = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
                    this.$nextTick(() => document.querySelector('[aria-invalid="true"]:not([hidden])')?.focus());
                } else if (status === 419) {
                    this.flash = 'Sesiunea a expirat. Reîncarcă pagina; datele salvate rămân.';
                } else if (status === 429) {
                    this.flash = 'Ai încercat de prea multe ori într-un timp scurt. Așteaptă un minut și încearcă din nou.';
                } else {
                    this.flash = data.message || 'Ceva n-a mers. Încearcă din nou.';
                }
            },

            async verify() {
                if (this.code.length !== 6) { this.errors = { code: 'Codul are 6 cifre.' }; return; }
                await this.send('/inregistrare/verificare', { code: this.code });
                if (this.errors.code) { this.code = ''; this.$refs.code.focus(); }
            },

            startResendTimer() {
                this.resendIn = 60;
                const t = setInterval(() => { this.resendIn--; if (this.resendIn <= 0) { this.resendIn = 0; clearInterval(t); } }, 1000);
            },

            async resend() {
                const { data } = await this.request('/inregistrare/verificare/retrimite', {});
                this.notice = data.message || '';
                this.startResendTimer();
            },

            async lookupCui() {
                if (!this.billing.cui.trim()) { this.errors = { cui: 'Scrie CUI-ul firmei.' }; return; }
                this.cuiBusy = true; this.errors = {}; this.company = null;
                try {
                    const { ok, status, data } = await this.request('/inregistrare/cui', { cui: this.billing.cui });
                    if (ok) {
                        this.company = data.company;
                        this.billing.cui = data.company.cui;
                        // Ce are ANAF înlocuiește ce era în formular
                        if (data.company.address) this.billing.address = data.company.address;
                        if (data.company.city) this.billing.city = data.company.city;
                        if (data.company.county) this.billing.state = data.company.county;
                    } else {
                        this.showErrors(status, data);
                    }
                } catch (e) {
                    this.flash = 'ANAF nu răspunde acum. Încearcă din nou peste câteva minute.';
                } finally {
                    this.cuiBusy = false;
                }
            },

            async checkSubdomain() {
                const v = this.site.subdomain = (this.site.subdomain || '').toLowerCase().trim();
                if (v.length < 3) { this.subdomainStatus = v ? 'taken' : ''; return; }
                this.subdomainStatus = 'checking';
                const { data } = await this.request('/inregistrare/subdomeniu', { subdomain: v });
                if (this.site.subdomain === v) this.subdomainStatus = data.available ? 'free' : 'taken';
            },

            // La firmă cerem adresa doar dacă ANAF nu ne-a dat-o complet
            needsAddress() {
                const t = this.billing.legal_entity_type;
                if (t === 'pf') return true;
                if (t !== 'srl' && t !== 'pfa') return false;
                return !!this.company && (!this.company.address || !this.company.city || !this.company.county);
            },

            saveSite() {
                const body = { mode: this.site.mode };
                if (this.site.mode === 'subdomain') body.subdomain = this.site.subdomain;
                if (this.site.mode === 'own') body.domain = this.site.domain;
                this.send('/inregistrare/site', body);
            },

            // Înapoi doar vizual: pașii deja salvați se pot modifica și retrimite
            back() {
                const order = ['organizer', 'billing', 'site', 'template', 'payments', 'review'];
                const i = order.indexOf(this.state.step);
                if (i > 0) this.goTo(order[i - 1]);
            },

            goTo(step) {
                this.errors = {}; this.flash = '';
                this.state = { ...this.state, step };
                window.scrollTo({ top: 0 });
            },

            reviewRows() {
                const s = this.state;
                const entity = { srl: 'SRL', pfa: 'PFA', pf: 'Persoană fizică' }[s.legal_entity_type] || '—';
                const site = !s.site || s.site.mode === 'later' ? 'Aleg mai târziu'
                    : s.site.mode === 'subdomain' ? s.site.subdomain + '.' + this.baseDomain : s.site.domain;
                const tpl = this.templates.find(t => t.slug === s.template);
                const proc = this.processors.find(p => p.value === s.processor);
                const method = this.workMethods.find(m => m.value === s.work_method);
                return [
                    { label: 'Organizator', value: (s.public_name || '—') + (s.tenant_type_label ? ' · ' + s.tenant_type_label : ''), step: 'organizer' },
                    { label: 'Comision', value: method ? method.fee + ' · ' + method.label : '—', step: 'organizer' },
                    { label: 'Facturează', value: entity + (s.company_name ? ' · ' + s.company_name : ''), step: 'billing' },
                    { label: 'Site', value: site, step: 'site' },
                    { label: 'Modelul site-ului', value: tpl ? tpl.name : 'Aleg mai târziu', step: 'template' },
                    { label: 'Plăți', value: proc ? proc.label : 'Nu am încă', step: 'payments' },
                ];
            },
        };
    }
</script>
</body>
</html>
