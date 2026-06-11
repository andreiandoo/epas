<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>QR Codes — Inventar fizic — {{ $tenantName }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
        }
        .toolbar {
            position: sticky;
            top: 0;
            background: #1f2937;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .toolbar h1 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }
        .toolbar button {
            background: white;
            color: #1f2937;
            border: 0;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            padding: 16px;
        }
        .card {
            border: 2px solid #111;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
            background: white;
        }
        .card img {
            display: block;
            margin: 4px auto 10px;
            width: 180px;
            height: 180px;
        }
        .card .name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 4px;
        }
        .card .label {
            color: #4b5563;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .card .code {
            font-family: monospace;
            font-size: 11px;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .card .type {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-top: 6px;
        }
        @media print {
            .toolbar { display: none; }
            .grid { padding: 0; }
            .card { border-color: #000; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>QR Codes — {{ $resources->count() }} resurse · {{ $tenantName }}</h1>
        <button type="button" onclick="window.print()">🖨️ Tipărește</button>
    </div>

    <div class="grid">
        @foreach ($resources as $resource)
            <div class="card">
                <div class="type">{{ $resource->resource_type }}</div>
                <div class="name">{{ $resource->name }}</div>
                @if ($resource->label)
                    <div class="label">{{ $resource->label }}</div>
                @endif
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=4&data={{ urlencode($resource->qr_code) }}"
                    alt="QR {{ $resource->qr_code }}"
                    loading="lazy"
                >
                <div class="code">{{ $resource->qr_code }}</div>
            </div>
        @endforeach
    </div>

    <script>
        // Auto-trigger print dialog after images load (if we're in a fresh tab).
        if (window.location.search.includes('autoprint=1')) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        }
    </script>
</body>
</html>
