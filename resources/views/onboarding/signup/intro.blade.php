@php
    $statLabels = [
        'tickets' => 'bilete emise',
        'orders' => 'comenzi plătite',
        'events' => 'evenimente',
        'buyers' => 'cumpărători',
        'accounts' => 'conturi active',
    ];
    $shots = array_values(array_filter(array_column($templates, 'preview')));
    // Peretele are nevoie de destule imagini ca să curgă fără goluri
    $wall = $shots ? array_slice(array_merge(...array_fill(0, (int) ceil(12 / count($shots)), $shots)), 0, 12) : [];
    $modelName = $preselectedModel ? (collect($templates)->firstWhere('slug', $preselectedModel)['name'] ?? null) : null;
@endphp
<div x-show="showIntro">
    {{-- Hero întunecat, indiferent de tema sistemului --}}
    <section class="ob-hero" aria-labelledby="intro-title" @mousemove="spot($event)">
        <div class="ob-hero__spot" aria-hidden="true"></div>
        <div class="ob-hero__aurora" aria-hidden="true"><i></i><i></i><i></i></div>
        @if ($wall)
            <div class="ob-wall" aria-hidden="true">
                @foreach ([0, 1, 2] as $row)
                    @php($items = array_merge(array_slice($wall, $row * 4), array_slice($wall, 0, $row * 4)))
                    <div class="ob-wall__row {{ $row % 2 ? 'is-rev' : '' }}">
                        @foreach (array_merge($items, $items) as $src)
                            <div class="ob-wall__shot"><img src="{{ $src }}" alt="" loading="lazy"></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
        <div class="ob-hero__fade" aria-hidden="true"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 pt-6 pb-16 lg:pb-24 min-h-[100svh] flex flex-col">
            <nav class="flex items-center justify-between h-12">
                <a href="/" class="text-xl font-bold tracking-tight">tixello</a>
                <a href="/tenant/login" class="text-sm text-white/70 hover:text-white underline underline-offset-4 py-3">Ai deja cont? Intră</a>
            </nav>

            <div class="flex-1 flex flex-col justify-center py-10">
                @if (! empty($stats['tickets']))
                    <p class="ob-in inline-flex self-start items-center gap-2 rounded-full border border-white/15 bg-white/5 backdrop-blur px-3 py-1.5 text-sm text-white/80">
                        <span class="ob-live" aria-hidden="true"></span>
                        <span><strong class="text-white stat-num" data-live="tickets">{{ number_format($stats['tickets'], 0, ',', '.') }}</strong> bilete emise prin Tixello, numărate live</span>
                    </p>
                @endif
                <h1 id="intro-title" class="ob-in text-[2.6rem] leading-[1.05] sm:text-6xl lg:text-[5.2rem] font-bold mt-6 max-w-5xl">
                    Bilete pentru
                    <span class="ob-rotator" aria-hidden="true"><span class="ob-rotator__word" x-text="rotator" x-effect="rotKey; $el.classList.remove('is-in'); void $el.offsetWidth; $el.classList.add('is-in')"></span></span>
                    <span class="sr-only">teatre, filarmonici, festivaluri, competiții și concerte</span>
                    <br class="hidden sm:block"> vândute de pe site-ul tău.
                </h1>
                <p class="ob-in text-lg sm:text-xl text-white/70 mt-6 max-w-2xl">Site de bilete, plată cu cardul, bilete trimise pe email și un panou în care vezi fiecare vânzare. Contul îl faci în câteva minute, iar noi îl verificăm înainte să pornești.</p>
                @if ($modelName)
                    <p class="ob-in mt-5 inline-flex self-start items-center gap-2 rounded-xl bg-white/10 border border-white/15 px-4 py-2.5 text-sm">
                        <i class="ph ph-check-circle text-lg text-[color:var(--accent)]" aria-hidden="true"></i>
                        Ai ales modelul <strong>„{{ $modelName }}”</strong>. Îl găsești deja bifat la pasul Aspect.
                    </p>
                @endif
                <div class="ob-in flex flex-wrap items-center gap-3 mt-8">
                    <button type="button" class="btn btn-primary text-base px-8 !min-h-[56px] ob-glow" @click="startSignup()">Începe înscrierea <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                    <a href="https://demo.tics.ro/" target="_blank" rel="noopener" class="btn !min-h-[56px] border border-white/20 text-white hover:bg-white/10">Vezi site-urile demo</a>
                </div>
                <p class="ob-in text-sm text-white/50 mt-4">Comision de la 1% pe bilet. Fără abonament lunar.</p>
            </div>

            @if ($stats)
                <dl class="ob-in grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ($stats as $key => $value)
                        <div class="ob-glass px-4 py-4">
                            <dd class="text-2xl sm:text-3xl font-bold stat-num" data-live="{{ $key }}">{{ number_format($value, 0, ',', '.') }}</dd>
                            <dt class="text-sm text-white/60 mt-0.5">{{ $statLabels[$key] ?? $key }}</dt>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-white/40 mt-3 flex items-center gap-2"><span class="ob-live" aria-hidden="true"></span> Cifre reale din platformă, actualizate la 30 de secunde.</p>
            @endif
        </div>
    </section>

    {{-- Ce vinzi: evenimente aici, atracții pe bilete.online --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-14 lg:py-20" aria-labelledby="path-title">
        <h2 id="path-title" class="text-3xl sm:text-4xl font-bold max-w-2xl">Ce vinzi?</h2>
        <p class="text-[color:var(--ink-2)] mt-3 max-w-2xl">Avem două platforme, fiecare făcută pentru un fel de bilete. Alege-o pe cea potrivită și te înscrii acolo.</p>
        <div class="grid md:grid-cols-2 gap-4 mt-8">
            <div class="card p-6 sm:p-8 flex flex-col relative overflow-hidden">
                <span class="absolute -right-10 -top-10 w-48 h-48 rounded-full opacity-60" style="background: radial-gradient(circle, var(--accent-soft), transparent 70%)" aria-hidden="true"></span>
                <span class="icon-box" aria-hidden="true"><i class="ph ph-masks-theater"></i></span>
                <h3 class="text-xl font-bold mt-4">Evenimente și spectacole</h3>
                <p class="text-[color:var(--ink-2)] mt-2 flex-1">Teatre, filarmonici, opere, festivaluri, concerte, competiții, gale, cluburi și săli de spectacol. Bilete pe dată și oră, cu locuri numerotate dacă ai nevoie.</p>
                <button type="button" class="btn btn-primary mt-6 self-start" @click="startSignup()">Începe înscrierea aici <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
            </div>
            <div class="card p-6 sm:p-8 flex flex-col">
                <span class="icon-box" aria-hidden="true"><i class="ph ph-tree-evergreen"></i></span>
                <h3 class="text-xl font-bold mt-4">Muzee, agrement și activități</h3>
                <p class="text-[color:var(--ink-2)] mt-2 flex-1">Muzee și expoziții, parcuri de distracții și de aventură, escape rooms, peșteri, tururi ghidate, ateliere. Pentru ele avem <strong class="text-[color:var(--ink)]">bilete.online</strong>, făcută special pentru atracții și experiențe.</p>
                <div class="flex flex-wrap items-center gap-3 mt-6">
                    <a href="https://bilete.online/autentificare?mode=register&amp;ca=venue" class="btn btn-line" target="_blank" rel="noopener">Înscrie-te pe bilete.online <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
                    <a href="https://bilete.online/parteneri" class="link text-sm" target="_blank" rel="noopener">Cum funcționează</a>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-14">
            @foreach ([
                ['ph-user-plus', 'Îți faci contul', 'Câteva minute. Dacă ai firmă, scrii doar CUI-ul, restul îl luăm de la ANAF.'],
                ['ph-shield-check', 'Verificăm datele', 'Îți scriem pe email când e gata. Între timp poți pregăti evenimentele în panou.'],
                ['ph-ticket', 'Pornești vânzarea', 'Site-ul și plata sunt pregătite. Adaugi evenimentul și biletele se pot cumpăra.'],
            ] as $i => [$icon, $title, $text])
                <div class="flex gap-4">
                    <span class="w-10 h-10 shrink-0 rounded-full grid place-items-center font-bold bg-[color:var(--ink)] text-[color:var(--bg)]">{{ $i + 1 }}</span>
                    <div>
                        <p class="font-semibold">{{ $title }}</p>
                        <p class="text-sm text-[color:var(--ink-2)] mt-1">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
