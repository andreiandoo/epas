<?php

namespace App\Console\Commands;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Quick health check for a CAPI connection. Prints which API surfaces
 * the token actually has access to, so you can pinpoint a permission
 * gap (token vs CAPI vs Marketing API vs Custom Audiences).
 *
 *   php artisan capi:diagnose --connection=12
 *   php artisan capi:diagnose --organizer=340
 */
class DiagnoseMetaCapiCommand extends Command
{
    protected $signature = 'capi:diagnose {--connection= : facebook_capi_connections.id} {--organizer= : marketplace_organizer_id}';
    protected $description = 'Probe a CAPI connection: token validity, pixel access, ad account access, custom audiences endpoint';

    protected string $apiVersion = 'v19.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    public function handle(): int
    {
        $connection = $this->resolveConnection();
        if (!$connection) {
            $this->error('No connection found.');
            return self::FAILURE;
        }

        $this->info("Connection #{$connection->id} (organizer={$connection->marketplace_organizer_id}, status={$connection->status})");
        $this->line('');

        $this->probeToken($connection->access_token);
        $this->probePixel($connection->access_token, $connection->pixel_id);

        if ($connection->ad_account_id) {
            $accountId = preg_replace('/^act_/i', '', $connection->ad_account_id);
            $this->probeAdAccount($connection->access_token, $accountId);
            $this->probeCustomAudiences($connection->access_token, $accountId);
        } else {
            $this->warn('No ad_account_id set on this connection — skipping ads probes.');
        }

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?FacebookCapiConnection
    {
        if ($id = $this->option('connection')) {
            return FacebookCapiConnection::find((int) $id);
        }
        if ($org = $this->option('organizer')) {
            return FacebookCapiConnection::where('marketplace_organizer_id', (int) $org)
                ->orderByDesc('id')
                ->first();
        }
        return null;
    }

    protected function probeToken(string $token): void
    {
        $r = Http::get("{$this->baseUrl}/{$this->apiVersion}/me", [
            'access_token' => $token,
            'fields' => 'id,name',
        ]);
        $this->report('Token /me', $r);
    }

    protected function probePixel(string $token, string $pixelId): void
    {
        $r = Http::get("{$this->baseUrl}/{$this->apiVersion}/{$pixelId}", [
            'access_token' => $token,
            'fields' => 'id,name,last_fired_time',
        ]);
        $this->report("Pixel /{$pixelId}", $r);
    }

    protected function probeAdAccount(string $token, string $accountId): void
    {
        $r = Http::get("{$this->baseUrl}/{$this->apiVersion}/act_{$accountId}", [
            'access_token' => $token,
            'fields' => 'id,name,account_status,currency,timezone_name',
        ]);
        $this->report("Ad account /act_{$accountId}", $r);
    }

    protected function probeCustomAudiences(string $token, string $accountId): void
    {
        $r = Http::get("{$this->baseUrl}/{$this->apiVersion}/act_{$accountId}/customaudiences", [
            'access_token' => $token,
            'fields' => 'id,name,subtype,approximate_count_lower_bound,approximate_count_upper_bound',
            'limit' => 5,
        ]);
        $this->report("Custom audiences /act_{$accountId}/customaudiences", $r);
    }

    protected function report(string $label, $response): void
    {
        if ($response->successful()) {
            $body = $response->json();
            $summary = is_array($body) && isset($body['data'])
                ? count($body['data']) . ' rows'
                : json_encode($body);
            $this->info("✓ {$label}: " . mb_substr((string) $summary, 0, 200));
        } else {
            $err = $response->json('error') ?? [];
            $msg = $err['message'] ?? "HTTP {$response->status()}";
            $code = $err['code'] ?? '';
            $sub = $err['error_subcode'] ?? '';
            $this->error("✗ {$label}: {$msg} (code={$code} subcode={$sub})");
        }
    }
}
