@props(['invoice', 'tenant' => null, 'settings' => null])

@php
    $tenant = $tenant ?? $invoice?->tenant;
    $settings = $settings ?? \App\Models\Setting::current();
@endphp

<div class="invoice-preview-container" {{ $attributes }}>
    <div class="invoice-preview-header">
        <div class="invoice-preview-title">Invoice Preview</div>
    </div>

    <div class="invoice-preview-content" id="invoice-preview-content">
        {{-- Header with Company Info (Left) and Tenant Billing Info (Right) --}}
        <div class="invoice-header-grid">
            <div class="invoice-company-section">
                @if($settings?->logo_path)
                    <img src="{{ Storage::url($settings->logo_path) }}" alt="{{ $settings->company_name }}" class="invoice-logo">
                @endif
                <h1 class="invoice-company-name">{{ $settings->company_name ?? 'Company Name' }}</h1>
                <div class="invoice-company-details">
                    @if($settings->cui)
                        <div>CUI: {{ $settings->cui }}</div>
                    @endif
                    @if($settings->reg_com)
                        <div>Reg. Com.: {{ $settings->reg_com }}</div>
                    @endif
                    @if($settings->address)
                        <div>{{ $settings->address }}</div>
                    @endif
                    @if($settings->city || $settings->state)
                        <div>{{ $settings->city }}@if($settings->city && $settings->state), @endif{{ $settings->state }}</div>
                    @endif
                    @if($settings->country)
                        <div>{{ $settings->country }}</div>
                    @endif
                    @if($settings->phone)
                        <div>Tel: {{ $settings->phone }}</div>
                    @endif
                    @if($settings->email)
                        <div>Email: {{ $settings->email }}</div>
                    @endif
                    @if($settings->bank_name)
                        <div>Bank: {{ $settings->bank_name }}</div>
                    @endif
                    @if($settings->bank_account)
                        <div>IBAN: {{ $settings->bank_account }}</div>
                    @endif
                    @if($settings->bank_swift)
                        <div>SWIFT: {{ $settings->bank_swift }}</div>
                    @endif
                </div>
            </div>

            {{-- Tenant Billing Info (Right Side) --}}
            <div class="invoice-tenant-billing-section">
                <h3>Client:</h3>
                <div class="invoice-tenant-billing-info" data-preview="tenant_info">
                    @if($tenant)
                        <div class="tenant-name"><strong>{{ $tenant->company_name ?? $tenant->name }}</strong></div>
                        @if($tenant->cui)
                            <div>CUI: {{ $tenant->cui }}</div>
                        @endif
                        @if($tenant->reg_com)
                            <div>Reg. Com.: {{ $tenant->reg_com }}</div>
                        @endif
                        @if($tenant->bank_name)
                            <div>Bank: {{ $tenant->bank_name }}</div>
                        @endif
                        @if($tenant->bank_account)
                            <div>IBAN: {{ $tenant->bank_account }}</div>
                        @endif
                        @if($tenant->address)
                            <div>{{ $tenant->address }}</div>
                        @endif
                        @if($tenant->city || $tenant->state)
                            <div>{{ $tenant->city }}@if($tenant->city && $tenant->state), @endif{{ $tenant->state }}</div>
                        @endif
                        @if($tenant->country)
                            <div>{{ $tenant->country }}</div>
                        @endif
                    @else
                        <div class="text-muted">Select Tenant to view billing details</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Invoice Details --}}
        <div class="invoice-details-section">
            <div class="invoice-number" data-preview="number">
                <strong>Invoice #:</strong> {{ $invoice?->number ?? 'INV-SERIES-000000' }}
            </div>
            <div data-preview="issue_date">
                <strong>Issue Date:</strong> {{ $invoice?->issue_date?->format('M d, Y') ?? date('M d, Y') }}
            </div>
            @if($invoice?->period_start && $invoice?->period_end)
                <div data-preview="period">
                    <strong>Billing Period:</strong> {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}
                </div>
            @endif
            <div data-preview="due_date">
                <strong>Due Date:</strong> {{ $invoice?->due_date?->format('M d, Y') ?? 'TBD' }}
            </div>
        </div>

        {{-- Invoice Items Table with VAT --}}
        <div class="invoice-amount-section">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-preview="description">
                            <strong>{{ $invoice?->description ?? 'Servicii digitale conform contract' }}</strong>
                            @if($invoice?->period_start && $invoice?->period_end)
                                <br><small>Period: {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}</small>
                            @endif
                        </td>
                        <td class="text-right" data-preview="subtotal">
                            <strong>{{ number_format($invoice?->subtotal ?? 0, 2) }} {{ $invoice?->currency ?? 'RON' }}</strong>
                        </td>
                    </tr>
                    @if($invoice?->vat_rate > 0)
                    <tr>
                        <td>
                            <strong>VAT ({{ number_format($invoice->vat_rate, 0) }}%)</strong>
                        </td>
                        <td class="text-right" data-preview="vat_amount">
                            <strong>{{ number_format($invoice->vat_amount ?? 0, 2) }} {{ $invoice->currency ?? 'RON' }}</strong>
                        </td>
                    </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="invoice-total-row">
                        <td><strong>Total Due:</strong></td>
                        <td class="text-right" data-preview="total">
                            <strong>{{ number_format($invoice?->amount ?? 0, 2) }} {{ $invoice?->currency ?? 'RON' }}</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Banking Details --}}
        @if($settings->bank_name || $settings->bank_account)
            <div class="invoice-banking-section">
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
</div>

<style>
.invoice-preview-container {
    position: sticky;
    top: 1rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
}

.invoice-preview-header {
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.invoice-preview-title {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.invoice-preview-content {
    overflow-y: auto;
    flex: 1;
    font-size: 0.875rem;
    line-height: 1.5;
}

.invoice-header-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.invoice-logo {
    max-width: 150px;
    max-height: 60px;
    margin-bottom: 1rem;
}

.invoice-company-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}

.invoice-company-details,
.invoice-tenant-billing-info {
    color: #6b7280;
    font-size: 0.875rem;
    line-height: 1.6;
}

.invoice-tenant-billing-section h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tenant-name {
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.5rem;
}

.invoice-details-section {
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 8px;
}

.invoice-details-section > div {
    margin-bottom: 0.5rem;
}

.invoice-number {
    font-size: 1.125rem;
    color: #111827;
    margin-bottom: 0.75rem;
}

.invoice-amount-section {
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-table thead th {
    background: #f9fafb;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.invoice-table tbody td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid #f3f4f6;
}

.invoice-table tfoot td {
    padding: 1rem 0.75rem;
    font-size: 1.125rem;
}

.invoice-table .text-right {
    text-align: right;
}

.invoice-total-row {
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.invoice-banking-section {
    background: #f9fafb;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.invoice-banking-section h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.invoice-banking-section div {
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.invoice-footer {
    padding-top: 2rem;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 0.75rem;
    line-height: 1.6;
    text-align: center;
}

.text-muted {
    color: #9ca3af;
    font-style: italic;
}
</style>
