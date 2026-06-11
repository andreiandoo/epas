<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Contract - {{ $tenant->contract_number ?? 'Draft' }}</title>
    <style>
        @page {
            margin: 2cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .header img {
            max-height: 60px;
            margin-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24pt;
            color: #333;
        }

        .header .contract-number {
            font-size: 12pt;
            color: #666;
            margin-top: 5px;
        }

        .contract-content {
            text-align: justify;
        }

        .contract-content h1,
        .contract-content h2,
        .contract-content h3 {
            color: #333;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .contract-content h1 {
            font-size: 16pt;
        }

        .contract-content h2 {
            font-size: 14pt;
        }

        .contract-content h3 {
            font-size: 12pt;
        }

        .contract-content p {
            margin-bottom: 10px;
        }

        .contract-content ul,
        .contract-content ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }

        .contract-content li {
            margin-bottom: 5px;
        }

        .contract-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .contract-content th,
        .contract-content td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .contract-content th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .signatures {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-block {
            display: inline-block;
            width: 45%;
            vertical-align: top;
        }

        .signature-block:first-child {
            margin-right: 8%;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .page-number:after {
            content: counter(page);
        }

        /* Table styles for template content */
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 5px 10px;
            border: none;
        }

        .info-table .label {
            font-weight: bold;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($settings && $settings->logo_path)
            <img src="{{ storage_path('app/public/' . $settings->logo_path) }}" alt="Logo">
        @endif
        <h1>CONTRACT</h1>
        <div class="contract-number">
            No. {{ $tenant->contract_number ?? 'DRAFT' }} / {{ now()->format('d.m.Y') }}
        </div>
    </div>

    <div class="contract-content">
        {{-- SECURITY FIX: Sanitize HTML content to prevent XSS --}}
        {!! \App\Helpers\HtmlSanitizer::sanitize($content) !!}
    </div>

    <div class="signatures">
        <div class="signature-block">
            <div class="signature-label">SERVICE PROVIDER</div>
            <p>{{ $settings->company_name ?? 'Platform' }}</p>
            <div class="signature-line">
                <p>Name: _____________________</p>
                <p>Position: _____________________</p>
                <p>Signature: _____________________</p>
                <p>Date: _____________________</p>
            </div>
        </div>

        <div class="signature-block">
            <div class="signature-label">CLIENT</div>
            <p>{{ $tenant->company_name ?? $tenant->name }}</p>
            <div class="signature-line">
                <p>Name: {{ $tenant->contact_first_name }} {{ $tenant->contact_last_name }}</p>
                <p>Position: {{ $tenant->contact_position ?? '_____________________' }}</p>
                <p>Signature: _____________________</p>
                <p>Date: _____________________</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <span class="page-number">Page </span> |
        {{ $settings->company_name ?? 'Platform' }} |
        {{ $settings->phone ?? '' }} |
        {{ $settings->email ?? '' }}
    </div>
</body>
</html>
