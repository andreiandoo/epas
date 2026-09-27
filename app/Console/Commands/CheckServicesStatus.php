<?php

namespace App\Console\Commands;

use App\Models\Microservice;
use App\Models\ServiceStatusLog;
use App\Services\Health\HealthCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckServicesStatus extends Command
{
    protected $signature = 'services:check-status';

    protected $description = 'Check and log status of all services for uptime tracking';

    public function handle(HealthCheckService $healthCheckService): int
    {
        $health = $healthCheckService->checkAll();

        // Log core status
        ServiceStatusLog::create([
            'service_name' => 'core',
            'service_type' => 'core',
            'is_online' => ($health['checks']['app']['status'] ?? '') === 'healthy',
            'response_time_ms' => $health['checks']['app']['response_time_ms'] ?? 0,
            'version' => $health['checks']['app']['laravel_version'] ?? app()->version(),
            'checked_at' => now(),
        ]);

        // Log database status
        ServiceStatusLog::create([
            'service_name' => 'database',
            'service_type' => 'infrastructure',
            'is_online' => ($health['checks']['database']['status'] ?? '') === 'healthy',
            'response_time_ms' => $health['checks']['database']['response_time_ms'] ?? 0,
            'version' => $health['checks']['database']['driver'] ?? 'unknown',
            'checked_at' => now(),
        ]);

        // Log API status
        ServiceStatusLog::create([
            'service_name' => 'api',
            'service_type' => 'api',
            'is_online' => ($health['status'] ?? '') === 'healthy',
            'response_time_ms' => 0,
            'version' => 'v1',
            'checked_at' => now(),
        ]);

        // Log microservices
        $microservices = Microservice::where('is_active', true)->get();
        foreach ($microservices as $ms) {
            ServiceStatusLog::create([
                'service_name' => $ms->slug,
                'service_type' => 'microservice',
                'is_online' => $ms->is_active,
                'response_time_ms' => 0,
                'version' => '1.0.0',
                'checked_at' => now(),
            ]);
        }

        // Background work (e-mails, ticket PDFs, e-Factura) and the cache: the public status pages show them.
        foreach (['queue', 'cache'] as $name) {
            $check = $health['checks'][$name] ?? [];
            ServiceStatusLog::create([
                'service_name' => $name,
                'service_type' => 'infrastructure',
                'is_online' => in_array($check['status'] ?? '', ['healthy', 'degraded'], true),
                'response_time_ms' => (int) ($check['response_time_ms'] ?? 0),
                'version' => (string) ($check['connection'] ?? $check['driver'] ?? ''),
                'checked_at' => now(),
            ]);
        }

        // The marketplace sites themselves, seen from outside: a GET of the home page (services.status_sites).
        foreach ((array) config('services.status_sites', []) as $host) {
            $host = strtolower(trim((string) $host));
            if ($host === '') {
                continue;
            }
            $started = microtime(true);
            try {
                $ok = Http::timeout(15)->withHeaders(['User-Agent' => 'TixelloStatus/1.0'])->get('https://' . $host . '/')->successful();
            } catch (\Throwable $e) {
                $ok = false;
            }
            ServiceStatusLog::create([
                'service_name' => 'site:' . $host,
                'service_type' => 'website',
                'is_online' => $ok,
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
                'version' => 'https',
                'checked_at' => now(),
            ]);
        }

        $this->info('Services status checked and logged successfully.');

        return Command::SUCCESS;
    }
}
