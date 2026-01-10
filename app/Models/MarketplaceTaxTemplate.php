<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceTaxTemplate extends Model
{
    protected $table = 'marketplace_tax_templates';

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'description',
        'html_content',
        'page_orientation',
        'html_content_page_2',
        'page_2_orientation',
        'type',
        'trigger',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Template triggers
     */
    public const TRIGGERS = [
        'after_event_published' => 'After Event is Published',
        'after_event_finished' => 'After Event is Finished',
    ];

    /**
     * Page orientations
     */
    public const ORIENTATIONS = [
        'portrait' => 'Portrait (A4 Vertical)',
        'landscape' => 'Landscape (A4 Horizontal)',
    ];

    /**
     * Template types
     */
    public const TYPES = [
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'fiscal_receipt' => 'Fiscal Receipt',
        'proforma' => 'Proforma Invoice',
        'credit_note' => 'Credit Note',
        'other' => 'Other',
    ];

    /**
     * Available template variables organized by section
     */
    public const TEMPLATE_VARIABLES = [
        'Tax Registry' => [
            '{{tax_registry_country}}' => 'Country',
            '{{tax_registry_county}}' => 'County',
            '{{tax_registry_city}}' => 'City',
            '{{tax_registry_name}}' => 'Name',
            '{{tax_registry_subname}}' => 'Subname',
            '{{tax_registry_address}}' => 'Address',
            '{{tax_registry_phone}}' => 'Phone',
            '{{tax_registry_email}}' => 'Email',
            '{{tax_registry_cif}}' => 'CIF / Tax ID',
            '{{tax_registry_iban}}' => 'IBAN',
        ],
        'Marketplace' => [
            '{{marketplace_legal_name}}' => 'Legal Company Name',
            '{{marketplace_vat}}' => 'CUI / VAT Number',
            '{{marketplace_trade_register}}' => 'Trade Register',
            '{{marketplace_address}}' => 'Street Address',
            '{{marketplace_city}}' => 'City',
            '{{marketplace_state}}' => 'State/County',
            '{{marketplace_email}}' => 'Contact Email',
            '{{marketplace_phone}}' => 'Contact Phone',
            '{{marketplace_website}}' => 'Website',
        ],
        'Organizer' => [
            '{{organizer_name}}' => 'Name',
            '{{organizer_email}}' => 'Email',
            '{{organizer_company_name}}' => 'Company Name',
            '{{organizer_tax_id}}' => 'Tax ID / VAT',
            '{{organizer_registration_number}}' => 'Registration Number',
            '{{organizer_address}}' => 'Company Address',
        ],
        'Event' => [
            '{{event_name}}' => 'Event Name',
            '{{event_date}}' => 'Event Date',
            '{{venue_name}}' => 'Venue Name',
            '{{venue_address}}' => 'Venue Address',
            '{{ticket_types_table}}' => 'Ticket Types Table (with names, prices, available, sold)',
            '{{total_tickets_available}}' => 'Total Tickets Available (Initial)',
            '{{total_tickets_sold}}' => 'Total Tickets Sold',
            '{{total_sales_value}}' => 'Total Sales Value',
            '{{total_sales_currency}}' => 'Sales Currency',
        ],
        'Order' => [
            '{{order_number}}' => 'Order Number',
            '{{order_date}}' => 'Order Date',
            '{{order_total}}' => 'Order Total',
            '{{order_currency}}' => 'Currency',
            '{{customer_name}}' => 'Customer Name',
            '{{customer_email}}' => 'Customer Email',
        ],
        'Date/Time' => [
            '{{current_day}}' => 'Current Day (01-31)',
            '{{current_month}}' => 'Current Month (01-12)',
            '{{current_month_name}}' => 'Current Month Name',
            '{{current_year}}' => 'Current Year',
            '{{current_date}}' => 'Current Date (DD.MM.YYYY)',
            '{{current_datetime}}' => 'Current Date & Time',
        ],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $baseSlug = Str::slug($template->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('marketplace_client_id', $template->marketplace_client_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $template->slug = $slug;
            }
        });

        // Ensure only one default per type
        static::saving(function ($template) {
            if ($template->is_default) {
                static::where('marketplace_client_id', $template->marketplace_client_id)
                    ->where('type', $template->type)
                    ->where('id', '!=', $template->id ?? 0)
                    ->update(['is_default' => false]);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // =========================================
    // Template Processing
    // =========================================

    /**
     * Process template with given variables
     */
    public function processTemplate(array $variables): string
    {
        $content = $this->html_content;

        foreach ($variables as $key => $value) {
            // Handle both {{variable}} and {{ variable }} formats
            $content = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                $value ?? '',
                $content
            );
        }

        return $content;
    }

    /**
     * Get all variables for a specific context
     */
    public static function getVariablesForContext(
        ?MarketplaceTaxRegistry $taxRegistry = null,
        ?MarketplaceClient $marketplace = null,
        ?MarketplaceOrganizer $organizer = null,
        ?MarketplaceEvent $event = null,
        ?Order $order = null
    ): array {
        $variables = [];

        // Tax Registry variables
        if ($taxRegistry) {
            $variables = array_merge($variables, $taxRegistry->toTemplateVariables());
        }

        // Marketplace variables
        if ($marketplace) {
            $variables['marketplace_legal_name'] = $marketplace->legal_name ?? $marketplace->name ?? '';
            $variables['marketplace_vat'] = $marketplace->vat_number ?? '';
            $variables['marketplace_trade_register'] = $marketplace->trade_register ?? '';
            $variables['marketplace_address'] = $marketplace->address ?? '';
            $variables['marketplace_city'] = $marketplace->city ?? '';
            $variables['marketplace_state'] = $marketplace->state ?? '';
            $variables['marketplace_email'] = $marketplace->contact_email ?? '';
            $variables['marketplace_phone'] = $marketplace->contact_phone ?? '';
            $variables['marketplace_website'] = $marketplace->website ?? $marketplace->domain ?? '';
        }

        // Organizer variables
        if ($organizer) {
            $variables['organizer_name'] = $organizer->name ?? '';
            $variables['organizer_email'] = $organizer->email ?? '';
            $variables['organizer_company_name'] = $organizer->company_name ?? '';
            $variables['organizer_tax_id'] = $organizer->tax_id ?? $organizer->cif ?? '';
            $variables['organizer_registration_number'] = $organizer->registration_number ?? '';
            $variables['organizer_address'] = $organizer->company_address ?? $organizer->address ?? '';
        }

        // Event variables
        if ($event) {
            $variables['event_name'] = $event->title ?? $event->name ?? '';
            $variables['event_date'] = $event->start_date ? $event->start_date->format('d.m.Y H:i') : '';

            // Venue info
            if ($event->venue) {
                $variables['venue_name'] = $event->venue->name ?? '';
                $variables['venue_address'] = $event->venue->address ?? '';
            } else {
                $variables['venue_name'] = '';
                $variables['venue_address'] = '';
            }

            // Calculate totals
            $totalAvailable = 0;
            $totalSold = 0;
            $totalSalesValue = 0;
            $currency = 'RON';

            // Build ticket types table with available column
            $ticketTypesHtml = '<table style="width:100%; border-collapse: collapse;">';
            $ticketTypesHtml .= '<thead><tr>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Ticket Type</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Price</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Available</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Sold</th>';
            $ticketTypesHtml .= '</tr></thead><tbody>';

            if ($event->ticketTypes) {
                foreach ($event->ticketTypes as $ticketType) {
                    $available = (int) ($ticketType->quantity ?? $ticketType->initial_quantity ?? 0);
                    $sold = (int) ($ticketType->sold_count ?? $ticketType->tickets_sold ?? 0);
                    $price = (float) ($ticketType->price ?? 0);
                    $currency = $ticketType->currency ?? 'RON';

                    $totalAvailable += $available;
                    $totalSold += $sold;
                    $totalSalesValue += ($sold * $price);

                    $ticketTypesHtml .= '<tr>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px;">' . htmlspecialchars($ticketType->name ?? '') . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . number_format($price, 2) . ' ' . $currency . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $available . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $sold . '</td>';
                    $ticketTypesHtml .= '</tr>';
                }
            }
            $ticketTypesHtml .= '</tbody></table>';

            $variables['ticket_types_table'] = $ticketTypesHtml;
            $variables['total_tickets_available'] = $totalAvailable;
            $variables['total_tickets_sold'] = $totalSold;
            $variables['total_sales_value'] = number_format($totalSalesValue, 2);
            $variables['total_sales_currency'] = $currency;
        }

        // Order variables
        if ($order) {
            $variables['order_number'] = $order->order_number ?? $order->id ?? '';
            $variables['order_date'] = $order->created_at ? $order->created_at->format('d.m.Y H:i') : '';
            $variables['order_total'] = number_format($order->total ?? 0, 2);
            $variables['order_currency'] = $order->currency ?? 'RON';
            $variables['customer_name'] = $order->customer_name ?? ($order->customer->full_name ?? '');
            $variables['customer_email'] = $order->customer_email ?? ($order->customer->email ?? '');
        }

        // Date/Time variables (always available)
        $now = now();
        $variables['current_day'] = $now->format('d');
        $variables['current_month'] = $now->format('m');
        $variables['current_month_name'] = $now->translatedFormat('F');
        $variables['current_year'] = $now->format('Y');
        $variables['current_date'] = $now->format('d.m.Y');
        $variables['current_datetime'] = $now->format('d.m.Y H:i');

        return $variables;
    }

    /**
     * Get flat list of all available variables
     */
    public static function getAllVariables(): array
    {
        $all = [];
        foreach (self::TEMPLATE_VARIABLES as $section => $variables) {
            foreach ($variables as $key => $label) {
                $all[$key] = $label;
            }
        }
        return $all;
    }
}
