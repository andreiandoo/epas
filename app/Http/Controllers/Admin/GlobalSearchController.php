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

        // Search Navigation/Pages first
        $pages = $this->searchAdminPages($query);
        if (!empty($pages)) {
            $results['pages'] = $pages;
        }

        // Search Venues (by name - translatable JSON field)
        // Use whereRaw to bypass Laravel's cast handling for JSON columns
        $venues = Venue::query()
            ->whereRaw("name LIKE ?", ['%' . $query . '%'])
            ->limit(5)
            ->get();

        if ($venues->isNotEmpty()) {
            $results['venues'] = $venues->map(function ($venue) use ($locale) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => $venue->city ?? '',
                    'url' => route('filament.admin.resources.venues.edit', ['record' => $venue]),
                ];
            })->toArray();
        }

        // Search Artists (by name - NOT translatable, regular string field)
        $artists = Artist::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($artists->isNotEmpty()) {
            $results['artists'] = $artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name ?? 'Unnamed',
                    'subtitle' => '',
                    'url' => route('filament.admin.resources.artists.edit', ['record' => $artist]),
                ];
            })->toArray();
        }

        // Search Events (by title - translatable JSON field)
        // Use whereRaw to bypass Laravel's cast handling for JSON columns
        $events = Event::query()
            ->whereRaw("title LIKE ?", ['%' . $query . '%'])
            ->limit(5)
            ->get();

        if ($events->isNotEmpty()) {
            $results['events'] = $events->map(function ($event) use ($locale) {
                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                    'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                    'url' => route('filament.admin.resources.events.edit', ['record' => $event]),
                ];
            })->toArray();
        }

        // Search Tenants (by name or public_name)
        $tenants = Tenant::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('public_name', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($tenants->isNotEmpty()) {
            $results['tenants'] = $tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->public_name ?? $tenant->name ?? 'Unnamed',
                    'subtitle' => $tenant->email ?? '',
                    'url' => route('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                ];
            })->toArray();
        }

        // Search Customers (by first_name, last_name, or email)
        $customers = Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
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
                    'url' => route('filament.admin.resources.customers.edit', ['record' => $customer]),
                ];
            })->toArray();
        }

        // Search Users (by name or email)
        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($users->isNotEmpty()) {
            $results['users'] = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'subtitle' => $user->email,
                    'url' => route('filament.admin.resources.users.edit', ['record' => $user]),
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
            $tenantId = $tenant;

        if (!$tenantId) {
            return response()->json([]);
        }

        // Search Navigation/Pages first
        $pages = $this->searchTenantPages($query, $tenantId);
        if (!empty($pages)) {
            $results['pages'] = $pages;
        }

        // Search Events (by title - translatable JSON field)
        // Use whereRaw to bypass Laravel's cast handling for JSON columns
        $events = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("title LIKE ?", ['%' . $query . '%'])
            ->limit(5)
            ->get();

        if ($events->isNotEmpty()) {
            $results['events'] = $events->map(function ($event) use ($locale) {
                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                    'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                    'url' => route('filament.tenant.resources.events.edit', ['record' => $event, 'tenant' => $tenantId]),
                ];
            })->toArray();
        }

        // Search Venues (by name - translatable JSON field)
        // Use whereRaw to bypass Laravel's cast handling for JSON columns
        $venues = Venue::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("name LIKE ?", ['%' . $query . '%'])
            ->limit(5)
            ->get();

        if ($venues->isNotEmpty()) {
            $results['venues'] = $venues->map(function ($venue) use ($locale, $tenantId) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => $venue->city ?? '',
                    'url' => route('filament.tenant.resources.venues.edit', ['record' => $venue, 'tenant' => $tenantId]),
                ];
            })->toArray();
        }

        // Search Orders (by customer_email or ID)
        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('customer_email', 'LIKE', "%{$query}%")
                    ->orWhere('id', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($orders->isNotEmpty()) {
            $results['orders'] = $orders->map(function ($order) use ($tenantId) {
                return [
                    'id' => $order->id,
                    'name' => "Order #{$order->id}",
                    'subtitle' => $order->customer_email ?? '',
                    'url' => route('filament.tenant.resources.orders.view', ['record' => $order, 'tenant' => $tenantId]),
                ];
            })->toArray();
        }

        // Search Tickets (by code or customer_email) - filter through orders since tickets don't have tenant_id
        $tickets = Ticket::query()
            ->whereHas('order', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->where(function ($q) use ($query) {
                $q->where('code', 'LIKE', "%{$query}%")
                    ->orWhereHas('order', function ($orderQ) use ($query) {
                        $orderQ->where('customer_email', 'LIKE', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get();

        if ($tickets->isNotEmpty()) {
            $results['tickets'] = $tickets->map(function ($ticket) use ($tenantId) {
                return [
                    'id' => $ticket->id,
                    'name' => $ticket->code,
                    'subtitle' => $ticket->order?->customer_email ?? '',
                    'url' => route('filament.tenant.resources.tickets.view', ['record' => $ticket, 'tenant' => $tenantId]),
                ];
            })->toArray();
        }

        // Search Customers (by first_name, last_name, email, or phone)
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($customers->isNotEmpty()) {
            $results['customers'] = $customers->map(function ($customer) use ($tenantId) {
                $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                return [
                    'id' => $customer->id,
                    'name' => $name ?: ($customer->email ?? 'Unnamed'),
                    'subtitle' => $customer->email ?? '',
                    'url' => route('filament.tenant.resources.customers.edit', ['record' => $customer, 'tenant' => $tenantId]),
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
    private function searchTenantPages(string $query, $tenantId): array
    {
        $baseUrl = "/tenant/{$tenantId}";

        $pages = [
            // Top level
            ['name' => 'Dashboard', 'keywords' => ['dashboard', 'acasa', 'home', 'panou'], 'url' => $baseUrl, 'subtitle' => 'Overview dashboard'],
            ['name' => 'Events', 'keywords' => ['events', 'event', 'evenimente'], 'url' => "{$baseUrl}/events", 'subtitle' => 'Manage your events'],
            ['name' => 'Venues', 'keywords' => ['venues', 'venue', 'locatii', 'location'], 'url' => "{$baseUrl}/venues", 'subtitle' => 'Manage venues'],
            ['name' => 'Pages', 'keywords' => ['pages', 'page', 'pagini', 'content'], 'url' => "{$baseUrl}/pages", 'subtitle' => 'Website pages'],

            // Sales
            ['name' => 'Orders', 'keywords' => ['orders', 'order', 'comenzi', 'comanda', 'sales'], 'url' => "{$baseUrl}/orders", 'subtitle' => 'View customer orders'],
            ['name' => 'Tickets', 'keywords' => ['tickets', 'ticket', 'bilete', 'bilet'], 'url' => "{$baseUrl}/tickets", 'subtitle' => 'View issued tickets'],
            ['name' => 'Customers', 'keywords' => ['customers', 'customer', 'clienti', 'client'], 'url' => "{$baseUrl}/customers", 'subtitle' => 'Customer database'],

            // Services
            ['name' => 'Affiliates', 'keywords' => ['affiliates', 'affiliate', 'afiliati', 'partners'], 'url' => "{$baseUrl}/affiliates", 'subtitle' => 'Affiliate management'],

            // Website
            ['name' => 'Theme Editor', 'keywords' => ['theme', 'editor', 'design', 'culori', 'colors', 'style'], 'url' => "{$baseUrl}/theme-editor", 'subtitle' => 'Customize website theme'],

            // Settings
            ['name' => 'Settings', 'keywords' => ['settings', 'setari', 'configuration', 'config'], 'url' => "{$baseUrl}/settings", 'subtitle' => 'Account settings'],
            ['name' => 'Payment Config', 'keywords' => ['payment', 'plati', 'stripe', 'processor', 'gateway'], 'url' => "{$baseUrl}/payment-config", 'subtitle' => 'Payment configuration'],
            ['name' => 'Domains', 'keywords' => ['domains', 'domain', 'domenii', 'dns'], 'url' => "{$baseUrl}/settings", 'subtitle' => 'Domain management (in Settings)'],
            ['name' => 'Invoices', 'keywords' => ['invoices', 'invoice', 'facturi', 'factura', 'billing'], 'url' => "{$baseUrl}/invoices", 'subtitle' => 'View invoices'],

            // Bottom
            ['name' => 'Documentation', 'keywords' => ['documentation', 'docs', 'documentatie', 'help', 'ajutor'], 'url' => "{$baseUrl}/documentation", 'subtitle' => 'Help & documentation'],
            ['name' => 'Activity Log', 'keywords' => ['activity', 'log', 'activitate', 'jurnal', 'history'], 'url' => "{$baseUrl}/activity-log", 'subtitle' => 'View activity history'],
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
