# Payment Confirmation Emails Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently, when a payment is captured (marked in code as TODO), no confirmation email is sent. This causes:
1. **Customer anxiety**: No receipt or confirmation that payment was successful
2. **Support inquiries**: Customers contact support asking "did my payment go through?"
3. **Missing paper trail**: No email proof of purchase for customer records
4. **Tenant unawareness**: Organizers don't get notified of new sales in real-time
5. **Lost tickets**: Without email, customers may lose access to their tickets

### What This Feature Does
Implements comprehensive payment confirmation emails that:
- Send detailed receipt to customers upon successful payment
- Notify tenants/organizers of new orders
- Include ticket download links and QR codes
- Handle multiple payment processors (Stripe, PayU, etc.)
- Attach PDF receipt for record keeping
- Include order details, event info, and ticket information

---

## Technical Implementation

### 1. Mail Classes

Create `app/Mail/PaymentConfirmationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Pdf\ReceiptPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmed - #{$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        $event = $this->order->event;
        $tickets = $this->order->tickets;
        $customer = $this->order->customer;

        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'order' => $this->order,
                'event' => $event,
                'tickets' => $tickets,
                'customer' => $customer,
                'ticketDownloadUrl' => $this->generateTicketDownloadUrl(),
                'orderViewUrl' => $this->generateOrderViewUrl(),
                'venueAddress' => $event->venue?->full_address,
                'eventDate' => $event->start_date?->format('l, F j, Y'),
                'eventTime' => $event->start_date?->format('g:i A'),
                'paymentMethod' => $this->formatPaymentMethod(),
                'subtotal' => $this->order->subtotal,
                'fees' => $this->order->fees,
                'taxes' => $this->order->tax_amount,
                'total' => $this->order->total,
                'currency' => $this->order->currency ?? 'USD',
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Attach PDF receipt
        try {
            $pdfService = app(ReceiptPdfService::class);
            $pdfContent = $pdfService->generateReceipt($this->order);

            $attachments[] = Attachment::fromData(
                fn () => $pdfContent,
                "receipt-{$this->order->order_number}.pdf"
            )->withMime('application/pdf');
        } catch (\Exception $e) {
            // Log error but don't fail email
            \Log::error('Failed to generate receipt PDF: ' . $e->getMessage());
        }

        return $attachments;
    }

    protected function generateTicketDownloadUrl(): string
    {
        return url("/orders/{$this->order->id}/tickets/download?token={$this->order->download_token}");
    }

    protected function generateOrderViewUrl(): string
    {
        return url("/orders/{$this->order->id}?token={$this->order->view_token}");
    }

    protected function formatPaymentMethod(): string
    {
        $processor = $this->order->payment_processor ?? 'card';
        $last4 = $this->order->payment_last_four;

        return match ($processor) {
            'stripe' => "Card ending in {$last4}",
            'paypal' => 'PayPal',
            'payu' => 'PayU',
            'netopia' => 'Netopia',
            default => ucfirst($processor) . ($last4 ? " ending in {$last4}" : ''),
        };
    }
}
```

Create `app/Mail/TenantPaymentNotificationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPaymentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Tenant $tenant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order #{$this->order->order_number} - {$this->order->total} {$this->order->currency}",
        );
    }

    public function content(): Content
    {
        $event = $this->order->event;
        $customer = $this->order->customer;

        return new Content(
            view: 'emails.tenant-payment-notification',
            with: [
                'order' => $this->order,
                'event' => $event,
                'customer' => $customer,
                'tenant' => $this->tenant,
                'orderViewUrl' => $this->generateAdminOrderUrl(),
                'ticketCount' => $this->order->tickets->count(),
                'revenue' => $this->calculateTenantRevenue(),
                'platformFee' => $this->order->platform_fee ?? 0,
                'netRevenue' => $this->calculateNetRevenue(),
            ],
        );
    }

    protected function generateAdminOrderUrl(): string
    {
        return url("/admin/orders/{$this->order->id}");
    }

    protected function calculateTenantRevenue(): float
    {
        return $this->order->total - ($this->order->platform_fee ?? 0);
    }

    protected function calculateNetRevenue(): float
    {
        $total = $this->order->total;
        $platformFee = $this->order->platform_fee ?? 0;
        $paymentProcessorFee = $this->order->payment_processor_fee ?? 0;

        return $total - $platformFee - $paymentProcessorFee;
    }
}
```

