<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Microservice;

class TicketCustomizerMicroserviceSeeder extends Seeder
{
    /**
     * Seed the Ticket Customizer Component microservice
     *
     * This microservice provides a WYSIWYG editor for designing custom ticket templates
     * with drag-and-drop, real-time preview, and variable placeholders.
     *
     * Price: 30 EUR one-time payment
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            [
                'slug' => 'ticket-customizer',
            ],
            [
                'name' => 'Ticket Customizer Component',
                'description' => 'WYSIWYG editor for designing custom ticket templates with drag-and-drop, real-time preview, and variable placeholders. Includes support for text, images, QR codes, barcodes, and shapes with print-ready output.',
                'category' => 'design',
                'price' => 30.00,
                'currency' => 'EUR',
                'billing_cycle' => null,
                'pricing_model' => 'one_time',
                'is_active' => true,
                'features' => [
                    // Core Features
                    'WYSIWYG visual editor with drag-and-drop',
                    'Real mm/DPI measurements for print accuracy',
                    'Multiple layer types: text, images, QR codes, barcodes, shapes',
                    'Layer management: z-index, lock/unlock, visibility',
                    'Print guides: bleed and safe area visualization',

                    // Variable System
                    'Variable placeholders: {{event.name}}, {{ticket.section}}, etc.',
                    'Comprehensive variable categories: event, venue, date, ticket, buyer, order, codes, organizer',
                    'Real-time variable preview with sample data',

                    // Canvas Features
                    'Zoom controls (25%-800%)',
                    'Ruler display in millimeters',
                    'Snap to grid functionality',
                    'Frame-based positioning (x, y, w, h)',
                    'Rotation and opacity controls',

                    // Layer Types
                    'Text layers: fonts, sizes, colors, alignment, weight',
                    'Image layers: upload and position logos/graphics',
                    'QR code layers: dynamic QR generation with error correction',
                    'Barcode layers: Code128, EAN-13, PDF417 support',
                    'Shape layers: rectangles, circles, lines with fill/stroke',

                    // Export & Preview
                    'SVG preview generation',
                    'Template JSON export',
                    'High-resolution preview (@2x)',
                    'Print-ready file preparation',

                    // Template Management
                    'Save and load templates',
                    'Template versioning',
                    'Set default templates per tenant',
                    'Template status workflow: draft, active, archived',
                    'Soft delete with restore capability',

                    // Preset Dimensions
                    'Standard Ticket (80×200mm)',
                    'Landscape Ticket (200×80mm)',
                    'A6 Portrait/Landscape (105×148mm / 148×105mm)',
                    'A4 Portrait/Landscape (210×297mm / 297×210mm)',

                    // API Features
                    'Complete REST API for integration',
                    'Real-time validation endpoint',
                    'Preview generation API',
                    'Variable listing with sample data',
                    'Template CRUD operations',

                    // Admin Features
                    'Filament admin panel integration',
                    'Visual template manager',
                    'Direct JSON editing (advanced)',
                    'Bulk operations support',
                    'Template duplication and versioning',
                ],
                'tech_stack' => [
                    'Laravel 12.x backend',
                    'React 18+ with TypeScript frontend',
                    'RESTful API architecture',
                    'SVG-based rendering',
                    'Filament 4.x admin interface',
                    'JSON schema validation',
                ],
                'requirements' => [
                    'PHP 8.2+',
                    'Laravel 12+',
                    'React 18+ (for WYSIWYG editor)',
                    'Node.js 18+ and npm (for frontend build)',
                    'GD or Imagick extension (for image handling)',
                    'Storage disk for templates and previews',
                ],
                'setup_instructions' => [
                    '1. Run migration: php artisan migrate',
                    '2. Install frontend dependencies: npm install react react-dom @types/react @types/react-dom axios',
                    '3. Configure Vite with React plugin',
                    '4. Build assets: npm run build',
                    '5. Ensure storage/app/public is linked: php artisan storage:link',
                    '6. Access admin panel: /admin/ticket-templates',
                    '7. Access visual editor: /ticket-customizer/{template-id}',
                ],
                'api_endpoints' => [
                    'GET /api/tickets/templates/variables?tenant={id}',
                    'POST /api/tickets/templates/validate',
                    'POST /api/tickets/templates/preview',
                    'GET /api/tickets/templates/presets',
                    'GET /api/tickets/templates?tenant={id}&status={status}',
                    'POST /api/tickets/templates',
                    'GET /api/tickets/templates/{id}',
                    'PUT /api/tickets/templates/{id}',
                    'DELETE /api/tickets/templates/{id}',
                    'POST /api/tickets/templates/{id}/set-default',
                    'POST /api/tickets/templates/{id}/create-version',
                ],
                'documentation_url' => '/docs/microservices/ticket-customizer',
                'demo_url' => null,
                'icon' => 'heroicon-o-ticket',
                'color' => '#8B5CF6', // Purple
                'metadata' => [
                    'version' => '1.0.0',
                    'author' => 'EPAS Development Team',
                    'created_at' => now()->toDateTimeString(),
                    'last_updated' => now()->toDateTimeString(),
                    'compatibility' => [
                        'min_php_version' => '8.2',
                        'min_laravel_version' => '12.0',
                        'requires_node' => true,
                        'requires_react' => true,
                    ],
                    'support' => [
                        'email' => 'support@epas.ro',
                        'docs' => '/docs/microservices/ticket-customizer',
                        'issues' => 'https://github.com/epas/issues',
                    ],
                ],
            ]
        );

        $this->command->info('✓ Ticket Customizer Component microservice seeded (30 EUR one-time)');
    }
}
