<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class MicroservicePurchaseEmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'microservice-purchase-confirmation'],
            [
                'name' => 'Microservice Purchase Confirmation',
                'subject' => 'Thank You for Your Purchase - {{invoice_number}}',
                'description' => 'Email sent to tenants after successful microservice purchase with invoice attachment',
                'html_content' => $this->getHtmlContent(),
                'text_content' => $this->getTextContent(),
                'placeholders' => [
                    '{{tenant_name}}' => 'Tenant name',
                    '{{tenant_email}}' => 'Tenant email',
                    '{{invoice_number}}' => 'Invoice number',
                    '{{invoice_date}}' => 'Invoice date',
                    '{{invoice_amount}}' => 'Total amount',
                    '{{invoice_currency}}' => 'Currency code',
                    '{{microservices_list}}' => 'List of purchased microservices',
                    '{{microservices_count}}' => 'Number of microservices',
                    '{{stripe_session_id}}' => 'Stripe session ID',
                    '{{payment_date}}' => 'Payment date and time',
                    '{{admin_url}}' => 'Admin panel URL',
                ],
                'category' => 'billing',
            ]
        );
    }

    protected function getHtmlContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .success-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }
        .invoice-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .invoice-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .invoice-row:last-child {
            border-bottom: none;
            font-weight: bold;
        }
        .services-list {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .service-item {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .service-item:last-child {
            border-bottom: none;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="success-icon">✓</div>
        <h1 style="margin: 0;">Payment Successful!</h1>
        <p style="margin: 10px 0 0;">Thank you for your purchase</p>
    </div>

    <div class="content">
        <p>Hello <strong>{{tenant_name}}</strong>,</p>

        <p>Thank you for purchasing our microservices! Your payment has been processed successfully and your services are now active.</p>

        <div class="invoice-box">
            <h3 style="margin-top: 0;">Invoice Details</h3>
            <div class="invoice-row">
                <span>Invoice Number:</span>
                <span><strong>{{invoice_number}}</strong></span>
            </div>
            <div class="invoice-row">
                <span>Date:</span>
                <span>{{invoice_date}}</span>
            </div>
            <div class="invoice-row">
                <span>Payment Method:</span>
                <span>Credit Card (Stripe)</span>
            </div>
            <div class="invoice-row">
                <span>Total Amount:</span>
                <span><strong>{{invoice_amount}} {{invoice_currency}}</strong></span>
            </div>
        </div>

        <h3>Activated Microservices ({{microservices_count}})</h3>
        <div class="services-list">
            {{microservices_list}}
        </div>

        <p><strong>What's next?</strong></p>
        <ul>
            <li>Your microservices are now active and ready to use</li>
            <li>You can configure them in your admin panel</li>
            <li>The invoice is attached to this email for your records</li>
        </ul>

        <div style="text-align: center;">
            <a href="{{admin_url}}" class="button">Go to Admin Panel</a>
        </div>

        <p style="margin-top: 30px; color: #6b7280; font-size: 14px;">
            If you have any questions or need assistance, please don't hesitate to contact our support team.
        </p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p style="font-size: 12px;">Transaction ID: {{stripe_session_id}}</p>
    </div>
</body>
</html>
HTML;
    }

    protected function getTextContent(): string
    {
        return <<<'TEXT'
PAYMENT SUCCESSFUL!

Hello {{tenant_name}},

Thank you for purchasing our microservices! Your payment has been processed successfully and your services are now active.

INVOICE DETAILS
---------------
Invoice Number: {{invoice_number}}
Date: {{invoice_date}}
Payment Method: Credit Card (Stripe)
Total Amount: {{invoice_amount}} {{invoice_currency}}

ACTIVATED MICROSERVICES ({{microservices_count}})
---------------
{{microservices_list}}

WHAT'S NEXT?
- Your microservices are now active and ready to use
- You can configure them in your admin panel: {{admin_url}}
- The invoice is attached to this email for your records

If you have any questions or need assistance, please don't hesitate to contact our support team.

---
Transaction ID: {{stripe_session_id}}
Payment Date: {{payment_date}}
TEXT;
    }
}
