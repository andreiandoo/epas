<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dispute Evidence — Order {{ $order['order_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; padding-bottom: 3px; border-bottom: 1px solid #999; }
        h3 { font-size: 11px; margin: 12px 0 4px 0; color: #555; }
        .muted { color: #666; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; padding: 2px 6px; }
        .label { font-weight: bold; width: 30%; color: #444; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        table.data th { background: #eee; }
        .summary-box { background: #f5f7fa; border: 1px solid #d4d8dc; padding: 8px; margin: 6px 0; }
        .summary-box td { padding: 3px 6px; }
        .badge-ok { color: #096; font-weight: bold; }
        .badge-warn { color: #b40; font-weight: bold; }
        .small { font-size: 9px; }
        .code { font-family: monospace; font-size: 9px; }
        .footer { margin-top: 24px; font-size: 8px; color: #888; text-align: center; }
    </style>
</head>
<body>

<h1>Dispute Evidence Report</h1>
<div class="muted">Generated: {{ $generated_at }} · Tixello.com</div>

<h2>Order</h2>
<table class="grid">
    <tr><td class="label">Order number</td><td><strong>{{ $order['order_number'] }}</strong> (id #{{ $order['id'] }})</td></tr>
    <tr><td class="label">Status</td><td>{{ $order['status'] }} · payment: {{ $order['payment_status'] ?? '—' }}</td></tr>
    <tr><td class="label">Created at</td><td>{{ $order['created_at'] }}</td></tr>
    <tr><td class="label">Paid at</td><td>{{ $order['paid_at'] ?? '—' }}</td></tr>
    <tr><td class="label">Total</td><td><strong>{{ number_format($order['total'], 2) }} {{ $order['currency'] }}</strong> (subtotal: {{ number_format($order['subtotal'], 2) }}, discount: {{ number_format($order['discount_amount'], 2) }})</td></tr>
    <tr><td class="label">Payment processor</td><td>{{ $order['payment_processor'] ?? '—' }}</td></tr>
    <tr><td class="label">Payment reference</td><td class="code">{{ $order['payment_reference'] ?? '—' }}</td></tr>
    <tr><td class="label">Marketplace</td><td>{{ $order['marketplace_client'] ?? '—' }}</td></tr>
    <tr><td class="label">Organizer</td><td>{{ $order['marketplace_organizer'] ?? '—' }}</td></tr>
</table>

<h2>Customer</h2>
<table class="grid">
    <tr><td class="label">Name</td><td>{{ $customer['name'] ?? '—' }}</td></tr>
    <tr><td class="label">Email</td><td>{{ $customer['email'] ?? '—' }}</td></tr>
    <tr><td class="label">Phone</td><td>{{ $customer['phone'] ?? '—' }}</td></tr>
    <tr><td class="label">Visitor ID</td><td class="code">{{ $summary['visitor_id'] ?? '—' }}</td></tr>
</table>

<h2>Engagement summary</h2>
<table class="summary-box">
    <tr>
        <td><strong>{{ $summary['visit_count'] }}</strong> sessions</td>
        <td><strong>{{ $summary['event_count'] }}</strong> tracked events</td>
        <td>First seen: <strong>{{ $summary['first_seen_at'] ?? '—' }}</strong></td>
    </tr>
    <tr>
        <td><strong>{{ $summary['tickets_total'] }}</strong> tickets in order</td>
        <td><strong>{{ $summary['tickets_checked_in'] }}</strong> checked in at venue</td>
        <td>Browsing → purchase: <strong>{{ $summary['days_browsing_before_purchase'] !== null ? $summary['days_browsing_before_purchase'] . ' days' : '—' }}</strong></td>
    </tr>
</table>
<p class="small muted">
    Cardholder visited the site, viewed event pages, added items to cart and completed payment with full intent.
    Tickets that were checked in at the venue are recorded with timestamp and operator id below.
</p>

<h2>Tickets ({{ count($tickets) }})</h2>
@if(count($tickets) > 0)
    <table class="data">
        <thead><tr><th>#</th><th>Code</th><th>Status</th><th>Seat</th><th>Price</th><th>Attendee</th><th>Checked-in at</th><th>By (user id)</th></tr></thead>
        <tbody>
        @foreach($tickets as $i => $t)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="code">{{ $t['code'] ?? '—' }}</td>
                <td>{{ $t['status'] ?? '—' }}{{ $t['is_cancelled'] ? ' (cancelled)' : '' }}</td>
                <td>{{ $t['seat_label'] ?? '—' }}</td>
                <td>{{ number_format($t['price'], 2) }}</td>
                <td>{{ $t['attendee_name'] ?? '—' }}<br><span class="small muted">{{ $t['attendee_email'] ?? '' }}</span></td>
                <td>
                    @if($t['checked_in_at'])
                        <span class="badge-ok">{{ $t['checked_in_at'] }}</span>
                    @else
                        <span class="muted">not checked in</span>
                    @endif
                </td>
                <td class="code">{{ $t['checked_in_by'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No tickets recorded for this order.</p>
@endif

<h2>Sessions ({{ count($sessions) }})</h2>
@if(count($sessions) > 0)
    <table class="data">
        <thead><tr><th>Started</th><th>Duration</th><th>Pageviews</th><th>Source / medium</th><th>Landing</th><th>Country / city</th><th>Device</th><th>Converted</th></tr></thead>
        <tbody>
        @foreach($sessions as $s)
            <tr>
                <td class="small">{{ $s['started_at'] ?? '—' }}</td>
                <td>{{ $s['duration_seconds'] ?? 0 }}s</td>
                <td>{{ $s['pageviews'] ?? 0 }}</td>
                <td class="small">{{ $s['source'] ?? 'direct' }} / {{ $s['medium'] ?? '—' }}{{ $s['campaign'] ? ' · '.$s['campaign'] : '' }}</td>
                <td class="small">{{ $s['landing_page'] ? \Illuminate\Support\Str::limit($s['landing_page'], 60) : '—' }}</td>
                <td>{{ $s['country_code'] ?? '—' }} / {{ $s['city'] ?? '—' }}</td>
                <td class="small">{{ $s['device_type'] ?? '—' }} · {{ $s['browser'] ?? '—' }} · {{ $s['os'] ?? '—' }}</td>
                <td>{!! ($s['converted'] ?? false) ? '<span class="badge-ok">YES</span>' : 'no' !!}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No session data recorded.</p>
@endif

<h2>Event timeline ({{ count($events) }})</h2>
@if(count($events) > 0)
    <table class="data">
        <thead><tr><th>Time</th><th>Event</th><th>Page</th><th>IP</th><th>Country/city</th><th>Device · Browser · OS</th></tr></thead>
        <tbody>
        @foreach($events as $e)
            <tr>
                <td class="small">{{ $e['occurred_at'] ?? '—' }}</td>
                <td><strong>{{ $e['event_type'] ?? '—' }}</strong>{{ isset($e['order_id']) && $e['order_id'] == $order['id'] ? ' (this order)' : '' }}</td>
                <td class="small">{{ $e['page_url'] ? \Illuminate\Support\Str::limit($e['page_url'], 70) : '—' }}</td>
                <td class="code">{{ $e['ip_address'] ?? '—' }}</td>
                <td class="small">{{ $e['country_code'] ?? '' }} / {{ $e['city'] ?? '' }}</td>
                <td class="small">{{ $e['device_type'] ?? '' }} · {{ $e['browser'] ?? '' }} · {{ $e['os'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No tracked events for this order.</p>
@endif

<div class="footer">
    Tixello · This document is an automated audit report. All data is sourced from the platform's first-party tracking and order systems.
</div>

</body>
</html>
