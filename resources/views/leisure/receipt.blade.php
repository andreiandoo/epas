<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Chitanță — {{ $order->order_number ?? $order->id }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 0;
            color: #000;
            font-size: 11px;
            line-height: 1.35;
            width: 72mm;
        }
        .toolbar {
            position: sticky;
            top: 0;
            background: #1f2937;
            color: white;
            padding: 10px;
            font-family: sans-serif;
            text-align: center;
        }
        .toolbar button {
            background: white;
            color: #1f2937;
            border: 0;
            padding: 6px 16px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        .receipt {
            padding: 4mm;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .lg { font-size: 14px; }
        .xl { font-size: 18px; font-weight: bold; }
        hr {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .item-name { width: 60%; }
        .item-qty { width: 15%; text-align: right; }
        .item-total { width: 25%; text-align: right; }
        @media print {
            .toolbar { display: none; }
            .receipt { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">🖨️ Tipărește</button>
    </div>

    <div class="receipt">
        @if ($taxRegistry)
            <div class="center bold lg">{{ $taxRegistry->company_name }}</div>
            <div class="center">CUI: {{ $taxRegistry->cui }}</div>
            @if ($taxRegistry->reg_com)
                <div class="center">{{ $taxRegistry->reg_com }}</div>
            @endif
            @if ($taxRegistry->address)
                <div class="center">{{ $taxRegistry->address }}, {{ $taxRegistry->city }}</div>
            @endif
        @else
            <div class="center bold lg">{{ $tenant?->public_name ?? $tenant?->name ?? 'Tixello' }}</div>
            @if ($tenant?->cui)
                <div class="center">CUI: {{ $tenant->cui }}</div>
            @endif
        @endif

        <hr>

        <div class="center bold">CHITANȚĂ</div>
        <div class="center">Nr. {{ $order->order_number ?? $order->id }}</div>
        <div class="center">{{ $printedAt->format('d.m.Y H:i') }}</div>

        <hr>

        <table>
            @foreach ($items as $item)
                <tr>
                    <td class="item-name">{{ $item->ticketType?->name ?? $item->name ?? 'Produs' }}</td>
                    <td class="item-qty">{{ $item->quantity ?? 1 }}</td>
                    <td class="item-total bold">{{ number_format(($item->total_cents ?? $item->subtotal_cents ?? 0) / 100, 2) }}</td>
                </tr>
            @endforeach
        </table>

        <hr>

        <table>
            <tr>
                <td class="bold xl">TOTAL</td>
                <td class="right bold xl">{{ number_format(($order->total_cents ?? 0) / 100, 2) }} RON</td>
            </tr>
        </table>

        <hr>

        <div>Plată: {{ strtoupper($paymentMethod) }}</div>
        <div>Canal: {{ $channelLabel }}</div>

        <hr>
        <div class="center" style="font-size: 9px;">
            Mulțumim!<br>
            Powered by Tixello
        </div>
    </div>

    <script>
        // Auto-print on load (unless ?noprint=1).
        if (!window.location.search.includes('noprint=1')) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        }
    </script>
</body>
</html>
