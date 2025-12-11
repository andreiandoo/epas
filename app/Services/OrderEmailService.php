<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderEmailService
{
    /**
     * Send order confirmation email to customer
     */
    public function sendOrderConfirmation(Order $order, Tenant $tenant): bool
    {
        try {
            // Find active order_confirmation template for this tenant
            $template = EmailTemplate::where('event_trigger', 'order_confirmation')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                // Use default template if no custom one exists
                $subject = 'Confirmarea comenzii #' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                $body = $this->getDefaultOrderConfirmationBody($order, $tenant);
            } else {
                $variables = $this->getOrderVariables($order, $tenant);
                $processed = $template->processTemplate($variables);
                $subject = $processed['subject'];
                $body = $processed['body'];
            }

            // Send email
            Mail::html($body, function ($message) use ($order, $subject, $tenant) {
                $message->to($order->customer_email)
                    ->subject($subject);

                // Set from address if configured
                $fromEmail = $tenant->settings['email']['from_email'] ?? config('mail.from.address');
                $fromName = $tenant->settings['email']['from_name'] ?? $tenant->public_name ?? config('mail.from.name');

                if ($fromEmail) {
                    $message->from($fromEmail, $fromName);
                }
            });

            // Log the sent email
            EmailLog::create([
                'email_template_id' => $template?->id,
                'recipient_email' => $order->customer_email,
                'subject' => $subject,
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => [
                    'order_id' => $order->id,
                    'tenant_id' => $tenant->id,
                    'trigger' => 'order_confirmation',
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            // Log the failed attempt
            EmailLog::create([
                'recipient_email' => $order->customer_email,
                'subject' => 'Order Confirmation',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'order_id' => $order->id,
                    'tenant_id' => $tenant->id,
                    'trigger' => 'order_confirmation',
                ],
            ]);

            return false;
        }
    }

    /**
     * Get variables for order email template
     */
    protected function getOrderVariables(Order $order, Tenant $tenant): array
    {
        $items = $order->meta['items'] ?? [];
        $itemsHtml = '';
        $currency = $tenant->settings['currency'] ?? 'RON';

        foreach ($items as $item) {
            $ticketType = \App\Models\TicketType::find($item['ticket_type_id']);
            $itemName = $ticketType?->name ?? 'Bilet';
            $itemPrice = number_format($item['total_cents'] / 100, 2);
            $itemsHtml .= "<tr><td>{$itemName}</td><td>{$item['quantity']}</td><td>{$itemPrice} {$currency}</td></tr>";
        }

        return [
            'first_name' => $order->meta['customer_first_name'] ?? $order->meta['customer_name'] ?? '',
            'last_name' => $order->meta['customer_last_name'] ?? '',
            'full_name' => $order->meta['customer_name'] ?? '',
            'email' => $order->customer_email,
            'phone' => $order->meta['customer_phone'] ?? '',
            'order_number' => str_pad($order->id, 6, '0', STR_PAD_LEFT),
            'order_id' => $order->id,
            'order_total' => number_format($order->total_cents / 100, 2) . ' ' . $currency,
            'order_date' => $order->created_at->format('d.m.Y H:i'),
            'order_items' => $itemsHtml,
            'coupon_code' => $order->promo_code ?? '',
            'discount_amount' => $order->promo_discount ? number_format($order->promo_discount, 2) . ' ' . $currency : '',
            'public_name' => $tenant->public_name ?? $tenant->name,
            'company_name' => $tenant->name,
            'website_url' => $tenant->domains()->where('is_primary', true)->first()?->domain ?? '',
        ];
    }

    /**
     * Get default order confirmation email body
     */
    protected function getDefaultOrderConfirmationBody(Order $order, Tenant $tenant): string
    {
        $variables = $this->getOrderVariables($order, $tenant);
        $publicName = $variables['public_name'];
        $orderNumber = $variables['order_number'];
        $orderTotal = $variables['order_total'];
        $orderDate = $variables['order_date'];
        $firstName = $variables['first_name'] ?: 'Client';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #4f46e5; }
        .content { padding: 30px 0; }
        .order-details { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px 0; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
        h1 { color: #4f46e5; margin: 0; }
        .total { font-size: 1.25rem; font-weight: bold; color: #059669; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$publicName}</h1>
        </div>
        <div class="content">
            <p>Bună {$firstName},</p>
            <p>Îți mulțumim pentru comandă! Am primit comanda ta și o procesăm.</p>

            <div class="order-details">
                <h3>Detalii comandă</h3>
                <p><strong>Număr comandă:</strong> #{$orderNumber}</p>
                <p><strong>Data:</strong> {$orderDate}</p>
                <p class="total"><strong>Total:</strong> {$orderTotal}</p>
            </div>

            <p>Vei primi biletele pe email după confirmarea plății.</p>
            <p>Dacă ai întrebări, nu ezita să ne contactezi.</p>

            <p>Cu drag,<br>{$publicName}</p>
        </div>
        <div class="footer">
            <p>Acest email a fost trimis automat. Te rugăm să nu răspunzi direct la acest mesaj.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
