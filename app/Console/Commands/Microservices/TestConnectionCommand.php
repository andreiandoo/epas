<?php

namespace App\Console\Commands\Microservices;

use Illuminate\Console\Command;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\EFactura\EFacturaService;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;

class TestConnectionCommand extends Command
{
    protected $signature = 'microservices:test-connection {service} {--tenant=}';

    protected $description = 'Test connection to a microservice adapter';

    public function handle(): int
    {
        $service = $this->argument('service');
        $tenantId = $this->option('tenant');

        $this->info("Testing connection to {$service} microservice...");

        try {
            $result = match ($service) {
                'whatsapp' => $this->testWhatsApp($tenantId),
                'efactura' => $this->testEFactura($tenantId),
                'accounting' => $this->testAccounting($tenantId),
                default => ['success' => false, 'message' => 'Unknown service'],
            };

            if ($result['success']) {
                $this->info("✓ Connection successful!");
                $this->line($result['message'] ?? '');
                return self::SUCCESS;
            } else {
                $this->error("✗ Connection failed!");
                $this->error($result['message'] ?? 'Unknown error');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("✗ Connection test failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function testWhatsApp(?string $tenantId): array
    {
        $service = app(WhatsAppService::class);

        if ($tenantId) {
            $credentials = $this->getCredentials($tenantId, 'whatsapp');
            $adapter = $credentials['adapter'] ?? 'mock';
            $service->setAdapter($adapter, $credentials);
        } else {
            $service->setAdapter('mock', []);
        }

        return $service->testConnection();
    }

    protected function testEFactura(?string $tenantId): array
    {
        $service = app(EFacturaService::class);

        if ($tenantId) {
            $credentials = $this->getCredentials($tenantId, 'efactura');
            $adapter = $credentials['adapter'] ?? 'mock';
            $service->setAdapter($adapter, $credentials);
        } else {
            $service->setAdapter('mock', []);
        }

        return $service->testConnection();
    }

    protected function testAccounting(?string $tenantId): array
    {
        $service = app(AccountingService::class);

        if ($tenantId) {
            $credentials = $this->getCredentials($tenantId, 'accounting');
            $adapter = $credentials['adapter'] ?? 'mock';
            $service->setAdapter($adapter, $credentials);
        } else {
            $service->setAdapter('mock', []);
        }

        return $service->testConnection();
    }

    protected function getCredentials(string $tenantId, string $service): array
    {
        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', "{$service}.credentials")
            ->first();

        if (!$config) {
            throw new \Exception("No credentials found for {$service}");
        }

        $value = $config->is_encrypted
            ? decrypt($config->value)
            : $config->value;

        return json_decode($value, true);
    }
}
