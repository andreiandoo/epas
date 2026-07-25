<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plată demo — comanda #{{ $order->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f1115; color: #e8e8ea; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { width: 100%; max-width: 460px; background: #171a21; border: 1px solid #262a33; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.4); }
        .banner { background: linear-gradient(90deg, #f59e0b, #d97706); color: #1a1a1a; text-align: center; padding: .55rem; font-size: .8rem; font-weight: 700; letter-spacing: .04em; }
        .body { padding: 1.75rem; }
        h1 { font-size: 1.15rem; margin-bottom: .35rem; }
        .muted { color: #9aa0ab; font-size: .85rem; }
        .summary { background: #0f1218; border: 1px solid #262a33; border-radius: 12px; padding: 1rem 1.1rem; margin: 1.25rem 0; }
        .row { display: flex; justify-content: space-between; align-items: center; padding: .25rem 0; font-size: .9rem; }
        .row.total { border-top: 1px solid #262a33; margin-top: .5rem; padding-top: .7rem; font-size: 1.15rem; font-weight: 700; }
        .amount { color: #f59e0b; }
        .seats { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .6rem; }
        .seat { background: #23272f; border: 1px solid #333844; border-radius: 6px; padding: .15rem .5rem; font-size: .72rem; color: #cfd3da; }
        .fake-card { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin: 1.1rem 0 .4rem; }
        .fake-card .full { grid-column: 1 / -1; }
        .fake-card label { display: block; font-size: .7rem; color: #9aa0ab; margin-bottom: .25rem; }
        .fake-card input { width: 100%; background: #0f1218; border: 1px solid #2b303a; border-radius: 8px; padding: .6rem .7rem; color: #e8e8ea; font-size: .9rem; }
        .actions { display: flex; flex-direction: column; gap: .6rem; margin-top: 1.3rem; }
        button { width: 100%; border: 0; border-radius: 10px; padding: .85rem; font-size: .98rem; font-weight: 600; cursor: pointer; transition: opacity .15s ease; }
        button:hover { opacity: .9; }
        .pay { background: #f59e0b; color: #1a1a1a; }
        .fail { background: transparent; color: #9aa0ab; border: 1px solid #2b303a; }
        .note { text-align: center; font-size: .72rem; color: #6b7280; margin-top: 1rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="banner">🔒 PLATĂ DEMO — MEDIU DE TEST, FĂRĂ BANI REALI</div>
        <div class="body">
            <h1>Finalizează plata</h1>
            <p class="muted">Comanda #{{ $order->id }} · {{ $customer }}</p>

            <div class="summary">
                <div class="row"><span class="muted">Total de plată</span></div>
                <div class="row total"><span>Total</span><span class="amount">{{ $total }} RON</span></div>
                @if (!empty($seatLabels))
                <div class="seats">
                    @foreach ($seatLabels as $label)
                        <span class="seat">{{ $label }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="fake-card">
                <div class="full">
                    <label>Număr card (demo)</label>
                    <input type="text" value="4242 4242 4242 4242" readonly>
                </div>
                <div>
                    <label>Expirare</label>
                    <input type="text" value="12 / 30" readonly>
                </div>
                <div>
                    <label>CVV</label>
                    <input type="text" value="123" readonly>
                </div>
            </div>

            <div class="actions">
                <form method="POST" action="{{ $payUrl }}">
                    @csrf
                    <button type="submit" class="pay">Plătește {{ $total }} RON</button>
                </form>
                <form method="POST" action="{{ $failUrl }}">
                    @csrf
                    <button type="submit" class="fail">Simulează plată eșuată</button>
                </form>
            </div>

            <p class="note">Acesta este un gateway simulat pentru testarea fluxului de achiziție.<br>Nu se procesează niciun card real.</p>
        </div>
    </div>
</body>
</html>
