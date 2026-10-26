@php
    $statLabels = [
        'sales' => 'vânzări prin Tixello',
        'tickets' => 'bilete emise',
        'orders' => 'comenzi',
        'events' => 'evenimente',
        'buyers' => 'cumpărători',
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
                            <dd class="text-2xl sm:text-3xl font-bold stat-num"><span data-live="{{ $key }}">{{ number_format($value, 0, ',', '.') }}</span>@if ($key === 'sales')<span class="text-base font-semibold text-white/60"> lei</span>@endif</dd>
                            <dt class="text-sm text-white/60 mt-0.5">{{ $statLabels[$key] ?? $key }}</dt>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-white/40 mt-3 flex items-center gap-2"><span class="ob-live" aria-hidden="true"></span> Cifre reale din platformă, actualizate la 30 de secunde.</p>
            @endif
        </div>
    </section>

    {{-- Ce vinzi: evenimente aici, atracții pe bilete.online --}}
    <section class="ob-paths" aria-labelledby="path-title">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 lg:py-24">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-semibold text-[color:var(--accent)]">Alege platforma potrivită</p>
                <h2 id="path-title" class="text-4xl sm:text-5xl font-bold mt-3">Ce vinzi?</h2>
                <p class="text-[color:var(--ink-2)] mt-4 text-lg">Avem două platforme, fiecare făcută pentru un alt fel de bilete. Te înscrii pe cea care se potrivește cu ce faci.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-5 mt-12">
                {{-- Evenimente și spectacole: rămâne aici --}}
                <article class="ob-path ob-path--events">
                    <div class="ob-path__media" aria-hidden="true">
                        @foreach (array_slice($shots, 0, 3) as $i => $src)
                            <div class="ob-path__shot ob-path__shot--{{ $i }}"><img src="{{ $src }}" alt="" loading="lazy"></div>
                        @endforeach
                    </div>
                    <div class="ob-path__body">
                        <p class="ob-path__brand"><span class="w-2 h-2 rounded-full bg-[color:var(--accent)]"></span> Tixello</p>
                        <h3 class="text-2xl sm:text-3xl font-bold mt-3">Evenimente și spectacole</h3>
                        <ul class="flex flex-wrap gap-2 mt-4" aria-label="Pentru cine">
                            @foreach (['Teatre', 'Filarmonici', 'Opere', 'Festivaluri', 'Concerte', 'Competiții', 'Gale', 'Cluburi'] as $tag)
                                <li class="ob-tag">{{ $tag }}</li>
                            @endforeach
                        </ul>
                        <ul class="mt-6 space-y-2.5 text-[15px]">
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[color:var(--accent)] mt-1" aria-hidden="true"></i> Bilete pe dată și oră, cu locuri numerotate pe hartă</li>
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[color:var(--accent)] mt-1" aria-hidden="true"></i> Site de bilete cu numele și culorile tale</li>
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[color:var(--accent)] mt-1" aria-hidden="true"></i> Vânzare online, la casă și la intrare, în același cont</li>
                        </ul>
                        <div class="ob-path__actions flex flex-wrap items-center gap-4">
                            <button type="button" class="btn btn-primary ob-glow px-7" @click="startSignup()">Începe înscrierea aici <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                            <span class="text-sm text-white/60">de la 1% pe bilet</span>
                        </div>
                    </div>
                </article>

                {{-- Muzee, agrement, activități: bilete.online --}}
                <article class="ob-path ob-path--leisure">
                    <div class="ob-path__media ob-path__icons" aria-hidden="true">
                        @foreach (['ph-bank', 'ph-confetti', 'ph-mountains', 'ph-key', 'ph-compass', 'ph-paint-brush-broad', 'ph-tree-evergreen', 'ph-binoculars'] as $i => $icon)
                            <span class="ob-path__icon" style="--i: {{ $i }}"><i class="ph {{ $icon }}"></i></span>
                        @endforeach
                    </div>
                    <div class="ob-path__body">
                        <p class="ob-path__brand"><span class="w-2 h-2 rounded-full bg-[#1f9d72]"></span> bilete.online</p>
                        <h3 class="text-2xl sm:text-3xl font-bold mt-3">Muzee, agrement și activități</h3>
                        <ul class="flex flex-wrap gap-2 mt-4" aria-label="Pentru cine">
                            @foreach (['Muzee', 'Parcuri de distracții', 'Aventură', 'Escape rooms', 'Peșteri', 'Tururi ghidate', 'Ateliere', 'Ferme educative'] as $tag)
                                <li class="ob-tag">{{ $tag }}</li>
                            @endforeach
                        </ul>
                        <ul class="mt-6 space-y-2.5 text-[15px] text-[color:var(--ink)]">
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[#1f9d72] mt-1" aria-hidden="true"></i> Program pe zile și intervale orare, nu doar pe date fixe</li>
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[#1f9d72] mt-1" aria-hidden="true"></i> Pagina ta pe un marketplace de experiențe și atracții</li>
                            <li class="flex gap-3"><i class="ph-bold ph-check text-[#1f9d72] mt-1" aria-hidden="true"></i> Fără abonament lunar și fără taxă de instalare</li>
                        </ul>
                        <div class="ob-path__actions flex flex-wrap items-center gap-4">
                            <a href="https://bilete.online/autentificare?mode=register&amp;ca=venue" class="btn btn-leisure px-7" target="_blank" rel="noopener">Înscrie-te pe bilete.online <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
                            <a href="https://bilete.online/parteneri" class="link text-sm" target="_blank" rel="noopener">Cum funcționează</a>
                        </div>
                    </div>
                </article>
            </div>

            <ol class="grid md:grid-cols-3 gap-6 mt-16 relative">
                <span class="hidden md:block absolute top-5 left-[16%] right-[16%] h-px bg-[color:var(--line)]" aria-hidden="true"></span>
                @foreach ([
                    ['Îți faci contul', 'Câteva minute. Dacă ai firmă, scrii doar CUI-ul, restul îl luăm de la ANAF.'],
                    ['Verificăm datele', 'Îți scriem pe email când e gata. Între timp poți pregăti evenimentele în panou.'],
                    ['Pornești vânzarea', 'Site-ul și plata sunt pregătite. Adaugi evenimentul și biletele se pot cumpăra.'],
                ] as $i => [$title, $text])
                    <li class="relative text-center md:px-6">
                        <span class="relative z-10 mx-auto w-10 h-10 rounded-full grid place-items-center font-bold bg-[color:var(--ink)] text-[color:var(--bg)] ring-8 ring-[color:var(--bg)]">{{ $i + 1 }}</span>
                        <p class="font-semibold mt-4">{{ $title }}</p>
                        <p class="text-sm text-[color:var(--ink-2)] mt-1">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</div>
