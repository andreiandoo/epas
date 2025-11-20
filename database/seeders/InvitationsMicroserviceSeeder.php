<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Microservice;

class InvitationsMicroserviceSeeder extends Seeder
{
    /**
     * Seed the Invitations microservice
     *
     * Zero-value tickets: generation, distribution, and tracking
     *
     * Price: 1 EUR per month (recurring)
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            [
                'slug' => 'invitations',
            ],
            [
                'name' => 'Invitations (Zero-Value Tickets)',
                'description' => 'Complete invitation management system for zero-value tickets with batch generation, CSV import, PDF rendering, email distribution, download tracking, and check-in integration. Perfect for VIP guests, press passes, and complimentary tickets.',
                'category' => 'distribution',
                'price' => 1.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'status' => 'active',
                'features' => [
                    // Batch Management
                    'Create invitation batches for events',
                    'Generate N invitations per batch',
                    'Batch status tracking: draft → rendering → ready → sending → completed',
                    'Cancel batches with automatic ticket voiding',

                    // Recipient Management
                    'CSV import with field mapping (name, email, phone, company, seat)',
                    'Drag & drop CSV upload with validation',
                    'Recipient data: name, email, phone, company, title, notes',
                    'Optional seat assignment (auto/manual/none modes)',

                    // Ticketing Integration
                    'Zero-value ticket generation with unique QR codes',
                    'Anti-replay QR protection with checksums',
                    'Ticket format: INV:{code}:{ticket_ref}:{checksum}',
                    'Compatible with existing check-in scanners',

                    // Template & Rendering
                    'Uses Ticket Templates microservice for PDF/PNG generation',
                    'Configurable watermarks (e.g., "INVITATION")',
                    'Bulk rendering for entire batches',
                    'High-resolution preview (@2x)',

                    // Distribution
                    'Individual PDF downloads with signed URLs',
                    'Bulk ZIP download for entire batches',
                    'Email delivery with queueing and chunking',
                    'Configurable chunk size (default: 100/batch)',
                    'Retry logic with exponential backoff',

                    // Email Features
                    'Per-tenant email templating',
                    'Personalization with recipient data',
                    'Delivery status tracking: pending, sent, delivered, bounced, failed',
                    'Send attempt tracking with error logging',
                    'Resend failed emails',

                    // Tracking & Analytics
                    'Status flow: created → rendered → emailed → downloaded → opened → checked_in',
                    'Timestamp tracking for all stages',
                    'Pixel tracking for email opens (GDPR-compliant, opt-in)',
                    'Download tracking with IP and user agent',
                    'Check-in tracking with gate and timestamp',

                    // Security
                    'Signed download URLs with expiration (30 days default)',
                    'Rate limiting on downloads',
                    'Anti-enumeration protection',
                    'PII minimization in emails',
                    'Consent-aware pixel tracking',

                    // Management
                    'Void/invalidate invitations',
                    'Voiding prevents check-in access',
                    'Re-generate invitations if unused',
                    'Audit log for all actions',

                    // Reporting & Export
                    'CSV export with comprehensive data',
                    'Export columns: code, recipient, email status, download status, check-in status, gate, seat',
                    'Batch statistics dashboard',
                    'Real-time progress tracking',

                    // API Endpoints
                    'Complete REST API (11 endpoints)',
                    'Webhook support for pixel tracking',
                    'Batch operations support',
                    'Individual invitation management',

                    // Status Management
                    'Invitation statuses: created, rendered, emailed, downloaded, opened, checked_in, void',
                    'Batch statuses: draft, rendering, ready, sending, completed, cancelled',
                    'Delivery statuses: pending, sent, delivered, bounced, failed, complaint',
                ],
                    'Laravel 12.x backend',
                    'Queue system for email delivery',
                    'Signed URLs for secure downloads',
                    'CSV processing with validation',
                    'Ticket Templates integration',
                    'Anti-replay QR codes with HMAC',
                ],
                    'PHP 8.2+',
                    'Laravel 12+',
                    'Ticket Templates microservice (for PDF generation)',
                    'Queue worker configured (for email delivery)',
                    'Mail driver configured (SMTP/SES/etc.)',
                    'Storage disk for invitations and exports',
                    'ZIP extension enabled',
                ],
                    '1. Run migrations: php artisan migrate',
                    '2. Configure mail driver in .env',
                    '3. Configure queue driver (redis/database recommended)',
                    '4. Start queue worker: php artisan queue:work',
                    '5. Ensure storage/app/public is linked: php artisan storage:link',
                    '6. (Optional) Configure email templates per tenant',
                    '7. Access API: /api/inv/*',
                ],
                    'POST /api/inv/batch - Create invitation batch',
                    'POST /api/inv/batch/import - Import recipients from CSV',
                    'POST /api/inv/batch/render - Render PDFs for batch',
                    'GET /api/inv/batch/{id}/export - Export batch as CSV',
                    'GET /api/inv/batch/{id}/download-zip - Download batch as ZIP',
                    'POST /api/inv/send - Send emails (batch or individual)',
                    'GET /api/inv/{id} - Get invitation details',
                    'POST /api/inv/{id}/void - Void invitation',
                    'POST /api/inv/{id}/resend - Resend invitation email',
                    'GET /api/inv/{id}/download - Download PDF (signed URL)',
                    'POST /api/inv/webhook/open - Track email opens (pixel)',
                ],
                'documentation_url' => '/docs/microservices/invitations',
                'icon' => 'heroicon-o-envelope',
                'metadata' => [
                    'version' => '1.0.0',
                    'author' => 'EPAS Development Team',
                    'created_at' => now()->toDateTimeString(),
                    'last_updated' => now()->toDateTimeString(),
                    'compatibility' => [
                        'min_php_version' => '8.2',
                        'min_laravel_version' => '12.0',
                        'requires_queue' => true,
                        'requires_mail' => true,
                        'depends_on' => ['ticket-customizer'],
                    ],
                    'support' => [
                        'email' => 'support@epas.ro',
                        'docs' => '/docs/microservices/invitations',
                        'issues' => 'https://github.com/epas/issues',
                    ],
                    'use_cases' => [
                        'VIP guest invitations',
                        'Press passes and media credentials',
                        'Complimentary tickets for partners',
                        'Staff and crew access',
                        'Sponsor guest lists',
                        'Early bird access for special guests',
                    ],
                ],
            ]
        );

        $this->command->info('✓ Invitations microservice seeded (1 EUR/month recurring)');
    }
}
