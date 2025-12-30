<?php

namespace App\Jobs;

use App\Models\MarketplaceRefundRequest;
use App\Models\MarketplaceAdmin;
use App\Services\MarketplaceEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRefundNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected MarketplaceRefundRequest $refundRequest;
    protected string $notificationType;

    /**
     * Notification types
     */
    public const TYPE_CREATED = 'created';
    public const TYPE_APPROVED = 'approved';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_COMPLETED = 'completed';

    public function __construct(MarketplaceRefundRequest $refundRequest, string $notificationType)
    {
        $this->refundRequest = $refundRequest;
        $this->notificationType = $notificationType;
    }

    public function handle(): void
    {
        $refund = $this->refundRequest;
        $marketplace = $refund->marketplaceClient;
        $emailService = new MarketplaceEmailService($marketplace);

        switch ($this->notificationType) {
            case self::TYPE_CREATED:
                $this->notifyCustomerRefundReceived($emailService);
                $this->notifyAdminsNewRefundRequest();
                break;

            case self::TYPE_APPROVED:
                $emailService->sendRefundApprovedEmail($refund);
                break;

            case self::TYPE_REJECTED:
                $emailService->sendRefundRejectedEmail($refund);
                break;

            case self::TYPE_COMPLETED:
                $this->notifyCustomerRefundCompleted($emailService);
                break;
        }
    }

    protected function notifyCustomerRefundReceived(MarketplaceEmailService $emailService): void
    {
        $refund = $this->refundRequest;
        $customer = $refund->customer;
        $order = $refund->order;

        $variables = [
            'customer_name' => $customer->full_name,
            'refund_reference' => $refund->reference,
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'refund_amount' => number_format($refund->requested_amount, 2) . ' RON',
            'refund_reason' => $refund->reason_label,
            'marketplace_name' => $refund->marketplaceClient->name,
        ];

        $emailService->sendTemplatedEmail(
            'refund_requested',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    protected function notifyAdminsNewRefundRequest(): void
    {
        $refund = $this->refundRequest;
        $marketplace = $refund->marketplaceClient;

        // Get all active admins for this marketplace
        $admins = MarketplaceAdmin::where('marketplace_client_id', $marketplace->id)
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            \Log::warning("No active admins found for marketplace {$marketplace->id} to notify about refund request {$refund->id}");
            return;
        }

        $emailService = new MarketplaceEmailService($marketplace);

        foreach ($admins as $admin) {
            $variables = [
                'admin_name' => $admin->name,
                'refund_reference' => $refund->reference,
                'customer_name' => $refund->customer->full_name,
                'customer_email' => $refund->customer->email,
                'order_number' => $refund->order->order_number ?? str_pad($refund->order_id, 8, '0', STR_PAD_LEFT),
                'refund_amount' => number_format($refund->requested_amount, 2) . ' RON',
                'refund_reason' => $refund->reason_label,
                'refund_type' => $refund->type,
                'customer_notes' => $refund->customer_notes ?? 'No notes provided',
                'review_url' => url("/marketplace/refund-requests/{$refund->id}"),
                'marketplace_name' => $marketplace->name,
            ];

            // Send a custom email since there's no specific admin template
            $subject = "New Refund Request: {$refund->reference}";
            $bodyHtml = $this->buildAdminNotificationHtml($variables);

            $emailService->sendCustomEmail(
                $admin->email,
                $subject,
                $bodyHtml,
                $admin->name
            );
        }
    }

    protected function notifyCustomerRefundCompleted(MarketplaceEmailService $emailService): void
    {
        $refund = $this->refundRequest;
        $customer = $refund->customer;
        $order = $refund->order;

        $variables = [
            'customer_name' => $customer->full_name,
            'refund_reference' => $refund->reference,
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'refund_amount' => number_format($refund->approved_amount, 2) . ' RON',
            'refund_method' => $this->getRefundMethodLabel($refund->refund_method),
            'payment_reference' => $refund->payment_refund_id ?? 'N/A',
            'marketplace_name' => $refund->marketplaceClient->name,
        ];

        $emailService->sendTemplatedEmail(
            'refund_completed',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    protected function buildAdminNotificationHtml(array $variables): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #374151;">New Refund Request</h2>
            <p>Hello {$variables['admin_name']},</p>
            <p>A new refund request has been submitted and requires your review.</p>

            <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Reference:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{$variables['refund_reference']}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Customer:</td>
                        <td style="padding: 8px 0;">{$variables['customer_name']} ({$variables['customer_email']})</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Order:</td>
                        <td style="padding: 8px 0;">#{$variables['order_number']}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Amount:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #dc2626;">{$variables['refund_amount']}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Reason:</td>
                        <td style="padding: 8px 0;">{$variables['refund_reason']}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Customer Notes:</td>
                        <td style="padding: 8px 0;">{$variables['customer_notes']}</td>
                    </tr>
                </table>
            </div>

            <p style="margin-top: 20px;">
                <a href="{$variables['review_url']}" style="background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                    Review Request
                </a>
            </p>

            <p style="color: #6b7280; font-size: 12px; margin-top: 30px;">
                This is an automated notification from {$variables['marketplace_name']}.
            </p>
        </div>
        HTML;
    }

    protected function getRefundMethodLabel(?string $method): string
    {
        return match ($method) {
            'original_payment' => 'Original Payment Method',
            'bank_transfer' => 'Bank Transfer',
            'store_credit' => 'Store Credit',
            'manual' => 'Manual Processing',
            default => 'Payment Provider',
        };
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("SendRefundNotificationsJob failed for refund {$this->refundRequest->id}: {$exception->getMessage()}");
    }
}
