<?php

namespace App\Models;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplacePayout;
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
        'by_proxy',
        'general_tax_ids',
    ];

    protected $attributes = [
        'html_content' => '',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'by_proxy' => 'boolean',
        'general_tax_ids' => 'array',
    ];

    /**
     * Template triggers
     */
    public const TRIGGERS = [
        'after_event_published' => 'After Event is Published',
        'after_event_finished' => 'After Event is Finished',
        'after_organizer_registered' => 'After Organizer is Registered',
        'after_payout_completed' => 'After Payout is Completed',
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
        'cerere_avizare' => 'Cerere avizare',
        'declaratie_impozite' => 'Declaratie impozite',
        'organizer_contract' => 'Organizer Contract',
        'decont' => 'Decont',
        'decont_ontop' => 'Decont On-Top',
        'decont_inclus' => 'Decont Inclus',
        'pv_distrugere' => 'PV Distrugere',
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
            '{{tax_registry_commune}}' => 'Comună',
            '{{tax_registry_name}}' => 'Name',
            '{{tax_registry_subname}}' => 'Subname',
            '{{tax_registry_address}}' => 'Address',
            '{{tax_registry_directions}}' => 'Indicații',
            '{{tax_registry_phone}}' => 'Phone',
            '{{tax_registry_email}}' => 'Email',
            '{{tax_registry_email2}}' => 'Email 2',
            '{{tax_registry_website_url}}' => 'Website URL',
            '{{tax_registry_cif}}' => 'CIF / Tax ID',
            '{{tax_registry_iban}}' => 'IBAN',
            '{{tax_registry_siruta_code}}' => 'Cod SIRUTA',
            '{{tax_registry_coat_of_arms}}' => 'Stema (tag img complet)',
            '{{tax_registry_tax_rate}}' => 'Cotă impozit (ex: 2%)',
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
            '{{marketplace_bank_name}}' => 'Bank Name',
            '{{marketplace_contract_number}}' => 'Contract Number (incremental)',
            '{{marketplace_signature_image}}' => 'Signature Image',
            '{{marketplace_logo_url}}' => 'Logo (tag img complet, se inserează direct)',
            '{{marketplace_invoice_preparer}}' => 'Persoana care completează factura',
            '{{proxy_signature_image}}' => 'Semnătura împuternicit (tag img complet, din admin curent)',
        ],
        'Organizer' => [
            '{{organizer_name}}' => 'Name',
            '{{organizer_email}}' => 'Email',
            '{{organizer_phone}}' => 'Phone',
            '{{organizer_company_name}}' => 'Company Name',
            '{{organizer_tax_id}}' => 'Tax ID / VAT',
            '{{organizer_registration_number}}' => 'Registration Number',
            '{{organizer_address}}' => 'Company Address',
            '{{organizer_city}}' => 'Company City',
            '{{organizer_county}}' => 'Company County/State',
            '{{organizer_vat_status}}' => 'VAT Status (platitor/neplatitor TVA cota %)',
            '{{organizer_work_mode}}' => 'Work Mode (Exclusiv/Neexclusiv)',
            '{{organizer_bank_name}}' => 'Bank Name',
            '{{organizer_iban}}' => 'IBAN',
        ],
        'Organizer Guarantor' => [
            '{{guarantor_first_name}}' => 'Guarantor First Name (Prenume)',
            '{{guarantor_last_name}}' => 'Guarantor Last Name (Nume)',
            '{{guarantor_cnp}}' => 'Guarantor CNP',
            '{{guarantor_id_type}}' => 'ID Type (BI/CI)',
            '{{guarantor_id_series}}' => 'ID Series (Serie)',
            '{{guarantor_id_number}}' => 'ID Number (Numar)',
            '{{guarantor_id_issued_by}}' => 'ID Issued By (Eliberat de)',
            '{{guarantor_id_issued_date}}' => 'ID Issue Date (La data de)',
            '{{guarantor_address}}' => 'Guarantor Address',
            '{{guarantor_city}}' => 'Guarantor City',
        ],
        'Împuternicit (admin sau organizator)' => [
            '{{proxy_full_name}}' => 'Nume și prenume împuternicit',
            '{{proxy_role}}' => 'Calitate (ex: Administrator)',
            '{{proxy_address}}' => 'Adresa împuternicit',
            '{{proxy_country}}' => 'Țara',
            '{{proxy_county}}' => 'Județ',
            '{{proxy_city}}' => 'Oraș',
            '{{proxy_sector}}' => 'Sector (doar București)',
            '{{proxy_id_series}}' => 'Serie CI',
            '{{proxy_id_number}}' => 'Număr CI',
            '{{proxy_cnp}}' => 'CNP',
            '{{proxy_phone}}' => 'Telefon',
        ],
        'Event' => [
            '{{event_name}}' => 'Event Name',
            '{{event_date}}' => 'Event Date',
            '{{event_city}}' => 'Event City (from venue)',
            '{{venue_name}}' => 'Venue Name',
            '{{venue_address}}' => 'Venue Address',
            '{{ticket_types_table}}' => 'Ticket Types Table (with names, prices, available, sold)',
            '{{ticket_types_series}}' => 'Ticket Types with Series (Name: START - END)',
            '{{ticket_types_rows}}' => 'Ticket Types Table Rows (name, stock, price, value, series)',
            '{{ticket_types_total_row}}' => 'Ticket Types Total Row (TOTAL, stock, X, value, X)',
            '{{total_tickets_for_sale}}' => 'Total Tickets For Sale',
            '{{total_value_for_sale}}' => 'Total Value of Tickets For Sale',
            '{{total_tickets_available}}' => 'Total Tickets Available (Initial)',
            '{{total_tickets_sold}}' => 'Total Tickets Sold',
            '{{total_sales_value}}' => 'Total Sales Value',
            '{{music_stamp_value}}' => 'Valoare timbru muzical (2% din vânzări)',
            '{{taxable_income}}' => 'Încasări supuse impozitului (vânzări - timbru muzical)',
            '{{tax_due}}' => 'Impozit datorat (cotă registry × încasări supuse impozitului)',
            '{{total_sales_currency}}' => 'Sales Currency',
            '{{unsold_tickets_rows}}' => 'PV Distrugere: rânduri bilete nevândute (exclude abonamente)',
            '{{total_unsold_tickets}}' => 'PV Distrugere: total bilete nevândute (exclude abonamente)',
            '{{total_unsold_value}}' => 'PV Distrugere: valoare totală bilete nevândute (exclude abonamente)',
            '{{unsold_subscriptions_rows}}' => 'PV Distrugere: rânduri abonamente nevândute',
            '{{total_unsold_subscriptions}}' => 'PV Distrugere: total abonamente nevândute',
            '{{total_unsold_subscriptions_value}}' => 'PV Distrugere: valoare totală abonamente nevândute',
        ],
        'Order' => [
            '{{order_number}}' => 'Order Number',
            '{{order_date}}' => 'Order Date',
            '{{order_total}}' => 'Order Total',
            '{{order_currency}}' => 'Currency',
            '{{customer_name}}' => 'Customer Name',
            '{{customer_email}}' => 'Customer Email',
        ],
        'Contract' => [
            '{{contract_number_series}}' => 'Contract Number & Series',
            '{{contract_date}}' => 'Contract Date',
        ],
        'Invoice' => [
            '{{invoice_number}}' => 'Invoice Number',
            '{{invoice_issue_date}}' => 'Issue Date (DD.MM.YYYY)',
            '{{invoice_due_date}}' => 'Due Date (DD.MM.YYYY)',
            '{{invoice_period}}' => 'Invoice Period (e.g., Ianuarie 2026)',
            '{{invoice_currency}}' => 'Currency (RON, EUR, etc.)',
            '{{invoice_subtotal}}' => 'Subtotal (without VAT)',
            '{{invoice_vat_rate}}' => 'VAT Rate (%)',
            '{{invoice_vat_amount}}' => 'VAT Amount',
            '{{invoice_total}}' => 'Total Amount (with VAT)',
            '{{invoice_status}}' => 'Status (Achitată/Neachitată)',
            '{{invoice_items_rows}}' => 'Invoice Items Table Rows (HTML <tr> elements)',
            '{{invoice_commission_rate}}' => 'Commission Rate (%)',
            '{{invoice_order_count}}' => 'Number of Orders',
            '{{invoice_total_sales}}' => 'Total Sales Amount',
            '{{issuer_name}}' => 'Issuer Company Name',
            '{{issuer_cui}}' => 'Issuer CUI/CIF',
            '{{issuer_reg_com}}' => 'Issuer Trade Register',
            '{{issuer_address}}' => 'Issuer Address',
            '{{issuer_bank_name}}' => 'Issuer Bank Name',
            '{{issuer_iban}}' => 'Issuer IBAN',
            '{{issuer_email}}' => 'Issuer Email',
            '{{issuer_phone}}' => 'Issuer Phone',
            '{{issuer_website}}' => 'Issuer Website',
            '{{issuer_vat_payer}}' => 'Issuer VAT Payer Status',
            '{{client_name}}' => 'Client Company Name',
            '{{client_cui}}' => 'Client CUI/CIF',
            '{{client_reg_com}}' => 'Client Trade Register',
            '{{client_address}}' => 'Client Address',
        ],
        'Payout' => [
            '{{payout_number}}' => 'Payout Reference Number',
            '{{payout_date}}' => 'Payout Completion Date',
            '{{payout_amount}}' => 'Net Payout Amount (de plată)',
            '{{payout_currency}}' => 'Payout Currency',
            '{{payout_gross_amount}}' => 'Gross Amount (sumă brută)',
            '{{payout_commission_amount}}' => 'Commission Amount',
            '{{payout_commission_percent}}' => 'Commission Percentage (ex: 6%)',
            '{{payout_vat_rate}}' => 'Cota TVA (ex: 19% sau 0%)',
            '{{payout_vat_amount}}' => 'Valoare TVA calculată',
            '{{payout_total_with_vat}}' => 'Total de plată cu TVA',
            '{{payout_fees_amount}}' => 'Fees Amount (taxe)',
            '{{payout_adjustments_amount}}' => 'Adjustments Amount (ajustări)',
            '{{payout_adjustments_note}}' => 'Adjustments Note',
            '{{payout_period_start}}' => 'Period Start Date',
            '{{payout_period_end}}' => 'Period End Date',
            '{{payout_payment_reference}}' => 'Payment/Transfer Reference',
            '{{payout_payment_method}}' => 'Payment Method',
            '{{payout_bank_name}}' => 'Payout Bank Name',
            '{{payout_iban}}' => 'Payout IBAN',
            '{{payout_account_holder}}' => 'Payout Account Holder',
            '{{payout_sequence_number}}' => 'Nr. ordine decont per eveniment (1, 2, 3...)',
            '{{payout_advance_amount}}' => 'Sumă decontată în avans (lei)',
        ],
        'Payout - Bilete' => [
            '{{tickets_breakdown_label}}' => 'Detaliu bilete vândute (ex: 50lei*2+60lei*16)',
            '{{total_tickets_sold}}' => 'Total bilete vândute',
            '{{total_tickets_refunded}}' => 'Total bilete returnate',
        ],
        'Payout - Bilete Pretipărite' => [
            '{{payout_preprinted_ticket_fee}}' => 'Taxă per bilet pretipărit (lei/buc)',
            '{{total_preprinted_tickets}}' => 'Nr. bilete pretipărite',
            '{{payout_preprinted_amount}}' => 'Valoare bilete pretipărite (lei)',
            '{{payout_preprinted_shipping_date}}' => 'Data expediție curier',
            '{{payout_shipping_amount}}' => 'Taxă curierat (lei)',
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
            // Skip array values - they can't be used in string replacement
            if (is_array($value)) {
                continue;
            }

            // Handle both {{variable}} and {{ variable }} formats
            $content = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                (string) ($value ?? ''),
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
        MarketplaceEvent|Event|null $event = null,
        ?Order $order = null,
        bool $incrementContractNumber = false,
        ?MarketplacePayout $payout = null,
        ?MarketplaceAdmin $generatedBy = null,
        ?self $template = null
    ): array {
        $variables = [];

        // Tax Registry variables
        if ($taxRegistry) {
            $variables = array_merge($variables, $taxRegistry->toTemplateVariables());
        }

        // Marketplace variables
        if ($marketplace) {
            $variables['marketplace_legal_name'] = $marketplace->company_name ?? $marketplace->name ?? '';
            $variables['marketplace_vat'] = $marketplace->cui ?? $marketplace->vat_number ?? '';
            $variables['marketplace_trade_register'] = $marketplace->reg_com ?? $marketplace->trade_register ?? '';
            $variables['marketplace_address'] = $marketplace->address ?? '';
            $variables['marketplace_city'] = $marketplace->city ?? '';
            $variables['marketplace_state'] = $marketplace->state ?? '';
            $variables['marketplace_email'] = $marketplace->contact_email ?? '';
            $variables['marketplace_phone'] = $marketplace->contact_phone ?? '';
            $variables['marketplace_website'] = $marketplace->website ?? $marketplace->domain ?? '';
            $variables['marketplace_bank_name'] = $marketplace->bank_name ?? '';
            $variables['marketplace_contract_number'] = $incrementContractNumber
                ? $marketplace->getNextContractNumber()
                : $marketplace->getCurrentContractNumber();

            // Signature image
            if ($marketplace->signature_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($marketplace->signature_image)) {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($marketplace->signature_image);
                $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($marketplace->signature_image) ?: 'image/png';
                // Convert WebP to PNG for DomPDF compatibility
                if (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp(\Illuminate\Support\Facades\Storage::disk('public')->path($marketplace->signature_image));
                    if ($img) {
                        ob_start();
                        imagepng($img);
                        $content = ob_get_clean();
                        imagedestroy($img);
                        $mime = 'image/png';
                    }
                }
                $b64 = 'data:' . $mime . ';base64,' . base64_encode($content);
                $variables['marketplace_signature_image'] = '<img src="' . $b64 . '" alt="Semnătura" style="max-height:100px;max-width:250px;display:block;" />';
            } else {
                $variables['marketplace_signature_image'] = '';
            }

            // Proxy signature image (from currently authenticated admin)
            $variables['proxy_signature_image'] = '';
            $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
            if ($admin && $admin->proxy_signature_image
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($admin->proxy_signature_image)) {
                $sigContent = \Illuminate\Support\Facades\Storage::disk('public')->get($admin->proxy_signature_image);
                $sigMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($admin->proxy_signature_image) ?: 'image/png';
                $sigB64 = 'data:' . $sigMime . ';base64,' . base64_encode($sigContent);
                $variables['proxy_signature_image'] = '<img src="' . $sigB64 . '" alt="Semnătura împuternicit" style="max-height:100px;max-width:250px;display:block;" />';
            }
        }

        // Organizer variables
        if ($organizer) {
            $variables['organizer_name'] = $organizer->name ?? '';
            $variables['organizer_email'] = $organizer->email ?? '';
            $variables['organizer_company_name'] = $organizer->company_name ?? '';
            $variables['organizer_tax_id'] = $organizer->tax_id ?? $organizer->company_tax_id ?? '';
            $variables['organizer_registration_number'] = $organizer->registration_number ?? $organizer->company_registration ?? '';
            $variables['organizer_address'] = $organizer->company_address ?? $organizer->address ?? '';
            $variables['organizer_city'] = $organizer->company_city ?? $organizer->city ?? '';
            $variables['organizer_county'] = $organizer->company_county ?? $organizer->state ?? '';

            // VAT status - check tax_settings or marketplace settings
            $taxSettings = $organizer->tax_settings ?? [];
            $isVatPayer = $taxSettings['is_vat_payer'] ?? false;
            $vatRate = $taxSettings['vat_rate'] ?? ($marketplace?->settings['tax']['vat_rate'] ?? 19);
            $variables['organizer_vat_status'] = $isVatPayer
                ? "plătitor TVA bilete (cota {$vatRate}%)"
                : "TVA 0%";

            // Work mode (exclusiv/neexclusiv)
            $workMode = $organizer->work_mode ?? '';
            $variables['organizer_work_mode'] = match ($workMode) {
                'exclusive' => 'Exclusiv',
                'non_exclusive', 'nonexclusive' => 'Neexclusiv',
                default => $workMode,
            };

            // Bank details
            $variables['organizer_bank_name'] = $organizer->bank_name ?? '';
            $variables['organizer_iban'] = $organizer->iban ?? '';

            // Guarantor variables
            $variables['guarantor_first_name'] = $organizer->guarantor_first_name ?? '';
            $variables['guarantor_last_name'] = $organizer->guarantor_last_name ?? '';
            $variables['guarantor_cnp'] = $organizer->guarantor_cnp ?? '';
            $variables['guarantor_id_type'] = $organizer->guarantor_id_type ?? 'CI';
            $variables['guarantor_id_series'] = $organizer->guarantor_id_series ?? '';
            $variables['guarantor_id_number'] = $organizer->guarantor_id_number ?? '';
            $variables['guarantor_id_issued_by'] = $organizer->guarantor_id_issued_by ?? '';
            $variables['guarantor_id_issued_date'] = $organizer->guarantor_id_issued_date
                ? (is_string($organizer->guarantor_id_issued_date)
                    ? $organizer->guarantor_id_issued_date
                    : $organizer->guarantor_id_issued_date->format('d.m.Y'))
                : '';
            $variables['guarantor_address'] = $organizer->guarantor_address ?? '';
            $variables['guarantor_city'] = $organizer->guarantor_city ?? '';

            // Organizer phone
            $variables['organizer_phone'] = $organizer->phone ?? $organizer->contact_phone ?? '';
        }

        // Proxy (împuternicit) variables — prefer organizer's assigned proxy admin
        $proxyAdmin = null;
        if ($organizer && $organizer->proxy_admin_id) {
            $proxyAdmin = MarketplaceAdmin::find($organizer->proxy_admin_id);
        }
        // Fallback: explicit generatedBy or current authenticated admin
        if (!$proxyAdmin) {
            $proxyAdmin = $generatedBy ?? \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
        }

        if ($proxyAdmin instanceof MarketplaceAdmin && $proxyAdmin->proxy_full_name) {
            // Use marketplace admin proxy data
            $variables['proxy_full_name'] = $proxyAdmin->proxy_full_name ?? '';
            $variables['proxy_role'] = $proxyAdmin->proxy_role ?? '';
            $variables['proxy_address'] = $proxyAdmin->proxy_address ?? '';
            $variables['proxy_country'] = $proxyAdmin->proxy_country ?? '';
            $variables['proxy_county'] = $proxyAdmin->proxy_county ?? '';
            $variables['proxy_city'] = $proxyAdmin->proxy_city ?? '';
            $variables['proxy_sector'] = $proxyAdmin->proxy_sector ?? '';
            $variables['proxy_id_series'] = $proxyAdmin->proxy_id_series ?? '';
            $variables['proxy_id_number'] = $proxyAdmin->proxy_id_number ?? '';
            $variables['proxy_cnp'] = $proxyAdmin->proxy_cnp ?? '';
            $variables['proxy_phone'] = $proxyAdmin->proxy_phone ?? '';
        } elseif ($organizer) {
            // Fallback to organizer guarantor data
            $variables['proxy_full_name'] = trim(($organizer->guarantor_first_name ?? '') . ' ' . ($organizer->guarantor_last_name ?? ''));
            $variables['proxy_role'] = '';
            $variables['proxy_address'] = $organizer->guarantor_address ?? '';
            $variables['proxy_country'] = '';
            $variables['proxy_county'] = '';
            $variables['proxy_city'] = $organizer->guarantor_city ?? '';
            $variables['proxy_sector'] = '';
            $variables['proxy_id_series'] = $organizer->guarantor_id_series ?? '';
            $variables['proxy_id_number'] = $organizer->guarantor_id_number ?? '';
            $variables['proxy_cnp'] = $organizer->guarantor_cnp ?? '';
            $variables['proxy_phone'] = $organizer->phone ?? $organizer->contact_phone ?? '';
        } else {
            // Empty defaults
            $variables['proxy_full_name'] = '';
            $variables['proxy_role'] = '';
            $variables['proxy_address'] = '';
            $variables['proxy_country'] = '';
            $variables['proxy_county'] = '';
            $variables['proxy_city'] = '';
            $variables['proxy_sector'] = '';
            $variables['proxy_id_series'] = '';
            $variables['proxy_id_number'] = '';
            $variables['proxy_cnp'] = '';
            $variables['proxy_phone'] = '';
        }

        // Event variables
        if ($event) {
            // Handle translatable title
            $title = $event->title ?? $event->name ?? '';
            if (is_array($title)) {
                $title = $title['ro'] ?? $title['en'] ?? reset($title) ?: '';
            }
            $variables['event_name'] = $title;

            // Event date (date only, no time)
            $variables['event_date'] = $event->start_date
                ? $event->start_date->format('d.m.Y')
                : ($event->event_date ? $event->event_date->format('d.m.Y') : '');

            // Event city (from venue)
            $variables['event_city'] = $event->venue?->city ?? $event->venue_city ?? '';

            // Venue info - handle translatable names
            if ($event->venue) {
                $venueName = $event->venue->name ?? '';
                if (is_array($venueName)) {
                    $venueName = $venueName['ro'] ?? $venueName['en'] ?? reset($venueName) ?: '';
                }
                $variables['venue_name'] = $venueName;
                $variables['venue_address'] = $event->venue->address ?? '';
            } else {
                $variables['venue_name'] = $event->suggested_venue_name ?? $event->venue_name ?? '';
                $variables['venue_address'] = $event->venue_address ?? '';
            }

            // Calculate totals
            $totalAvailable = 0;
            $totalSold = 0;
            $totalSalesValue = 0;
            $totalForSale = 0;
            $totalValueForSale = 0;
            $currency = 'RON';

            // Build ticket types table with available column
            $ticketTypesHtml = '<table style="width:100%; border-collapse: collapse;">';
            $ticketTypesHtml .= '<thead><tr>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Ticket Type</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Price</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Available</th>';
            $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Sold</th>';
            $ticketTypesHtml .= '</tr></thead><tbody>';

            // Arrays for new variables
            $ticketSeriesList = [];
            $ticketRowsHtml = '';

            // PV Distrugere: unsold tickets rows (NON-subscription)
            $unsoldRowsHtml = '';
            $totalUnsold = 0;
            $totalUnsoldValue = 0;

            // PV Distrugere: unsold subscriptions rows
            $unsoldSubscriptionsRowsHtml = '';
            $totalUnsoldSubscriptions = 0;
            $totalUnsoldSubscriptionsValue = 0;

            if ($event->ticketTypes) {
                foreach ($event->ticketTypes as $ticketType) {
                    // Skip non-declarable ticket types for document generation
                    if (isset($ticketType->is_declarable) && $ticketType->is_declarable === false) {
                        continue;
                    }
                    $isSubscription = (bool) ($ticketType->is_subscription ?? false);
                    $available = (int) ($ticketType->quota_total ?? $ticketType->capacity ?? 0);
                    $sold = (int) ($ticketType->quota_sold ?? 0);
                    $price = (float) ($ticketType->display_price ?? $ticketType->price ?? 0);
                    $currency = $ticketType->currency ?? 'RON';
                    $seriesStart = $ticketType->series_start ?? '';
                    $seriesEnd = $ticketType->series_end ?? '';
                    $ticketName = htmlspecialchars($ticketType->name ?? '');

                    $totalAvailable += $available;
                    $totalSold += $sold;
                    $totalSalesValue += ($sold * $price);
                    $totalForSale += $available;
                    $totalValueForSale += ($available * $price);

                    // Original table
                    $ticketTypesHtml .= '<tr>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px;">' . $ticketName . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . number_format($price, 2) . ' ' . $currency . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $available . '</td>';
                    $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $sold . '</td>';
                    $ticketTypesHtml .= '</tr>';

                    // Series format: "VIP: ABC001 - ABC100"
                    if ($seriesStart || $seriesEnd) {
                        $ticketSeriesList[] = $ticketName . ': ' . $seriesStart . ' - ' . $seriesEnd;
                    }

                    // Custom rows format with series
                    $seriesDisplay = ($seriesStart || $seriesEnd) ? $seriesStart . ' - ' . $seriesEnd : '-';
                    $ticketRowsHtml .= '<tr>';
                    $ticketRowsHtml .= '<td class="left-align">' . $ticketName . '</td>';
                    $ticketRowsHtml .= '<td>' . $available . '</td>';
                    $ticketRowsHtml .= '<td>' . number_format($price, 2) . '</td>';
                    $ticketRowsHtml .= '<td>' . number_format($available * $price, 2) . '</td>';
                    $ticketRowsHtml .= '<td><span class="underline-blue">' . $seriesDisplay . '</span></td>';
                    $ticketRowsHtml .= '</tr>';

                    // PV Distrugere: calculate unsold tickets with series range
                    $unsold = max(0, $available - $sold);
                    if ($unsold > 0) {
                        $unsoldValue = $unsold * $price;
                        $unsoldSeriesDisplay = '-';

                        if ($seriesStart && $seriesEnd && preg_match('/^(.+-)(\d+)$/', $seriesEnd, $mEnd)) {
                            $seriesPrefix = $mEnd[1];
                            $unsoldStartNum = $sold + 1;
                            $padLen = strlen($mEnd[2]);
                            $unsoldSeriesStart = $seriesPrefix . str_pad($unsoldStartNum, $padLen, '0', STR_PAD_LEFT);
                            $unsoldSeriesDisplay = $unsoldSeriesStart . ' — ' . $seriesEnd;
                        }

                        $rowHtml = '<tr>';
                        $rowHtml .= '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $unsoldSeriesDisplay . '</td>';
                        $rowHtml .= '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $unsold . '</td>';
                        $rowHtml .= '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . number_format($price, 2) . '</td>';
                        $rowHtml .= '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . number_format($unsoldValue, 2) . '</td>';
                        $rowHtml .= '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $ticketName . '</td>';
                        $rowHtml .= '</tr>';

                        if ($isSubscription) {
                            $totalUnsoldSubscriptions += $unsold;
                            $totalUnsoldSubscriptionsValue += $unsoldValue;
                            $unsoldSubscriptionsRowsHtml .= $rowHtml;
                        } else {
                            $totalUnsold += $unsold;
                            $totalUnsoldValue += $unsoldValue;
                            $unsoldRowsHtml .= $rowHtml;
                        }
                    }
                }
            }
            $ticketTypesHtml .= '</tbody></table>';

            // Total row
            $totalRowHtml = '<tr class="total-row">';
            $totalRowHtml .= '<td><span class="bold">TOTAL</span></td>';
            $totalRowHtml .= '<td>' . $totalForSale . '</td>';
            $totalRowHtml .= '<td>X</td>';
            $totalRowHtml .= '<td>' . number_format($totalValueForSale, 2) . '</td>';
            $totalRowHtml .= '<td>X</td>';
            $totalRowHtml .= '</tr>';

            $variables['ticket_types_table'] = $ticketTypesHtml;
            $variables['ticket_types_series'] = implode("\n", $ticketSeriesList);
            $variables['ticket_types_rows'] = $ticketRowsHtml;
            $variables['ticket_types_total_row'] = $totalRowHtml;
            $variables['total_tickets_for_sale'] = $totalForSale;
            $variables['total_value_for_sale'] = number_format($totalValueForSale, 2);
            $variables['total_tickets_available'] = $totalAvailable;
            $variables['total_tickets_sold'] = $totalSold;
            $variables['total_sales_value'] = number_format($totalSalesValue, 2);
            $variables['total_sales_currency'] = $currency;

            // === Calcule pentru decont impozit pe spectacole ===
            // Sumează taxele generale aplicabile (din template->general_tax_ids)
            // Filtrate după event_types: doar taxele care includ unul dintre tipurile evenimentului
            $musicStampValue = 0;
            $generalTaxIds = $template?->general_tax_ids ?? [];

            if (!empty($generalTaxIds) && $event) {
                $eventTypeIds = $event->relationLoaded('eventTypes')
                    ? $event->eventTypes->pluck('id')->toArray()
                    : $event->eventTypes()->pluck('event_types.id')->toArray();

                $applicableTaxes = \App\Models\Tax\GeneralTax::whereIn('id', $generalTaxIds)
                    ->where('is_active', true)
                    ->with('eventTypes:id')
                    ->get();

                foreach ($applicableTaxes as $tax) {
                    $taxEventTypeIds = $tax->eventTypes->pluck('id')->toArray();

                    // Apply if: tax has no event_types restriction (global) OR matches at least one event type
                    $applies = empty($taxEventTypeIds) || !empty(array_intersect($taxEventTypeIds, $eventTypeIds));

                    if (!$applies) continue;

                    if ($tax->value_type === 'percentage') {
                        $musicStampValue += round($totalSalesValue * (float) $tax->value / 100, 2);
                    } else {
                        $musicStampValue += (float) $tax->value;
                    }
                }
            }

            // Încasări supuse impozitului = total vânzări - taxe aplicate
            $taxableIncome = round($totalSalesValue - $musicStampValue, 2);

            // Impozit datorat = cota tax registry * încasări supuse impozitului
            $taxRate = $taxRegistry?->tax_rate !== null ? (float) $taxRegistry->tax_rate : 0;
            $taxDue = round($taxableIncome * $taxRate / 100, 2);

            $variables['music_stamp_value'] = number_format($musicStampValue, 2);
            $variables['taxable_income'] = number_format($taxableIncome, 2);
            $variables['tax_due'] = number_format($taxDue, 2);

            // PV Distrugere variables — unsold tickets (excluding subscriptions)
            $variables['unsold_tickets_rows'] = $unsoldRowsHtml;
            $variables['total_unsold_tickets'] = $totalUnsold;
            $variables['total_unsold_value'] = number_format($totalUnsoldValue, 2);

            // PV Distrugere variables — unsold subscriptions
            $variables['unsold_subscriptions_rows'] = $unsoldSubscriptionsRowsHtml;
            $variables['total_unsold_subscriptions'] = $totalUnsoldSubscriptions;
            $variables['total_unsold_subscriptions_value'] = number_format($totalUnsoldSubscriptionsValue, 2);
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

        // Contract variables (from organizer)
        if ($organizer) {
            $variables['contract_number_series'] = $organizer->contract_number_series ?? '';
            $variables['contract_date'] = $organizer->contract_date
                ? (is_string($organizer->contract_date)
                    ? $organizer->contract_date
                    : $organizer->contract_date->format('d.m.Y'))
                : '';
        }

        // Payout variables
        if ($payout) {
            $variables['payout_number'] = $payout->reference ?? '';
            $variables['payout_date'] = $payout->completed_at
                ? $payout->completed_at->format('d.m.Y')
                : now()->format('d.m.Y');
            $variables['payout_amount'] = number_format($payout->amount ?? 0, 2);
            $variables['payout_currency'] = $payout->currency ?? 'RON';
            $variables['payout_gross_amount'] = number_format($payout->gross_amount ?? 0, 2);
            $variables['payout_commission_amount'] = number_format($payout->commission_amount ?? 0, 2);

            // Commission percentage: use per-ticket rate if available, then organizer rate, then calculate from amounts
            $commissionPercent = null;
            $ticketBreakdownForRate = $payout->ticket_breakdown ?? [];
            if (!empty($ticketBreakdownForRate)) {
                // Get commission from first ticket type in breakdown
                $firstItem = $ticketBreakdownForRate[0] ?? [];
                $commissionPercent = $firstItem['commission_rate'] ?? $firstItem['commission_percent'] ?? null;
            }
            if ($commissionPercent === null && $event) {
                // Try per-ticket-type commission from DB
                $firstTt = $event->ticketTypes()->first();
                if ($firstTt && $firstTt->commission_rate) {
                    $commissionPercent = $firstTt->commission_rate;
                }
            }
            if ($commissionPercent === null && $organizer) {
                $commissionPercent = $organizer->commission_rate;
            }
            if ($commissionPercent === null && $payout->gross_amount > 0) {
                $commissionPercent = round(($payout->commission_amount / $payout->gross_amount) * 100, 2);
            }
            $variables['payout_commission_percent'] = ($commissionPercent ?? 0) . '%';

            $variables['payout_fees_amount'] = number_format($payout->fees_amount ?? 0, 2);
            $variables['payout_adjustments_amount'] = number_format($payout->adjustments_amount ?? 0, 2);

            // VAT calculations
            $vatPayer = $organizer?->vat_payer ?? false;
            $vatRate = $vatPayer ? 19 : 0;
            $vatAmount = $vatPayer ? round(($payout->commission_amount ?? 0) * $vatRate / 100, 2) : 0;
            $variables['payout_vat_rate'] = $vatRate > 0 ? $vatRate . '%' : '0%';
            $variables['payout_vat_amount'] = number_format($vatAmount, 2);
            $variables['payout_total_with_vat'] = number_format(($payout->fees_amount ?? 0) + $vatAmount, 2);
            $variables['payout_adjustments_note'] = $payout->adjustments_note ?? '';
            $variables['payout_period_start'] = $payout->period_start
                ? $payout->period_start->format('d.m.Y')
                : '';
            $variables['payout_period_end'] = $payout->period_end
                ? $payout->period_end->format('d.m.Y')
                : '';
            $variables['payout_payment_reference'] = $payout->payment_reference ?? '';
            $variables['payout_payment_method'] = $payout->payment_method ?? '';

            // Payout method (bank details)
            $payoutMethod = $payout->payout_method ?? [];
            $variables['payout_bank_name'] = $payoutMethod['bank_name'] ?? $organizer?->bank_name ?? '';
            $variables['payout_iban'] = $payoutMethod['iban'] ?? $organizer?->iban ?? '';
            $variables['payout_account_holder'] = $payoutMethod['account_holder'] ?? '';

            // === NEW VARIABLES FOR DECONT TEMPLATE ===

            // Payout sequence number: how many payouts exist for same event+organizer before this one
            $sequenceNumber = 1;
            if ($payout->event_id && $payout->marketplace_organizer_id) {
                $sequenceNumber = MarketplacePayout::where('event_id', $payout->event_id)
                    ->where('marketplace_organizer_id', $payout->marketplace_organizer_id)
                    ->where('id', '<=', $payout->id)
                    ->whereIn('status', ['approved', 'completed'])
                    ->count();
            }
            $variables['payout_sequence_number'] = $sequenceNumber;

            // Tickets breakdown label: (50lei*2+60lei*16) format
            $breakdownParts = [];
            $ticketBreakdown = $payout->ticket_breakdown ?? [];
            $totalTicketsSold = 0;
            $totalTicketsRefunded = 0;
            foreach ($ticketBreakdown as $item) {
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
                $totalTicketsSold += $qty;
                if ($qty > 0 && $price > 0) {
                    $breakdownParts[] = number_format($price, 0) . 'lei*' . $qty;
                }
            }
            $variables['tickets_breakdown_label'] = !empty($breakdownParts) ? ' (' . implode('+', $breakdownParts) . ')' : '';
            // Use breakdown qty (from this decont) over total event sold
            if ($totalTicketsSold > 0) {
                $variables['total_tickets_sold'] = $totalTicketsSold;
            } elseif ($payout->gross_amount > 0 && !empty($events)) {
                // Fallback: estimate from gross amount and average ticket price
                $event = $events->first();
                $avgPrice = $event?->ticketTypes->avg(fn ($tt) => $tt->price_cents > 0 ? $tt->price_cents / 100 : 0);
                if ($avgPrice > 0) {
                    $variables['total_tickets_sold'] = (int) round($payout->gross_amount / $avgPrice);
                }
            }
            $variables['total_tickets_refunded'] = 0;

            // Preprinted tickets (physical tickets sent by courier)
            $preprintedData = $payout->payout_method['preprinted'] ?? [];
            $variables['payout_preprinted_ticket_fee'] = number_format((float) ($preprintedData['fee_per_ticket'] ?? 0), 2);
            $variables['total_preprinted_tickets'] = (int) ($preprintedData['count'] ?? 0);
            $variables['payout_preprinted_amount'] = number_format((float) ($preprintedData['total_amount'] ?? 0), 2);
            $variables['payout_preprinted_shipping_date'] = $preprintedData['shipping_date'] ?? '';
            $variables['payout_shipping_amount'] = number_format((float) ($preprintedData['shipping_cost'] ?? 0), 2);

            // Advance amount (previously settled amount)
            $variables['payout_advance_amount'] = number_format((float) ($payout->payout_method['advance_amount'] ?? 0), 2);

            // Marketplace invoice preparer
            $variables['marketplace_invoice_preparer'] = $marketplace?->settings['invoice_preparer'] ?? $marketplace?->contact_name ?? '';

            // Marketplace logo - convert to base64 data URI for PDF compatibility
            // Build logo as complete <img> tag with base64 (like signature_image)
            $variables['marketplace_logo_url'] = '';
            $logoPath = $marketplace?->logo ?? null;
            $logoUrl = $marketplace?->settings['logo_url'] ?? null;
            $logoB64 = null;

            if ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);
                $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
                if (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp(\Illuminate\Support\Facades\Storage::disk('public')->path($logoPath));
                    if ($img) { ob_start(); imagepng($img); $content = ob_get_clean(); imagedestroy($img); $mime = 'image/png'; }
                }
                $logoB64 = 'data:' . $mime . ';base64,' . base64_encode($content);
            } elseif ($logoUrl) {
                try {
                    $content = @file_get_contents($logoUrl);
                    if ($content) {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($content) ?: 'image/png';
                        if (str_contains($mime, 'webp') && function_exists('imagecreatefromstring')) {
                            $img = @imagecreatefromstring($content);
                            if ($img) { ob_start(); imagepng($img); $content = ob_get_clean(); imagedestroy($img); $mime = 'image/png'; }
                        }
                        $logoB64 = 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to load marketplace logo', ['url' => $logoUrl, 'error' => $e->getMessage()]);
                }
            }
            $variables['marketplace_logo_url'] = $logoB64
                ? '<img src="' . $logoB64 . '" alt="' . htmlspecialchars($marketplace?->name ?? '') . '" style="max-height:44px;max-width:130px;display:block;" />'
                : '';

            // Organizer phone (ensure it's available)
            $variables['organizer_phone'] = $variables['organizer_phone'] ?? $organizer?->phone ?? $organizer?->contact_phone ?? '';
        }

        // Date/Time variables (always available)
        $now = now();
        $variables['current_day'] = $now->format('d');
        $variables['current_month'] = $now->format('m');
        $variables['current_month_name'] = match ((int) $now->format('m')) {
            1 => 'Ianuarie', 2 => 'Februarie', 3 => 'Martie', 4 => 'Aprilie',
            5 => 'Mai', 6 => 'Iunie', 7 => 'Iulie', 8 => 'August',
            9 => 'Septembrie', 10 => 'Octombrie', 11 => 'Noiembrie', 12 => 'Decembrie',
            default => $now->format('F'),
        };
        $variables['current_year'] = $now->format('Y');
        $variables['current_date'] = $now->format('d.m.Y');
        $variables['current_datetime'] = $now->format('d.m.Y H:i');

        return $variables;
    }

    /**
     * Get invoice-specific variables from an Invoice model
     */
    public static function getInvoiceVariables(Invoice $invoice): array
    {
        $meta = $invoice->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];
        $currency = $invoice->currency ?? 'RON';

        // Build items HTML rows
        $itemsRows = '';
        $nr = 0;
        foreach ($items as $item) {
            $nr++;
            $itemsRows .= '<tr>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;text-align:center;">' . $nr . '</td>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;">' . e($item['description'] ?? '') . '</td>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;text-align:center;">buc</td>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;text-align:right;">' . ($item['quantity'] ?? 0) . '</td>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;text-align:right;">' . number_format($item['price'] ?? 0, 2) . '</td>';
            $itemsRows .= '<td style="border:1px solid #000;padding:6px;text-align:right;">' . number_format($item['total'] ?? 0, 2) . '</td>';
            $itemsRows .= '</tr>';
        }

        $statusLabel = $invoice->status === 'paid' ? 'Achitată' : 'Neachitată';

        // Period
        $period = '';
        if ($invoice->period_start) {
            $period = $invoice->period_start->translatedFormat('F Y');
        }

        return [
            'invoice_number' => $invoice->number ?? '',
            'invoice_issue_date' => $invoice->issue_date?->format('d.m.Y') ?? '',
            'invoice_due_date' => $invoice->due_date?->format('d.m.Y') ?? '',
            'invoice_period' => $period,
            'invoice_currency' => $currency,
            'invoice_subtotal' => number_format($invoice->subtotal ?? 0, 2),
            'invoice_vat_rate' => $invoice->vat_rate ?? 0,
            'invoice_vat_amount' => number_format($invoice->vat_amount ?? 0, 2),
            'invoice_total' => number_format($invoice->amount ?? 0, 2),
            'invoice_status' => $statusLabel,
            'invoice_items_rows' => $itemsRows,
            'invoice_commission_rate' => $meta['commission_rate'] ?? '',
            'invoice_order_count' => $meta['order_count'] ?? 0,
            'invoice_total_sales' => number_format($meta['total_sales'] ?? 0, 2),
            'issuer_name' => $issuer['name'] ?? '',
            'issuer_cui' => $issuer['cui'] ?? '',
            'issuer_reg_com' => $issuer['reg_com'] ?? '',
            'issuer_address' => $issuer['address'] ?? '',
            'issuer_bank_name' => $issuer['bank_name'] ?? '',
            'issuer_iban' => $issuer['iban'] ?? '',
            'issuer_email' => $issuer['email'] ?? '',
            'issuer_phone' => $issuer['phone'] ?? '',
            'issuer_website' => $issuer['website'] ?? '',
            'issuer_vat_payer' => !empty($issuer['vat_payer']) ? 'Plătitor TVA' : 'Neplătitor TVA',
            'client_name' => $client['name'] ?? '',
            'client_cui' => $client['cui'] ?? '',
            'client_reg_com' => $client['reg_com'] ?? '',
            'client_address' => $client['address'] ?? '',
        ];
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
