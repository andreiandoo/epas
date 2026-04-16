<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkPasswordResetCommand extends Command
{
    protected $signature = 'bulk:password-reset
        {--type=customer : customer or organizer}
        {--marketplace=1 : marketplace_client_id}
        {--batch-size=200 : emails per batch}
        {--delay=10 : seconds between batches}
        {--limit= : max emails to send (for testing)}
        {--dry-run : show what would be sent}';

    protected $description = 'Send bulk password reset emails to customers or organizers';

    public function handle(): int
    {
        $type = $this->option('type');
        $clientId = (int) $this->option('marketplace');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        $client = MarketplaceClient::find($clientId);
        if (!$client) {
            $this->error("Marketplace client {$clientId} not found.");
            return 1;
        }

        $templateSlug = $type === 'customer' ? 'bulk_password_reset_customer' : 'bulk_password_reset_organizer';
        $template = MarketplaceEmailTemplate::where('marketplace_client_id', $clientId)
            ->where('slug', $templateSlug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            $this->error("Template '{$templateSlug}' not found. Run: php artisan db:seed --class=BulkPasswordResetTemplateSeeder");
            return 1;
        }

        // Count recipients
        if ($type === 'customer') {
            $query = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->whereNotNull('password')
                ->where('status', 'active');
        } else {
            $query = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                ->whereNotNull('password')
                ->whereIn('status', ['active', 'pending']);
        }

        $total = $limit ? min($query->count(), $limit) : $query->count();
        $this->info("Type: {$type} | Recipients: {$total} | Batch: {$batchSize} | Delay: {$delay}s");

        if ($dryRun) {
            $sample = $query->limit(5)->get(['id', 'email', $type === 'customer' ? 'first_name' : 'name']);
            $this->line('Sample recipients:');
            foreach ($sample as $r) {
                $this->line("  #{$r->id} {$r->email} ({$r->first_name ?? $r->name ?? '?'})");
            }
            $this->info("[DRY RUN] Would send {$total} emails.");
            return 0;
        }

        if (!$this->confirm("Send {$total} password reset emails?")) {
            return 0;
        }

        $domain = $client->domain ? rtrim($client->domain, '/') : 'https://ambilet.ro';
        if (!str_starts_with($domain, 'http')) $domain = 'https://' . $domain;
        $siteName = $client->name ?? 'AmBilet';
        $tokenType = $type === 'customer' ? 'bulk_customer' : 'bulk_organizer';
        $resetPath = $type === 'customer' ? '/resetare-parola' : '/organizator/resetare-parola';

        $sent = 0;
        $failed = 0;
        $processed = 0;

        $query->orderBy('id')->chunk($batchSize, function ($recipients) use (
            $client, $template, $domain, $siteName, $tokenType, $resetPath, $type, $delay, $limit,
            &$sent, &$failed, &$processed
        ) {
            foreach ($recipients as $recipient) {
                if ($limit && $processed >= $limit) return false;

                try {
                    $email = $recipient->email;
                    $firstName = $type === 'customer'
                        ? ($recipient->first_name ?: 'Client')
                        : ($recipient->contact_name ?: $recipient->name ?: 'Organizator');

                    // Generate token
                    DB::table('marketplace_password_resets')
                        ->where('email', $email)->where('type', $tokenType)
                        ->where('marketplace_client_id', $client->id)->delete();

                    $token = Str::random(64);
                    DB::table('marketplace_password_resets')->insert([
                        'email' => $email, 'type' => $tokenType,
                        'marketplace_client_id' => $client->id,
                        'token' => Hash::make($token), 'created_at' => now(),
                    ]);

                    $resetUrl = $domain . $resetPath . '?' . http_build_query(['token' => $token, 'email' => $email]);
                    $variables = [
                        'first_name' => $firstName, 'email' => $email,
                        'reset_link' => $resetUrl, 'site_name' => $siteName, 'expire_days' => '7',
                    ];

                    $subject = $template->subject;
                    $html = $template->body_html;
                    foreach ($variables as $k => $v) {
                        $subject = str_replace('{{' . $k . '}}', $v, $subject);
                        $html = str_replace('{{' . $k . '}}', $v, $html);
                    }

                    BaseController::sendViaMarketplace($client, $email, $firstName, $subject, $html, [
                        'template_slug' => $template->slug,
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  Failed: {$recipient->email} — {$e->getMessage()}");
                }

                $processed++;
                if ($processed % 50 === 0) {
                    $this->line("  Progress: {$sent} sent, {$failed} failed ({$processed}/{$limit ?? '∞'})");
                }
            }

            $this->line("  Batch done. Sleeping {$delay}s...");
            sleep($delay);
            return true;
        });

        $this->info("Done! Sent: {$sent} | Failed: {$failed}");
        return 0;
    }
}
