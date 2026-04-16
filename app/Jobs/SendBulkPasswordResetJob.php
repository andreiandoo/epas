<?php

namespace App\Jobs;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendBulkPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public array $backoff = [60, 120, 300];

    public function __construct(
        protected int $campaignId,
    ) {}

    public function handle(): void
    {
        $campaign = DB::table('bulk_password_reset_campaigns')->find($this->campaignId);

        if (!$campaign || $campaign->status !== 'sending') {
            return; // paused, completed, or deleted
        }

        $client = MarketplaceClient::find($campaign->marketplace_client_id);
        if (!$client) return;

        $batchSize = $campaign->batch_size;
        $delay = $campaign->delay_seconds;
        $type = $campaign->type;
        $lastId = $campaign->last_processed_id;

        // Get template
        $template = MarketplaceEmailTemplate::where('marketplace_client_id', $client->id)
            ->where('slug', $campaign->template_slug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            DB::table('bulk_password_reset_campaigns')->where('id', $this->campaignId)->update([
                'status' => 'failed', 'updated_at' => now(),
            ]);
            Log::error('Bulk password reset: template not found', ['campaign_id' => $this->campaignId, 'slug' => $campaign->template_slug]);
            return;
        }

        // Get next batch of recipients
        if ($type === 'customer') {
            $recipients = MarketplaceCustomer::where('marketplace_client_id', $client->id)
                ->whereNotNull('password')
                ->where('id', '>', $lastId)
                ->where('status', 'active')
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'email', 'first_name', 'last_name']);
        } else {
            $recipients = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
                ->whereNotNull('password')
                ->where('id', '>', $lastId)
                ->whereIn('status', ['active', 'pending'])
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'email', 'name', 'contact_name']);
        }

        if ($recipients->isEmpty()) {
            DB::table('bulk_password_reset_campaigns')->where('id', $this->campaignId)->update([
                'status' => 'completed', 'completed_at' => now(), 'updated_at' => now(),
            ]);
            return;
        }

        $domain = $client->domain ? rtrim($client->domain, '/') : 'https://ambilet.ro';
        if (!str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        $siteName = $client->name ?? 'AmBilet';
        $tokenType = $type === 'customer' ? 'bulk_customer' : 'bulk_organizer';
        $resetPath = $type === 'customer' ? '/resetare-parola' : '/organizator/resetare-parola';

        $sent = 0;
        $failed = 0;
        $lastProcessedId = $lastId;

        foreach ($recipients as $recipient) {
            // Re-check campaign status (in case paused mid-batch)
            $currentStatus = DB::table('bulk_password_reset_campaigns')->where('id', $this->campaignId)->value('status');
            if ($currentStatus !== 'sending') break;

            try {
                $email = $recipient->email;
                $firstName = $type === 'customer'
                    ? ($recipient->first_name ?: 'Client')
                    : ($recipient->contact_name ?: $recipient->name ?: 'Organizator');

                // Delete existing bulk tokens for this email
                DB::table('marketplace_password_resets')
                    ->where('email', $email)
                    ->where('type', $tokenType)
                    ->where('marketplace_client_id', $client->id)
                    ->delete();

                // Generate new token
                $token = Str::random(64);
                DB::table('marketplace_password_resets')->insert([
                    'email' => $email,
                    'type' => $tokenType,
                    'marketplace_client_id' => $client->id,
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]);

                // Build reset URL
                $resetUrl = $domain . $resetPath . '?' . http_build_query([
                    'token' => $token,
                    'email' => $email,
                ]);

                // Render template
                $variables = [
                    'first_name' => $firstName,
                    'email' => $email,
                    'reset_link' => $resetUrl,
                    'site_name' => $siteName,
                    'expire_days' => '7',
                ];

                $subject = $template->subject;
                $html = $template->body_html;
                foreach ($variables as $key => $value) {
                    $subject = str_replace('{{' . $key . '}}', $value, $subject);
                    $html = str_replace('{{' . $key . '}}', $value, $html);
                }

                // Send email
                BaseController::sendViaMarketplace($client, $email, $firstName, $subject, $html, [
                    'template_slug' => $campaign->template_slug,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Bulk password reset: email failed', [
                    'campaign_id' => $this->campaignId,
                    'email' => $recipient->email ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }

            $lastProcessedId = $recipient->id;
        }

        // Update campaign progress
        DB::table('bulk_password_reset_campaigns')->where('id', $this->campaignId)->update([
            'sent_count' => DB::raw("sent_count + {$sent}"),
            'failed_count' => DB::raw("failed_count + {$failed}"),
            'last_processed_id' => $lastProcessedId,
            'updated_at' => now(),
        ]);

        // Check if more to send
        $updatedCampaign = DB::table('bulk_password_reset_campaigns')->find($this->campaignId);
        if ($updatedCampaign->status === 'sending' && ($updatedCampaign->sent_count + $updatedCampaign->failed_count) < $updatedCampaign->total_recipients) {
            // Dispatch next batch with delay
            self::dispatch($this->campaignId)->delay(now()->addSeconds($delay));
        } else {
            DB::table('bulk_password_reset_campaigns')->where('id', $this->campaignId)->update([
                'status' => 'completed', 'completed_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
