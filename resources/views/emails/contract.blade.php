<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Contract</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #f0f0f0;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        strong {
            color: #222;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($settings && $settings->logo_path)
            <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="{{ $settings->company_name ?? 'Logo' }}" style="max-height: 50px;">
        @endif
    </div>

    <div class="content">
        {!! $emailContent !!}
    </div>

    <div class="footer">
        @if($settings)
            <p>{{ $settings->company_name }}</p>
            @if($settings->address)
                <p>{{ $settings->address }}, {{ $settings->city }}, {{ $settings->country }}</p>
            @endif
            @if($settings->phone || $settings->email)
                <p>
                    @if($settings->phone){{ $settings->phone }}@endif
                    @if($settings->phone && $settings->email) | @endif
                    @if($settings->email){{ $settings->email }}@endif
                </p>
            @endif
        @endif

        @if($settings && $settings->email_footer)
            <div style="margin-top: 15px;">
                {!! $settings->email_footer !!}
            </div>
        @endif
    </div>
</body>
</html>
