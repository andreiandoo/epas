@php
    $statLabels = [
        'tickets' => 'bilete vândute prin Tixello',
        'events' => 'evenimente',
        'organizers' => 'organizatori',
        'buyers' => 'cumpărători în conturi',
    ];
    $shots = array_values(array_filter(array_column($templates, 'preview')));
    $modelName = $preselectedModel ? (collect($templates)->firstWhere('slug', $preselectedModel)['name'] ?? null) : null;
@endphp
<section x-show="showIntro" x-ref="intro" class="relative overflow-hidden" aria-labelledby="intro-title">
    <div class="intro-glow" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-6 pb-12 lg:pb-16">
        <nav class="flex items-center justify-between h-12">
            <a href="/" class="text-xl font-bold tracking-tight">tixello</a>
            <a href="/tenant/login" class="link text-sm">Ai deja cont? Intră</a>
        </nav>

        <div class="grid lg:grid-cols-[1.05fr_1fr] gap-10 lg:gap-14 items-center mt-8 lg:mt-14">
            <div>
                <p class="ob-in inline-flex items-center gap-2 rounded-full bg-[color:var(--surface)] border border-[color:var(--line)] px-3 py-1.5 text-sm text-[color:var(--ink-2)]">
                    <span class="w-2 h-2 rounded-full bg-[color:var(--accent)]" aria-hidden="true"></span> Pentru organizatori de evenimente
                </p>
                <h1 id="intro-title" class="ob-in text-4xl sm:text-5xl lg:text-6xl font-bold mt-5">Vinde bilete pe site-ul tău, din prima zi.</h1>
                <p class="ob-in text-lg text-[color:var(--ink-2)] mt-5 max-w-xl">Tixello îți dă tot ce trebuie ca să vinzi bilete: site, plată cu cardul, bilete trimise pe email și un panou în care vezi fiecare vânzare. Contul îl faci în câteva minute, iar noi îl verificăm înainte să pornești.</p>

                @if ($modelName)
                    <p class="ob-in mt-5 inline-flex items-center gap-2 rounded-xl bg-[color:var(--accent-soft)] px-4 py-2.5 text-sm">
                        <i class="ph ph-check-circle text-lg text-[color:var(--accent)]" aria-hidden="true"></i>
                        Ai ales modelul <strong>„{{ $modelName }}”</strong>. Îl găsești deja bifat la pasul Aspect.
                    </p>
                @endif

                <div class="ob-in flex flex-wrap items-center gap-3 mt-7">
                    <button type="button" class="btn btn-primary text-base px-7" @click="startSignup()">Începe înscrierea <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                    <a href="https://demo.tics.ro/" target="_blank" rel="noopener" class="btn btn-line">Vezi site-urile demo</a>
                </div>
                <p class="ob-in text-sm text-[color:var(--ink-3)] mt-4">Comision de la 1% pe bilet. Fără abonament lunar.</p>
            </div>

            {{-- Colaj din site-urile demo; pe mobil rămâne o singură imagine --}}
            <div class="relative h-[300px] sm:h-[420px] lg:h-[480px]" aria-hidden="true">
                @if (count($shots) >= 3)
                    <div class="shot ob-shot" data-tilt style="inset: 8% 22% 18% 0; z-index: 1;"><div class="shot__bar"><i></i><i></i><i></i></div><img src="{{ $shots[1] }}" alt="" loading="eager"></div>
                    <div class="shot ob-shot hidden sm:block" data-tilt style="inset: 0 0 34% 38%; z-index: 2;"><div class="shot__bar"><i></i><i></i><i></i></div><img src="{{ $shots[0] }}" alt="" loading="eager"></div>
                    <div class="shot ob-shot hidden sm:block" data-tilt style="inset: 48% 6% 0 30%; z-index: 3;"><div class="shot__bar"><i></i><i></i><i></i></div><img src="{{ $shots[2] }}" alt="" loading="lazy"></div>
                @elseif ($shots)
                    <div class="shot ob-shot" data-tilt style="inset: 0;"><div class="shot__bar"><i></i><i></i><i></i></div><img src="{{ $shots[0] }}" alt=""></div>
                @else
                    <div class="absolute inset-0 rounded-2xl skel"></div>
                @endif
            </div>
        </div>

        @if ($stats)
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-px mt-12 lg:mt-16 rounded-2xl overflow-hidden border border-[color:var(--line)] bg-[color:var(--line)]">
                @foreach ($stats as $key => $value)
                    <div class="ob-in bg-[color:var(--surface)] px-5 py-5">
                        <dt class="text-sm text-[color:var(--ink-2)] order-2">{{ $statLabels[$key] ?? $key }}</dt>
                        <dd class="text-3xl font-bold mt-1 stat-num">
                            <span class="text-[color:var(--ink-3)] text-base font-medium">peste</span>
                            <span data-count="{{ $value }}">{{ number_format($value, 0, ',', '.') }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="grid md:grid-cols-3 gap-4 mt-6">
            @foreach ([
                ['ph-user-plus', 'Îți faci contul', 'Câteva minute. Dacă ai firmă, scrii doar CUI-ul, restul îl luăm de la ANAF.'],
                ['ph-shield-check', 'Verificăm datele', 'Îți scriem pe email când e gata. Între timp poți pregăti evenimentele în panou.'],
                ['ph-ticket', 'Pornești vânzarea', 'Site-ul și plata sunt pregătite. Adaugi evenimentul și biletele se pot cumpăra.'],
            ] as $i => [$icon, $title, $text])
                <div class="ob-in card p-5 flex gap-4">
                    <span class="icon-box" aria-hidden="true"><i class="ph {{ $icon }}"></i></span>
                    <div>
                        <p class="text-xs font-semibold text-[color:var(--ink-3)]">Pasul {{ $i + 1 }}</p>
                        <p class="font-semibold">{{ $title }}</p>
                        <p class="text-sm text-[color:var(--ink-2)] mt-1">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
