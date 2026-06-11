<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Amendment {{ $amendment->amendment_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .reference {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #333;
        }
        .reference p {
            margin: 5px 0;
        }
        .content {
            margin: 30px 0;
        }
        .content h2 {
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .signature-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-block {
            display: inline-block;
            width: 45%;
            margin-top: 30px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRACT AMENDMENT</h1>
        <p><strong>Amendment Number:</strong> {{ $amendment->amendment_number }}</p>
        <p><strong>Date:</strong> {{ now()->format('F d, Y') }}</p>
    </div>

    <div class="reference">
        <p><strong>Original Contract:</strong> {{ $tenant->contract_number ?? 'N/A' }}</p>
        <p><strong>Party:</strong> {{ $tenant->company_name ?? $tenant->name }}</p>
        <p><strong>Amendment Title:</strong> {{ $amendment->title }}</p>
        @if($amendment->description)
        <p><strong>Description:</strong> {{ $amendment->description }}</p>
        @endif
    </div>

    <div class="content">
        <h2>Amendment Terms</h2>
        <p>This Amendment is entered into as of the date first written above, by and between the parties to the Original Contract referenced above.</p>

        <p>The parties hereby agree to amend the Original Contract as follows:</p>

        <div style="margin: 20px 0; padding: 15px; background-color: #fafafa;">
            {!! $amendment->content !!}
        </div>

        <p>All other terms and conditions of the Original Contract shall remain in full force and effect.</p>
    </div>

    <div class="signature-section">
        <h2>Signatures</h2>
        <p>IN WITNESS WHEREOF, the parties have executed this Amendment as of the date first above written.</p>

        <div class="signature-block">
            <div class="signature-line">
                <p><strong>{{ $tenant->company_name ?? $tenant->name }}</strong></p>
                <p>Name: {{ $tenant->contact_first_name }} {{ $tenant->contact_last_name }}</p>
                <p>Title: {{ $tenant->contact_position ?? 'Authorized Representative' }}</p>
                <p>Date: _________________</p>
            </div>
        </div>

        <div class="signature-block" style="float: right;">
            <div class="signature-line">
                <p><strong>Platform Representative</strong></p>
                <p>Name: _________________</p>
                <p>Title: _________________</p>
                <p>Date: _________________</p>
            </div>
        </div>
    </div>

    <div class="footer">
        Amendment {{ $amendment->amendment_number }} | Generated on {{ now()->format('F d, Y') }}
    </div>
</body>
</html>
