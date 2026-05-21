<!DOCTYPE html>
<html lang="ro" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->public_name ?? $tenant->name }} — Bilete</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --accent: {{ $accent }}; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            @if ($bgImage) background: url('{{ $bgImage }}') center/cover; @endif
        }
        .btn-accent { background: var(--accent); color: white; }
        .btn-accent:hover { filter: brightness(0.92); }
        .text-accent { color: var(--accent); }
        .border-accent { border-color: var(--accent); }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 dark:text-white">
    <div class="max-w-3xl mx-auto p-4">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="h-12 mx-auto mb-4">
        @endif

        <header class="text-center mb-6">
            <h1 class="text-2xl font-bold">{{ $tenant->public_name ?? $tenant->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selectează biletele dorite</p>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="ticket-list">
            @foreach ($ticketTypes as $tt)
                @php
                    $cp = is_array($tt->channel_pricing) ? $tt->channel_pricing : [];
                    $priceCents = $cp['embed'] ?? $tt->price_cents ?? 0;
                @endphp
                <div class="p-4 rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $tt->service_category ?? 'access' }}</div>
                    <div class="font-semibold mt-1">{{ $tt->name }}</div>
                    <div class="text-2xl font-bold mt-2 text-accent">
                        {{ number_format($priceCents / 100, 2) }} {{ $tt->currency ?? 'RON' }}
                    </div>
                    @if ($tt->service_duration_minutes)
                        <div class="text-xs text-gray-500 mt-1">Durată: {{ $tt->service_duration_minutes }} min</div>
                    @endif
                    <button
                        data-ticket-id="{{ $tt->id }}"
                        class="add-btn mt-3 w-full py-2 rounded-lg btn-accent font-semibold"
                    >
                        Adaugă
                    </button>
                </div>
            @endforeach
        </div>

        @if ($ticketTypes->isEmpty())
            <div class="text-center py-10 text-gray-500">
                Niciun bilet disponibil momentan.
            </div>
        @endif

        <div id="cart-footer" class="fixed bottom-0 inset-x-0 bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 p-3 hidden">
            <div class="max-w-3xl mx-auto flex items-center justify-between">
                <div>
                    <span id="cart-count" class="font-semibold">0</span> bilete ·
                    <span id="cart-total" class="font-bold">0.00 RON</span>
                </div>
                <button id="checkout-btn" class="px-6 py-3 rounded-lg btn-accent font-semibold">
                    Continuă →
                </button>
            </div>
        </div>
    </div>

    <script>
        // Send container height to parent for auto-resize
        function sendHeight() {
            var h = document.body.scrollHeight;
            try { parent.postMessage({ type: 'tixello:resize', height: h }, '*'); } catch (e) {}
        }
        window.addEventListener('load', sendHeight);
        window.addEventListener('resize', sendHeight);
        new ResizeObserver(sendHeight).observe(document.body);

        // Tiny cart
        var cart = {};
        document.querySelectorAll('.add-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-ticket-id');
                cart[id] = (cart[id] || 0) + 1;
                renderCart();
            });
        });

        function renderCart() {
            var count = Object.values(cart).reduce(function (a, b) { return a + b; }, 0);
            document.getElementById('cart-count').textContent = count;
            document.getElementById('cart-footer').classList.toggle('hidden', count === 0);
            // TODO: compute total from data-prices when expanded.
        }

        document.getElementById('checkout-btn')?.addEventListener('click', function () {
            // E11 stub: actual checkout would POST to /api/leisure/orders.
            // For now we just signal the parent.
            try { parent.postMessage({ type: 'tixello:checkout:click', cart: cart }, '*'); } catch (e) {}
            alert('Checkout integration coming next. Cart: ' + JSON.stringify(cart));
        });
    </script>
</body>
</html>
