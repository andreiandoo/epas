<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        .company-info, .client-info {
            margin-bottom: 20px;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        table thead {
            background: #f3f4f6;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        table th {
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-left: auto;
            width: 300px;
            margin-top: 20px;
        }
        .totals table {
            margin: 0;
        }
        .totals table td {
            border: none;
            padding: 8px 0;
        }
        .total-row {
            font-size: 18px;
            font-weight: bold;
        }
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        .paid-stamp {
            color: #10b981;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            border: 3px solid #10b981;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="invoice-title">INVOICE</div>
                <div class="company-info">
                    @php
                        $settings = \App\Models\Setting::current();
                    @endphp
                    <strong>{{ $settings->company_name ?? config('app.name') }}</strong><br>
                    @if($settings->cui)CUI: {{ $settings->cui }}<br>@endif
                    @if($settings->reg_com)Reg. Com.: {{ $settings->reg_com }}<br>@endif
                    @if($settings->address){{ $settings->address }}<br>@endif
                    @if($settings->city){{ $settings->city }}@if($settings->postal_code), {{ $settings->postal_code }}@endif<br>@endif
                    @if($settings->country){{ $settings->country }}<br>@endif
                </div>
            </div>
            <div class="header-right text-right">
                <p>
                    <span class="label">Invoice Number:</span><br>
                    <strong>{{ $invoice->number }}</strong>
                </p>
                <p>
                    <span class="label">Invoice Date:</span><br>
                    {{ $invoice->issue_date->format('d/m/Y') }}
                </p>
                <p>
                    <span class="label">Due Date:</span><br>
                    {{ $invoice->due_date->format('d/m/Y') }}
                </p>
            </div>
        </div>

        <div class="client-info">
            <span class="label">Bill To:</span><br>
            <strong>{{ $tenant->public_name ?? $tenant->name }}</strong><br>
            @if($tenant->company_name){{ $tenant->company_name }}<br>@endif
            @if($tenant->cui)CUI: {{ $tenant->cui }}<br>@endif
            @if($tenant->address){{ $tenant->address }}<br>@endif
            @if($tenant->city){{ $tenant->city }}@if($tenant->postal_code), {{ $tenant->postal_code }}@endif<br>@endif
            @if($tenant->contact_email)Email: {{ $tenant->contact_email }}<br>@endif
        </div>

        @if($invoice->status === 'paid')
            <div class="paid-stamp">✓ PAID</div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Pricing Model</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($microservices as $microservice)
                    <tr>
                        <td>
                            <strong>{{ $microservice->name }}</strong><br>
                            <small>{{ $microservice->short_description }}</small>
                        </td>
                        <td>{{ ucfirst($microservice->pricing_model) }}</td>
                        <td class="text-right">{{ number_format($microservice->price, 2) }} {{ $invoice->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">{{ number_format($invoice->subtotal, 2) }} {{ $invoice->currency }}</td>
                </tr>
                <tr>
                    <td>VAT ({{ $invoice->vat_rate }}%):</td>
                    <td class="text-right">{{ number_format($invoice->vat_amount, 2) }} {{ $invoice->currency }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total:</td>
                    <td class="text-right">{{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            @if($settings->invoice_footer)
                <p>{{ $settings->invoice_footer }}</p>
            @endif
            <p>Payment Method: Credit Card (Stripe)</p>
            <p>This is an automated invoice. Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
