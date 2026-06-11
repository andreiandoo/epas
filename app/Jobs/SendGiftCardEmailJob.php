<?php

namespace App\Jobs;

use App\Models\MarketplaceGiftCard;
use App\Services\MarketplaceEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGiftCardEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected MarketplaceGiftCard $giftCard;
    protected string $emailType;

    /**
     * Email types
     */
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_EXPIRY_REMINDER = 'expiry_reminder';
    public const TYPE_PURCHASE_CONFIRMATION = 'purchase_confirmation';

    public function __construct(MarketplaceGiftCard $giftCard, string $emailType = self::TYPE_DELIVERY)
    {
        $this->giftCard = $giftCard;
        $this->emailType = $emailType;
    }

    public function handle(): void
    {
        $giftCard = $this->giftCard;
        $marketplace = $giftCard->marketplaceClient;

        if (!$marketplace) {
            \Log::error("SendGiftCardEmailJob: Marketplace not found for gift card {$giftCard->id}");
            return;
        }

        $emailService = new MarketplaceEmailService($marketplace);

        switch ($this->emailType) {
            case self::TYPE_DELIVERY:
                $this->sendDeliveryEmail($emailService);
                break;
            case self::TYPE_EXPIRY_REMINDER:
                $this->sendExpiryReminderEmail($emailService);
                break;
            case self::TYPE_PURCHASE_CONFIRMATION:
                $this->sendPurchaseConfirmationEmail($emailService);
                break;
        }
    }

    protected function sendDeliveryEmail(MarketplaceEmailService $emailService): void
    {
        $giftCard = $this->giftCard;

        $variables = [
            'recipient_name' => $giftCard->recipient_name ?? 'Friend',
            'purchaser_name' => $giftCard->purchaser_name ?? 'Someone special',
            'gift_card_code' => $giftCard->code,
            'gift_card_pin' => $giftCard->pin,
            'gift_card_amount' => number_format($giftCard->initial_amount, 2) . ' ' . $giftCard->currency,
            'personal_message' => $giftCard->personal_message ?? '',
            'occasion' => $giftCard->occasion_label ?? '',
            'expires_at' => $giftCard->expires_at->format('d M Y'),
            'claim_url' => $this->getClaimUrl(),
            'marketplace_name' => $giftCard->marketplaceClient->name,
            'marketplace_url' => $giftCard->marketplaceClient->website ?? config('app.url'),
        ];

        // Try to use templated email first
        $sent = $emailService->sendTemplatedEmail(
            'gift_card_delivery',
            $giftCard->recipient_email,
            $giftCard->recipient_name,
            $variables
        );

        // If no template exists, send custom email
        if (!$sent) {
            $subject = $this->buildSubject();
            $bodyHtml = $this->buildDeliveryEmailHtml($variables);

            $emailService->sendCustomEmail(
                $giftCard->recipient_email,
                $subject,
                $bodyHtml,
                $giftCard->recipient_name
            );
        }

        // Mark as delivered
        $giftCard->update([
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);
    }

    protected function sendExpiryReminderEmail(MarketplaceEmailService $emailService): void
    {
        $giftCard = $this->giftCard;

        if ($giftCard->balance <= 0) {
            return;
        }

        $recipientEmail = $giftCard->recipient_customer_id
            ? $giftCard->recipient->email
            : $giftCard->recipient_email;

        $recipientName = $giftCard->recipient_customer_id
            ? $giftCard->recipient->full_name
            : $giftCard->recipient_name;

        $variables = [
            'recipient_name' => $recipientName ?? 'Valued Customer',
            'gift_card_code' => $giftCard->masked_code,
            'remaining_balance' => number_format($giftCard->balance, 2) . ' ' . $giftCard->currency,
            'expires_at' => $giftCard->expires_at->format('d M Y'),
            'days_remaining' => $giftCard->days_until_expiry,
            'marketplace_name' => $giftCard->marketplaceClient->name,
            'marketplace_url' => $giftCard->marketplaceClient->website ?? config('app.url'),
        ];

        $subject = "Your gift card expires in {$giftCard->days_until_expiry} days - {$giftCard->marketplaceClient->name}";
        $bodyHtml = $this->buildExpiryReminderHtml($variables);

        $emailService->sendCustomEmail(
            $recipientEmail,
            $subject,
            $bodyHtml,
            $recipientName
        );
    }

    protected function sendPurchaseConfirmationEmail(MarketplaceEmailService $emailService): void
    {
        $giftCard = $this->giftCard;

        $variables = [
            'purchaser_name' => $giftCard->purchaser_name ?? 'Valued Customer',
            'recipient_name' => $giftCard->recipient_name,
            'recipient_email' => $giftCard->recipient_email,
            'gift_card_amount' => number_format($giftCard->initial_amount, 2) . ' ' . $giftCard->currency,
            'gift_card_code' => $giftCard->code,
            'delivery_method' => $giftCard->delivery_method === 'email' ? 'Email' : 'Print at Home',
            'scheduled_delivery' => $giftCard->scheduled_delivery_at
                ? $giftCard->scheduled_delivery_at->format('d M Y H:i')
                : 'Immediately',
            'marketplace_name' => $giftCard->marketplaceClient->name,
        ];

        $subject = "Gift Card Purchase Confirmation - {$giftCard->marketplaceClient->name}";
        $bodyHtml = $this->buildPurchaseConfirmationHtml($variables);

        $emailService->sendCustomEmail(
            $giftCard->purchaser_email,
            $subject,
            $bodyHtml,
            $giftCard->purchaser_name
        );
    }

    protected function buildSubject(): string
    {
        $giftCard = $this->giftCard;
        $purchaserName = $giftCard->purchaser_name ?? 'Someone special';

        if ($giftCard->occasion) {
            $occasionLabel = $giftCard->occasion_label ?? '';
            return "{$purchaserName} sent you a {$occasionLabel} gift card!";
        }

        return "{$purchaserName} sent you a gift card!";
    }

    protected function getClaimUrl(): string
    {
        $giftCard = $this->giftCard;
        $baseUrl = $giftCard->marketplaceClient->website ?? config('app.url');
        return rtrim($baseUrl, '/') . '/gift-card/claim?code=' . urlencode($giftCard->code);
    }

    protected function buildDeliveryEmailHtml(array $variables): string
    {
        $messageSection = !empty($variables['personal_message'])
            ? '<div style="background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; font-style: italic;">
                <p style="margin: 0; color: #92400e;">"' . e($variables['personal_message']) . '"</p>
                <p style="margin: 10px 0 0 0; color: #b45309; font-size: 14px;">— ' . e($variables['purchaser_name']) . '</p>
               </div>'
            : '';

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 40px 20px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 28px;">🎁 You've received a gift card!</h1>
            </div>

            <div style="padding: 40px 20px;">
                <p style="font-size: 18px; color: #374151;">Hello {$variables['recipient_name']},</p>

                <p style="color: #6b7280; font-size: 16px;">
                    <strong>{$variables['purchaser_name']}</strong> has sent you a gift card worth
                    <strong style="color: #4f46e5;">{$variables['gift_card_amount']}</strong>!
                </p>

                {$messageSection}

                <div style="background: #f3f4f6; padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0;">
                    <p style="margin: 0 0 10px 0; color: #6b7280; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">Your Gift Card Code</p>
                    <p style="font-family: monospace; font-size: 28px; font-weight: bold; color: #1f2937; margin: 0; letter-spacing: 2px;">{$variables['gift_card_code']}</p>
                    <p style="margin: 15px 0 0 0; color: #6b7280; font-size: 14px;">PIN: <strong>{$variables['gift_card_pin']}</strong></p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{$variables['claim_url']}" style="background: #4f46e5; color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">
                        Claim Your Gift Card
                    </a>
                </div>

                <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px;">
                    <p style="color: #9ca3af; font-size: 14px; margin: 0;">
                        <strong>Valid until:</strong> {$variables['expires_at']}<br>
                        Use this gift card to purchase tickets on {$variables['marketplace_name']}.
                    </p>
                </div>
            </div>

            <div style="background: #f9fafb; padding: 20px; text-align: center;">
                <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                    This gift card was purchased from {$variables['marketplace_name']}<br>
                    <a href="{$variables['marketplace_url']}" style="color: #4f46e5;">{$variables['marketplace_url']}</a>
                </p>
            </div>
        </div>
        HTML;
    }

    protected function buildExpiryReminderHtml(array $variables): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #fef3c7; padding: 20px; text-align: center;">
                <h1 style="color: #92400e; margin: 0;">⏰ Your Gift Card Expires Soon!</h1>
            </div>

            <div style="padding: 40px 20px;">
                <p style="font-size: 18px; color: #374151;">Hello {$variables['recipient_name']},</p>

                <p style="color: #6b7280; font-size: 16px;">
                    Your gift card ({$variables['gift_card_code']}) with a remaining balance of
                    <strong style="color: #4f46e5;">{$variables['remaining_balance']}</strong>
                    will expire in <strong style="color: #dc2626;">{$variables['days_remaining']} days</strong>.
                </p>

                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <p style="color: #dc2626; margin: 0; font-weight: bold;">
                        ⚠️ Expires on: {$variables['expires_at']}
                    </p>
                </div>

                <p style="color: #6b7280;">
                    Don't let your gift card go to waste! Visit {$variables['marketplace_name']} and use it before it expires.
                </p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{$variables['marketplace_url']}" style="background: #4f46e5; color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">
                        Shop Now
                    </a>
                </div>
            </div>
        </div>
        HTML;
    }

    protected function buildPurchaseConfirmationHtml(array $variables): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #10b981; padding: 20px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0;">✅ Gift Card Purchase Confirmed</h1>
            </div>

            <div style="padding: 40px 20px;">
                <p style="font-size: 18px; color: #374151;">Hello {$variables['purchaser_name']},</p>

                <p style="color: #6b7280; font-size: 16px;">
                    Your gift card purchase has been confirmed. Here are the details:
                </p>

                <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 10px 0; color: #6b7280;">Amount:</td>
                            <td style="padding: 10px 0; font-weight: bold; text-align: right;">{$variables['gift_card_amount']}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #6b7280;">Recipient:</td>
                            <td style="padding: 10px 0; text-align: right;">{$variables['recipient_name']}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #6b7280;">Recipient Email:</td>
                            <td style="padding: 10px 0; text-align: right;">{$variables['recipient_email']}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #6b7280;">Delivery:</td>
                            <td style="padding: 10px 0; text-align: right;">{$variables['scheduled_delivery']}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #6b7280;">Gift Card Code:</td>
                            <td style="padding: 10px 0; font-family: monospace; font-weight: bold; text-align: right;">{$variables['gift_card_code']}</td>
                        </tr>
                    </table>
                </div>

                <p style="color: #6b7280; font-size: 14px;">
                    The recipient will receive their gift card via email. You can keep this code as a reference.
                </p>
            </div>

            <div style="background: #f9fafb; padding: 20px; text-align: center;">
                <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                    Thank you for your purchase from {$variables['marketplace_name']}
                </p>
            </div>
        </div>
        HTML;
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("SendGiftCardEmailJob failed for gift card {$this->giftCard->id}: {$exception->getMessage()}");
    }
}
