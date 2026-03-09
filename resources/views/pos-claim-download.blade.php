<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Biletele tale - {{ $claim->event_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f3ff;
            color: #1f2937;
            min-height: 100vh;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: #fff;
            padding: 24px 20px;
            text-align: center;
        }
        .card-header h1 { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
        .card-header .event-info { font-size: 13px; opacity: 0.9; margin-top: 8px; line-height: 1.4; }
        .card-body { padding: 24px 20px; }
        .ticket-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
        }
        .ticket-type {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .ticket-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: 700;
            color: #7c3aed;
            letter-spacing: 1px;
            text-align: center;
            padding: 12px;
            background: #f5f3ff;
            border-radius: 8px;
        }
        .ticket-note {
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
            margin-top: 6px;
        }
        .order-info {
            font-size: 13px;
            color: #6b7280;
            text-align: center;
            margin-bottom: 20px;
        }
        .download-status {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 14px;
            margin-top: 20px;
            text-align: center;
        }
        .download-status p {
            font-size: 13px;
            color: #166534;
            line-height: 1.5;
        }
        .download-status.downloading {
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .download-status.downloading p { color: #1e40af; }
        .btn-save {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            background: #7c3aed;
            color: #fff;
            margin-top: 16px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-save:active { transform: scale(0.98); }
        .ticket-images { margin-top: 16px; }
        .ticket-images img {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h1>🎫 Biletele tale</h1>
        <div class="event-info">
            <strong>{{ $claim->event_name }}</strong>
            @if($claim->event_date)
                <br>{{ $claim->event_date }}
            @endif
            @if($claim->venue_name)
                — {{ $claim->venue_name }}
            @endif
        </div>
    </div>

    <div class="card-body">
        <div class="order-info">
            Comanda: <strong>{{ $order->order_number }}</strong>
        </div>

        @foreach($order->tickets as $ticket)
            <div class="ticket-card">
                <div class="ticket-type">{{ $ticket->ticketType?->name ?? 'Bilet' }}</div>
                <div class="ticket-code">{{ $ticket->code }}</div>
                <div class="ticket-note">Prezintă acest cod la intrare</div>
            </div>
        @endforeach

        <div class="download-status downloading" id="download-status">
            <p id="status-text">⏳ Se generează biletele pentru descărcare...</p>
        </div>

        <div class="ticket-images" id="ticket-images"></div>

        <button class="btn-save" id="btn-save-all" style="display:none;" onclick="saveAllTickets()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Salvează biletele în galerie
        </button>
    </div>
</div>

{{-- Hidden canvas for ticket generation --}}
<canvas id="ticket-canvas" style="display:none;"></canvas>

<script>
(function() {
    const TICKETS = @json($order->tickets->map(fn($t) => [
        'type' => $t->ticketType?->name ?? 'Bilet',
        'code' => $t->code,
    ]));
    const EVENT_NAME = @json($claim->event_name);
    const EVENT_DATE = @json($claim->event_date ?? '');
    const VENUE_NAME = @json($claim->venue_name ?? '');
    const ORDER_NUM = @json($order->order_number);

    const generatedImages = [];

    function drawTicket(canvas, ticket, index) {
        const W = 800;
        const H = 460;
        const ctx = canvas.getContext('2d');
        canvas.width = W;
        canvas.height = H;

        // Background
        ctx.fillStyle = '#ffffff';
        roundRect(ctx, 0, 0, W, H, 16, true);

        // Purple header bar
        const headerH = 120;
        ctx.save();
        ctx.beginPath();
        roundRectPath(ctx, 0, 0, W, headerH, [16, 16, 0, 0]);
        ctx.clip();
        const grad = ctx.createLinearGradient(0, 0, W, 0);
        grad.addColorStop(0, '#7c3aed');
        grad.addColorStop(1, '#8b5cf6');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, headerH);

        // Header text
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎫 ' + EVENT_NAME, W / 2, 45);

        ctx.font = '15px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.globalAlpha = 0.9;
        let subLine = '';
        if (EVENT_DATE) subLine += EVENT_DATE;
        if (VENUE_NAME) subLine += (subLine ? ' — ' : '') + VENUE_NAME;
        if (subLine) ctx.fillText(subLine, W / 2, 72);

        ctx.font = '13px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.globalAlpha = 0.7;
        ctx.fillText('Comanda: ' + ORDER_NUM, W / 2, 100);
        ctx.restore();

        // Dashed separator (perforated line effect)
        ctx.setLineDash([8, 6]);
        ctx.strokeStyle = '#d1d5db';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(30, headerH + 10);
        ctx.lineTo(W - 30, headerH + 10);
        ctx.stroke();
        ctx.setLineDash([]);

        // Ticket type label
        const bodyY = headerH + 40;
        ctx.fillStyle = '#6b7280';
        ctx.font = '15px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(ticket.type, W / 2, bodyY);

        // Code background
        const codeY = bodyY + 20;
        const codeH = 70;
        ctx.fillStyle = '#f5f3ff';
        roundRect(ctx, 40, codeY, W - 80, codeH, 12, true);

        // Code text
        ctx.fillStyle = '#7c3aed';
        ctx.font = 'bold 32px "Courier New", monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(ticket.code, W / 2, codeY + codeH / 2);
        ctx.textBaseline = 'alphabetic';

        // Instruction text
        ctx.fillStyle = '#9ca3af';
        ctx.font = '14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.fillText('Prezintă acest cod la intrare', W / 2, codeY + codeH + 35);

        // Ticket number (if multiple)
        if (TICKETS.length > 1) {
            ctx.fillStyle = '#d1d5db';
            ctx.font = '13px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText('Bilet ' + (index + 1) + ' din ' + TICKETS.length, W - 30, H - 20);
        }

        // Bottom branding
        ctx.fillStyle = '#e5e7eb';
        ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText('ambilet.ro', 30, H - 20);

        // Border
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 2;
        roundRect(ctx, 0, 0, W, H, 16, false, true);
    }

    function roundRect(ctx, x, y, w, h, r, fill, stroke) {
        roundRectPath(ctx, x, y, w, h, typeof r === 'number' ? [r,r,r,r] : r);
        if (fill) ctx.fill();
        if (stroke) ctx.stroke();
    }

    function roundRectPath(ctx, x, y, w, h, radii) {
        const [tl, tr, br, bl] = radii;
        ctx.beginPath();
        ctx.moveTo(x + tl, y);
        ctx.lineTo(x + w - tr, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + tr);
        ctx.lineTo(x + w, y + h - br);
        ctx.quadraticCurveTo(x + w, y + h, x + w - br, y + h);
        ctx.lineTo(x + bl, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - bl);
        ctx.lineTo(x, y + tl);
        ctx.quadraticCurveTo(x, y, x + tl, y);
        ctx.closePath();
    }

    function triggerDownload(dataUrl, filename) {
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function generateAndDownload() {
        const canvas = document.getElementById('ticket-canvas');
        const container = document.getElementById('ticket-images');
        const statusEl = document.getElementById('download-status');
        const statusText = document.getElementById('status-text');

        TICKETS.forEach(function(ticket, i) {
            drawTicket(canvas, ticket, i);
            const dataUrl = canvas.toDataURL('image/png');
            generatedImages.push({ dataUrl, filename: 'bilet-' + (i + 1) + '.png' });

            // Show preview
            const img = document.createElement('img');
            img.src = dataUrl;
            img.alt = ticket.type + ' - ' + ticket.code;
            container.appendChild(img);

            // Auto-download each ticket
            const safeName = EVENT_NAME.replace(/[^a-zA-Z0-9ăâîșțĂÂÎȘȚ ]/g, '').replace(/\s+/g, '-').substring(0, 30);
            const filename = 'bilet-' + safeName + (TICKETS.length > 1 ? '-' + (i + 1) : '') + '.png';
            triggerDownload(dataUrl, filename);
        });

        // Update status
        statusEl.classList.remove('downloading');
        statusText.textContent = '✅ ' + TICKETS.length + (TICKETS.length === 1 ? ' bilet descărcat!' : ' bilete descărcate!') + ' Verifică folderul Downloads.';
        document.getElementById('btn-save-all').style.display = 'flex';
    }

    window.saveAllTickets = function() {
        generatedImages.forEach(function(img, i) {
            const safeName = EVENT_NAME.replace(/[^a-zA-Z0-9ăâîșțĂÂÎȘȚ ]/g, '').replace(/\s+/g, '-').substring(0, 30);
            const filename = 'bilet-' + safeName + (TICKETS.length > 1 ? '-' + (i + 1) : '') + '.png';
            triggerDownload(img.dataUrl, filename);
        });
    };

    // Small delay to let page render first
    setTimeout(generateAndDownload, 500);
})();
</script>

</body>
</html>
