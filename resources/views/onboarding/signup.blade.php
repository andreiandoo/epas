<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Înscriere organizator · Tixello</title>
    <meta name="description" content="Fă-ți contul de organizator Tixello: site de bilete, plată cu cardul și panou de vânzări. Comision de la 1% pe bilet, fără abonament lunar.">
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Geist: o singură familie pentru tot, ca în galeria demo.tics.ro --}}
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <script src="https://cdn.tailwindcss.com/3.4.5"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Geist', 'system-ui', 'sans-serif'] } } } };</script>
    @include('onboarding.signup.styles')
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.1/vanilla-tilt.min.js"></script>
    @include('onboarding.signup.script')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="font-sans">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] btn btn-primary">Sari la formular</a>

{{-- Până pornește Alpine: scheletul paginii, nu un ecran gol --}}
<div id="ob-boot" class="max-w-6xl mx-auto px-4 sm:px-6 pt-6" aria-hidden="true">
    <div class="skel h-8 w-28"></div>
    <div class="grid lg:grid-cols-2 gap-10 mt-14">
        <div class="space-y-4"><div class="skel h-6 w-48"></div><div class="skel h-14 w-full"></div><div class="skel h-14 w-4/5"></div><div class="skel h-20 w-full"></div><div class="skel h-12 w-52 rounded-full"></div></div>
        <div class="skel h-[360px] rounded-2xl"></div>
    </div>
</div>