### 2. Email Templates

Create `resources/views/emails/payment-confirmation.blade.php`:

```blade
@component('mail::message')
# Order Confirmed!

Hi {{ $customer->first_name ?? $customer->name ?? 'there' }},

Thank you for your purchase! Your payment has been confirmed and your tickets are ready.

## Order Details

**Order Number:** #{{ $order->order_number }}
**Order Date:** {{ $order->created_at->format('F j, Y g:i A') }}

---

## Event Information

**{{ $event->name }}**

@if($venueAddress)
📍 {{ $venueAddress }}
@endif

@if($eventDate)
📅 {{ $eventDate }}
@endif

@if($eventTime)
🕐 {{ $eventTime }}
@endif

---

## Your Tickets

@component('mail::table')
| Ticket Type | Quantity | Price |
|:------------|:--------:|------:|
@foreach($tickets->groupBy('ticket_type_id') as $typeId => $ticketGroup)
| {{ $ticketGroup->first()->ticketType->name ?? 'General Admission' }} | {{ $ticketGroup->count() }} | {{ number_format($ticketGroup->sum('price'), 2) }} {{ $currency }} |
@endforeach
@endcomponent

---

## Payment Summary

@component('mail::table')
| | |
|:------------|------:|
| Subtotal | {{ number_format($subtotal, 2) }} {{ $currency }} |
@if($fees > 0)
| Service Fees | {{ number_format($fees, 2) }} {{ $currency }} |
@endif
@if($taxes > 0)
| Taxes | {{ number_format($taxes, 2) }} {{ $currency }} |
@endif
| **Total Paid** | **{{ number_format($total, 2) }} {{ $currency }}** |
@endcomponent

**Payment Method:** {{ $paymentMethod }}

---

@component('mail::button', ['url' => $ticketDownloadUrl, 'color' => 'primary'])
Download Your Tickets
@endcomponent

@component('mail::button', ['url' => $orderViewUrl, 'color' => 'secondary'])
View Order Details
@endcomponent

---

## Important Information

- **Arrive Early:** We recommend arriving at least 30 minutes before the event starts.
- **Ticket Display:** You can show your tickets on your mobile device or print them.
- **ID Required:** Please bring a valid ID matching the name on your order.

If you have any questions, please don't hesitate to contact us.

See you at the event!

Thanks,<br>
{{ $event->tenant->name ?? config('app.name') }}

@component('mail::subcopy')
This email confirms your payment was successful. Please keep this email for your records. If you didn't make this purchase, please contact us immediately.
@endcomponent
@endcomponent
```

Create `resources/views/emails/tenant-payment-notification.blade.php`:

```blade
@component('mail::message')
# New Order Received! 🎉

You have a new order for **{{ $event->name }}**.

---

## Order Summary

**Order Number:** #{{ $order->order_number }}
**Date:** {{ $order->created_at->format('F j, Y g:i A') }}

---

## Customer Information

**Name:** {{ $customer->full_name ?? $customer->first_name . ' ' . $customer->last_name }}
**Email:** {{ $customer->email }}
@if($customer->phone)
**Phone:** {{ $customer->phone }}
@endif

---

## Order Details

**Tickets Purchased:** {{ $ticketCount }}

@component('mail::table')
| Ticket Type | Quantity | Price |
|:------------|:--------:|------:|
@foreach($order->tickets->groupBy('ticket_type_id') as $typeId => $ticketGroup)
| {{ $ticketGroup->first()->ticketType->name ?? 'General Admission' }} | {{ $ticketGroup->count() }} | {{ number_format($ticketGroup->sum('price'), 2) }} {{ $order->currency }} |
@endforeach
@endcomponent

---

## Revenue Breakdown

@component('mail::table')
| | |
|:------------|------:|
| Order Total | {{ number_format($order->total, 2) }} {{ $order->currency }} |
| Platform Fee | -{{ number_format($platformFee, 2) }} {{ $order->currency }} |
| **Your Revenue** | **{{ number_format($netRevenue, 2) }} {{ $order->currency }}** |
@endcomponent

---

@component('mail::button', ['url' => $orderViewUrl])
View Order in Dashboard
@endcomponent

---

**Event:** {{ $event->name }}
**Event Date:** {{ $event->start_date?->format('F j, Y g:i A') }}

Thanks,<br>
{{ config('app.name') }} Platform
@endcomponent
```

