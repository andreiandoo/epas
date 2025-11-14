<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; }
        .invoice-container { padding: 40px; max-width: 800px; margin: 0 auto; }

        /* Header Grid */
        .invoice-header-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .invoice-header-left, .invoice-header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        /* Company Info */
        .company-logo { max-width: 200px; max-height: 80px; margin-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .company-details { font-size: 12px; line-height: 1.8; color: #666; }

        /* Tenant Billing Info */
        .tenant-billing { text-align: right; }
        .tenant-billing h3 {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #333;
        }
        .tenant-billing-info { font-size: 12px; line-height: 1.8; color: #666; }
        .tenant-name { font-weight: bold; color: #333; margin-bottom: 5px; }

        /* Invoice Details */
        .invoice-details {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .invoice-details-item { margin-bottom: 8px; font-size: 13px; }
        .invoice-number { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #333; }

        /* Invoice Table */
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .invoice-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #ddd;
        }
        .invoice-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .invoice-table .text-right { text-align: right; }
        .invoice-subtotal { background: #fafafa; font-weight: normal; }
        .invoice-vat { background: #fafafa; font-weight: normal; }
        .invoice-total { background: #f0f0f0; font-weight: bold; font-size: 16px; border-top: 2px solid #ddd; }

        /* Banking Section */
        .invoice-banking { background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .invoice-banking h3 { font-size: 14px; font-weight: bold; margin-bottom: 10px; }
        .invoice-banking div { margin-bottom: 5px; font-size: 13px; }

        /* Footer */
        .invoice-footer { text-align: center; font-size: 11px; color: #666; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="invoice-container">
        {{-- Header Grid: Company Info (Left) and Tenant Billing Info (Right) --}}
        <div class="invoice-header-grid">
            <div class="invoice-header-left">
                @if($settings?->logo_path)
                    <img src="{{ public_path('storage/' . $settings->logo_path) }}" alt="{{ $settings->company_name }}" class="company-logo">
                @endif
                <div class="company-name">{{ $settings->company_name ?? 'Company Name' }}</div>
                <div class="company-details">
                    @if($settings->cui)
                        CUI: {{ $settings->cui }} |
                    @endif
                    @if($settings->reg_com)
                        Reg. Com.: {{ $settings->reg_com }} |
                    @endif
                    @if($settings->vat_number)
                        VAT: {{ $settings->vat_number }}
                    @endif
                    <br>
                    @if($settings->address)
                        {{ $settings->address }}@if($settings->city || $settings->state),@endif
                    @endif
                    @if($settings->city)
                        {{ $settings->city }}@if($settings->state),@endif
                    @endif
                    @if($settings->state)
                        {{ $settings->state }}
                    @endif
                    @if($settings->country)
                        {{ $settings->country }}
                    @endif
                    <br>
                    @if($settings->phone)
                        Tel: {{ $settings->phone }} |
                    @endif
                    @if($settings->email)
                        Email: {{ $settings->email }}
                    @endif
                    @if($settings->website)
                        | {{ $settings->website }}
                    @endif
                    <br>
                    @if($settings->bank_name)
                        Bank: {{ $settings->bank_name }} |
                    @endif
                    @if($settings->bank_account)
                        IBAN: {{ $settings->bank_account }}
                    @endif
                    @if($settings->bank_swift)
                        | SWIFT: {{ $settings->bank_swift }}
                    @endif
                </div>
            </div>

            <div class="invoice-header-right tenant-billing">
                <h3>Client:</h3>
                <div class="tenant-billing-info">
                    <div class="tenant-name">{{ $tenant->company_name ?? $tenant->name ?? 'N/A' }}</div>
                    @if($tenant)
                        @if($tenant->cui)
                            CUI: {{ $tenant->cui }}<br>
                        @endif
                        @if($tenant->reg_com)
                            Reg. Com.: {{ $tenant->reg_com }}<br>
                        @endif
                        @if($tenant->bank_name)
                            Bank: {{ $tenant->bank_name }}<br>
                        @endif
                        @if($tenant->bank_account)
                            IBAN: {{ $tenant->bank_account }}<br>
                        @endif
                        {{ $tenant->address }}<br>
                        {{ $tenant->city }}@if($tenant->city && $tenant->state), @endif{{ $tenant->state }}<br>
                        @if($tenant->country)
                            {{ $tenant->country }}<br>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Invoice Details --}}
        <div class="invoice-details">
            <div class="invoice-number">Invoice #{{ $invoice->number }}</div>
            <div class="invoice-details-item"><strong>Issue Date:</strong> {{ $invoice->issue_date->format('M d, Y') }}</div>
            @if($invoice->period_start && $invoice->period_end)
                <div class="invoice-details-item"><strong>Billing Period:</strong> {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}</div>
            @endif
            <div class="invoice-details-item"><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</div>
            <div class="invoice-details-item"><strong>Status:</strong> <span style="text-transform: uppercase;">{{ $invoice->status }}</span></div>
        </div>

        {{-- Invoice Items Table with VAT --}}
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $invoice->description ?? 'Servicii digitale conform contract' }}</strong><br>
                        @if($invoice->period_start && $invoice->period_end)
                            <small style="color: #666;">Period: {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}</small><br>
                        @endif
                    </td>
                    <td class="text-right">
                        <strong>{{ number_format($invoice->subtotal, 2) }} {{ $invoice->currency }}</strong>
                    </td>
                </tr>
                @if($invoice->vat_rate > 0)
                <tr class="invoice-vat">
                    <td>
                        <strong>VAT ({{ number_format($invoice->vat_rate, 0) }}%)</strong>
                    </td>
                    <td class="text-right">
                        <strong>{{ number_format($invoice->vat_amount, 2) }} {{ $invoice->currency }}</strong>
                    </td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="invoice-total">
                    <td><strong>Total Due:</strong></td>
                    <td class="text-right"><strong>{{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</strong></td>
                </tr>
            </tfoot>
        </table>

        {{-- Banking Details --}}
        @if($settings->bank_name || $settings->bank_account)
            <div class="invoice-banking">
                <h3>Payment Details:</h3>
                @if($settings->bank_name)
                    <div><strong>Bank:</strong> {{ $settings->bank_name }}</div>
                @endif
                @if($settings->bank_account)
                    <div><strong>IBAN:</strong> {{ $settings->bank_account }}</div>
                @endif
                @if($settings->bank_swift)
                    <div><strong>SWIFT/BIC:</strong> {{ $settings->bank_swift }}</div>
                @endif
            </div>
        @endif

        {{-- Footer --}}
        @if($settings->invoice_footer)
            <div class="invoice-footer">
                {!! nl2br(e($settings->invoice_footer)) !!}
            </div>
        @endif
    </div>
</body>
</html>
