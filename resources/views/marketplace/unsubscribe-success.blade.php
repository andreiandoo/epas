<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dezabonare reușită</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }
        .icon {
            width: 64px;
            height: 64px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon svg { width: 32px; height: 32px; color: #059669; }
        h1 { color: #111827; font-size: 24px; margin: 0 0 12px; }
        p { color: #6b7280; font-size: 16px; line-height: 1.6; margin: 0; }
        .marketplace { font-weight: 600; color: #374151; }

        .reason {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            text-align: left;
        }
        .reason h2 { font-size: 15px; color: #374151; margin: 0 0 4px; text-align: center; }
        .reason .hint { font-size: 13px; color: #9ca3af; text-align: center; margin: 0 0 16px; }
        .opt {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #374151;
            transition: border-color .15s, background .15s;
        }
        .opt:hover { border-color: #a7f3d0; background: #f0fdf4; }
        .opt input { accent-color: #059669; width: 16px; height: 16px; }
        textarea {
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 68px;
        }
        button {
            margin-top: 14px;
            width: 100%;
            padding: 11px 16px;
            background: #059669;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #047857; }
        button:disabled { background: #9ca3af; cursor: default; }
        .thanks { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #059669; font-weight: 600; font-size: 15px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1>Dezabonare reușită</h1>
        <p>Ai fost dezabonat de la newsletterele <span class="marketplace">{{ $marketplace }}</span>. Nu vei mai primi emailuri de marketing de la noi.</p>

        @isset($recipientId)
        <form id="reason-form" class="reason"
              data-id="{{ $recipientId }}"
              data-token="{{ $token }}"
              data-url="{{ url('/api/marketplace-client/newsletter/unsubscribe-reason') }}">
            <h2>Ne poți spune de ce?</h2>
            <p class="hint">Opțional — ne ajută să îmbunătățim ce trimitem.</p>

            <label class="opt"><input type="radio" name="reason" value="too_many"> Primesc prea multe emailuri</label>
            <label class="opt"><input type="radio" name="reason" value="not_relevant"> Conținutul nu mai e relevant pentru mine</label>
            <label class="opt"><input type="radio" name="reason" value="never_signed_up"> Nu îmi amintesc să mă fi abonat</label>
            <label class="opt"><input type="radio" name="reason" value="spam"> Emailurile ajung în spam / sunt nedorite</label>
            <label class="opt"><input type="radio" name="reason" value="other"> Alt motiv</label>

            <textarea id="reason-detail" class="hidden" name="detail" maxlength="500" placeholder="Spune-ne mai multe (opțional)"></textarea>

            <button type="submit" id="reason-submit" disabled>Trimite feedback</button>
        </form>
        <div id="reason-thanks" class="thanks hidden">Îți mulțumim pentru feedback!</div>
        @endisset
    </div>

    <script>
        (function () {
            var form = document.getElementById('reason-form');
            if (!form) return;
            var detail = document.getElementById('reason-detail');
            var submit = document.getElementById('reason-submit');
            var thanks = document.getElementById('reason-thanks');

            form.addEventListener('change', function (e) {
                if (e.target.name === 'reason') {
                    submit.disabled = false;
                    detail.classList.toggle('hidden', e.target.value !== 'other');
                }
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var chosen = form.querySelector('input[name="reason"]:checked');
                if (!chosen) return;
                submit.disabled = true;
                submit.textContent = 'Se trimite...';

                fetch(form.dataset.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        id: form.dataset.id,
                        token: form.dataset.token,
                        reason: chosen.value,
                        detail: detail.value || null
                    })
                }).then(function () {
                    form.classList.add('hidden');
                    thanks.classList.remove('hidden');
                }).catch(function () {
                    // Feedback is best-effort; never block the user on a failure.
                    form.classList.add('hidden');
                    thanks.classList.remove('hidden');
                });
            });
        })();
    </script>
</body>
</html>
