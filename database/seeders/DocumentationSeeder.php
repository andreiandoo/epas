<?php

namespace Database\Seeders;

use App\Models\Doc;
use App\Models\DocCategory;
use Illuminate\Database\Seeder;

class DocumentationSeeder extends Seeder
{
    public function run(): void
    {
        // Create Categories
        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Installation, configuration, and initial setup guides',
                'icon' => 'heroicon-o-rocket-launch',
                'color' => '#10B981',
                'order' => 1,
                'is_public' => true,
            ],
            [
                'name' => 'Components',
                'slug' => 'components',
                'description' => 'UI components and reusable elements',
                'icon' => 'heroicon-o-cube',
                'color' => '#6366F1',
                'order' => 2,
                'is_public' => true,
            ],
            [
                'name' => 'Modules',
                'slug' => 'modules',
                'description' => 'Core system modules and features',
                'icon' => 'heroicon-o-squares-2x2',
                'color' => '#F59E0B',
                'order' => 3,
                'is_public' => true,
            ],
            [
                'name' => 'Microservices',
                'slug' => 'microservices',
                'description' => 'Microservices architecture and integrations',
                'icon' => 'heroicon-o-server-stack',
                'color' => '#EF4444',
                'order' => 4,
                'is_public' => true,
            ],
            [
                'name' => 'API Reference',
                'slug' => 'api-reference',
                'description' => 'REST API endpoints and authentication',
                'icon' => 'heroicon-o-code-bracket',
                'color' => '#8B5CF6',
                'order' => 5,
                'is_public' => true,
            ],
            [
                'name' => 'Integrations',
                'slug' => 'integrations',
                'description' => 'Third-party integrations and connectors',
                'icon' => 'heroicon-o-link',
                'color' => '#EC4899',
                'order' => 6,
                'is_public' => true,
            ],
        ];

        $categoryModels = [];
        foreach ($categories as $category) {
            $categoryModels[$category['slug']] = DocCategory::create($category);
        }

        // Getting Started Documentation
        $this->seedGettingStarted($categoryModels['getting-started']);

        // Components Documentation
        $this->seedComponents($categoryModels['components']);

        // Modules Documentation
        $this->seedModules($categoryModels['modules']);

        // Microservices Documentation
        $this->seedMicroservices($categoryModels['microservices']);

        // API Documentation
        $this->seedApiReference($categoryModels['api-reference']);

        // Integrations Documentation
        $this->seedIntegrations($categoryModels['integrations']);
    }

    private function seedGettingStarted(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Installation Guide',
                'slug' => 'installation-guide',
                'excerpt' => 'Complete guide to installing and setting up the EPAS platform',
                'content' => $this->getInstallationContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 1,
                'tags' => ['installation', 'setup', 'configuration'],
            ],
            [
                'title' => 'Configuration',
                'slug' => 'configuration',
                'excerpt' => 'Environment variables and application configuration',
                'content' => $this->getConfigurationContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['configuration', 'environment', 'settings'],
            ],
            [
                'title' => 'Quick Start',
                'slug' => 'quick-start',
                'excerpt' => 'Get up and running in 5 minutes',
                'content' => $this->getQuickStartContent(),
                'type' => 'tutorial',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 3,
                'tags' => ['quickstart', 'tutorial', 'beginner'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    private function seedComponents(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Event Resource',
                'slug' => 'event-resource',
                'excerpt' => 'Filament resource for managing events with full CRUD operations',
                'content' => $this->getEventResourceContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 1,
                'tags' => ['filament', 'events', 'resource'],
            ],
            [
                'title' => 'Artist Resource',
                'slug' => 'artist-resource',
                'excerpt' => 'Manage artists with social media integration and statistics',
                'content' => $this->getArtistResourceContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['filament', 'artists', 'resource'],
            ],
            [
                'title' => 'Venue Resource',
                'slug' => 'venue-resource',
                'excerpt' => 'Venue management with seating layouts and capacity',
                'content' => $this->getVenueResourceContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 3,
                'tags' => ['filament', 'venues', 'resource'],
            ],
            [
                'title' => 'Customer Resource',
                'slug' => 'customer-resource',
                'excerpt' => 'Customer management with order history and analytics',
                'content' => $this->getCustomerResourceContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 4,
                'tags' => ['filament', 'customers', 'crm'],
            ],
            [
                'title' => 'Order Resource',
                'slug' => 'order-resource',
                'excerpt' => 'Order processing and ticket management',
                'content' => $this->getOrderResourceContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 5,
                'tags' => ['filament', 'orders', 'tickets'],
            ],
            [
                'title' => 'Translatable Input',
                'slug' => 'translatable-input',
                'excerpt' => 'Multi-language input component for forms',
                'content' => $this->getTranslatableInputContent(),
                'type' => 'component',
                'status' => 'published',
                'is_public' => true,
                'order' => 6,
                'tags' => ['filament', 'i18n', 'forms'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    private function seedModules(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Seating Module',
                'slug' => 'seating-module',
                'excerpt' => 'Reserved seating with interactive seat selection and dynamic pricing',
                'content' => $this->getSeatingModuleContent(),
                'type' => 'module',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 1,
                'tags' => ['seating', 'venues', 'pricing'],
            ],
            [
                'title' => 'Billing Module',
                'slug' => 'billing-module',
                'excerpt' => 'Invoicing, payments, and revenue management',
                'content' => $this->getBillingModuleContent(),
                'type' => 'module',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['billing', 'invoices', 'payments'],
            ],
            [
                'title' => 'Email Templates',
                'slug' => 'email-templates',
                'excerpt' => 'Customizable email templates with variable substitution',
                'content' => $this->getEmailTemplatesContent(),
                'type' => 'module',
                'status' => 'published',
                'is_public' => true,
                'order' => 3,
                'tags' => ['email', 'templates', 'notifications'],
            ],
            [
                'title' => 'Promo Codes',
                'slug' => 'promo-codes',
                'excerpt' => 'Discount codes with usage limits and analytics',
                'content' => $this->getPromoCodesContent(),
                'type' => 'module',
                'status' => 'published',
                'is_public' => true,
                'order' => 4,
                'tags' => ['promo', 'discounts', 'marketing'],
            ],
            [
                'title' => 'Tenant Management',
                'slug' => 'tenant-management',
                'excerpt' => 'Multi-tenant architecture and tenant onboarding',
                'content' => $this->getTenantManagementContent(),
                'type' => 'module',
                'status' => 'published',
                'is_public' => true,
                'order' => 5,
                'tags' => ['tenants', 'multi-tenant', 'onboarding'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    private function seedMicroservices(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Microservices Overview',
                'slug' => 'microservices-overview',
                'excerpt' => 'Architecture and marketplace for extending platform functionality',
                'content' => $this->getMicroservicesOverviewContent(),
                'type' => 'microservice',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 1,
                'tags' => ['microservices', 'architecture', 'marketplace'],
            ],
            [
                'title' => 'Affiliate Tracking',
                'slug' => 'affiliate-tracking',
                'excerpt' => 'Track affiliate referrals and manage commissions',
                'content' => $this->getAffiliateTrackingContent(),
                'type' => 'microservice',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['affiliate', 'tracking', 'commissions'],
            ],
            [
                'title' => 'Mobile Wallet',
                'slug' => 'mobile-wallet',
                'excerpt' => 'Apple Wallet and Google Pay pass generation',
                'content' => $this->getMobileWalletContent(),
                'type' => 'microservice',
                'status' => 'published',
                'is_public' => true,
                'order' => 3,
                'tags' => ['wallet', 'apple', 'google', 'passes'],
            ],
            [
                'title' => 'CRM Automation',
                'slug' => 'crm-automation',
                'excerpt' => 'Customer segmentation and automated workflows',
                'content' => $this->getCRMAutomationContent(),
                'type' => 'microservice',
                'status' => 'published',
                'is_public' => true,
                'order' => 4,
                'tags' => ['crm', 'automation', 'workflows'],
            ],
            [
                'title' => 'Analytics Dashboard',
                'slug' => 'analytics-dashboard',
                'excerpt' => 'Custom dashboards and real-time analytics',
                'content' => $this->getAnalyticsDashboardContent(),
                'type' => 'microservice',
                'status' => 'published',
                'is_public' => true,
                'order' => 5,
                'tags' => ['analytics', 'dashboard', 'reporting'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    private function seedApiReference(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Authentication',
                'slug' => 'api-authentication',
                'excerpt' => 'API key authentication and tenant authorization',
                'content' => $this->getApiAuthenticationContent(),
                'type' => 'api',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 1,
                'tags' => ['api', 'authentication', 'security'],
            ],
            [
                'title' => 'Events API',
                'slug' => 'events-api',
                'excerpt' => 'REST endpoints for event management',
                'content' => $this->getEventsApiContent(),
                'type' => 'api',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['api', 'events', 'rest'],
            ],
            [
                'title' => 'Orders API',
                'slug' => 'orders-api',
                'excerpt' => 'REST endpoints for order processing',
                'content' => $this->getOrdersApiContent(),
                'type' => 'api',
                'status' => 'published',
                'is_public' => true,
                'order' => 3,
                'tags' => ['api', 'orders', 'checkout'],
            ],
            [
                'title' => 'Webhooks',
                'slug' => 'webhooks',
                'excerpt' => 'Event-driven webhooks for integrations',
                'content' => $this->getWebhooksContent(),
                'type' => 'api',
                'status' => 'published',
                'is_public' => true,
                'order' => 4,
                'tags' => ['api', 'webhooks', 'events'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    private function seedIntegrations(DocCategory $category): void
    {
        $docs = [
            [
                'title' => 'Stripe Integration',
                'slug' => 'stripe-integration',
                'excerpt' => 'Payment processing with Stripe Connect',
                'content' => $this->getStripeIntegrationContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'is_featured' => true,
                'order' => 1,
                'tags' => ['stripe', 'payments', 'connect'],
            ],
            [
                'title' => 'WhatsApp Integration',
                'slug' => 'whatsapp-integration',
                'excerpt' => 'WhatsApp Business API for notifications',
                'content' => $this->getWhatsAppIntegrationContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'order' => 2,
                'tags' => ['whatsapp', 'notifications', 'messaging'],
            ],
            [
                'title' => 'E-Factura Romania',
                'slug' => 'efactura-integration',
                'excerpt' => 'Romanian e-invoicing compliance',
                'content' => $this->getEFacturaIntegrationContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'order' => 3,
                'tags' => ['efactura', 'romania', 'invoicing'],
            ],
            [
                'title' => 'Tracking Pixels',
                'slug' => 'tracking-pixels',
                'excerpt' => 'Meta Pixel, Google Analytics, and TikTok pixel integration',
                'content' => $this->getTrackingPixelsContent(),
                'type' => 'guide',
                'status' => 'published',
                'is_public' => true,
                'order' => 4,
                'tags' => ['tracking', 'analytics', 'marketing'],
            ],
        ];

        foreach ($docs as $doc) {
            $category->docs()->create($doc);
        }
    }

    // Content methods - abbreviated for brevity but with substantial content

    private function getInstallationContent(): string
    {
        return <<<'HTML'
<h2>Prerequisites</h2>
<p>Before installing EPAS, ensure you have the following:</p>
<ul>
<li>PHP 8.2 or higher</li>
<li>Composer 2.x</li>
<li>Node.js 18+ and npm</li>
<li>MySQL 8.0 or PostgreSQL 14+</li>
<li>Redis (optional, for caching)</li>
</ul>

<h2>Installation Steps</h2>

<h3>1. Clone the Repository</h3>
<pre><code>git clone https://github.com/your-org/epas.git
cd epas</code></pre>

<h3>2. Install Dependencies</h3>
<pre><code>composer install
npm install</code></pre>

<h3>3. Environment Setup</h3>
<pre><code>cp .env.example .env
php artisan key:generate</code></pre>

<h3>4. Database Setup</h3>
<p>Configure your database credentials in <code>.env</code>, then run:</p>
<pre><code>php artisan migrate --seed</code></pre>

<h3>5. Build Assets</h3>
<pre><code>npm run build</code></pre>

<h3>6. Start the Server</h3>
<pre><code>php artisan serve</code></pre>

<h2>Production Deployment</h2>
<p>For production deployments, ensure you:</p>
<ul>
<li>Set <code>APP_ENV=production</code></li>
<li>Set <code>APP_DEBUG=false</code></li>
<li>Configure a proper cache driver (Redis recommended)</li>
<li>Set up a queue worker for background jobs</li>
<li>Configure proper mail driver</li>
</ul>
HTML;
    }

    private function getConfigurationContent(): string
    {
        return <<<'HTML'
<h2>Environment Variables</h2>
<p>EPAS uses environment variables for configuration. Key variables include:</p>

<h3>Application</h3>
<pre><code>APP_NAME=EPAS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com</code></pre>

<h3>Database</h3>
<pre><code>DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=epas
DB_USERNAME=root
DB_PASSWORD=</code></pre>

<h3>Mail</h3>
<pre><code>MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls</code></pre>

<h3>Stripe</h3>
<pre><code>STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx</code></pre>

<h2>Config Files</h2>
<p>Application configuration files are located in the <code>config/</code> directory:</p>
<ul>
<li><code>config/app.php</code> - Application settings</li>
<li><code>config/auth.php</code> - Authentication guards</li>
<li><code>config/seating.php</code> - Seating module settings</li>
<li><code>config/microservices.php</code> - Microservice configuration</li>
</ul>
HTML;
    }

    private function getQuickStartContent(): string
    {
        return <<<'HTML'
<h2>Quick Start Guide</h2>
<p>Get EPAS up and running in minutes with this quick start guide.</p>

<h3>Step 1: Create Your First Tenant</h3>
<p>After installation, log into the admin panel at <code>/admin</code> and create a new tenant:</p>
<ol>
<li>Navigate to Tenants → Create</li>
<li>Fill in tenant details (name, domain, contact info)</li>
<li>Configure payment processor</li>
<li>Save the tenant</li>
</ol>

<h3>Step 2: Create a Venue</h3>
<p>Venues are where events take place:</p>
<ol>
<li>Go to Venues → Create</li>
<li>Enter venue name, address, and capacity</li>
<li>Optionally create seating layouts</li>
</ol>

<h3>Step 3: Create an Event</h3>
<p>Now create your first event:</p>
<ol>
<li>Navigate to Events → Create</li>
<li>Select venue and dates</li>
<li>Configure ticket types and pricing</li>
<li>Publish the event</li>
</ol>

<h3>Step 4: Test the Checkout</h3>
<p>Visit your tenant's public site and test the ticket purchase flow.</p>
HTML;
    }

    private function getEventResourceContent(): string
    {
        return <<<'HTML'
<h2>Event Resource</h2>
<p>The Event Resource is the central component for managing events in EPAS.</p>

<h3>Features</h3>
<ul>
<li>Full CRUD operations for events</li>
<li>Multi-language support for event details</li>
<li>Ticket type management</li>
<li>Artist assignments</li>
<li>Venue and seating configuration</li>
<li>Publishing workflow</li>
</ul>

<h3>Location</h3>
<pre><code>app/Filament/Resources/Events/EventResource.php</code></pre>

<h3>Form Sections</h3>
<p>The event form includes the following sections:</p>
<ul>
<li><strong>Basic Info</strong> - Title, slug, description</li>
<li><strong>Schedule</strong> - Dates, times, timezone</li>
<li><strong>Venue</strong> - Location and seating</li>
<li><strong>Tickets</strong> - Types, pricing, availability</li>
<li><strong>Media</strong> - Images and promotional content</li>
<li><strong>Settings</strong> - Visibility, restrictions</li>
</ul>

<h3>Usage Example</h3>
<pre><code>use App\Models\Event;

$event = Event::create([
    'title' => 'Concert Name',
    'venue_id' => 1,
    'starts_at' => now()->addMonth(),
    'status' => 'published',
]);</code></pre>
HTML;
    }

    private function getArtistResourceContent(): string
    {
        return <<<'HTML'
<h2>Artist Resource</h2>
<p>Manage artists with integrated social media statistics.</p>

<h3>Features</h3>
<ul>
<li>Artist profiles with biography</li>
<li>Social media links</li>
<li>Spotify and YouTube integration</li>
<li>Event associations</li>
<li>Genre classification</li>
</ul>

<h3>Social Media Integration</h3>
<p>EPAS automatically fetches statistics from:</p>
<ul>
<li>Spotify - Monthly listeners, followers</li>
<li>YouTube - Subscribers, video counts</li>
<li>Instagram - Follower counts</li>
</ul>

<h3>Usage</h3>
<pre><code>use App\Models\Artist;

$artist = Artist::create([
    'name' => 'Artist Name',
    'slug' => 'artist-name',
    'spotify_id' => 'xxx',
]);</code></pre>
HTML;
    }

    private function getVenueResourceContent(): string
    {
        return <<<'HTML'
<h2>Venue Resource</h2>
<p>Complete venue management with seating layouts.</p>

<h3>Features</h3>
<ul>
<li>Venue profiles and details</li>
<li>Capacity management</li>
<li>Seating layout designer</li>
<li>Section and row configuration</li>
<li>Event history</li>
</ul>

<h3>Seating Layouts</h3>
<p>Create interactive seating charts with:</p>
<ul>
<li>Multiple sections</li>
<li>Row and seat numbering</li>
<li>Price tier assignments</li>
<li>Accessibility options</li>
</ul>
HTML;
    }

    private function getCustomerResourceContent(): string
    {
        return <<<'HTML'
<h2>Customer Resource</h2>
<p>CRM functionality for customer management.</p>

<h3>Features</h3>
<ul>
<li>Customer profiles</li>
<li>Order history</li>
<li>Purchase analytics</li>
<li>Segmentation</li>
<li>Communication preferences</li>
</ul>

<h3>Analytics Widgets</h3>
<ul>
<li>Orders by month</li>
<li>Purchase hour distribution</li>
<li>Genre preferences</li>
<li>Lifetime value</li>
</ul>
HTML;
    }

    private function getOrderResourceContent(): string
    {
        return <<<'HTML'
<h2>Order Resource</h2>
<p>Order processing and ticket management.</p>

<h3>Order Lifecycle</h3>
<ol>
<li><strong>Pending</strong> - Order created, awaiting payment</li>
<li><strong>Paid</strong> - Payment confirmed</li>
<li><strong>Fulfilled</strong> - Tickets delivered</li>
<li><strong>Cancelled</strong> - Order cancelled</li>
<li><strong>Refunded</strong> - Payment refunded</li>
</ol>

<h3>Ticket Generation</h3>
<p>Tickets are automatically generated with:</p>
<ul>
<li>Unique QR codes</li>
<li>PDF download</li>
<li>Mobile wallet passes</li>
<li>Email delivery</li>
</ul>
HTML;
    }

    private function getTranslatableInputContent(): string
    {
        return <<<'HTML'
<h2>Translatable Input</h2>
<p>Multi-language input component for Filament forms.</p>

<h3>Usage</h3>
<pre><code>use App\Filament\Forms\Components\TranslatableInput;

TranslatableInput::make('title')
    ->label('Event Title')
    ->required()
    ->locales(['en', 'ro', 'de'])</code></pre>

<h3>Features</h3>
<ul>
<li>Tab-based language switching</li>
<li>Automatic locale detection</li>
<li>Validation per locale</li>
<li>JSON storage format</li>
</ul>
HTML;
    }

    private function getSeatingModuleContent(): string
    {
        return <<<'HTML'
<h2>Seating Module</h2>
<p>Advanced reserved seating with interactive seat selection.</p>

<h3>Architecture</h3>
<ul>
<li><strong>SeatingLayout</strong> - Template layout for venues</li>
<li><strong>EventSeatingLayout</strong> - Event-specific instance</li>
<li><strong>SeatingSection</strong> - Grouped seating areas</li>
<li><strong>SeatingRow</strong> - Row definitions</li>
<li><strong>SeatingSeat</strong> - Individual seats</li>
</ul>

<h3>Dynamic Pricing</h3>
<p>Configure price rules based on:</p>
<ul>
<li>Section location</li>
<li>Row position</li>
<li>Time until event</li>
<li>Demand (sold percentage)</li>
</ul>

<h3>Seat Holds</h3>
<p>Temporary holds during checkout:</p>
<pre><code>use App\Services\Seating\SeatHoldService;

$holdService->createHold($seatIds, $sessionId, 15); // 15 min hold</code></pre>
HTML;
    }

    private function getBillingModuleContent(): string
    {
        return <<<'HTML'
<h2>Billing Module</h2>
<p>Complete invoicing and payment management.</p>

<h3>Invoice States</h3>
<ul>
<li><strong>Draft</strong> - Not yet finalized</li>
<li><strong>Issued</strong> - Sent to customer</li>
<li><strong>Paid</strong> - Payment received</li>
<li><strong>Overdue</strong> - Past due date</li>
<li><strong>Cancelled</strong> - Voided</li>
</ul>

<h3>Features</h3>
<ul>
<li>Automatic invoice generation</li>
<li>PDF export</li>
<li>Payment tracking</li>
<li>Revenue analytics</li>
<li>E-factura integration (Romania)</li>
</ul>
HTML;
    }

    private function getEmailTemplatesContent(): string
    {
        return <<<'HTML'
<h2>Email Templates</h2>
<p>Customizable templates for all system emails.</p>

<h3>Template Types</h3>
<ul>
<li>Order confirmation</li>
<li>Ticket delivery</li>
<li>Event reminders</li>
<li>Invoice notifications</li>
<li>Welcome emails</li>
</ul>

<h3>Variables</h3>
<p>Use placeholders in templates:</p>
<pre><code>Hello {{customer_name}},

Your order #{{order_number}} for {{event_name}}
has been confirmed.</code></pre>

<h3>Customization</h3>
<p>Templates support:</p>
<ul>
<li>HTML content</li>
<li>Conditional blocks</li>
<li>Dynamic content</li>
<li>Attachments</li>
</ul>
HTML;
    }

    private function getPromoCodesContent(): string
    {
        return <<<'HTML'
<h2>Promo Codes</h2>
<p>Flexible discount system with analytics.</p>

<h3>Discount Types</h3>
<ul>
<li><strong>Percentage</strong> - % off order</li>
<li><strong>Fixed Amount</strong> - $ off order</li>
<li><strong>Free Tickets</strong> - BOGO offers</li>
</ul>

<h3>Restrictions</h3>
<ul>
<li>Usage limits (per code, per user)</li>
<li>Date ranges</li>
<li>Minimum purchase</li>
<li>Event restrictions</li>
<li>Customer segments</li>
</ul>

<h3>Analytics</h3>
<p>Track promo code performance:</p>
<ul>
<li>Usage count</li>
<li>Revenue generated</li>
<li>Conversion rate</li>
<li>Average discount</li>
</ul>
HTML;
    }

    private function getTenantManagementContent(): string
    {
        return <<<'HTML'
<h2>Tenant Management</h2>
<p>Multi-tenant architecture for SaaS deployment.</p>

<h3>Tenant Isolation</h3>
<p>Each tenant has isolated:</p>
<ul>
<li>Events and tickets</li>
<li>Customers</li>
<li>Orders</li>
<li>Settings</li>
<li>API keys</li>
</ul>

<h3>Onboarding Flow</h3>
<ol>
<li>Registration with company details</li>
<li>Domain configuration</li>
<li>Payment processor setup</li>
<li>Email verification</li>
<li>Initial configuration</li>
</ol>

<h3>Domain Management</h3>
<p>Tenants can use:</p>
<ul>
<li>Subdomain (tenant.epas.com)</li>
<li>Custom domain (events.tenant.com)</li>
</ul>
HTML;
    }

    private function getMicroservicesOverviewContent(): string
    {
        return <<<'HTML'
<h2>Microservices Architecture</h2>
<p>Extend EPAS with modular microservices.</p>

<h3>Marketplace</h3>
<p>Tenants can browse and purchase microservices from the marketplace at <code>/store</code>.</p>

<h3>Available Categories</h3>
<ul>
<li>Marketing & Analytics</li>
<li>Payments & Billing</li>
<li>Communication</li>
<li>Integrations</li>
</ul>

<h3>Activation Flow</h3>
<ol>
<li>Purchase microservice</li>
<li>Configure settings</li>
<li>Activate for tenant</li>
<li>Use via API or UI</li>
</ol>

<h3>Feature Flags</h3>
<p>Control feature availability:</p>
<pre><code>use App\Services\FeatureFlag;

if (FeatureFlag::isEnabled('affiliate-tracking', $tenant)) {
    // Feature is active
}</code></pre>
HTML;
    }

    private function getAffiliateTrackingContent(): string
    {
        return <<<'HTML'
<h2>Affiliate Tracking</h2>
<p>Create affiliate partners who earn commissions on ticket sales they refer.</p>

<h3>Where to Manage</h3>
<p><strong>Location:</strong> Services &rarr; Affiliates (<code>/tenant/affiliates</code>)</p>

<h3>Creating an Affiliate</h3>
<ol>
<li>Go to <strong>Services &rarr; Affiliates</strong></li>
<li>Click <strong>Create Affiliate</strong></li>
<li>Fill in the required fields</li>
</ol>

<h4>Affiliate Information</h4>
<table>
<tr><td><strong>Name</strong></td><td>Affiliate's name or company</td></tr>
<tr><td><strong>Affiliate Code</strong></td><td>Auto-generated unique code (e.g., <code>AFF-ABC123</code>)</td></tr>
<tr><td><strong>Contact Email</strong></td><td>Where to send commission reports</td></tr>
<tr><td><strong>Status</strong></td><td>Active, Suspended, or Inactive</td></tr>
</table>

<h4>Commission Settings</h4>
<table>
<tr><td><strong>Commission Type</strong></td><td><strong>Percentage (%)</strong> - Earn % of each sale<br><strong>Fixed Amount</strong> - Earn fixed RON per sale</td></tr>
<tr><td><strong>Commission Rate</strong></td><td>The percentage (0-100) or fixed amount per order</td></tr>
</table>

<h3>Tracking Methods</h3>

<h4>1. Referral Links</h4>
<p>Affiliates share their unique tracking URL:</p>
<pre><code>https://your-site.com/?ref=AFF-ABC123</code></pre>
<p>When a customer clicks this link and purchases, the order is attributed to the affiliate.</p>

<h4>2. Coupon Codes</h4>
<p>Assign a coupon code to an affiliate in the <strong>Coupon Code</strong> section. When a customer uses this coupon at checkout, the order is attributed to the affiliate.</p>

<h3>Viewing Affiliate Performance</h3>
<p>On the affiliates list, you can see:</p>
<ul>
<li><strong>Conversions</strong> - Number of successful orders</li>
<li><strong>Commission Earned</strong> - Total approved commissions</li>
</ul>
<p>Click on an affiliate name to view detailed statistics.</p>

<h3>Conversion Tracking</h3>
<p>View all conversions at <strong>Services &rarr; Affiliate Conversions</strong>:</p>
<ul>
<li>Order details and amount</li>
<li>Commission value calculated</li>
<li>Conversion status (Pending, Approved, Paid)</li>
</ul>

<h3>How Attribution Works</h3>
<ol>
<li>Customer clicks affiliate link or uses coupon</li>
<li>A tracking cookie is set (30-day duration)</li>
<li>When customer completes purchase, order is attributed</li>
<li>Commission is calculated based on affiliate's rate</li>
<li>Commission appears in affiliate's dashboard</li>
</ol>
HTML;
    }

    private function getMobileWalletContent(): string
    {
        return <<<'HTML'
<h2>Mobile Wallet</h2>
<p>Generate Apple Wallet and Google Pay passes for tickets.</p>

<h3>Supported Platforms</h3>
<ul>
<li>Apple Wallet (.pkpass)</li>
<li>Google Pay</li>
</ul>

<h3>Pass Features</h3>
<ul>
<li>Event details</li>
<li>QR code for entry</li>
<li>Location-based notifications</li>
<li>Real-time updates</li>
</ul>

<h3>Configuration</h3>
<p>Requires Apple Developer account and Google Pay API access.</p>

<h3>API</h3>
<pre><code>POST /api/wallet/generate
{
    "ticket_id": 123,
    "platform": "apple"
}</code></pre>
HTML;
    }

    private function getCRMAutomationContent(): string
    {
        return <<<'HTML'
<h2>CRM Automation</h2>
<p>Customer segmentation and automated workflows.</p>

<h3>Segmentation</h3>
<p>Create segments based on:</p>
<ul>
<li>Purchase history</li>
<li>Event attendance</li>
<li>Engagement metrics</li>
<li>Demographics</li>
</ul>

<h3>Workflows</h3>
<p>Automate actions based on triggers:</p>
<ul>
<li>Welcome series</li>
<li>Post-event follow-up</li>
<li>Re-engagement campaigns</li>
<li>Birthday offers</li>
</ul>

<h3>Actions</h3>
<ul>
<li>Send email</li>
<li>Send SMS</li>
<li>Add tag</li>
<li>Update field</li>
<li>Wait delay</li>
</ul>
HTML;
    }

    private function getAnalyticsDashboardContent(): string
    {
        return <<<'HTML'
<h2>Analytics Dashboard</h2>
<p>Advanced analytics with real-time metrics and traffic analysis. Requires the <strong>Analytics</strong> microservice.</p>

<h3>Where to Access</h3>
<p><strong>Location:</strong> Services &rarr; Analytics (<code>/tenant/analytics-dashboard</code>)</p>

<h3>Key Metrics</h3>
<p>The dashboard displays:</p>

<h4>Revenue Metrics</h4>
<table>
<tr><td><strong>Total Revenue</strong></td><td>Sum of all paid orders in selected period</td></tr>
<tr><td><strong>Total Orders</strong></td><td>Number of completed orders</td></tr>
<tr><td><strong>Total Tickets</strong></td><td>Number of tickets sold</td></tr>
<tr><td><strong>Average Order Value</strong></td><td>Revenue divided by orders</td></tr>
<tr><td><strong>Revenue Change</strong></td><td>% change compared to previous period</td></tr>
</table>

<h3>Date Range Filter</h3>
<p>Filter all metrics by time period:</p>
<ul>
<li><strong>Last 7 days</strong> - Past week</li>
<li><strong>Last 30 days</strong> - Past month (default)</li>
<li><strong>Last 90 days</strong> - Past quarter</li>
<li><strong>All time</strong> - Since account creation</li>
</ul>

<h3>Sales Chart</h3>
<p>Interactive line chart showing:</p>
<ul>
<li>Daily revenue over time</li>
<li>Order count per day</li>
</ul>

<h3>Top Events</h3>
<p>Ranking of your best-performing events by:</p>
<ul>
<li>Event name</li>
<li>Number of orders</li>
<li>Revenue generated</li>
</ul>

<h3>Real-Time Analytics</h3>
<p>Live visitor tracking section shows:</p>
<ul>
<li><strong>Active Users</strong> - Currently on your site</li>
<li><strong>Users per Minute</strong> - Traffic trend (30-min chart)</li>
<li><strong>Active Pages</strong> - Most viewed pages right now</li>
<li><strong>Recent Activity</strong> - Live feed of purchases, views, cart additions</li>
</ul>

<h3>Traffic Sources</h3>
<p>Breakdown of where visitors come from:</p>
<ul>
<li>Direct traffic</li>
<li>Organic search</li>
<li>Social media</li>
<li>Referral links</li>
<li>Email campaigns</li>
</ul>

<h3>Geographic Data</h3>
<p>See where your customers are located by country.</p>

<h3>How to Enable</h3>
<ol>
<li>Purchase the <strong>Analytics</strong> microservice from the Store</li>
<li>Navigate to Services &rarr; Analytics</li>
<li>Dashboard loads automatically with your data</li>
</ol>
HTML;
    }

    private function getApiAuthenticationContent(): string
    {
        return <<<'HTML'
<h2>API Authentication</h2>
<p>Secure your API requests with proper authentication.</p>

<h3>API Keys</h3>
<p>Generate API keys from the admin panel:</p>
<ol>
<li>Go to Settings → API Keys</li>
<li>Click "Generate New Key"</li>
<li>Copy the key (shown only once)</li>
</ol>

<h3>Usage</h3>
<pre><code>curl -H "X-API-Key: your-api-key" \
     https://api.epas.com/v1/events</code></pre>

<h3>Rate Limiting</h3>
<p>API requests are rate limited:</p>
<ul>
<li>60 requests per minute (default)</li>
<li>Custom limits per key</li>
</ul>

<h3>Security</h3>
<ul>
<li>HTTPS required</li>
<li>Key rotation support</li>
<li>IP whitelisting available</li>
</ul>
HTML;
    }

    private function getEventsApiContent(): string
    {
        return <<<'HTML'
<h2>Events API</h2>
<p>REST API for event management.</p>

<h3>Endpoints</h3>

<h4>List Events</h4>
<pre><code>GET /api/v1/events
GET /api/v1/events?status=published&page=1</code></pre>

<h4>Get Event</h4>
<pre><code>GET /api/v1/events/{slug}</code></pre>

<h4>Create Event</h4>
<pre><code>POST /api/v1/events
{
    "title": "Event Name",
    "venue_id": 1,
    "starts_at": "2024-12-01T19:00:00Z"
}</code></pre>

<h4>Update Event</h4>
<pre><code>PUT /api/v1/events/{id}</code></pre>

<h4>Delete Event</h4>
<pre><code>DELETE /api/v1/events/{id}</code></pre>
HTML;
    }

    private function getOrdersApiContent(): string
    {
        return <<<'HTML'
<h2>Orders API</h2>
<p>REST API for order processing.</p>

<h3>Checkout Flow</h3>
<ol>
<li>Create cart</li>
<li>Add items</li>
<li>Apply promo code (optional)</li>
<li>Process payment</li>
<li>Confirm order</li>
</ol>

<h3>Endpoints</h3>

<h4>Create Order</h4>
<pre><code>POST /api/v1/orders
{
    "event_id": 1,
    "tickets": [
        {"type_id": 1, "quantity": 2}
    ],
    "customer_email": "customer@email.com"
}</code></pre>

<h4>Get Order</h4>
<pre><code>GET /api/v1/orders/{id}</code></pre>

<h4>Cancel Order</h4>
<pre><code>POST /api/v1/orders/{id}/cancel</code></pre>
HTML;
    }

    private function getWebhooksContent(): string
    {
        return <<<'HTML'
<h2>Webhooks</h2>
<p>Receive real-time notifications for events.</p>

<h3>Available Events</h3>
<ul>
<li><code>order.created</code></li>
<li><code>order.paid</code></li>
<li><code>order.cancelled</code></li>
<li><code>ticket.checked_in</code></li>
<li><code>event.published</code></li>
</ul>

<h3>Configuration</h3>
<p>Register webhook endpoints in Settings → Webhooks</p>

<h3>Payload Format</h3>
<pre><code>{
    "event": "order.paid",
    "data": {
        "id": 123,
        "total": 99.00,
        "currency": "USD"
    },
    "timestamp": "2024-01-15T10:30:00Z"
}</code></pre>

<h3>Signature Verification</h3>
<p>Verify webhook signatures using HMAC-SHA256.</p>
HTML;
    }

    private function getStripeIntegrationContent(): string
    {
        return <<<'HTML'
<h2>Stripe Integration</h2>
<p>Accept payments with Stripe. Configure your Stripe credentials to process ticket sales.</p>

<h3>Where to Configure</h3>
<p><strong>Location:</strong> Settings &rarr; Payment Config (<code>/tenant/payment-config</code>)</p>

<h3>Step 1: Get Your Stripe Credentials</h3>
<ol>
<li>Log in to your <a href="https://dashboard.stripe.com" target="_blank">Stripe Dashboard</a></li>
<li>Navigate to <strong>Developers &rarr; API Keys</strong></li>
<li>Copy your keys:
    <ul>
        <li><strong>Publishable key</strong> (starts with <code>pk_test_</code> or <code>pk_live_</code>)</li>
        <li><strong>Secret key</strong> (starts with <code>sk_test_</code> or <code>sk_live_</code>)</li>
    </ul>
</li>
</ol>

<h3>Step 2: Configure in EPAS</h3>
<p>In Payment Config, you'll find two sections for Stripe:</p>

<h4>Test Credentials</h4>
<table>
<tr><td><strong>Test Publishable Key</strong></td><td>Your <code>pk_test_...</code> key for testing</td></tr>
<tr><td><strong>Test Secret Key</strong></td><td>Your <code>sk_test_...</code> key for testing</td></tr>
</table>

<h4>Live Credentials</h4>
<table>
<tr><td><strong>Live Publishable Key</strong></td><td>Your <code>pk_live_...</code> key for production</td></tr>
<tr><td><strong>Live Secret Key</strong></td><td>Your <code>sk_live_...</code> key for production</td></tr>
<tr><td><strong>Webhook Secret</strong></td><td>Your webhook signing secret (<code>whsec_...</code>)</td></tr>
</table>

<h3>Step 3: Set Up Webhooks</h3>
<ol>
<li>In Stripe Dashboard, go to <strong>Developers &rarr; Webhooks</strong></li>
<li>Click <strong>Add endpoint</strong></li>
<li>Enter your webhook URL: <code>https://your-domain.com/webhooks/stripe</code></li>
<li>Select events to listen for:
    <ul>
        <li><code>payment_intent.succeeded</code></li>
        <li><code>payment_intent.payment_failed</code></li>
        <li><code>checkout.session.completed</code></li>
        <li><code>charge.refunded</code></li>
    </ul>
</li>
<li>Copy the <strong>Signing secret</strong> and paste it in the Webhook Secret field</li>
</ol>

<h3>Step 4: Select Mode</h3>
<p>Use the <strong>Mode</strong> toggle to switch between:</p>
<ul>
<li><strong>Test</strong> - Uses test credentials (no real charges)</li>
<li><strong>Live</strong> - Uses live credentials (real payments)</li>
</ul>

<h3>Testing Payments</h3>
<p>Use Stripe test card numbers:</p>
<ul>
<li><code>4242 4242 4242 4242</code> - Success</li>
<li><code>4000 0000 0000 0002</code> - Declined</li>
<li><code>4000 0025 0000 3155</code> - Requires 3D Secure</li>
</ul>

<h3>Troubleshooting</h3>
<ul>
<li><strong>Payments not processing:</strong> Check that your mode matches your credentials (test vs live)</li>
<li><strong>Webhook errors:</strong> Verify the webhook URL is accessible and the signing secret is correct</li>
<li><strong>SSL required:</strong> Live mode requires HTTPS</li>
</ul>
HTML;
    }

    private function getWhatsAppIntegrationContent(): string
    {
        return <<<'HTML'
<h2>WhatsApp Integration</h2>
<p>Send notifications via WhatsApp Business API.</p>

<h3>Setup</h3>
<ol>
<li>Apply for WhatsApp Business API</li>
<li>Choose BSP (Twilio, etc.)</li>
<li>Configure templates</li>
<li>Add credentials to EPAS</li>
</ol>

<h3>Message Types</h3>
<ul>
<li>Order confirmations</li>
<li>Ticket delivery</li>
<li>Event reminders</li>
<li>Custom notifications</li>
</ul>

<h3>Templates</h3>
<p>WhatsApp requires pre-approved templates for business messages.</p>

<h3>Opt-in Management</h3>
<p>Track customer consent for WhatsApp messaging.</p>
HTML;
    }

    private function getEFacturaIntegrationContent(): string
    {
        return <<<'HTML'
<h2>E-Factura Integration</h2>
<p>Romanian e-invoicing compliance (ANAF).</p>

<h3>Requirements</h3>
<ul>
<li>Romanian company (CUI)</li>
<li>ANAF digital certificate</li>
<li>SPV registration</li>
</ul>

<h3>Invoice Flow</h3>
<ol>
<li>Create invoice in EPAS</li>
<li>Generate XML (UBL format)</li>
<li>Submit to ANAF</li>
<li>Receive confirmation</li>
<li>Download PDF</li>
</ol>

<h3>Configuration</h3>
<p>Add ANAF credentials in Settings → E-Factura</p>

<h3>Testing</h3>
<p>Use ANAF test environment for development.</p>
HTML;
    }

    private function getTrackingPixelsContent(): string
    {
        return <<<'HTML'
<h2>Tracking Pixels</h2>
<p>Track conversions and analyze traffic with marketing pixels. Requires the <strong>Tracking & Pixels</strong> microservice.</p>

<h3>Where to Configure</h3>
<p><strong>Location:</strong> Services &rarr; Tracking & Pixels (<code>/tenant/tracking-settings</code>)</p>

<h3>Supported Platforms</h3>

<h4>Google Analytics 4 (GA4)</h4>
<table>
<tr><td><strong>Field</strong></td><td><strong>Format</strong></td><td><strong>Where to Find</strong></td></tr>
<tr><td>Measurement ID</td><td><code>G-XXXXXXXXXX</code></td><td>GA4 Admin &rarr; Data Streams &rarr; Web stream details</td></tr>
</table>

<h4>Google Tag Manager (GTM)</h4>
<table>
<tr><td><strong>Field</strong></td><td><strong>Format</strong></td><td><strong>Where to Find</strong></td></tr>
<tr><td>Container ID</td><td><code>GTM-XXXXXX</code></td><td>GTM Dashboard &rarr; Top of container page</td></tr>
</table>

<h4>Meta Pixel (Facebook/Instagram)</h4>
<table>
<tr><td><strong>Field</strong></td><td><strong>Format</strong></td><td><strong>Where to Find</strong></td></tr>
<tr><td>Pixel ID</td><td><code>1234567890123456</code></td><td>Meta Events Manager &rarr; Data Sources &rarr; Select Pixel</td></tr>
</table>

<h4>TikTok Pixel</h4>
<table>
<tr><td><strong>Field</strong></td><td><strong>Format</strong></td><td><strong>Where to Find</strong></td></tr>
<tr><td>Pixel ID</td><td><code>CXXXXXXXXXXXXXXXXX</code></td><td>TikTok Ads Manager &rarr; Assets &rarr; Events &rarr; Website Pixel</td></tr>
</table>

<h3>Configuration Options</h3>
<p>For each pixel, you can configure:</p>
<ul>
<li><strong>Enable/Disable Toggle</strong> - Turn tracking on or off</li>
<li><strong>Inject Location</strong> - Where to add the script (Head recommended, or Body End)</li>
<li><strong>Page Scope</strong> - Public pages only, or All pages</li>
</ul>

<h3>Events Automatically Tracked</h3>
<ul>
<li><strong>PageView</strong> - Every page load</li>
<li><strong>ViewContent</strong> - Event detail pages</li>
<li><strong>AddToCart</strong> - When tickets added to cart</li>
<li><strong>InitiateCheckout</strong> - Checkout started</li>
<li><strong>Purchase</strong> - Successful order completion</li>
</ul>

<h3>GDPR Compliance</h3>
<p>All tracking pixels respect user consent:</p>
<ul>
<li><strong>Analytics pixels</strong> (GA4, GTM) - Require "Analytics" consent category</li>
<li><strong>Marketing pixels</strong> (Meta, TikTok) - Require "Marketing" consent category</li>
</ul>
<p>No tracking occurs until the user explicitly consents via the cookie consent banner.</p>

<h3>How to Enable</h3>
<ol>
<li>Purchase the <strong>Tracking & Pixels</strong> microservice from the Store</li>
<li>Navigate to Services &rarr; Tracking & Pixels</li>
<li>Enable desired pixels and enter your IDs</li>
<li>Click <strong>Save Settings</strong></li>
</ol>
HTML;
    }
}