<div x-data="signup()" x-init="init()" x-cloak>
    @include('onboarding.signup.intro')

    <div x-show="!showIntro">
        <header class="sticky top-0 z-40 border-b border-[color:var(--line)]" style="background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(10px);">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">
                <a href="/" class="text-lg font-bold tracking-tight shrink-0">tixello</a>
                {{-- Pașii: etichete pe ecran lat, bară de progres pe mobil --}}
                <nav aria-label="Pașii înscrierii" class="flex-1 min-w-0">
                    <ol class="hidden lg:flex items-center gap-1 text-sm">
                        <template x-for="(s, i) in steps" :key="s.key">
                            <li class="flex items-center gap-1">
                                <span class="flex items-center gap-2 rounded-full px-3 py-1.5"
                                      :class="i === stepIndex() ? 'bg-[color:var(--ink)] text-[color:var(--bg)] font-semibold' : (i < stepIndex() ? 'text-[color:var(--ink)]' : 'text-[color:var(--ink-3)]')"
                                      :aria-current="i === stepIndex() ? 'step' : null">
                                    <span class="w-5 h-5 rounded-full grid place-items-center text-[11px] font-bold"
                                          :class="i < stepIndex() ? 'bg-[color:var(--success)] text-white' : (i === stepIndex() ? 'bg-[color:var(--accent)] text-white' : 'border border-[color:var(--line)]')"
                                          x-text="i < stepIndex() ? '✓' : i + 1"></span>
                                    <span x-text="s.label"></span>
                                </span>
                                <span x-show="i < steps.length - 1" class="w-3 h-px bg-[color:var(--line)]" aria-hidden="true"></span>
                            </li>
                        </template>
                    </ol>
                    <div class="lg:hidden" x-show="stepIndex() >= 0">
                        <p class="text-sm"><span class="text-[color:var(--ink-3)]" x-text="'Pasul ' + (stepIndex() + 1) + ' din ' + steps.length + ' · '"></span><span class="font-semibold" x-text="steps[stepIndex()]?.label"></span></p>
                        <div class="h-1 mt-1.5 rounded-full bg-[color:var(--surface-2)] overflow-hidden"><div class="h-full bg-[color:var(--accent)] transition-all duration-500" :style="'width:' + ((stepIndex() + 1) / steps.length * 100) + '%'"></div></div>
                    </div>
                </nav>
                <p x-show="stepIndex() > 0" class="hidden md:flex items-center gap-1.5 text-xs text-[color:var(--ink-3)] shrink-0"><i class="ph ph-cloud-check text-base" aria-hidden="true"></i> Salvat la fiecare pas</p>
            </div>
        </header>

        <main id="main" class="max-w-6xl mx-auto px-4 sm:px-6 py-6 lg:py-10">
            <div class="grid gap-6 items-start" :class="gridClass()">
                <div class="card p-5 sm:p-8 min-w-0 relative" x-ref="stage">
                    <div x-show="flash" role="alert" class="mb-6 rounded-xl border border-[color:var(--error)]/30 bg-[color:var(--error-soft)] p-4 text-sm" x-text="flash"></div>
                    {{-- Răspunsul întârzie: scheletul pasului, nu un formular înghețat --}}
                    <div x-show="pending" class="absolute inset-0 p-5 sm:p-8 space-y-4 bg-[color:var(--surface)] rounded-[20px] z-10" aria-hidden="true">
                        <div class="skel h-8 w-2/3"></div><div class="skel h-4 w-1/2"></div>
                        <div class="grid sm:grid-cols-2 gap-4 pt-4"><div class="skel h-12"></div><div class="skel h-12"></div><div class="skel h-12"></div><div class="skel h-12"></div></div>
                        <div class="skel h-24"></div>
                    </div>
                    <div x-ref="stepBody">
                        @include('onboarding.signup.steps')
                    </div>
                </div>
                <div x-show="showAside()">
                    @include('onboarding.signup.aside')
                </div>
            </div>
        </main>

        {{-- Pe mobil, la Funcții în plus: totalul rămâne la vedere --}}
        <div x-show="state.step === 'extras'" class="lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-[color:var(--line)] px-4 py-3 flex items-center gap-3" style="background: color-mix(in srgb, var(--surface) 94%, transparent); backdrop-filter: blur(10px);">
            <div class="flex-1 text-sm leading-tight">
                <span class="font-bold" x-text="commissionPreview() + '% comision'"></span>
                <span class="block text-[color:var(--ink-2)]" x-text="paidChosen('monthly').length ? sumLabel('monthly') + ' / lună' : 'fără abonamente'"></span>
            </div>
            <button type="button" class="btn btn-primary" @click="send('/inregistrare/functii', extras)" :aria-busy="busy">Continuă</button>
        </div>
    </div>

    {{-- „Ce primești”: panou lateral (jos, pe mobil) --}}
    <dialog x-ref="sheet" class="sheet" @close="detail = null" @click="if ($event.target === $refs.sheet) $refs.sheet.close()" aria-labelledby="sheet-title">
        <template x-if="detail">
            <div class="h-full flex flex-col">
                <div class="p-5 sm:p-6 border-b border-[color:var(--line)] flex gap-4 items-start">
                    <span class="icon-box" aria-hidden="true">
                        <img x-show="detail.icon" :src="detail.icon" alt="" class="w-7 h-7 object-contain">
                        <i x-show="!detail.icon" class="ph" :class="categoryIcon(detail.category)"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-[color:var(--ink-3)]" x-text="detail.category"></p>
                        <h2 id="sheet-title" class="text-xl font-bold" x-text="detail.name"></h2>
                        <span class="price mt-2" :class="detail.raises_commission && 'price--commission'" x-text="priceLabel(detail)"></span>
                    </div>
                    <button type="button" class="btn btn-ghost !min-h-0 !p-2 -mr-2 -mt-1" @click="$refs.sheet.close()" aria-label="Închide"><i class="ph ph-x text-xl" aria-hidden="true"></i></button>
                </div>
                <div class="p-5 sm:p-6 flex-1 overflow-y-auto ob-scroll-y">
                    <p x-show="detail.description" class="text-[color:var(--ink-2)]" x-text="detail.description"></p>
                    <h3 class="font-semibold mt-6">Ce primești</h3>
                    <ul class="mt-3 space-y-2.5">
                        <template x-for="f in (detail.features.length ? detail.features : [detail.text])" :key="f">
                            <li class="flex gap-3 text-sm"><i class="ph-bold ph-check text-[color:var(--success)] mt-0.5" aria-hidden="true"></i><span x-text="f"></span></li>
                        </template>
                    </ul>
                    <p x-show="detail.raises_commission" class="mt-6 rounded-xl bg-[color:var(--accent-soft)] p-3 text-sm">Nu are abonament. Dacă o alegi, comisionul crește cu un punct, același oricâte funcții de acest fel alegi.</p>
                </div>
                <div class="p-5 sm:p-6 border-t border-[color:var(--line)]">
                    <button type="button" class="btn w-full" :class="isChosen(detail) ? 'btn-line' : 'btn-primary'" @click="toggleMicroservice(detail); $refs.sheet.close()" x-text="isChosen(detail) ? 'Scoate din listă' : 'Adaugă'"></button>
                </div>
            </div>
        </template>
    </dialog>
</div>
</body>
</html>
