<?php

namespace App\Services\Integrations\FacebookCapi;

use App\Models\Integrations\FacebookCapi\FacebookAdsAccount;
use App\Models\Integrations\FacebookCapi\FacebookAdsCampaign;
use App\Models\Integrations\FacebookCapi\FacebookAdsInsight;
use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper around the Meta Marketing API (read-only) for pulling ads
 * performance data per organizer. Uses the same access_token as
 * FacebookCapiConnection (System User token with `ads_management` scope
 * — superset of `ads_read` — so no extra credential is required).
 */
class FacebookMarketingApiService
{
    protected string $apiVersion = 'v19.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    /**
     * Sync the account-level metadata (currency, status, name) for the
     * organizer's Ad Account. Called before campaigns/insights so we
     * fail fast on bad credentials.
     */
    public function syncAccount(FacebookCapiConnection $connection): ?FacebookAdsAccount
    {
        if (!$connection->ad_account_id) {
            return null;
        }

        $accountId = $this->normalizeAccountId($connection->ad_account_id);
        $url = "{$this->baseUrl}/{$this->apiVersion}/act_{$accountId}";

        try {
            $response = Http::timeout(15)->get($url, [
                'access_token' => $connection->access_token,
                'fields' => 'name,currency,account_status,timezone_name',
            ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? "HTTP {$response->status()}";
                return $this->markAccountFailed($connection, $accountId, $error);
            }

            $data = $response->json() ?? [];

            return FacebookAdsAccount::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'fb_account_id' => $accountId,
                ],
                [
                    'marketplace_organizer_id' => $connection->marketplace_organizer_id,
                    'marketplace_client_id' => $connection->marketplace_client_id,
                    'tenant_id' => $connection->tenant_id,
                    'account_name' => $data['name'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'account_status' => isset($data['account_status']) ? (string) $data['account_status'] : null,
                    'timezone_name' => $data['timezone_name'] ?? null,
                    'last_synced_at' => now(),
                    'last_sync_status' => 'ok',
                    'last_sync_error' => null,
                ]
            );
        } catch (\Throwable $e) {
            return $this->markAccountFailed($connection, $accountId, $e->getMessage());
        }
    }