### 3. PDF Receipt Service

Create `app/Services/Pdf/ReceiptPdfService.php`:

```php
<?php

namespace App\Services\Pdf;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdfService
{
    /**
     * Generate PDF receipt for an order
     */
    public function generateReceipt(Order $order): string
    {
        $order->load(['event', 'customer', 'tickets.ticketType', 'tenant']);

        $data = [
            'order' => $order,
            'event' => $order->event,
            'customer' => $order->customer,
            'tenant' => $order->tenant,
            'tickets' => $order->tickets,
            'generatedAt' => now(),
        ];

        $pdf = Pdf::loadView('pdfs.receipt', $data);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Save receipt to storage
     */
    public function saveReceipt(Order $order, ?string $disk = null): string
    {
        $content = $this->generateReceipt($order);
        $path = "receipts/{$order->id}/receipt-{$order->order_number}.pdf";

        $disk = $disk ?? config('filesystems.default');

        \Storage::disk($disk)->put($path, $content);

        return $path;
    }
}
```

Create `resources/views/pdfs/receipt.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 40%;
            padding: 5px 0;
            color: #666;
        }
        .info-value {
            display: table-cell;
            padding: 5px 0;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .amount {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenant->name ?? config('app.name') }}</h1>
        <p>RECEIPT</p>
        <p>Order #{{ $order->order_number }}</p>
    </div>

    <div class="section">
        <div class="section-title">Order Information</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span class="info-value">#{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Order Date:</span>
                <span class="info-value">{{ $order->created_at->format('F j, Y g:i A') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Status:</span>
                <span class="info-value">{{ ucfirst($order->status) }}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Customer Information</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $customer->full_name ?? $customer->first_name . ' ' . $customer->last_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $customer->email }}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Event Details</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Event:</span>
                <span class="info-value">{{ $event->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value">{{ $event->start_date?->format('F j, Y g:i A') }}</span>
            </div>
            @if($event->venue)
            <div class="info-row">
                <span class="info-label">Venue:</span>
                <span class="info-value">{{ $event->venue->name }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Items Purchased</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th class="amount">Price</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets->groupBy('ticket_type_id') as $typeId => $ticketGroup)
                <tr>
                    <td>{{ $ticketGroup->first()->ticketType->name ?? 'General Admission' }}</td>
                    <td>{{ $ticketGroup->count() }}</td>
                    <td class="amount">{{ number_format($ticketGroup->first()->price, 2) }} {{ $order->currency }}</td>
                    <td class="amount">{{ number_format($ticketGroup->sum('price'), 2) }} {{ $order->currency }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="amount">Subtotal:</td>
                    <td class="amount">{{ number_format($order->subtotal, 2) }} {{ $order->currency }}</td>
                </tr>
                @if($order->fees > 0)
                <tr>
                    <td colspan="3" class="amount">Service Fees:</td>
                    <td class="amount">{{ number_format($order->fees, 2) }} {{ $order->currency }}</td>
                </tr>
                @endif
                @if($order->tax_amount > 0)
                <tr>
                    <td colspan="3" class="amount">Tax:</td>
                    <td class="amount">{{ number_format($order->tax_amount, 2) }} {{ $order->currency }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td colspan="3" class="amount">Total Paid:</td>
                    <td class="amount">{{ number_format($order->total, 2) }} {{ $order->currency }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <p>This is an official receipt for your records.</p>
        <p>Generated on {{ $generatedAt->format('F j, Y g:i A') }}</p>
        <p>{{ $tenant->name ?? config('app.name') }}</p>
    </div>
</body>
</html>
```

### 4. Event Listener

Create `app/Listeners/SendPaymentConfirmationEmail.php`:

