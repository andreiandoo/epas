<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Microservice;

class AccountingConnectorsMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'accounting-connectors'],
            [
                'name' => 'Conectori Contabilitate',
                'description' => 'Integrations with accounting systems (SmartBill, FGO, Exact, Xero, QuickBooks) for external invoice issuance with provider-agnostic adapter pattern.',
                'category' => 'accounting',
                'price' => 1.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'status' => 'active',
                'features' => [
                    'Provider-agnostic adapter pattern',
                    'Supported: SmartBill, FGO, Exact, Xero, QuickBooks',
                    'External invoice issuance (issue_extern mode)',
                    'Mapping wizard (products, taxes, accounts, series)',
                    'Customer sync (ensureCustomer)',
                    'Product sync (ensureProducts)',
                    'Invoice creation via provider API',
                    'PDF retrieval and delivery',
                    'Credit note generation',
                    'Job queue with retry logic',
                    'Prevent duplicate issuance',
                    'OAuth2 and API key authentication',
                    'Encrypted credential storage',
                    'Connection testing',
                    'eFactura integration (provider-managed or platform)',
                    'Error tracking and DLQ',
                ],
                    'Laravel 12.x',
                    'Adapter pattern',
                    'Job queue',
                    'Encrypted credentials',
                ],
                    'PHP 8.2+',
                    'Laravel 12+',
                    'Accounting provider account',
                ],
                    'POST /api/acc/connect',
                    'POST /api/acc/map',
                    'POST /api/acc/issue',
                    'POST /api/acc/credit',
                ],
                'icon' => 'heroicon-o-document-text',
                'metadata' => [
                    'version' => '1.0.0',
                    'author' => 'EPAS Development Team',
                ],
            ]
        );

        $this->command->info('✓ Accounting Connectors microservice seeded (1 EUR/month)');
    }
}
