<?php

namespace App\Console\Commands;

use App\Services\Health\HealthCheckService;
use Illuminate\Console\Command;

class CheckSystemHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check
                            {--verbose : Show detailed health information}
                            {--service= : Check specific service only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check system health status';

    /**
     * Execute the console command.
     */
    public function handle(HealthCheckService $healthService): int
    {
        $service = $this->option('service');

        if ($service) {
            // Check specific service
            $method = 'check' . ucfirst($service);

            if (!method_exists($healthService, $method)) {
                $this->error("Unknown service: {$service}");
                return Command::FAILURE;
            }

            $result = $healthService->$method();

            $this->displayServiceHealth($service, $result);

            return $result['status'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
        }

        // Check all services
        $health = $healthService->checkAll();

        $this->displayOverallHealth($health);

        if ($this->option('verbose')) {
            $this->newLine();
            $this->info('Service Details:');
            $this->newLine();

            foreach ($health['checks'] as $serviceName => $result) {
                $this->displayServiceHealth($serviceName, $result);
            }
        }

        return $health['status'] === 'unhealthy' ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Display overall health status
     *
     * @param array $health
     * @return void
     */
    protected function displayOverallHealth(array $health): void
    {
        $status = $health['status'];

        $emoji = match($status) {
            'healthy' => '✓',
            'degraded' => '⚠',
            'unhealthy' => '✗',
            default => '?',
        };

        $method = match($status) {
            'healthy' => 'info',
            'degraded' => 'warn',
            'unhealthy' => 'error',
            default => 'line',
        };

        $this->$method("{$emoji} Overall Status: " . strtoupper($status));
        $this->line("Timestamp: {$health['timestamp']}");
    }

    /**
     * Display service health status
     *
     * @param string $name
     * @param array $result
     * @return void
     */
    protected function displayServiceHealth(string $name, array $result): void
    {
        $status = $result['status'] ?? 'unknown';

        $emoji = match($status) {
            'healthy' => '✓',
            'degraded' => '⚠',
            'unhealthy' => '✗',
            default => '?',
        };

        $method = match($status) {
            'healthy' => 'info',
            'degraded' => 'warn',
            'unhealthy' => 'error',
            default => 'line',
        };

        $message = "{$emoji} " . ucfirst($name) . ": " . strtoupper($status);

        if (isset($result['message'])) {
            $message .= " - {$result['message']}";
        }

        $this->$method($message);

        if ($this->option('verbose') && isset($result['details'])) {
            foreach ($result['details'] as $key => $value) {
                $this->line("  {$key}: " . (is_array($value) ? json_encode($value) : $value));
            }
        }
    }
}
