<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\StripeService;
use App\Mail\MicroservicePurchaseConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StripeWebhookController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle incoming Stripe webhooks
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;

                case 'invoice.paid':
                    $this->handleInvoicePaid($event->data->object);
                    break;

                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'event_type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle checkout.session.completed event
     */
    protected function handleCheckoutCompleted($session)
    {
        Log::info('Processing checkout.session.completed', ['session_id' => $session->id]);

        // Process the order and activate microservices
        $result = $this->stripeService->processCheckoutCompleted($session);

        $tenant = $result['tenant'];
        $microservices = $result['microservices'];

        // Log activity
        activity()
            ->causedBy($tenant->owner)
            ->performedOn($tenant)
            ->withProperties([
                'microservices' => $microservices->map(fn ($ms) => $ms->getTranslation('name', 'en'))->toArray(),
                'amount' => $result['amount_total'],
                'currency' => $result['currency'],
                'session_id' => $session->id,
            ])
            ->log('Purchased microservices');

        // Generate invoice
        $invoice = $this->generateInvoice($tenant, $microservices, $result);

        // Send confirmation email
        $this->sendConfirmationEmail($tenant, $microservices, $invoice, $session);

        Log::info('Checkout processed successfully', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'microservices' => $microservices->pluck('id'),
        ]);
    }

    /**
     * Generate invoice for microservice purchase
     */
    protected function generateInvoice(Tenant $tenant, $microservices, $result): Invoice
    {
        $settings = Setting::current();

        $subtotal = collect($microservices)->sum('price');

        // Apply VAT if enabled
        $vatRate = $settings->vat_enabled ? ($settings->vat_rate ?? 21.00) : 0;
        $vatAmount = ($subtotal * $vatRate) / 100;
        $total = $subtotal + $vatAmount;

        // Build description
        $descriptions = $microservices->map(function ($ms) {
            $name = $ms->getTranslation('name', 'en');
            return "{$name} ({$ms->pricing_model})";
        })->implode(', ');

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'number' => $settings->getNextInvoiceNumber(),
            'description' => "Microservice Purchase: {$descriptions}",
            'issue_date' => now(),
            'due_date' => now(), // Paid immediately
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $total,
            'currency' => $result['currency'] ?? 'RON',
            'status' => 'paid',
            'meta' => [
                'stripe_session_id' => $result['session']->id,
                'microservice_ids' => $microservices->pluck('id')->toArray(),
                'payment_method' => 'stripe',
            ],
        ]);

        return $invoice;
    }

    /**
     * Send confirmation email to tenant
     */
    protected function sendConfirmationEmail(Tenant $tenant, $microservices, Invoice $invoice, $session)
    {
        try {
            $recipientEmail = $tenant->contact_email ?? $tenant->owner->email ?? null;

            if (!$recipientEmail) {
                Log::warning('No email address found for tenant', ['tenant_id' => $tenant->id]);
                return;
            }

            Mail::to($recipientEmail)->send(
                new MicroservicePurchaseConfirmation($tenant, $microservices, $invoice, $session)
            );

            Log::info('Confirmation email sent', [
                'tenant_id' => $tenant->id,
                'email' => $recipientEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send confirmation email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle invoice.paid event
     */
    protected function handleInvoicePaid($stripeInvoice)
    {
        Log::info('Processing invoice.paid', ['invoice_id' => $stripeInvoice->id]);

        // Update subscription invoice status if exists
        $invoice = Invoice::where('meta->stripe_invoice_id', $stripeInvoice->id)->first();

        if ($invoice) {
            $invoice->update(['status' => 'paid']);
        }
    }

    /**
     * Handle customer.subscription.created event
     */
    protected function handleSubscriptionCreated($subscription)
    {
        Log::info('Processing customer.subscription.created', ['subscription_id' => $subscription->id]);

        // Handle subscription-specific logic if needed
        // For example, extend microservice access period
    }

    /**
     * Handle customer.subscription.deleted event
     */
    protected function handleSubscriptionDeleted($subscription)
    {
        Log::info('Processing customer.subscription.deleted', ['subscription_id' => $subscription->id]);

        // Deactivate microservices related to this subscription
        $metadata = $subscription->metadata->toArray();
        $tenantId = $metadata['tenant_id'] ?? null;

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if ($tenant && isset($metadata['microservice_ids'])) {
                $microserviceIds = explode(',', $metadata['microservice_ids']);

                foreach ($microserviceIds as $microserviceId) {
                    $tenant->microservices()->updateExistingPivot($microserviceId, [
                        'is_active' => false,
                        'expires_at' => now(),
                    ]);
                }

                Log::info('Microservices deactivated due to subscription cancellation', [
                    'tenant_id' => $tenantId,
                    'microservices' => $microserviceIds,
                ]);
            }
        }
    }
}
