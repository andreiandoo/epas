<?php

namespace App\Observers;

use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // Send "invoice_created" email
        $this->sendInvoiceEmail($invoice, 'invoice_created');
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Check if status changed
        if ($invoice->isDirty('status')) {
            $newStatus = $invoice->status;
            $oldStatus = $invoice->getOriginal('status');

            // Send appropriate email based on new status
            if ($newStatus === 'paid' && $oldStatus !== 'paid') {
                $this->sendInvoiceEmail($invoice, 'invoice_paid');
            } elseif ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $this->sendInvoiceEmail($invoice, 'invoice_cancelled');
            } else {
                // General update notification
                $this->sendInvoiceEmail($invoice, 'invoice_updated');
            }
        }
    }

    /**
     * Send invoice email
     */
    protected function sendInvoiceEmail(Invoice $invoice, string $eventTrigger): void
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                \Log::warning("Cannot send invoice email: Tenant email not found for invoice {$invoice->id}");
                return;
            }

            // Send email
            Mail::to($tenant->email)->send(new InvoiceMail($invoice, $eventTrigger));

            // Log email
            EmailLog::create([
                'recipient_email' => $tenant->email,
                'recipient_name' => $tenant->name,
                'subject' => "Invoice {$invoice->number} - " . ucfirst(str_replace('_', ' ', $eventTrigger)),
                'body' => "Email sent for invoice {$invoice->number}",
                'sent_at' => now(),
                'status' => 'sent',
                'event_trigger' => $eventTrigger,
            ]);

            \Log::info("Invoice email sent successfully for invoice {$invoice->id} (trigger: {$eventTrigger})");
        } catch (\Exception $e) {
            \Log::error("Failed to send invoice email for invoice {$invoice->id}: " . $e->getMessage());

            // Log failed email
            EmailLog::create([
                'recipient_email' => $tenant->email ?? 'unknown',
                'recipient_name' => $tenant->name ?? 'unknown',
                'subject' => "Invoice {$invoice->number} - " . ucfirst(str_replace('_', ' ', $eventTrigger)),
                'body' => "Failed to send email: " . $e->getMessage(),
                'sent_at' => now(),
                'status' => 'failed',
                'event_trigger' => $eventTrigger,
            ]);
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
