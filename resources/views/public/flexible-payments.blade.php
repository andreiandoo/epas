<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plăți flexibile — {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1f2937; line-height: 1.6; }
        .hero { background: linear-gradient(135deg, #A51C30, #8B1728); color: #fff; padding: 64px 24px; text-align: center; }
        .hero h1 { font-size: 34px; margin-bottom: 12px; }
        .hero p { font-size: 18px; opacity: .9; max-width: 640px; margin: 0 auto; }
        .wrap { max-width: 960px; margin: -32px auto 48px; padding: 0 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .card h2 { font-size: 20px; color: #A51C30; margin-bottom: 8px; }
        .card ul { margin: 12px 0 0 18px; color: #4b5563; }
        .steps { background: #fff; border-radius: 14px; padding: 28px; margin-top: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .steps ol { margin-left: 20px; }
        .note { font-size: 13px; color: #6b7280; margin-top: 24px; text-align: center; }
        .badge { display:inline-block; background:#fde68a; color:#92400e; font-size:12px; padding:2px 8px; border-radius:9999px; }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Plătește în felul tău</h1>
        <p>Cumperi biletele acum și alegi cum plătești: în rate, mai târziu, sau lași pe altcineva să achite.</p>
    </div>

    <div class="wrap">
        <div class="grid">
            <div class="card">
                <h2>💳 Plata în rate</h2>
                <p>Împarți costul biletului în rate lunare, cu un avans mic la început.</p>
                <ul>
                    <li>Avans la checkout, apoi rate automate</li>
                    <li>Debitare automată de pe card</li>
                    <li>Trebuie achitat integral înainte de eveniment</li>
                    <li>Biletul devine valabil după ultima plată</li>
                </ul>
            </div>
            <div class="card">
                <h2>⏳ Buy Now, Pay Later</h2>
                <p>Rezervi acum și plătești integral în maximum 30 de zile.</p>
                <ul>
                    <li>O singură plată amânată</li>
                    <li>Reminder pe email + link de plată</li>
                    <li>Înainte de data evenimentului</li>
                </ul>
            </div>
            <div class="card">
                <h2>🎁 Plătește altcineva</h2>
                <p>Blochezi biletul și trimiți un link de plată cuiva drag — de exemplu părintelui.</p>
                <ul>
                    <li>Link valabil 24 de ore</li>
                    <li>Fără costuri suplimentare</li>
                    <li>Biletele devin valabile după confirmare</li>
                </ul>
            </div>
        </div>

        <div class="steps">
            <h2>Cum funcționează</h2>
            <ol>
                <li>Alegi biletele și, la checkout, opțiunea de plată flexibilă (dacă e disponibilă pentru eveniment).</li>
                <li>Vezi transparent graficul de plăți și costul total înainte să confirmi.</li>
                <li>Plătești avansul (sau confirmi cardul), iar restul se debitează automat conform graficului.</li>
                <li>Poți plăti oricând anticipat din contul tău.</li>
            </ol>
            <p style="margin-top:16px;"><span class="badge">Important</span> Costul total în rate este puțin mai mare decât plata directă. Vezi mereu suma exactă înainte de a confirma.</p>
        </div>

        <p class="note">Metodele disponibile diferă de la un eveniment la altul și pot fi combinate doar cu bilete de la un singur eveniment per comandă.</p>
    </div>
</body>
</html>
