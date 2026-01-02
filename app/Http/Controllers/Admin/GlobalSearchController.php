<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\User;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Invite;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Search for admin panel (global platform search)
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('q', '');

            if (strlen($query) < 3) {
                return response()->json([]);
            }

            $results = [];
            $locale = app()->getLocale();
            $lowerQuery = '%' . mb_strtolower($query) . '%';

        // Search Navigation/Pages first
        $pages = $this->searchAdminPages($query);
        if (!empty($pages)) {
            $results['pages'] = $pages;
        }

        // Search Venues (by name - translatable JSON field)
        // Use LOWER() for case-insensitive search (works on MySQL and PostgreSQL)
        $venues = Venue::query()
            ->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($venues->isNotEmpty()) {
            $results['venues'] = $venues->map(function ($venue) use ($locale) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => $venue->city ?? '',
                    'url' => "/admin/venues/{$venue->id}/edit",
                ];
            })->toArray();
        }

        // Search Artists (by name - NOT translatable, regular string field)
        $artists = Artist::query()
            ->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($artists->isNotEmpty()) {
            $results['artists'] = $artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name ?? 'Unnamed',
                    'subtitle' => '',
                    'url' => "/admin/artists/{$artist->id}/edit",
                ];
            })->toArray();
        }

        // Search Events (by title - translatable JSON field)
        // Use LOWER() for case-insensitive search (works on MySQL and PostgreSQL)
        $events = Event::query()
            ->whereRaw("LOWER(title) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($events->isNotEmpty()) {
            $results['events'] = $events->map(function ($event) use ($locale) {
                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                    'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                    'url' => "/admin/events/{$event->id}/edit",
                ];
            })->toArray();
        }

        // Search Tenants (by name or public_name)
        $tenants = Tenant::query()
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(public_name) LIKE ?", [$lowerQuery]);
            })
            ->limit(5)
            ->get();

        if ($tenants->isNotEmpty()) {
            $results['tenants'] = $tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->public_name ?? $tenant->name ?? 'Unnamed',
                    'subtitle' => $tenant->email ?? '',
                    'url' => "/admin/tenants/{$tenant->id}/edit",
                ];
            })->toArray();
        }

        // Search Customers (by first_name, last_name, or email)
        $customers = Customer::query()
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw("LOWER(first_name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(last_name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(email) LIKE ?", [$lowerQuery]);
            })
            ->limit(5)
            ->get();

        if ($customers->isNotEmpty()) {
            $results['customers'] = $customers->map(function ($customer) {
                $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                return [
                    'id' => $customer->id,
                    'name' => $name ?: ($customer->email ?? 'Unnamed'),
                    'subtitle' => $customer->email ?? '',
                    'url' => "/admin/customers/{$customer->id}/edit",
                ];
            })->toArray();
        }

        // Search Users (by name or email)
        $users = User::query()
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(email) LIKE ?", [$lowerQuery]);
            })
            ->limit(5)
            ->get();

        if ($users->isNotEmpty()) {
            $results['users'] = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'subtitle' => $user->email,
                    'url' => "/admin/users/{$user->id}/edit",
                ];
            })->toArray();
        }

            return response()->json($results);
        } catch (\Exception $e) {
            \Log::error('Admin search error: ' . $e->getMessage(), [
                'query' => $request->input('q'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for tenant panel (tenant-scoped search)
     */
    public function searchTenant(Request $request, $tenant)
    {
        try {
            $query = $request->input('q', '');

            if (strlen($query) < 3) {
                return response()->json([]);
            }

            $results = [];
            $locale = app()->getLocale();
            $lowerQuery = '%' . mb_strtolower($query) . '%';

            // Resolve tenant - can be ID or slug
            if (is_numeric($tenant)) {
                $tenantId = (int) $tenant;
            } else {
                // Look up tenant by slug
                $tenantRecord = Tenant::where('slug', $tenant)->first();
                if (!$tenantRecord) {
                    return response()->json([]);
                }
                $tenantId = $tenantRecord->id;
            }

        if (!$tenantId) {
            return response()->json([]);
        }

        // Search Navigation/Pages first
        $pages = $this->searchTenantPages($query);
        if (!empty($pages)) {
            $results['pages'] = $pages;
        }

        // Search Events (by title - translatable JSON field)
        $events = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("LOWER(title) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($events->isNotEmpty()) {
            $results['events'] = $events->map(function ($event) use ($locale) {
                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                    'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                    'url' => "/tenant/events/{$event->id}/edit",
                ];
            })->toArray();
        }

        // Search Venues (by name - translatable JSON field)
        $venues = Venue::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($venues->isNotEmpty()) {
            $results['venues'] = $venues->map(function ($venue) use ($locale) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => $venue->city ?? '',
                    'url' => "/tenant/venues/{$venue->id}/edit",
                ];
            })->toArray();
        }

        // Search Orders (by customer_email or ID)
        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($lowerQuery, $query) {
                $q->whereRaw("LOWER(customer_email) LIKE ?", [$lowerQuery])
                    ->orWhere('id', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($orders->isNotEmpty()) {
            $results['orders'] = $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'name' => "Order #{$order->id}",
                    'subtitle' => $order->customer_email ?? '',
                    'url' => "/tenant/orders/{$order->id}",
                ];
            })->toArray();
        }

        // Search Tickets (by code or customer_email)
        $tickets = Ticket::query()
            ->whereHas('order', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw("LOWER(code) LIKE ?", [$lowerQuery])
                    ->orWhereHas('order', function ($orderQ) use ($lowerQuery) {
                        $orderQ->whereRaw("LOWER(customer_email) LIKE ?", [$lowerQuery]);
                    });
            })
            ->limit(5)
            ->get();

        if ($tickets->isNotEmpty()) {
            $results['tickets'] = $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'name' => $ticket->code,
                    'subtitle' => $ticket->order?->customer_email ?? '',
                    'url' => "/tenant/tickets/{$ticket->id}",
                ];
            })->toArray();
        }

        // Search Customers (by first_name, last_name, email, or phone)
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw("LOWER(first_name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(last_name) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(email) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(phone) LIKE ?", [$lowerQuery]);
            })
            ->limit(5)
            ->get();

        if ($customers->isNotEmpty()) {
            $results['customers'] = $customers->map(function ($customer) {
                $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                return [
                    'id' => $customer->id,
                    'name' => $name ?: ($customer->email ?? 'Unnamed'),
                    'subtitle' => $customer->email ?? '',
                    'url' => "/tenant/customers/{$customer->id}/edit",
                ];
            })->toArray();
        }

        // Search Invitations (by code or recipient name/email in JSON)
        $invites = Invite::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($lowerQuery) {
                // Search by invite_code (case insensitive)
                $q->whereRaw("LOWER(invite_code) LIKE ?", [$lowerQuery])
                    // Search in recipient JSON field for name or email
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(recipient, '$.name'))) LIKE ?", [$lowerQuery])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(recipient, '$.email'))) LIKE ?", [$lowerQuery]);
            })
            ->with('batch')
            ->limit(5)
            ->get();

        if ($invites->isNotEmpty()) {
            $results['invitations'] = $invites->map(function ($invite) {
                $recipientName = $invite->getRecipientName() ?? $invite->getRecipientEmail() ?? '';
                return [
                    'id' => $invite->id,
                    'name' => $invite->invite_code,
                    'subtitle' => $recipientName ?: ($invite->batch?->name ?? 'No recipient'),
                    'url' => "/tenant/invitations?tableFilters[invite_code][value]={$invite->invite_code}",
                ];
            })->toArray();
        }

        // Search Beneficiaries (in Order meta and Ticket meta)
        // Search in Order meta->beneficiaries[].name/email
        $ordersWithBeneficiaries = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("LOWER(meta) LIKE ?", [$lowerQuery])
            ->limit(10)
            ->get()
            ->filter(function ($order) use ($query) {
                $beneficiaries = $order->meta['beneficiaries'] ?? [];
                foreach ($beneficiaries as $b) {
                    if (
                        (isset($b['name']) && mb_stripos($b['name'], $query) !== false) ||
                        (isset($b['email']) && mb_stripos($b['email'], $query) !== false)
                    ) {
                        return true;
                    }
                }
                return false;
            })
            ->take(5);

        if ($ordersWithBeneficiaries->isNotEmpty()) {
            $results['beneficiaries'] = $ordersWithBeneficiaries->map(function ($order) use ($query) {
                // Find the matching beneficiary name
                $matchedName = '';
                $beneficiaries = $order->meta['beneficiaries'] ?? [];
                foreach ($beneficiaries as $b) {
                    if (
                        (isset($b['name']) && mb_stripos($b['name'], $query) !== false) ||
                        (isset($b['email']) && mb_stripos($b['email'], $query) !== false)
                    ) {
                        $matchedName = $b['name'] ?? $b['email'] ?? '';
                        break;
                    }
                }
                return [
                    'id' => $order->id,
                    'name' => $matchedName ?: 'Beneficiary',
                    'subtitle' => "Order #{$order->id}",
                    'url' => "/tenant/orders/{$order->id}",
                ];
            })->values()->toArray();
        }

        // Search Artists (public site URL - external domain)
        $artists = Artist::query()
            ->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
            ->limit(5)
            ->get();

        if ($artists->isNotEmpty()) {
            $results['artists'] = $artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name ?? 'Unnamed',
                    'subtitle' => 'Public artist page',
                    'url' => "https://tixello.com/artist/{$artist->slug}",
                ];
            })->toArray();
        }

            return response()->json($results);
        } catch (\Exception $e) {
            \Log::error('Tenant search error: ' . $e->getMessage(), [
                'query' => $request->input('q'),
                'tenant' => $tenant,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search admin panel navigation pages
     */
    private function searchAdminPages(string $query): array
    {
        $pages = [
            // Resources
            ['name' => 'Events', 'keywords' => ['events', 'event', 'evenimente'], 'url' => '/admin/events', 'subtitle' => 'Manage events'],
            ['name' => 'Venues', 'keywords' => ['venues', 'venue', 'locatii', 'location'], 'url' => '/admin/venues', 'subtitle' => 'Manage venues'],
            ['name' => 'Artists', 'keywords' => ['artists', 'artist', 'artisti', 'performer'], 'url' => '/admin/artists', 'subtitle' => 'Manage artists'],
            ['name' => 'Tenants', 'keywords' => ['tenants', 'tenant', 'organizatori', 'organizer'], 'url' => '/admin/tenants', 'subtitle' => 'Manage tenants/organizers'],
            ['name' => 'Customers', 'keywords' => ['customers', 'customer', 'clienti', 'client'], 'url' => '/admin/customers', 'subtitle' => 'Manage customers'],
            ['name' => 'Users', 'keywords' => ['users', 'user', 'utilizatori', 'admin'], 'url' => '/admin/users', 'subtitle' => 'Manage users'],
            ['name' => 'Orders', 'keywords' => ['orders', 'order', 'comenzi', 'comanda'], 'url' => '/admin/orders', 'subtitle' => 'View orders'],
            ['name' => 'Tickets', 'keywords' => ['tickets', 'ticket', 'bilete', 'bilet'], 'url' => '/admin/tickets', 'subtitle' => 'View tickets'],
            ['name' => 'Invoices', 'keywords' => ['invoices', 'invoice', 'facturi', 'factura', 'billing'], 'url' => '/admin/invoices', 'subtitle' => 'Manage invoices'],
            ['name' => 'Ticket Types', 'keywords' => ['ticket types', 'tipuri bilete', 'ticket type', 'pricing'], 'url' => '/admin/ticket-types', 'subtitle' => 'Manage ticket types'],

            // Taxonomies
            ['name' => 'Event Types', 'keywords' => ['event types', 'tipuri evenimente', 'categories'], 'url' => '/admin/event-types', 'subtitle' => 'Event categories'],
            ['name' => 'Event Genres', 'keywords' => ['event genres', 'genuri evenimente', 'genres'], 'url' => '/admin/event-genres', 'subtitle' => 'Event genres'],
            ['name' => 'Tags', 'keywords' => ['tags', 'tag', 'etichete'], 'url' => '/admin/tags', 'subtitle' => 'Content tags'],

            // System
            ['name' => 'Email Templates', 'keywords' => ['email', 'templates', 'sabloane email', 'mail'], 'url' => '/admin/email-templates', 'subtitle' => 'Email templates'],
            ['name' => 'Email Logs', 'keywords' => ['email logs', 'log email', 'sent emails'], 'url' => '/admin/email-logs', 'subtitle' => 'Email sending history'],
            ['name' => 'Contract Templates', 'keywords' => ['contract', 'templates', 'contracte'], 'url' => '/admin/contract-templates', 'subtitle' => 'Contract templates'],
            ['name' => 'Microservices', 'keywords' => ['microservices', 'integrations', 'integrari', 'services'], 'url' => '/admin/microservices', 'subtitle' => 'Manage microservices'],
            ['name' => 'Ticket Templates', 'keywords' => ['ticket templates', 'sabloane bilete', 'design'], 'url' => '/admin/ticket-templates', 'subtitle' => 'Ticket design templates'],
            ['name' => 'Documentation', 'keywords' => ['documentation', 'docs', 'documentatie', 'help'], 'url' => '/admin/documentation', 'subtitle' => 'View documentation'],
        ];

        $lowerQuery = mb_strtolower($query);
        $matches = [];

        foreach ($pages as $page) {
            // Check name match
            if (mb_stripos($page['name'], $query) !== false) {
                $matches[] = [
                    'name' => $page['name'],
                    'subtitle' => $page['subtitle'],
                    'url' => $page['url'],
                ];
                continue;
            }

            // Check keywords match
            foreach ($page['keywords'] as $keyword) {
                if (mb_stripos($keyword, $lowerQuery) !== false) {
                    $matches[] = [
                        'name' => $page['name'],
                        'subtitle' => $page['subtitle'],
                        'url' => $page['url'],
                    ];
                    break;
                }
            }
        }

        return array_slice($matches, 0, 5);
    }

    /**
     * Search tenant panel navigation pages
     */
    private function searchTenantPages(string $query): array
    {
        $pages = [
            // Top level
            ['name' => 'Dashboard', 'keywords' => ['dashboard', 'acasa', 'home', 'panou'], 'url' => '/tenant', 'subtitle' => 'Overview dashboard'],
            ['name' => 'Events', 'keywords' => ['events', 'event', 'evenimente'], 'url' => '/tenant/events', 'subtitle' => 'Manage your events'],
            ['name' => 'Venues', 'keywords' => ['venues', 'venue', 'locatii', 'location'], 'url' => '/tenant/venues', 'subtitle' => 'Manage venues'],
            ['name' => 'Pages', 'keywords' => ['pages', 'page', 'pagini', 'content'], 'url' => '/tenant/pages', 'subtitle' => 'Website pages'],

            // Sales
            ['name' => 'Orders', 'keywords' => ['orders', 'order', 'comenzi', 'comanda', 'sales', 'vanzari'], 'url' => '/tenant/orders', 'subtitle' => 'View customer orders'],
            ['name' => 'Tickets', 'keywords' => ['tickets', 'ticket', 'bilete', 'bilet'], 'url' => '/tenant/tickets', 'subtitle' => 'View issued tickets'],
            ['name' => 'Customers', 'keywords' => ['customers', 'customer', 'clienti', 'client'], 'url' => '/tenant/customers', 'subtitle' => 'Customer database'],

            // Services
            ['name' => 'Affiliates', 'keywords' => ['affiliates', 'affiliate', 'afiliati', 'partners', 'parteneri'], 'url' => '/tenant/affiliates', 'subtitle' => 'Affiliate management'],
            ['name' => 'Microservices', 'keywords' => ['microservices', 'micro', 'services', 'servicii', 'integrations', 'integrari'], 'url' => '/tenant/microservices', 'subtitle' => 'Integrations & services'],
            ['name' => 'Invitations', 'keywords' => ['invitations', 'invitation', 'invitatii', 'invitatie', 'invite', 'codes'], 'url' => '/tenant/invitations', 'subtitle' => 'Manage invitations'],

            // Website
            ['name' => 'Theme Editor', 'keywords' => ['theme', 'editor', 'design', 'culori', 'colors', 'style', 'tema'], 'url' => '/tenant/theme-editor', 'subtitle' => 'Customize website theme'],

            // Settings
            ['name' => 'Settings', 'keywords' => ['settings', 'setari', 'configuration', 'config'], 'url' => '/tenant/settings', 'subtitle' => 'Account settings'],
            ['name' => 'Payment Config', 'keywords' => ['payment', 'plati', 'stripe', 'processor', 'gateway'], 'url' => '/tenant/payment-config', 'subtitle' => 'Payment configuration'],
            ['name' => 'Domains', 'keywords' => ['domains', 'domain', 'domenii', 'dns'], 'url' => '/tenant/settings', 'subtitle' => 'Domain management (in Settings)'],
            ['name' => 'Invoices', 'keywords' => ['invoices', 'invoice', 'facturi', 'factura', 'billing'], 'url' => '/tenant/invoices', 'subtitle' => 'View invoices'],

            // Bottom
            ['name' => 'Documentation', 'keywords' => ['documentation', 'docs', 'documentatie', 'help', 'ajutor'], 'url' => '/tenant/documentation', 'subtitle' => 'Help & documentation'],
            ['name' => 'Activity Log', 'keywords' => ['activity', 'log', 'activitate', 'jurnal', 'history', 'istoric'], 'url' => '/tenant/activity-log', 'subtitle' => 'View activity history'],
        ];

        $lowerQuery = mb_strtolower($query);
        $matches = [];

        foreach ($pages as $page) {
            // Check name match
            if (mb_stripos($page['name'], $query) !== false) {
                $matches[] = [
                    'name' => $page['name'],
                    'subtitle' => $page['subtitle'],
                    'url' => $page['url'],
                ];
                continue;
            }

            // Check keywords match
            foreach ($page['keywords'] as $keyword) {
                if (mb_stripos($keyword, $lowerQuery) !== false) {
                    $matches[] = [
                        'name' => $page['name'],
                        'subtitle' => $page['subtitle'],
                        'url' => $page['url'],
                    ];
                    break;
                }
            }
        }

        return array_slice($matches, 0, 5);
    }

    /**
     * Search for marketplace panel (marketplace-scoped search)
     */
    public function searchMarketplace(Request $request, $marketplaceId)
    {
        try {
            $query = $request->input('q', '');

            if (strlen($query) < 3) {
                return response()->json([]);
            }

            $results = [];
            $locale = app()->getLocale();
            $lowerQuery = '%' . mb_strtolower($query) . '%';

            // Verify marketplace exists
            $marketplace = MarketplaceClient::find($marketplaceId);
            if (!$marketplace) {
                return response()->json([]);
            }

            // Search Navigation/Pages first
            $pages = $this->searchMarketplacePages($query);
            if (!empty($pages)) {
                $results['pages'] = $pages;
            }

            // Search Events (by title - translatable JSON field)
            $events = Event::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereRaw("LOWER(title) LIKE ?", [$lowerQuery])
                ->limit(5)
                ->get();

            if ($events->isNotEmpty()) {
                $results['events'] = $events->map(function ($event) use ($locale) {
                    return [
                        'id' => $event->id,
                        'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                        'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                        'url' => "/marketplace/events/{$event->id}/edit",
                    ];
                })->toArray();
            }

            // Search Organizers (by name or email)
            $organizers = MarketplaceOrganizer::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->where(function ($q) use ($lowerQuery) {
                    $q->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
                        ->orWhereRaw("LOWER(email) LIKE ?", [$lowerQuery])
                        ->orWhereRaw("LOWER(company_name) LIKE ?", [$lowerQuery]);
                })
                ->limit(5)
                ->get();

            if ($organizers->isNotEmpty()) {
                $results['organizers'] = $organizers->map(function ($organizer) {
                    return [
                        'id' => $organizer->id,
                        'name' => $organizer->name ?? 'Unnamed',
                        'subtitle' => $organizer->company_name ?? $organizer->email ?? '',
                        'url' => "/marketplace/organizers/{$organizer->id}",
                    ];
                })->toArray();
            }

            // Search Venues (by name - translatable JSON field)
            $venues = Venue::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereRaw("LOWER(name) LIKE ?", [$lowerQuery])
                ->limit(5)
                ->get();

            if ($venues->isNotEmpty()) {
                $results['venues'] = $venues->map(function ($venue) use ($locale) {
                    return [
                        'id' => $venue->id,
                        'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                        'subtitle' => $venue->city ?? '',
                        'url' => "/marketplace/venues/{$venue->id}/edit",
                    ];
                })->toArray();
            }

            // Search Customers (by first_name, last_name, email, or phone)
            if (class_exists(MarketplaceCustomer::class)) {
                $customers = MarketplaceCustomer::query()
                    ->where('marketplace_client_id', $marketplaceId)
                    ->where(function ($q) use ($lowerQuery) {
                        $q->whereRaw("LOWER(first_name) LIKE ?", [$lowerQuery])
                            ->orWhereRaw("LOWER(last_name) LIKE ?", [$lowerQuery])
                            ->orWhereRaw("LOWER(email) LIKE ?", [$lowerQuery])
                            ->orWhereRaw("LOWER(phone) LIKE ?", [$lowerQuery]);
                    })
                    ->limit(5)
                    ->get();

                if ($customers->isNotEmpty()) {
                    $results['customers'] = $customers->map(function ($customer) {
                        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                        return [
                            'id' => $customer->id,
                            'name' => $name ?: ($customer->email ?? 'Unnamed'),
                            'subtitle' => $customer->email ?? '',
                            'url' => "/marketplace/customers/{$customer->id}/edit",
                        ];
                    })->toArray();
                }
            }

            // Search Orders (by customer_email or ID)
            if (class_exists(MarketplaceOrder::class)) {
                $orders = MarketplaceOrder::query()
                    ->where('marketplace_client_id', $marketplaceId)
                    ->where(function ($q) use ($lowerQuery, $query) {
                        $q->whereRaw("LOWER(customer_email) LIKE ?", [$lowerQuery])
                            ->orWhere('id', 'LIKE', "%{$query}%");
                    })
                    ->limit(5)
                    ->get();

                if ($orders->isNotEmpty()) {
                    $results['orders'] = $orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'name' => "Order #{$order->id}",
                            'subtitle' => $order->customer_email ?? '',
                            'url' => "/marketplace/orders/{$order->id}",
                        ];
                    })->toArray();
                }
            }

            return response()->json($results);
        } catch (\Exception $e) {
            \Log::error('Marketplace search error: ' . $e->getMessage(), [
                'query' => $request->input('q'),
                'marketplace' => $marketplaceId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search marketplace panel navigation pages
     */
    private function searchMarketplacePages(string $query): array
    {
        $pages = [
            // Top level
            ['name' => 'Dashboard', 'keywords' => ['dashboard', 'acasa', 'home', 'panou'], 'url' => '/marketplace', 'subtitle' => 'Overview dashboard'],
            ['name' => 'Events', 'keywords' => ['events', 'event', 'evenimente'], 'url' => '/marketplace/events', 'subtitle' => 'Manage events'],
            ['name' => 'Venues', 'keywords' => ['venues', 'venue', 'locatii', 'location'], 'url' => '/marketplace/venues', 'subtitle' => 'Manage venues'],

            // Organizers
            ['name' => 'Organizers', 'keywords' => ['organizers', 'organizer', 'organizatori'], 'url' => '/marketplace/organizers', 'subtitle' => 'Manage organizers'],

            // Sales
            ['name' => 'Orders', 'keywords' => ['orders', 'order', 'comenzi', 'comanda', 'sales', 'vanzari'], 'url' => '/marketplace/orders', 'subtitle' => 'View orders'],
            ['name' => 'Tickets', 'keywords' => ['tickets', 'ticket', 'bilete', 'bilet'], 'url' => '/marketplace/tickets', 'subtitle' => 'View tickets'],
            ['name' => 'Customers', 'keywords' => ['customers', 'customer', 'clienti', 'client'], 'url' => '/marketplace/customers', 'subtitle' => 'Customer database'],
            ['name' => 'Payouts', 'keywords' => ['payouts', 'payout', 'plati', 'payments'], 'url' => '/marketplace/payouts', 'subtitle' => 'Payout management'],
            ['name' => 'Refund Requests', 'keywords' => ['refund', 'refunds', 'rambursari', 'rambursare'], 'url' => '/marketplace/refund-requests', 'subtitle' => 'Handle refunds'],

            // Services
            ['name' => 'Microservices', 'keywords' => ['microservices', 'micro', 'services', 'servicii', 'integrations'], 'url' => '/marketplace/microservices', 'subtitle' => 'Integrations'],

            // Content
            ['name' => 'Pages', 'keywords' => ['pages', 'page', 'pagini', 'content'], 'url' => '/marketplace/pages', 'subtitle' => 'Website pages'],
            ['name' => 'Blog', 'keywords' => ['blog', 'articles', 'articole'], 'url' => '/marketplace/blog-articles', 'subtitle' => 'Blog articles'],

            // Settings
            ['name' => 'Settings', 'keywords' => ['settings', 'setari', 'configuration', 'config'], 'url' => '/marketplace/settings', 'subtitle' => 'Account settings'],
            ['name' => 'Email Templates', 'keywords' => ['email', 'templates', 'sabloane email', 'mail'], 'url' => '/marketplace/email-templates', 'subtitle' => 'Email templates'],
            ['name' => 'Users', 'keywords' => ['users', 'user', 'utilizatori', 'admin', 'admins'], 'url' => '/marketplace/users', 'subtitle' => 'Platform users'],

            // Shop
            ['name' => 'Shop Products', 'keywords' => ['shop', 'products', 'produse', 'magazin'], 'url' => '/marketplace/shop-products', 'subtitle' => 'Shop products'],
            ['name' => 'Gift Cards', 'keywords' => ['gift', 'cards', 'carduri', 'cadou'], 'url' => '/marketplace/gift-cards', 'subtitle' => 'Gift cards'],
        ];

        $lowerQuery = mb_strtolower($query);
        $matches = [];

        foreach ($pages as $page) {
            // Check name match
            if (mb_stripos($page['name'], $query) !== false) {
                $matches[] = [
                    'name' => $page['name'],
                    'subtitle' => $page['subtitle'],
                    'url' => $page['url'],
                ];
                continue;
            }

            // Check keywords match
            foreach ($page['keywords'] as $keyword) {
                if (mb_stripos($keyword, $lowerQuery) !== false) {
                    $matches[] = [
                        'name' => $page['name'],
                        'subtitle' => $page['subtitle'],
                        'url' => $page['url'],
                    ];
                    break;
                }
            }
        }

        return array_slice($matches, 0, 5);
    }
}