```php
<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Mail\PaymentConfirmationMail;
use App\Mail\TenantPaymentNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPaymentConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function handle(PaymentCaptured $event): void
    {
        $order = $event->order;

        // Load necessary relationships
        $order->load(['event', 'customer', 'tickets.ticketType', 'tenant']);

        // Send confirmation to customer
        if ($order->customer && $order->customer->email) {
            Mail::to($order->customer->email)
                ->queue(new PaymentConfirmationMail($order));
        }

        // Send notification to tenant
        $this->notifyTenant($order);
    }

    protected function notifyTenant($order): void
    {
        $tenant = $order->tenant;

        if (!$tenant) {
            return;
        }

        // Get notification emails for tenant
        $notificationEmails = $this->getTenantNotificationEmails($tenant);

        foreach ($notificationEmails as $email) {
            Mail::to($email)
                ->queue(new TenantPaymentNotificationMail($order, $tenant));
        }
    }

    protected function getTenantNotificationEmails($tenant): array
    {
        $emails = [];

        // Primary contact
        if ($tenant->email) {
            $emails[] = $tenant->email;
        }

        // Check for notification settings
        if ($tenant->notification_settings) {
            $settings = is_array($tenant->notification_settings)
                ? $tenant->notification_settings
                : json_decode($tenant->notification_settings, true);

            if (!empty($settings['order_notification_emails'])) {
                $emails = array_merge($emails, $settings['order_notification_emails']);
            }
        }

        // Get admin users for tenant
        $adminEmails = $tenant->users()
            ->where('role', 'admin')
            ->where('receive_order_notifications', true)
            ->pluck('email')
            ->toArray();

        $emails = array_merge($emails, $adminEmails);

        return array_unique(array_filter($emails));
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentCaptured $event, \Throwable $exception): void
    {
        \Log::error('Failed to send payment confirmation email', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 5. Register Event Listener

Update `app/Providers/EventServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Events\PaymentCaptured;
use App\Listeners\SendPaymentConfirmationEmail;
use App\Listeners\GenerateEFactura;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentCaptured::class => [
            SendPaymentConfirmationEmail::class,
            GenerateEFactura::class, // Existing listener
            // Add other listeners as needed
        ],
    ];
}
```

### 6. Ensure PaymentCaptured Event is Dispatched

Check and update `app/Http/Controllers/Webhooks/StripeWebhookController.php`:

```php
// In the payment_intent.succeeded handler, ensure event is dispatched:

case 'payment_intent.succeeded':
    $paymentIntent = $event->data->object;
    $order = Order::where('payment_intent_id', $paymentIntent->id)->first();

    if ($order) {
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();

        // Dispatch the event - this triggers email sending
        event(new PaymentCaptured($order));
    }
    break;
```

### 7. Create PaymentCaptured Event (if not exists)

Create `app/Events/PaymentCaptured.php`:

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCaptured
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
```

### 8. Add Download Token to Order Model

Update `app/Models/Order.php`:

```php
// Add to fillable
protected $fillable = [
    // ... existing fields
    'download_token',
    'view_token',
];

// Add boot method to generate tokens
protected static function boot()
{
    parent::boot();

    static::creating(function ($order) {
        $order->download_token = $order->download_token ?? \Str::random(32);
        $order->view_token = $order->view_token ?? \Str::random(32);
    });
}
```

Create migration for tokens if needed:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('download_token', 32)->nullable()->after('status');
    $table->string('view_token', 32)->nullable()->after('download_token');
});
```

---

## Configuration

Add to `.env.example`:

```
# Payment confirmation settings
PAYMENT_CONFIRMATION_ENABLED=true
TENANT_NOTIFICATION_ENABLED=true
```

Add to `config/mail.php` or create `config/notifications.php`:

```php
return [
    'payment_confirmation' => [
        'enabled' => env('PAYMENT_CONFIRMATION_ENABLED', true),
        'attach_pdf' => true,
    ],
    'tenant_notification' => [
        'enabled' => env('TENANT_NOTIFICATION_ENABLED', true),
    ],
];
```

---

## Testing Checklist

1. [ ] Customer receives confirmation email after payment
2. [ ] Email contains correct order details
3. [ ] Email contains correct event information
4. [ ] Ticket download link works
5. [ ] PDF receipt is attached and readable
6. [ ] Tenant receives notification email
7. [ ] Tenant email shows correct revenue breakdown
8. [ ] Works with different payment processors
9. [ ] Queue jobs retry on failure
10. [ ] Emails render correctly in different email clients
