<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class InvoiceEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Invoice Created - New Invoice Generated',
                'subject' => 'New Invoice {{invoice_number}} - {{company_name}}',
                'body' => '<p>Dear {{tenant_name}},</p>

<p>A new invoice has been generated for your account.</p>

<p><strong>Invoice Details:</strong></p>
<ul>
    <li>Invoice Number: {{invoice_number}}</li>
    <li>Issue Date: {{issue_date}}</li>
    <li>Due Date: {{due_date}}</li>
    <li>Amount: {{invoice_amount}} {{currency}}</li>
    <li>Billing Period: {{billing_period}}</li>
</ul>

<p><strong>Payment Information:</strong></p>
<p>{{payment_details}}</p>

<p>Please find the invoice attached to this email.</p>

<p>If you have any questions, please don\'t hesitate to contact us.</p>

<p>Best regards,<br>
{{company_name}}</p>',
                'event_trigger' => 'invoice_created',
                'is_active' => true,
            ],
            [
                'name' => 'Invoice Updated - Invoice Modified',
                'subject' => 'Invoice {{invoice_number}} Updated - {{company_name}}',
                'body' => '<p>Dear {{tenant_name}},</p>

<p>Your invoice {{invoice_number}} has been updated.</p>

<p><strong>Updated Invoice Details:</strong></p>
<ul>
    <li>Invoice Number: {{invoice_number}}</li>
    <li>Issue Date: {{issue_date}}</li>
    <li>Due Date: {{due_date}}</li>
    <li>Amount: {{invoice_amount}} {{currency}}</li>
    <li>Status: {{invoice_status}}</li>
</ul>

<p>Please review the updated invoice attached to this email.</p>

<p>If you have any questions regarding this update, please contact us.</p>

<p>Best regards,<br>
{{company_name}}</p>',
                'event_trigger' => 'invoice_updated',
                'is_active' => true,
            ],
            [
                'name' => 'Invoice Overdue - Payment Reminder',
                'subject' => 'URGENT: Invoice {{invoice_number}} is Overdue - {{company_name}}',
                'body' => '<p>Dear {{tenant_name}},</p>

<p><strong style="color: #dc2626;">This is an important notice regarding your overdue invoice.</strong></p>

<p><strong>Invoice Details:</strong></p>
<ul>
    <li>Invoice Number: {{invoice_number}}</li>
    <li>Issue Date: {{issue_date}}</li>
    <li>Due Date: {{due_date}}</li>
    <li>Amount: {{invoice_amount}} {{currency}}</li>
    <li>Days Overdue: {{days_overdue}}</li>
</ul>

<p style="color: #dc2626;"><strong>Please arrange payment as soon as possible to avoid service interruption.</strong></p>

<p><strong>Payment Information:</strong></p>
<p>{{payment_details}}</p>

<p>If you have already made the payment, please disregard this notice and contact us to confirm.</p>

<p>For any questions or payment arrangements, please contact us immediately.</p>

<p>Best regards,<br>
{{company_name}}</p>',
                'event_trigger' => 'invoice_overdue',
                'is_active' => true,
            ],
            [
                'name' => 'Invoice Paid - Payment Confirmation',
                'subject' => 'Payment Confirmed - Invoice {{invoice_number}} - {{company_name}}',
                'body' => '<p>Dear {{tenant_name}},</p>

<p><strong style="color: #059669;">Thank you! Your payment has been received and confirmed.</strong></p>

<p><strong>Payment Details:</strong></p>
<ul>
    <li>Invoice Number: {{invoice_number}}</li>
    <li>Amount Paid: {{invoice_amount}} {{currency}}</li>
    <li>Payment Date: {{payment_date}}</li>
    <li>Status: PAID</li>
</ul>

<p>Your account is now up to date.</p>

<p>A receipt is attached to this email for your records.</p>

<p>Thank you for your prompt payment!</p>

<p>Best regards,<br>
{{company_name}}</p>',
                'event_trigger' => 'invoice_paid',
                'is_active' => true,
            ],
            [
                'name' => 'Invoice Cancelled - Invoice Cancellation Notice',
                'subject' => 'Invoice {{invoice_number}} Cancelled - {{company_name}}',
                'body' => '<p>Dear {{tenant_name}},</p>

<p>This is to inform you that invoice {{invoice_number}} has been cancelled.</p>

<p><strong>Cancelled Invoice Details:</strong></p>
<ul>
    <li>Invoice Number: {{invoice_number}}</li>
    <li>Original Amount: {{invoice_amount}} {{currency}}</li>
    <li>Cancellation Date: {{cancellation_date}}</li>
</ul>

<p><strong>Reason for Cancellation:</strong></p>
<p>{{cancellation_reason}}</p>

<p>No payment is required for this invoice.</p>

<p>If you have any questions about this cancellation, please contact us.</p>

<p>Best regards,<br>
{{company_name}}</p>',
                'event_trigger' => 'invoice_cancelled',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['event_trigger' => $template['event_trigger']],
                $template
            );
        }

        $this->command->info('Invoice email templates created successfully!');
    }
}
