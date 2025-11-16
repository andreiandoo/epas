<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EFacturaMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert eFactura microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'efactura-ro'],
            [
                'name' => 'eFactura (RO)',
                'description' => 'Automatic submission of invoices to ANAF SPV (Romanian tax authority). Transforms internal invoices to UBL/CII XML format, signs, submits, and polls for acceptance/rejection status.',
                'price' => 3.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'Automatic invoice transformation to eFactura XML (UBL/CII)',
                    'Digital signature and packaging',
                    'Automatic submission to ANAF SPV',
                    'Status polling with exponential backoff',
                    'Idempotent submission (no duplicates)',
                    'Queue management with retry logic',
                    'Error tracking and diagnostics',
                    'Download ANAF receipts/confirmations',
                    'Provider-agnostic adapter pattern',
                    'Encrypted certificate storage',
                    'Comprehensive audit logs',
                    'Multi-tenant credential isolation',
                    'Fallback queue for SPV downtime',
                    'CSV export of errors',
                    'Status workflow: queued → submitted → accepted/rejected',
                    'Real-time statistics dashboard',
                ]),
                'category' => 'compliance',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'POST /api/efactura/submit',
                        'POST /api/efactura/retry',
                        'POST /api/efactura/poll',
                        'GET /api/efactura/status/{queueId}',
                        'GET /api/efactura/download/{queueId}',
                        'GET /api/efactura/stats/{tenantId}',
                        'GET /api/efactura/queue/{tenantId}',
                    ],
                    'adapter_interface' => 'AnafAdapterInterface',
                    'supported_formats' => ['UBL 2.1', 'CII'],
                    'signing_methods' => ['XMLDSig', 'PKCS#7'],
                    'max_retries' => 5,
                    'backoff_schedule' => '5min, 15min, 30min, 1h, 2h',
                    'storage_path' => 'efactura/{tenant_id}/{invoice_id}.xml',
                    'database_tables' => ['anaf_queue'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create sample ANAF queue entries for demonstration
        $tenantId = 'tenant_demo';

        DB::table('anaf_queue')->insert([
            [
                'tenant_id' => $tenantId,
                'invoice_id' => 1001,
                'payload_ref' => "efactura/{$tenantId}/1001.xml",
                'status' => 'accepted',
                'anaf_ids' => json_encode([
                    'remote_id' => 'ANAF-DEMO-ACCEPTED-001',
                    'download_id' => 'DWN-ACCEPTED-001',
                    'confirmation_number' => 'CONF-ABC12345',
                ]),
                'attempts' => 1,
                'max_attempts' => 5,
                'xml_hash' => hash('sha256', 'demo-xml-content-1001'),
                'submitted_at' => now()->subHours(2),
                'accepted_at' => now()->subHours(1),
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(1),
            ],
            [
                'tenant_id' => $tenantId,
                'invoice_id' => 1002,
                'payload_ref' => "efactura/{$tenantId}/1002.xml",
                'status' => 'rejected',
                'error_message' => 'Invalid VAT number format; Line item missing unit code',
                'anaf_ids' => json_encode([
                    'remote_id' => 'ANAF-DEMO-REJECTED-002',
                ]),
                'response_data' => json_encode([
                    'errors' => [
                        'Invalid VAT number format',
                        'Line item missing unit code',
                    ],
                ]),
                'attempts' => 1,
                'max_attempts' => 5,
                'xml_hash' => hash('sha256', 'demo-xml-content-1002'),
                'submitted_at' => now()->subHours(2),
                'rejected_at' => now()->subHours(1),
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(1),
            ],
            [
                'tenant_id' => $tenantId,
                'invoice_id' => 1003,
                'payload_ref' => "efactura/{$tenantId}/1003.xml",
                'status' => 'submitted',
                'anaf_ids' => json_encode([
                    'remote_id' => 'ANAF-DEMO-PENDING-003',
                    'download_id' => 'DWN-PENDING-003',
                ]),
                'attempts' => 1,
                'max_attempts' => 5,
                'xml_hash' => hash('sha256', 'demo-xml-content-1003'),
                'submitted_at' => now()->subMinutes(30),
                'created_at' => now()->subHour(),
                'updated_at' => now()->subMinutes(30),
            ],
            [
                'tenant_id' => $tenantId,
                'invoice_id' => 1004,
                'payload_ref' => "efactura/{$tenantId}/1004.xml",
                'status' => 'queued',
                'attempts' => 0,
                'max_attempts' => 5,
                'xml_hash' => hash('sha256', 'demo-xml-content-1004'),
                'next_retry_at' => now(),
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ],
            [
                'tenant_id' => $tenantId,
                'invoice_id' => 1005,
                'payload_ref' => "efactura/{$tenantId}/1005.xml",
                'status' => 'error',
                'error_message' => 'Connection timeout to ANAF SPV',
                'attempts' => 2,
                'max_attempts' => 5,
                'xml_hash' => hash('sha256', 'demo-xml-content-1005'),
                'last_attempt_at' => now()->subMinutes(20),
                'next_retry_at' => now()->addMinutes(10),
                'created_at' => now()->subMinutes(45),
                'updated_at' => now()->subMinutes(20),
            ],
        ]);

        $this->command->info('✓ eFactura microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - 5 sample queue entries created (accepted, rejected, submitted, queued, error)');
    }
}