    /**
     * Sync campaigns list for the account. Idempotent on (account, fb_campaign_id).
     */
    public function syncCampaigns(FacebookAdsAccount $account, FacebookCapiConnection $connection): int
    {
        $accountId = $account->fb_account_id;
        $url = "{$this->baseUrl}/{$this->apiVersion}/act_{$accountId}/campaigns";

        try {
            $synced = 0;
            $next = $url;
            $params = [
                'access_token' => $connection->access_token,
                'fields' => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,start_time,stop_time',
                'limit' => 100,
            ];

            while ($next) {
                $response = $next === $url
                    ? Http::timeout(15)->get($url, $params)
                    : Http::timeout(15)->get($next);

                if (!$response->successful()) {
                    Log::warning('FacebookMarketingApi: campaigns fetch failed', [
                        'account_id' => $accountId,
                        'http_status' => $response->status(),
                        'error' => $response->json('error.message'),
                    ]);
                    break;
                }

                $body = $response->json() ?? [];
                foreach ($body['data'] ?? [] as $row) {
                    FacebookAdsCampaign::updateOrCreate(
                        [
                            'ads_account_id' => $account->id,
                            'fb_campaign_id' => (string) $row['id'],
                        ],
                        [
                            'name' => $row['name'] ?? null,
                            'objective' => $row['objective'] ?? null,
                            'status' => $row['status'] ?? null,
                            'effective_status' => $row['effective_status'] ?? null,
                            // Meta returns budgets in minor units (cents).
                            'daily_budget' => isset($row['daily_budget']) ? ((float) $row['daily_budget']) / 100 : null,
                            'lifetime_budget' => isset($row['lifetime_budget']) ? ((float) $row['lifetime_budget']) / 100 : null,
                            'budget_currency' => $account->currency,
                            'start_time' => isset($row['start_time']) ? Carbon::parse($row['start_time']) : null,
                            'stop_time' => isset($row['stop_time']) ? Carbon::parse($row['stop_time']) : null,
                            'last_synced_at' => now(),
                        ]
                    );
                    $synced++;
                }

                $next = $body['paging']['next'] ?? null;
                $params = []; // subsequent pages use the cursor URL directly
            }

            return $synced;
        } catch (\Throwable $e) {
            Log::warning('FacebookMarketingApi: campaigns sync exception', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Sync daily insights per campaign for the given date range. Inserts
     * one row per (account, campaign, date). Idempotent — the unique key
     * means re-runs overwrite stale numbers.
     */
    public function syncInsights(
        FacebookAdsAccount $account,
        FacebookCapiConnection $connection,
        Carbon $startDate,
        Carbon $endDate
    ): int {
        $accountId = $account->fb_account_id;
        $url = "{$this->baseUrl}/{$this->apiVersion}/act_{$accountId}/insights";

        try {
            $synced = 0;
            $next = $url;
            $params = [
                'access_token' => $connection->access_token,
                'level' => 'campaign',
                'time_increment' => 1, // daily rows
                'time_range' => json_encode([
                    'since' => $startDate->format('Y-m-d'),
                    'until' => $endDate->format('Y-m-d'),
                ]),
                'fields' => 'campaign_id,date_start,impressions,reach,clicks,spend,ctr,cpc,cpm,actions,action_values',
                'limit' => 200,
            ];

            $campaignIdMap = FacebookAdsCampaign::where('ads_account_id', $account->id)
                ->pluck('id', 'fb_campaign_id');

            while ($next) {
                $response = $next === $url
                    ? Http::timeout(20)->get($url, $params)
                    : Http::timeout(20)->get($next);

                if (!$response->successful()) {
                    Log::warning('FacebookMarketingApi: insights fetch failed', [
                        'account_id' => $accountId,
                        'http_status' => $response->status(),
                        'error' => $response->json('error.message'),
                    ]);
                    break;
                }

                $body = $response->json() ?? [];
                foreach ($body['data'] ?? [] as $row) {
                    $fbCampaignId = (string) ($row['campaign_id'] ?? '');
                    $date = $row['date_start'] ?? null;
                    if (!$fbCampaignId || !$date) {
                        continue;
                    }

                    [$conv, $convValue] = $this->extractPurchaseAction(
                        $row['actions'] ?? [],
                        $row['action_values'] ?? []
                    );

                    FacebookAdsInsight::updateOrCreate(
                        [
                            'ads_account_id' => $account->id,
                            'fb_campaign_id' => $fbCampaignId,
                            'date' => $date,
                        ],
                        [
                            'campaign_id' => $campaignIdMap[$fbCampaignId] ?? null,
                            'impressions' => (int) ($row['impressions'] ?? 0),
                            'reach' => (int) ($row['reach'] ?? 0),
                            'clicks' => (int) ($row['clicks'] ?? 0),
                            'spend' => (float) ($row['spend'] ?? 0),
                            'ctr' => (float) ($row['ctr'] ?? 0),
                            'cpc' => (float) ($row['cpc'] ?? 0),
                            'cpm' => (float) ($row['cpm'] ?? 0),
                            'conversions' => $conv,
                            'conversion_value' => $convValue,
                            'actions' => $row['actions'] ?? null,
                            'action_values' => $row['action_values'] ?? null,
                            'currency' => $account->currency,
                        ]
                    );
                    $synced++;
                }

                $next = $body['paging']['next'] ?? null;
                $params = [];
            }

            return $synced;
        } catch (\Throwable $e) {
            Log::warning('FacebookMarketingApi: insights sync exception', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Pull purchase action count + value out of Meta's actions array.
     * Meta returns many action types (post_engagement, link_click, etc.).
     * For ROAS we want the offsite_conversion.fb_pixel_purchase / purchase
     * variant. Falls back to whatever 'purchase' label is present.
     *
     * @return array{0:int, 1:float}
     */
    protected function extractPurchaseAction(array $actions, array $actionValues): array
    {
        $purchaseTypes = [
            'offsite_conversion.fb_pixel_purchase',
            'purchase',
            'omni_purchase',
        ];

        $count = 0;
        foreach ($actions as $a) {
            if (in_array($a['action_type'] ?? '', $purchaseTypes, true)) {
                $count = max($count, (int) ($a['value'] ?? 0));
            }
        }

        $value = 0.0;
        foreach ($actionValues as $a) {
            if (in_array($a['action_type'] ?? '', $purchaseTypes, true)) {
                $value = max($value, (float) ($a['value'] ?? 0));
            }
        }

        return [$count, $value];
    }

    protected function normalizeAccountId(string $raw): string
    {
        return ltrim($raw, 'act_');
    }

    protected function markAccountFailed(FacebookCapiConnection $connection, string $accountId, string $error): ?FacebookAdsAccount
    {
        return FacebookAdsAccount::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'fb_account_id' => $accountId,
            ],
            [
                'marketplace_organizer_id' => $connection->marketplace_organizer_id,
                'marketplace_client_id' => $connection->marketplace_client_id,
                'tenant_id' => $connection->tenant_id,
                'last_synced_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_error' => mb_substr($error, 0, 1000),
            ]
        );
    }
}
