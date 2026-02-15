<?php

namespace App\Notifications;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Unified notification for ad campaign lifecycle events.
 *
 * Actions:
 * - request_approved: Service request approved, campaign being created
 * - request_rejected: Service request rejected with reason
 * - campaign_launched: Campaign is now live on platforms
 * - campaign_paused: Campaign paused (manual or auto)
 * - report_ready: Weekly/daily report available
 * - budget_alert: Budget exhausted or nearly exhausted
 * - campaign_completed: Campaign finished, final results available
 * - performance_alert: Notable performance change (high ROAS, low CTR, etc.)
 */
class AdsCampaignNotification extends Notification
{
    public function __construct(
        public string $action,
        public ?AdsCampaign $campaign = null,
        public ?AdsServiceRequest $serviceRequest = null,
        public array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->action) {
            'request_approved' => $this->requestApprovedMail($notifiable),
            'request_rejected' => $this->requestRejectedMail($notifiable),
            'campaign_launched' => $this->campaignLaunchedMail($notifiable),
            'campaign_paused' => $this->campaignPausedMail($notifiable),
            'report_ready' => $this->reportReadyMail($notifiable),
            'budget_alert' => $this->budgetAlertMail($notifiable),
            'campaign_completed' => $this->campaignCompletedMail($notifiable),
            'performance_alert' => $this->performanceAlertMail($notifiable),
            default => $this->defaultMail($notifiable),
        };
    }

    protected function requestApprovedMail($notifiable): MailMessage
    {
        $name = $this->serviceRequest?->name ?? 'your campaign';

        return (new MailMessage)
            ->subject("Ad Campaign Request Approved: {$name}")
            ->greeting("Great news!")
            ->line("Your ad campaign request **{$name}** has been approved!")
            ->line("Our team is now setting up your campaign. You'll receive another notification once it goes live.")
            ->line("**Budget:** {$this->serviceRequest?->budget} {$this->serviceRequest?->currency}")
            ->line("**Platforms:** " . $this->formatPlatforms($this->serviceRequest?->target_platforms))
            ->line("We'll make sure your ads reach the right audience to maximize ticket sales.");
    }

    protected function requestRejectedMail($notifiable): MailMessage
    {
        $name = $this->serviceRequest?->name ?? 'your campaign';
        $reason = $this->data['reason'] ?? 'Please contact support for details.';

        return (new MailMessage)
            ->subject("Ad Campaign Request Update: {$name}")
            ->greeting("Hello,")
            ->line("We've reviewed your ad campaign request **{$name}** and unfortunately cannot proceed at this time.")
            ->line("**Reason:** {$reason}")
            ->line("You can submit a new request with adjusted parameters, or contact our team for assistance.");
    }

    protected function campaignLaunchedMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';
        $platforms = $this->formatPlatforms($this->campaign?->target_platforms);

        return (new MailMessage)
            ->subject("Your Ad Campaign is LIVE: {$name}")
            ->greeting("Your ads are now running!")
            ->line("**{$name}** has been launched successfully on {$platforms}.")
            ->line("**Daily Budget:** {$this->campaign?->daily_budget} {$this->campaign?->currency}")
            ->line("**Campaign Period:** " . $this->campaign?->start_date?->format('M d') . ' - ' . $this->campaign?->end_date?->format('M d, Y'))
            ->line("Here's what happens next:")
            ->line("- Your ads are being shown to your target audience")
            ->line("- Our optimization engine monitors performance 24/7")
            ->line("- You'll receive a performance report within 48 hours")
            ->line("You can track results in your dashboard anytime.");
    }

    protected function campaignPausedMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';
        $reason = $this->data['reason'] ?? 'Manual pause requested';

        return (new MailMessage)
            ->subject("Ad Campaign Paused: {$name}")
            ->greeting("Campaign Update")
            ->line("**{$name}** has been paused.")
            ->line("**Reason:** {$reason}")
            ->line("**Results so far:**")
            ->line("- Impressions: " . number_format($this->campaign?->total_impressions ?? 0))
            ->line("- Clicks: " . number_format($this->campaign?->total_clicks ?? 0))
            ->line("- Conversions: " . number_format($this->campaign?->total_conversions ?? 0))
            ->line("The campaign can be resumed at any time from your dashboard.");
    }

    protected function reportReadyMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';
        $reportType = $this->data['report_type'] ?? 'performance';

        $mail = (new MailMessage)
            ->subject("Ad Campaign Report: {$name}")
            ->greeting("Your {$reportType} report is ready!")
            ->line("Here's a summary of **{$name}** performance:");

        if ($this->campaign) {
            $roas = $this->campaign->roas ? number_format((float) $this->campaign->roas, 2) . 'x' : 'N/A';
            $mail->line("**Key Metrics:**")
                ->line("- Total Spend: " . number_format($this->campaign->total_spend ?? 0, 2) . " {$this->campaign->currency}")
                ->line("- Revenue Generated: " . number_format($this->campaign->total_revenue ?? 0, 2) . " {$this->campaign->currency}")
                ->line("- ROAS: {$roas}")
                ->line("- Conversions: " . number_format($this->campaign->total_conversions ?? 0))
                ->line("- Click-Through Rate: " . number_format($this->campaign->avg_ctr ?? 0, 2) . "%");
        }

        return $mail->line("View the full report with platform breakdowns and recommendations in your dashboard.");
    }

    protected function budgetAlertMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';
        $percentUsed = $this->data['percent_used'] ?? 100;
        $remaining = $this->data['remaining'] ?? 0;

        $subject = $percentUsed >= 100
            ? "Budget Exhausted: {$name}"
            : "Budget Alert: {$name} ({$percentUsed}% used)";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Budget Update");

        if ($percentUsed >= 100) {
            $mail->line("The advertising budget for **{$name}** has been fully spent.")
                ->line("The campaign has been automatically paused.")
                ->line("**Final Results:**")
                ->line("- Total Spend: " . number_format($this->campaign?->total_spend ?? 0, 2) . " {$this->campaign?->currency}")
                ->line("- Conversions: " . number_format($this->campaign?->total_conversions ?? 0))
                ->line("- ROAS: " . number_format((float) ($this->campaign?->roas ?? 0), 2) . "x")
                ->line("To continue running ads, you can top up the budget from your dashboard.");
        } else {
            $mail->line("**{$name}** has used {$percentUsed}% of its budget.")
                ->line("**Remaining:** " . number_format($remaining, 2) . " {$this->campaign?->currency}")
                ->line("At the current pace, the budget will be fully spent within " . ($this->data['days_remaining'] ?? '?') . " days.")
                ->line("Consider increasing the budget to maintain campaign momentum.");
        }

        return $mail;
    }

    protected function campaignCompletedMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';

        $mail = (new MailMessage)
            ->subject("Campaign Complete: {$name} — Final Results")
            ->greeting("Your campaign has finished!")
            ->line("**{$name}** has completed its run. Here are your final results:");

        if ($this->campaign) {
            $roas = number_format((float) ($this->campaign->roas ?? 0), 2);
            $mail->line("**Performance Summary:**")
                ->line("- Total Impressions: " . number_format($this->campaign->total_impressions ?? 0))
                ->line("- Total Clicks: " . number_format($this->campaign->total_clicks ?? 0))
                ->line("- Conversions (Ticket Sales): " . number_format($this->campaign->total_conversions ?? 0))
                ->line("- Total Ad Spend: " . number_format($this->campaign->total_spend ?? 0, 2) . " {$this->campaign->currency}")
                ->line("- Revenue Generated: " . number_format($this->campaign->total_revenue ?? 0, 2) . " {$this->campaign->currency}")
                ->line("- Return on Ad Spend: {$roas}x")
                ->line("- Cost per Acquisition: " . number_format($this->campaign->cac ?? 0, 2) . " {$this->campaign->currency}");
        }

        return $mail->line("A detailed final report has been generated and is available in your dashboard.")
            ->line("Thank you for choosing our advertising service!");
    }

    protected function performanceAlertMail($notifiable): MailMessage
    {
        $name = $this->campaign?->name ?? 'Your campaign';
        $alertType = $this->data['alert_type'] ?? 'update';
        $message = $this->data['message'] ?? '';

        $emoji = match ($alertType) {
            'positive' => '🎉',
            'warning' => '⚠️',
            'critical' => '🚨',
            default => '📊',
        };

        return (new MailMessage)
            ->subject("{$emoji} Performance Alert: {$name}")
            ->greeting("Performance Update")
            ->line("We noticed something important about **{$name}**:")
            ->line($message)
            ->line("Our optimization engine is automatically adjusting the campaign to maximize your results.")
            ->line("Check your dashboard for detailed metrics and insights.");
    }

    protected function defaultMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ad Campaign Update")
            ->greeting("Hello,")
            ->line("There's an update to your ad campaign.")
            ->line($this->data['message'] ?? 'Please check your dashboard for details.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'action' => $this->action,
            'campaign_id' => $this->campaign?->id,
            'campaign_name' => $this->campaign?->name,
            'service_request_id' => $this->serviceRequest?->id,
            'data' => $this->data,
        ];
    }

    protected function formatPlatforms(?array $platforms): string
    {
        if (!$platforms) {
            return 'selected platforms';
        }

        return implode(', ', array_map(fn ($p) => match ($p) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'google' => 'Google Ads',
            default => ucfirst($p),
        }, $platforms));
    }
}
