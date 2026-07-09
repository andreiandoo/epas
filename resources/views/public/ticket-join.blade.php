<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alătură-te online — {{ $eventTitle }}</title>
    @php
        $titles = [
            'ready'          => 'Alătură-te online',
            'too_early'      => 'Nu a început încă',
            'ended'          => 'Evenimentul s-a încheiat',
            'not_found'      => 'Bilet negăsit',
            'cancelled'      => 'Bilet anulat',
            'refunded'       => 'Bilet returnat',
            'unpaid'         => 'Comandă neplătită',
            'not_configured' => 'Meeting nesetat',
        ];
        $pageTitle = $titles[$status] ?? 'Alătură-te online';
    @endphp
    <meta name="robots" content="noindex,nofollow">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #A51C30;
            --primary-dark: #8B1728;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --success: #16a34a;
            --success-bg: #f0fdf4;
            --warning: #d97706;
            --warning-bg: #fffbeb;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--slate-700);
            line-height: 1.6;
        }
        .card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08), 0 4px 12px rgba(15, 23, 42, 0.04);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
        }
        .banner {
            padding: 32px 32px 24px;
            text-align: center;
            border-bottom: 1px solid var(--slate-100);
        }
        .banner-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .banner-icon svg { width: 40px; height: 40px; }
        .banner.ready .banner-icon { background: var(--success-bg); color: var(--success); }
        .banner.too_early .banner-icon { background: var(--warning-bg); color: var(--warning); }
        .banner.ended .banner-icon,
        .banner.cancelled .banner-icon,
        .banner.refunded .banner-icon,
        .banner.unpaid .banner-icon,
        .banner.not_configured .banner-icon,
        .banner.not_found .banner-icon { background: var(--danger-bg); color: var(--danger); }
        .banner h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--slate-900);
            margin-bottom: 4px;
            letter-spacing: -0.01em;
        }
        .banner p { color: var(--slate-500); font-size: 14px; }

        .body { padding: 24px 32px 32px; }

        .event-meta {
            padding: 16px 20px;
            background: var(--slate-50);
            border-radius: 14px;
            margin-bottom: 20px;
        }
        .event-meta .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--slate-400);
            margin-bottom: 6px;
        }
        .event-meta .title {
            font-size: 17px;
            font-weight: 700;
            color: var(--slate-900);
            line-height: 1.3;
        }
        .event-meta .attendee {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: var(--slate-600);
        }
        .event-meta .attendee svg { flex-shrink: 0; width: 14px; height: 14px; }

        .join-panel {
            background: linear-gradient(135deg, rgba(165, 28, 48, 0.02), rgba(220, 38, 38, 0.01));
            border: 1px solid rgba(165, 28, 48, 0.12);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .join-panel .row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed var(--slate-200);
        }
        .join-panel .row:last-child { border-bottom: 0; }
        .join-panel .row .k {
            width: 90px;
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .join-panel .row .v {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            color: var(--slate-900);
            word-break: break-all;
        }
        .passcode-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Fira Code', ui-monospace, SFMono-Regular, monospace;
            font-size: 15px;
            font-weight: 700;
            padding: 4px 10px;
            background: var(--slate-100);
            color: var(--slate-800);
            border-radius: 8px;
            letter-spacing: 0.05em;
        }
        .copy-btn {
            background: none;
            border: 0;
            color: var(--slate-500);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
        }
        .copy-btn:hover { color: var(--primary); background: var(--slate-100); }
        .copy-btn svg { width: 14px; height: 14px; }

        .cta {
            display: block;
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            text-align: center;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.01em;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 4px 12px rgba(165, 28, 48, 0.25);
            margin-bottom: 12px;
        }
        .cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(165, 28, 48, 0.32);
        }
        .cta-secondary {
            display: block;
            width: 100%;
            padding: 12px;
            text-align: center;
            color: var(--slate-500);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        .cta-secondary:hover { color: var(--primary); }

        .instructions {
            padding: 16px 20px;
            background: var(--slate-50);
            border-radius: 12px;
            margin-top: 16px;
            font-size: 14px;
            color: var(--slate-700);
        }
        .instructions h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--slate-500);
            margin-bottom: 10px;
        }
        .instructions p { margin-bottom: 8px; line-height: 1.55; }
        .instructions p:last-child { margin-bottom: 0; }
        .instructions ul, .instructions ol { padding-left: 20px; margin: 6px 0; }
        .instructions a { color: var(--primary); }

        .lobby-countdown {
            padding: 20px 24px;
            text-align: center;
            background: var(--slate-50);
            border-radius: 14px;
            margin-bottom: 16px;
        }
        .lobby-countdown .relative-time {
            font-size: 28px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .lobby-countdown .abs-time { font-size: 12px; color: var(--slate-500); }

        .footer {
            text-align: center;
            padding: 16px 0 0;
            border-top: 1px solid var(--slate-100);
            color: var(--slate-400);
            font-size: 12px;
            margin-top: 16px;
        }
        .footer a { color: var(--slate-500); text-decoration: none; }
        .footer a:hover { color: var(--primary); }

        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--slate-900);
            color: #fff;
            padding: 10px 20px;
            border-radius: 999px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.2s, transform 0.2s;
            pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>
    <div class="card">
        <div class="banner {{ $status }}">
            <div class="banner-icon">
                @if($status === 'ready')
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                @elseif($status === 'too_early')
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif($status === 'ended')
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                @elseif($status === 'not_found')
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                @endif
            </div>
            <h1>{{ $pageTitle }}</h1>
            @if($status === 'ready')
                <p>Bilet valid. Poți intra în meeting.</p>
            @elseif($status === 'too_early')
                <p>Link-ul devine activ cu puțin înainte de start.</p>
            @elseif($status === 'ended')
                <p>Fereastra de participare s-a închis.</p>
            @elseif($status === 'not_found')
                <p>Codul acesta nu corespunde niciunui bilet.</p>
            @elseif($status === 'cancelled')
                <p>Acest bilet a fost anulat.</p>
            @elseif($status === 'refunded')
                <p>Acest bilet a fost returnat.</p>
            @elseif($status === 'unpaid')
                <p>Comanda nu a fost încă plătită.</p>
            @elseif($status === 'not_configured')
                <p>Organizatorul nu a completat încă link-ul de meeting.</p>
            @endif
        </div>

        <div class="body">
            @if(in_array($status, ['ready', 'too_early', 'ended', 'cancelled', 'refunded', 'unpaid', 'not_configured']) && $eventTitle !== '—')
            <div class="event-meta">
                <div class="label">Eveniment</div>
                <div class="title">{{ $eventTitle }}</div>
                @if($buyerName)
                <div class="attendee">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>{{ $buyerName }}</span>
                    @if($ticketType)
                    <span style="color: var(--slate-300);">·</span>
                    <span>{{ $ticketType }}</span>
                    @endif
                </div>
                @endif
            </div>
            @endif

            @if($status === 'ready')
                <div class="join-panel">
                    <div class="row">
                        <div class="k">Platformă</div>
                        <div class="v">{{ $providerLabel }}</div>
                    </div>
                    @if($passcode)
                    <div class="row">
                        <div class="k">Parolă</div>
                        <div class="v">
                            <span class="passcode-chip" id="passcode">{{ $passcode }}</span>
                            <button class="copy-btn" onclick="copyTxt('{{ addslashes($passcode) }}', 'Parolă copiată')" title="Copiază parola">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>
                    @endif
                </div>

                <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="cta">
                    Alătură-te acum →
                </a>
                <button class="cta-secondary" onclick="copyTxt('{{ addslashes($meetingUrl) }}', 'Link copiat')">
                    Copiază link direct
                </button>

                @if(!empty($instructionsHtml))
                <div class="instructions">
                    <h4>Instrucțiuni de acces</h4>
                    {!! $instructionsHtml !!}
                </div>
                @endif

            @elseif($status === 'too_early')
                @php
                    $lobbyTs = optional($lobbyOpensAt ?? null)->timestamp;
                @endphp
                <div class="lobby-countdown">
                    <div class="relative-time" id="countdown">se calculează…</div>
                    @if($lobbyTs)
                    <div class="abs-time">
                        Link-ul devine activ la {{ optional($lobbyOpensAt)->locale('ro')->translatedFormat('l, j F Y H:i') }}
                    </div>
                    @endif
                </div>
                <div class="instructions">
                    <h4>Ce urmează</h4>
                    <p>Când se apropie ora, revino la această pagină și vei putea intra direct în meeting.</p>
                    <p>Îți recomandăm să adaugi această pagină la favorite ca să o găsești ușor.</p>
                </div>

            @elseif($status === 'ended')
                <div class="instructions">
                    <h4>Evenimentul s-a terminat</h4>
                    <p>Fereastra de acces s-a închis. Dacă evenimentul a fost înregistrat, organizatorul îți va trimite link-ul înregistrării pe email.</p>
                </div>

            @elseif($status === 'not_configured')
                <div class="instructions">
                    <h4>Organizatorul nu a completat încă link-ul</h4>
                    <p>Va apărea aici imediat ce organizatorul îl setează. Îți recomandăm să revii mai aproape de start.</p>
                    @if(!empty($organizerContact['email']))
                    <p style="margin-top: 10px;">Pentru asistență: <a href="mailto:{{ $organizerContact['email'] }}">{{ $organizerContact['email'] }}</a></p>
                    @endif
                </div>
            @endif

            <div class="footer">
                @if($code)Cod bilet: <strong style="font-family: 'Fira Code', monospace; color: var(--slate-600);">{{ $code }}</strong> · @endif
                @if($marketplaceName)
                    @if($marketplaceSite)
                        <a href="{{ $marketplaceSite }}">{{ $marketplaceName }}</a>
                    @else
                        {{ $marketplaceName }}
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="toast" id="toast">Copiat</div>

    <script>
        function copyTxt(text, msg) {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(() => {
                const t = document.getElementById('toast');
                t.textContent = msg || 'Copiat';
                t.classList.add('show');
                setTimeout(() => t.classList.remove('show'), 1600);
            }).catch(() => {});
        }

        @if($status === 'too_early' && !empty($lobbyOpensAt))
        // Live countdown to lobby-opens time. Reload the page when it hits
        // 0 so the visitor gets the "ready" view without manually refreshing.
        (function () {
            const target = {{ optional($lobbyOpensAt)->timestamp * 1000 }};
            const el = document.getElementById('countdown');
            function tick() {
                const diff = target - Date.now();
                if (diff <= 0) {
                    location.reload();
                    return;
                }
                const s = Math.floor(diff / 1000);
                const d = Math.floor(s / 86400);
                const h = Math.floor((s % 86400) / 3600);
                const m = Math.floor((s % 3600) / 60);
                const sec = s % 60;
                let out;
                if (d > 0) out = `în ${d}z ${h}h ${m}m`;
                else if (h > 0) out = `în ${h}h ${m}m ${sec}s`;
                else if (m > 0) out = `în ${m}m ${sec}s`;
                else out = `în ${sec}s`;
                el.textContent = out;
            }
            tick();
            setInterval(tick, 1000);
        })();
        @endif
    </script>
</body>
</html>
