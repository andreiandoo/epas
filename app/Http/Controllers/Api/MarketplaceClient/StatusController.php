<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\ServiceStatusLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The public status page of a marketplace site (bilete.online/status): the parts a visitor or an operator depends
 * on, each with its state now and its availability day by day over the last 90 days. Everything comes from
 * service_status_logs, written every 5 minutes by services:check-status (the site itself is fetched from outside,
 * see services.status_sites); a day without checks is reported as such, never as up.
 */
class StatusController extends BaseController
{
    private const DAYS = 90;

    /** GET marketplace-client/status */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $host = self::host((string) ($client->domain ?? ''));

        $data = Cache::remember('mp-status:' . $client->id, 300, function () use ($host) {
            $components = [];
            if ($host !== '') {
                $components[] = ['site:' . $host, 'Site-ul ' . $host, 'Paginile publice, căutarea și coșul.'];
            }
            $components[] = ['core', 'Platforma de bilete', 'Comenzile, plățile, biletele și contul tău.'];
            $components[] = ['api', 'API', 'Legătura dintre site, panoul operatorilor și platformă.'];
            $components[] = ['database', 'Baza de date', 'Unde sunt păstrate comenzile și biletele.'];
            $components[] = ['queue', 'Procesare în fundal', 'Trimiterea biletelor pe e-mail, PDF-urile și documentele fiscale.'];
            $components[] = ['cache', 'Cache și sesiuni', 'Păstrarea coșului și a sesiunii de autentificare.'];

            $from = now()->startOfDay()->subDays(self::DAYS - 1);
            $out = [];
            foreach ($components as [$key, $name, $about]) {
                $rows = ServiceStatusLog::query()
                    ->where('service_name', $key)
                    ->where('checked_at', '>=', $from)
                    ->selectRaw('DATE(checked_at) as d, COUNT(*) as n, SUM(CASE WHEN is_online THEN 1 ELSE 0 END) as ok')
                    ->groupByRaw('DATE(checked_at)')
                    ->get()
                    ->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

                $days = [];
                $n = 0;
                $ok = 0;
                for ($i = 0; $i < self::DAYS; $i++) {
                    $date = $from->copy()->addDays($i)->toDateString();
                    $r = $rows->get($date);
                    if ($r && (int) $r->n > 0) {
                        $n += (int) $r->n;
                        $ok += (int) $r->ok;
                        $days[] = [$date, round((int) $r->ok / (int) $r->n * 100, 2), (int) $r->n];
                    } else {
                        $days[] = [$date, null, 0];
                    }
                }
                $last = ServiceStatusLog::where('service_name', $key)->latest('checked_at')->first(['is_online', 'checked_at', 'response_time_ms']);
                $fresh = $last && $last->checked_at && $last->checked_at->gt(now()->subMinutes(20));

                $out[] = [
                    'key'       => $key,
                    'name'      => $name,
                    'about'     => $about,
                    'status'    => !$fresh ? 'unknown' : ($last->is_online ? 'up' : 'down'),
                    'checked_at' => $last?->checked_at?->toIso8601String(),
                    'response_ms' => $fresh ? (int) $last->response_time_ms : null,
                    'uptime'    => $n > 0 ? round($ok / $n * 100, 2) : null,
                    'days'      => $days,
                ];
            }

            return ['components' => $out, 'days' => self::DAYS, 'generated_at' => now()->toIso8601String()];
        });

        return $this->success($data);
    }

    /** "https://www.bilete.online/" → "bilete.online". */
    private static function host(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return '';
        }
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://' . $domain, PHP_URL_HOST) ?: '';
        return preg_replace('/^www\./', '', $host);
    }
}
