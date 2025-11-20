<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Microservice;

class TicketInsuranceMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'ticket-insurance'],
            [
                'name' => 'Ticket Insurance',
                'description' => 'Optional insurance add-on for ticket purchases with provider-agnostic integration, real-time quoting, and automated policy management.',
                'category' => 'payment-addons',
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => null,
                'pricing_model' => 'free', // Revenue from insurance commission
                'status' => 'active',
                'features' => [
                    // Configuration
                    'Hierarchical config: tenant → event → ticket_type',
                    'Pricing modes: fixed amount or percentage of ticket price',
                    'Min/max premium caps',
                    'Tax policy configuration',
                    'Scope: per-ticket or per-order',
                    'Eligibility rules (country, ticket type, event, price range)',

                    // Quote System
                    'Real-time premium calculation',
                    'Provider adapter integration',
                    'Multiple pricing strategies',
                    'Tax calculation (inclusive/exclusive)',

                    // Policy Management
                    'Idempotent policy issuance',
                    'Provider-agnostic adapter pattern',
                    'Policy document storage/URLs',
                    'Status tracking: pending, issued, voided, refunded, error',
                    'Comprehensive event logging',

                    // Refund & Void
                    'Configurable cancellation policies',
                    'Policy options: no_refund, proportional, full_if_unused',
                    'Provider sync for refunds/voids',
                    'Partial refund support',

                    // Provider Integration
                    'Adapter interface for multiple insurers',
                    'Mock adapter for testing',
                    'Quote, issue, void, refund, sync operations',
                    'Error handling and retry logic',

                    // Checkout Integration
                    'Optional add-on checkbox',
                    'Real-time premium display',
                    'Terms & consent UI',
                    'Per-line or per-order selection',

                    // Security & Compliance
                    'Clear separation from ticket price',
                    'Explicit consent required',
                    'PII minimization',
                    'Secure document storage',

                    // Reporting & Analytics
                    'Attach rate tracking',
                    'Premium GMV calculation',
                    'Void/refund rate monitoring',
                    'Provider error tracking',
                    'CSV export',

                    // API Endpoints
                    'GET /api/ti/quote - Get insurance quote',
                    'POST /api/ti/issue - Issue policy',
                    'POST /api/ti/sync - Sync with provider',
                    'GET /api/ti/policies - List policies',
                    'GET /api/ti/stats - Get statistics',
                    'POST /api/ti/{id}/void - Void policy',
                    'POST /api/ti/{id}/refund - Refund policy',
                ],
                    'Laravel 12.x',
                    'Provider adapter pattern',
                    'Event sourcing for audit trail',
                    'JSON schema validation',
                ],
                    'PHP 8.2+',
                    'Laravel 12+',
                    'Insurance provider account (or use mock adapter)',
                ],
                    '1. Run migrations: php artisan migrate',
                    '2. Configure insurance provider adapter',
                    '3. Create insurance config for tenant',
                    '4. Integrate checkout UI component',
                    '5. Set up webhook for payment_captured event',
                ],
                    'GET /api/ti/quote',
                    'POST /api/ti/issue',
                    'POST /api/ti/sync',
                    'GET /api/ti/policies',
                    'GET /api/ti/stats',
                    'POST /api/ti/{id}/void',
                    'POST /api/ti/{id}/refund',
                ],
                'documentation_url' => '/docs/microservices/ticket-insurance',
                'icon' => 'heroicon-o-shield-check',
                'metadata' => [
                    'version' => '1.0.0',
                    'author' => 'EPAS Development Team',
                ],
            ]
        );

        $this->command->info('✓ Ticket Insurance microservice seeded');
    }
}
