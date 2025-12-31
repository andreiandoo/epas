<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0;">Payment Successful!</h1>
        <p style="margin: 10px 0 0;">Thank you for your purchase</p>
    </div>

    <div style="background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none;">
        <p>Hello <strong>{{ $tenant->public_name ?? $tenant->name }}</strong>,</p>

        <p>Thank you for purchasing our microservices! Your payment has been processed successfully and your services are now active.</p>

        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Invoice Details</h3>
            <p><strong>Invoice Number:</strong> {{ $invoice->number }}</p>
            <p><strong>Date:</strong> {{ $invoice->issue_date->format('d/m/Y') }}</p>
            <p><strong>Total Amount:</strong> {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</p>
        </div>

        <h3>Activated Microservices ({{ $microservices->count() }})</h3>
        <div style="background: #f9fafb; border-radius: 8px; padding: 15px; margin: 20px 0;">
            @foreach($microservices as $microservice)
                <div style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                    • {{ $microservice->name }} - {{ number_format($microservice->price, 2) }} RON
                </div>
            @endforeach
        </div>

        <p><strong>What's next?</strong></p>
        <ul>
            <li>Your microservices are now active and ready to use</li>
            <li>You can configure them in your admin panel</li>
            <li>The invoice is attached to this email for your records</li>
        </ul>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ url('/admin') }}" style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px;">
                Go to Admin Panel
            </a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 14px;">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
