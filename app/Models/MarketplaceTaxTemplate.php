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
            '{{marketplace_iban}}' => 'IBAN',
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
            '{{event_has_monument_tax}}' => 'Eveniment plătitor de Taxa Monument (Da/Nu)',
            '{{event_manifestation_type}}' => 'Tip manifestare (ex: "Manifestare Muzicală", "Manifestare - Altele")',
            '{{ticket_types_table}}' => 'Ticket Types Table (with names, prices, available, sold)',
            '{{ticket_types_series}}' => 'Ticket Types with Series (Name: START - END)',
            '{{ticket_types_rows}}' => 'Ticket Types Table Rows (legacy — name, stock, price, value, series; uses quota_total for every row)',
            '{{ticket_types_total_row}}' => 'Ticket Types Total Row (TOTAL, stock, X, value, X)',
            '{{ticket_types_with_series_rows}}' => 'Phase B: Cerere avizare rânduri din EventTicketTypePromoSeries (qty_allocated respectă usage_limit pe coduri; un rând per (tip × cod) inclusiv coupons + RED + parent)',
            '{{ticket_types_with_series_total_row}}' => 'Phase B: TOTAL <tr> pentru rândurile de mai sus (aceeași structură ca ticket_types_total_row)',
            '{{ticket_types_with_series_total_tickets_qty}}' => 'Phase B: Total qty alocată pentru rândurile non-abonament',
            '{{ticket_types_with_series_total_tickets_value}}' => 'Phase B: Total valoare alocată pentru rândurile non-abonament',
            '{{ticket_types_with_series_total_subscriptions_qty}}' => 'Phase B: Total qty alocată pentru abonamente',
            '{{ticket_types_with_series_total_subscriptions_value}}' => 'Phase B: Total valoare alocată pentru abonamente',
            '{{total_tickets_for_sale}}' => 'Total Tickets For Sale (cumulative — abonamente + bilete)',
            '{{total_value_for_sale}}' => 'Total Value of Tickets For Sale (cumulative)',
            '{{total_subscriptions_for_sale}}' => 'Total Abonamente — only is_subscription tickets (line 1 of form)',
            '{{total_subscriptions_value_for_sale}}' => 'Total Value of Abonamente (line 1 of form)',
            '{{total_non_subscription_tickets_for_sale}}' => 'Total Bilete (non-subscription) — line 2 of form',
            '{{total_non_subscription_value_for_sale}}' => 'Total Value of Bilete (line 2 of form)',
            '{{total_tickets_available}}' => 'Total Tickets Available (Initial)',
            '{{total_tickets_sold}}' => 'Total Tickets Sold',
            '{{total_sales_value}}' => 'Total Sales Value',
            '{{music_stamp_value}}' => 'Valoare timbru muzical (2% din vânzări)',
            '{{humanitarian_amount}}' => 'Sume cedate în scopuri umanitare (default 0)',
            '{{taxable_income}}' => 'Încasări supuse impozitului (col 5 = 2 - 3 - 4)',
            '{{tax_due}}' => 'Impozit datorat (col 7 = col 5 × cotă registry)',
            '{{tax_paid}}' => 'Impozit plătit (default 0)',
            '{{tax_difference_to_receive}}' => 'Diferență de primit (col 9 = max(0, plătit - datorat))',
            '{{tax_difference_to_pay}}' => 'Diferență de plătit (col 10 = max(0, datorat - plătit))',
            '{{tax_situation_table_rows}}' => 'Rânduri tabel "Situația biletelor" (Page 2 — un rând per tip × preț, inclusiv variante reduse)',
            '{{tax_situation_total_tickets}}' => 'Total bilete vândute pentru tabel Situația',
            '{{tax_situation_total_value}}' => 'Total valoare pentru tabel Situația',
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
            '{{payout_number}}' => 'Payout Reference Number (serie decont dacă există, altfel referința PAY-...)',
            '{{decont_series}}' => 'Serie decont (DECAMB1, DECAMB2... — gol pentru deconturi vechi)',
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
            '{{total_refunded_amount}}' => 'Valoarea totală bilete returnate (lei)',
            '{{total_refunded_commission}}' => 'Comision returnat pentru biletele rambursate (lei)',
            '{{refunded_tickets_breakdown_label}}' => 'Detaliu bilete returnate (ex: 120lei*1)',
            '{{total_discount_amount}}' => 'Total discounturi aplicate (lei)',
            '{{promo_codes_used}}' => 'Coduri promo folosite (ex: " — coduri: SUMMER25 (x3), VIP10")',
            '{{sales_breakdown_rows}}' => 'Bloc HTML cu rândurile 1a/1b (+ 1c/1d, etc.) grupate per regulă de comision',
            '{{refund_breakdown_rows}}' => 'Bloc HTML cu rândurile 2a/2b (+ 2c/2d, etc.) grupate per regulă de comision',
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

    /**
     * Whether an HTML chunk has any visible content. Empty string, null, and
     * stub markup like '<p></p>' or '<p>&nbsp;</p>' (left behind by the
     * RichEditor when the user clears a textarea) all return false. Used to
     * gate page 2 inclusion so an empty page doesn't generate a phantom
     * trailing PDF page after a forced page-break.
     */
    public static function hasMeaningfulContent(?string $html): bool
    {
        if ($html === null) {
            return false;
        }
        return trim(strip_tags(str_replace('&nbsp;', ' ', $html))) !== '';
    }

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
            $variables['marketplace_iban'] = $marketplace->bank_account ?? '';
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
            $vatRate = $taxSettings['vat_rate'] ?? ($marketplace?->settings['tax']['vat_rate'] ?? 21);
            $variables['organizer_vat_status'] = $isVatPayer
                ? "plătitor TVA bilete (cota {$vatRate}%)"
                : "Neplătitor de TVA";

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

            // Historical monument tax flag — sourced from the venue first,
            // with an event-level override (event->has_historical_monument_tax)
            // taking precedence when explicitly set. Returns "Da" / "Nu" so it
            // can be dropped straight into the form text.
            $eventOverride = $event->has_historical_monument_tax ?? null;
            if ($eventOverride !== null) {
                $hasMonumentTax = (bool) $eventOverride;
            } else {
                $hasMonumentTax = (bool) ($event->venue?->has_historical_monument_tax ?? false);
            }
            $variables['event_has_monument_tax'] = $hasMonumentTax ? 'Da' : 'Nu';

            // Manifestation type — "Manifestare {label}" with the Romanian
            // label of the event's `manifestation_type` enum. The "altele"
            // value gets a dash separator: "Manifestare - Altele". Falls back
            // to empty string when the event has no value set.
            $manifestationLabels = [
                'muzicala' => 'Muzicală',
                'artistica' => 'Artistică',
                'teatrala' => 'Teatrală',
                'standup' => 'Stand-up',
                'sportiva' => 'Sportivă',
                'altele' => 'Altele',
            ];
            $manifestationKey = $event->manifestation_type ?? null;
            if ($manifestationKey && isset($manifestationLabels[$manifestationKey])) {
                $variables['event_manifestation_type'] = $manifestationKey === 'altele'
                    ? 'Manifestare - Altele'
                    : 'Manifestare ' . $manifestationLabels[$manifestationKey];
            } else {
                $variables['event_manifestation_type'] = '';
            }

            // Calculate totals
            $totalAvailable = 0;
            $totalSold = 0;
            $totalSalesValue = 0;
            $totalForSale = 0;
            $totalValueForSale = 0;
            // Split totals — line 1 of the tax form ("S-au înregistrat … abonamente")
            // and line 2 ("S-au înregistrat … bilete") need separate counts.
            // Determined by ticket_types.is_subscription on the parent row;
            // RED + promo variants inherit the parent's classification.
            $totalSubscriptionsForSale = 0;
            $totalSubscriptionsValueForSale = 0;
            $totalNonSubscriptionTicketsForSale = 0;
            $totalNonSubscriptionValueForSale = 0;
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
                // Helper: build the underline-blue series cell text. Reuses the
                // parent's series prefix and numeric range, but injects an
                // optional discount label between the prefix and the numbers
                // (RED for intrinsic discount, the promo code itself for
                // promo-driven rows). Falls back to plain label or "-" when
                // the parent has no parseable series range.
                $buildSeriesDisplay = function (string $seriesStart, string $seriesEnd, string $labelSuffix): string {
                    if ($seriesStart && $seriesEnd
                        && preg_match('/^(.*?)(\d+)$/', $seriesStart, $sm)
                        && preg_match('/^(.*?)(\d+)$/', $seriesEnd, $em)
                        && trim($sm[1]) === trim($em[1])) {
                        $prefix = trim($sm[1]);
                        $effective = $labelSuffix === ''
                            ? $prefix
                            : ($prefix !== '' ? $prefix . '-' . $labelSuffix : $labelSuffix);
                        return ($effective !== '' ? $effective . '&nbsp;&nbsp;' : '')
                            . $sm[2] . ' &gt; ' . $em[2];
                    }
                    if ($labelSuffix !== '') {
                        return $labelSuffix;
                    }
                    if ($seriesStart || $seriesEnd) {
                        return $seriesStart . ' &gt; ' . $seriesEnd;
                    }
                    return '-';
                };

                // Helper: emit one tax-table row + advance the running totals.
                // The grand total ($totalForSale / $totalValueForSale) keeps
                // cumulative behavior. The split totals (subscription vs not)
                // feed the form's two-line breakdown ("abonamente" vs "bilete").
                $appendTicketRow = function (string $rowName, int $rowStock, float $rowPrice, string $seriesText, bool $isSubscription = false)
                    use (
                        &$ticketRowsHtml,
                        &$totalForSale,
                        &$totalValueForSale,
                        &$totalSubscriptionsForSale,
                        &$totalSubscriptionsValueForSale,
                        &$totalNonSubscriptionTicketsForSale,
                        &$totalNonSubscriptionValueForSale,
                    ) {
                    $ticketRowsHtml .= '<tr>';
                    $ticketRowsHtml .= '<td class="left-align">' . $rowName . '</td>';
                    $ticketRowsHtml .= '<td>' . $rowStock . '</td>';
                    $ticketRowsHtml .= '<td>' . number_format($rowPrice, 2) . '</td>';
                    $ticketRowsHtml .= '<td>' . number_format($rowStock * $rowPrice, 2) . '</td>';
                    $ticketRowsHtml .= '<td><span class="underline-blue">' . $seriesText . '</span></td>';
                    $ticketRowsHtml .= '</tr>';
                    $rowValue = $rowStock * $rowPrice;
                    $totalForSale += $rowStock;
                    $totalValueForSale += $rowValue;
                    if ($isSubscription) {
                        $totalSubscriptionsForSale += $rowStock;
                        $totalSubscriptionsValueForSale += $rowValue;
                    } else {
                        $totalNonSubscriptionTicketsForSale += $rowStock;
                        $totalNonSubscriptionValueForSale += $rowValue;
                    }
                };

                foreach ($event->ticketTypes as $ticketType) {
                    // Skip non-declarable ticket types for document generation
                    if (isset($ticketType->is_declarable) && $ticketType->is_declarable === false) {
                        continue;
                    }
                    // Skip invitations — no series, no price, not part of fiscal
                    // declaration. Detected the same way as the EventResource
                    // breakdown: by exact name "Invitatie" or meta flag.
                    $meta = is_array($ticketType->meta ?? null) ? $ticketType->meta : [];
                    $isInvitation = ($ticketType->name === 'Invitatie') || ($meta['is_invitation'] ?? false);
                    if ($isInvitation) {
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

                    // Row 1 — normal (full) price.
                    $appendTicketRow(
                        $ticketName,
                        $available,
                        $price,
                        $buildSeriesDisplay($seriesStart, $seriesEnd, ''),
                        $isSubscription
                    );

                    // Row 2 — intrinsic earlybird discount on the ticket type
                    // (column `discount_percent`). Same physical stock as the
                    // parent — the row declares max value at that price point.
                    $discountPercent = (float) ($ticketType->discount_percent ?? 0);
                    if ($discountPercent > 0) {
                        $reducedPrice = max(0.0, $price - ($price * $discountPercent / 100));
                        $appendTicketRow(
                            $ticketName . ' - RED',
                            $available,
                            $reducedPrice,
                            $buildSeriesDisplay($seriesStart, $seriesEnd, 'RED'),
                            $isSubscription
                        );
                    }

                    // Row 3+ — promo codes that apply to this ticket type.
                    // A code is relevant if EITHER:
                    //   - ticket_type_id matches this exact ticket type
                    //     (per-tt scoped code, applies only here), OR
                    //   - marketplace_event_id matches the current event AND
                    //     ticket_type_id is NULL (event-scoped code, applies
                    //     to every ticket type of the event — generates one
                    //     row per ticket type).
                    // Active codes only (status='active', within start/end
                    // window). Chronological order on created_at.
                    $promoCodes = \App\Models\MarketplaceOrganizerPromoCode::where('status', 'active')
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                        })
                        ->where(function ($q) use ($ticketType, $event) {
                            $q->where('ticket_type_id', $ticketType->id)
                              ->orWhere(function ($q2) use ($event) {
                                  $q2->where('marketplace_event_id', $event->id)
                                     ->whereNull('ticket_type_id');
                              });
                        })
                        ->orderBy('created_at')
                        ->get();

                    foreach ($promoCodes as $promo) {
                        $codeValue = (float) $promo->value;
                        $reducedPrice = match ($promo->type) {
                            'percentage' => $price - ($price * $codeValue / 100),
                            'fixed' => $price - $codeValue,
                            default => null,
                        };
                        if ($reducedPrice === null) {
                            continue;
                        }
                        $reducedPrice = max(0.0, $reducedPrice);
                        $codeText = htmlspecialchars(strtoupper($promo->code));

                        $appendTicketRow(
                            $ticketName . ' - ' . $codeText,
                            $available,
                            $reducedPrice,
                            $buildSeriesDisplay($seriesStart, $seriesEnd, $codeText),
                            $isSubscription
                        );
                    }

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

            // Phase B: also emit promo / coupon / RED tier unsold rows
            // for PV distrugere. These mirror what was declared in cerere
            // avizare (one row per allocation), so the destruction document
            // accounts for every series the organizer declared upfront —
            // even if those tickets are fabricated (no physical paper).
            if ($event) {
                try {
                    $pvAllocations = app(\App\Services\Marketplace\SeriesAllocator::class)
                        ->getForEvent($event);
                } catch (\Throwable $e) {
                    $pvAllocations = collect();
                }

                // Same discount-source lookup the cerere-avizare generator
                // already does — we need the reduced unit price per tier
                // to compute the unsold value. Build the lookup once.
                $pvOrgPromosByCode = collect();
                $pvCouponsByCode = collect();
                if ($event->marketplace_client_id) {
                    try {
                        $pvOrgPromosByCode = \App\Models\MarketplaceOrganizerPromoCode::query()
                            ->where('marketplace_client_id', $event->marketplace_client_id)
                            ->get(['id', 'code', 'type', 'value'])
                            ->keyBy(fn ($p) => strtoupper((string) $p->code));
                    } catch (\Throwable $e) {}
                    try {
                        $pvCouponsByCode = \App\Models\Coupon\CouponCode::query()
                            ->where('marketplace_client_id', $event->marketplace_client_id)
                            ->get(['id', 'code', 'discount_type', 'discount_value'])
                            ->keyBy(fn ($c) => strtoupper((string) $c->code));
                    } catch (\Throwable $e) {}
                }

                foreach ($pvAllocations as $pvAlloc) {
                    // Skip the parent (full-price) row — already emitted by the
                    // main ticketTypes loop above using quota_total/quota_sold.
                    if (($pvAlloc->discount_code ?? '') === '' && !$pvAlloc->is_intrinsic_red) {
                        continue;
                    }
                    $pvQtyAllocated = (int) $pvAlloc->qty_allocated;
                    $pvQtySold = (int) $pvAlloc->qty_sold;
                    $pvUnsold = max(0, $pvQtyAllocated - $pvQtySold);
                    if ($pvUnsold <= 0) continue;

                    $pvTt = $pvAlloc->ticketType;
                    if (!$pvTt) continue;
                    $pvTtMeta = is_array($pvTt->meta ?? null) ? $pvTt->meta : [];
                    $pvIsInvitation = ($pvTt->name === 'Invitatie') || ((bool) ($pvTtMeta['is_invitation'] ?? false));
                    if ($pvIsInvitation) continue;

                    // Reduced unit price lookup. Reused from cerere avizare.
                    $pvBasePrice = (float) ($pvTt->display_price ?? $pvTt->price ?? 0);
                    $pvUnitPrice = $pvBasePrice;
                    $pvCodeLabel = '';

                    if ($pvAlloc->is_intrinsic_red) {
                        $pvPercent = (float) ($pvTt->discount_percent ?? 0);
                        if ($pvPercent > 0) {
                            $pvUnitPrice = max(0.0, $pvBasePrice - ($pvBasePrice * $pvPercent / 100));
                        }
                        $pvCodeLabel = 'RED';
                    } elseif ($pvAlloc->discount_source === 'organizer_promo') {
                        $pvK = strtoupper((string) ($pvAlloc->discount_code ?? ''));
                        $pvP = $pvOrgPromosByCode->get($pvK);
                        if ($pvP) {
                            $pvUnitPrice = match($pvP->type) {
                                'percentage' => max(0.0, $pvBasePrice - ($pvBasePrice * (float) $pvP->value / 100)),
                                'fixed'      => max(0.0, $pvBasePrice - (float) $pvP->value),
                                default      => $pvBasePrice,
                            };
                        }
                        $pvCodeLabel = (string) $pvAlloc->discount_code;
                    } elseif ($pvAlloc->discount_source === 'coupon') {
                        $pvK = strtoupper((string) ($pvAlloc->discount_code ?? ''));
                        $pvC = $pvCouponsByCode->get($pvK);
                        if ($pvC) {
                            $pvUnitPrice = match($pvC->discount_type) {
                                'percentage' => max(0.0, $pvBasePrice - ($pvBasePrice * (float) $pvC->discount_value / 100)),
                                'fixed'      => max(0.0, $pvBasePrice - (float) $pvC->discount_value),
                                default      => $pvBasePrice,
                            };
                        }
                        $pvCodeLabel = (string) $pvAlloc->discount_code;
                    }

                    $pvUnsoldValue = $pvUnsold * $pvUnitPrice;

                    // Series range for the unsold slice = (qty_sold + 1) .. qty_allocated.
                    $pvPadLen = 3;
                    if ($pvTt->series_start && preg_match('/^.*?(\d+)$/', (string) $pvTt->series_start, $pvSm)) {
                        $pvPadLen = strlen($pvSm[1]);
                    }
                    $pvStartUnsold = $pvQtySold + 1;
                    $pvEndUnsold = $pvQtyAllocated;
                    $pvSeriesDisplay = ((string) $pvAlloc->series_prefix)
                        . str_pad((string) $pvStartUnsold, $pvPadLen, '0', STR_PAD_LEFT)
                        . ' &mdash; ' . ((string) $pvAlloc->series_prefix)
                        . str_pad((string) $pvEndUnsold, $pvPadLen, '0', STR_PAD_LEFT);

                    $pvTicketName = htmlspecialchars(
                        (string) ($pvTt->name ?? '') . ($pvCodeLabel !== '' ? ' - ' . $pvCodeLabel : '')
                    );

                    $pvIsSubscription = (bool) ($pvTt->is_subscription ?? false);

                    $pvRowHtml = '<tr>'
                        . '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $pvSeriesDisplay . '</td>'
                        . '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $pvUnsold . '</td>'
                        . '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . number_format($pvUnitPrice, 2) . '</td>'
                        . '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . number_format($pvUnsoldValue, 2) . '</td>'
                        . '<td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">' . $pvTicketName . '</td>'
                        . '</tr>';

                    if ($pvIsSubscription) {
                        $totalUnsoldSubscriptions += $pvUnsold;
                        $totalUnsoldSubscriptionsValue += $pvUnsoldValue;
                        $unsoldSubscriptionsRowsHtml .= $pvRowHtml;
                    } else {
                        $totalUnsold += $pvUnsold;
                        $totalUnsoldValue += $pvUnsoldValue;
                        $unsoldRowsHtml .= $pvRowHtml;
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
            // Split totals — for the form's two-line breakdown:
            //  line 1: '… abonamente' uses the subscription pair
            //  line 2: '… bilete'     uses the non-subscription pair
            $variables['total_subscriptions_for_sale'] = $totalSubscriptionsForSale;
            $variables['total_subscriptions_value_for_sale'] = number_format($totalSubscriptionsValueForSale, 2);
            $variables['total_non_subscription_tickets_for_sale'] = $totalNonSubscriptionTicketsForSale;
            $variables['total_non_subscription_value_for_sale'] = number_format($totalNonSubscriptionValueForSale, 2);
            $variables['total_tickets_available'] = $totalAvailable;
            $variables['total_tickets_sold'] = $totalSold;
            // Subtract discounts applied across the event's paid orders so
            // total_sales_value reflects what was actually collected, not the
            // catalog (price * qty_sold) gross. Cascades into music_stamp_value
            // and taxable_income below. Lifetime sum — matches the existing
            // quota_sold-based totalSalesValue scope (no period filter).
            if ($event) {
                $eventDiscountSum = (float) \App\Models\Order::query()
                    ->where(fn ($q) => $q->where('event_id', $event->id)
                                          ->orWhere('marketplace_event_id', $event->id))
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'external_import')
                    ->sum('discount_amount');
                if ($eventDiscountSum > 0) {
                    $totalSalesValue = max(0.0, $totalSalesValue - $eventDiscountSum);
                }
            }
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

            // Taxă monument istoric (2%) — dacă venue-ul are flag-ul activ
            if ($event->venue && ($event->venue->has_historical_monument_tax ?? false)) {
                $musicStampValue += round($totalSalesValue * 2 / 100, 2);
            }

            // Col 4 — Sume cedate in scopuri umanitare. No system field yet,
            // defaults to 0. Surfaces as a variable so the template can
            // reference it consistently with the other columns.
            $humanitarianAmount = 0.0;

            // Încasări supuse impozitului = total vânzări - taxe aplicate - umanitare
            // (col 5 = col 2 - col 3 - col 4)
            $taxableIncome = round($totalSalesValue - $musicStampValue - $humanitarianAmount, 2);

            // Impozit datorat = cota tax registry * încasări supuse impozitului (col 7 = col 5 × col 6)
            $taxRate = $taxRegistry?->tax_rate !== null ? (float) $taxRegistry->tax_rate : 0;
            $taxDue = round($taxableIncome * $taxRate / 100, 2);

            // Col 8 — Impozit plătit. No payment-tracking system yet, default 0.
            // Col 9 — Diferență de primit (= max(0, plătit - datorat)) shows
            //   what the registry owes back when payments exceed the due amount.
            // Col 10 — Diferență de plătit (= max(0, datorat - plătit)) shows
            //   the outstanding amount the organizer still owes.
            $taxPaid = 0.0;
            $taxDifferenceToReceive = max(0.0, round($taxPaid - $taxDue, 2));
            $taxDifferenceToPay = max(0.0, round($taxDue - $taxPaid, 2));

            $variables['music_stamp_value'] = number_format($musicStampValue, 2);
            $variables['humanitarian_amount'] = number_format($humanitarianAmount, 2);
            $variables['taxable_income'] = number_format($taxableIncome, 2);
            $variables['tax_due'] = number_format($taxDue, 2);
            $variables['tax_paid'] = number_format($taxPaid, 2);
            $variables['tax_difference_to_receive'] = number_format($taxDifferenceToReceive, 2);
            $variables['tax_difference_to_pay'] = number_format($taxDifferenceToPay, 2);

            // Tax-situation table rows for page 2 of monthly tax declarations
            // ("Situatia biletelor si abonamentelor la spectacole, vandute").
            //
            // Phase B: series_prefix and qty_allocated come from the persisted
            // event_ticket_type_promo_series table (synced by
            // SeriesAllocator + the promo observer). qty_sold for the actual
            // declaration comes from buildPayoutSplitTable so the number
            // reflects current live sales without depending on the
            // refreshQtySold cache freshness. The combination keeps the
            // series_prefix identical across cerere avizare / declaratie /
            // PV distrugere while letting the qty column stay live.
            //
            // Skips invitations (price=0) — not declarable income.
            $taxSituationRowsHtml = '';
            $taxSituationTotalQty = 0;
            $taxSituationTotalValue = 0.0;
            if ($event) {
                // Auto-sync allocations on first access for this event so
                // a freshly-deployed environment doesn't need a manual
                // `php artisan series:sync` to populate Phase B data.
                try {
                    $hasAllocations = \App\Models\EventTicketTypePromoSeries::query()
                        ->whereIn('ticket_type_id', $event->ticketTypes->pluck('id'))
                        ->exists();
                    if (!$hasAllocations) {
                        app(\App\Services\Marketplace\SeriesAllocator::class)->syncForEvent($event);
                    }
                } catch (\Throwable $e) {
                    // Non-fatal — fall back to the on-the-fly derivation below.
                }

                // Index persisted allocations by (ticket_type_id, discount_code, is_intrinsic_red).
                // discount_code is the universal lookup — empty string for
                // parent rows, the code value for promo / coupon tiers.
                $allocationByKey = [];
                try {
                    $allocations = \App\Models\EventTicketTypePromoSeries::query()
                        ->whereIn('ticket_type_id', $event->ticketTypes->pluck('id'))
                        ->get();
                    foreach ($allocations as $a) {
                        $key = $a->ticket_type_id . '|' . ((string) ($a->discount_code ?? '')) . '|' . ($a->is_intrinsic_red ? 'RED' : 'STD');
                        $allocationByKey[$key] = $a;
                    }
                } catch (\Throwable $e) {
                    $allocationByKey = [];
                }

                try {
                    $splitRows = app(\App\Services\Marketplace\SalesBreakdownService::class)
                        ->buildPayoutSplitTable($event, null, null, excludePos: true);
                } catch (\Throwable $e) {
                    $splitRows = [];
                }
                // Identify invitation rows so we can render them with a
                // special "Invitatie-01..Invitatie-N" placeholder series and
                // keep the total bilete count aligned with the payout
                // (which DOES include invitations). Rows with price=0 that
                // belong to a non-invitation type fall through and don't
                // render (likely test/edge data).
                $ttIndex = $event->ticketTypes->keyBy('id');
                $isInvitationRow = function ($row) use ($ttIndex) {
                    $tt = $ttIndex->get($row['ticket_type_id'] ?? null);
                    if (!$tt) return false;
                    $m = is_array($tt->meta ?? null) ? $tt->meta : [];
                    return ($tt->name === 'Invitatie') || ((bool) ($m['is_invitation'] ?? false));
                };
                $splitRows = array_values(array_filter($splitRows, function ($r) use ($isInvitationRow) {
                    $price = (float) ($r['price'] ?? 0);
                    if ($price > 0) return true;
                    return $isInvitationRow($r);
                }));
                // Sort: invitations LAST (after all priced rows), then by
                // type name (alpha), non-reduced before reduced, price desc.
                usort($splitRows, function ($a, $b) use ($isInvitationRow) {
                    $aInv = $isInvitationRow($a);
                    $bInv = $isInvitationRow($b);
                    if ($aInv !== $bInv) return $aInv ? 1 : -1;
                    $cmp = strcmp((string) ($a['ticket_type_name'] ?? ''), (string) ($b['ticket_type_name'] ?? ''));
                    if ($cmp !== 0) return $cmp;
                    $aRed = (bool) ($a['is_reduced'] ?? false);
                    $bRed = (bool) ($b['is_reduced'] ?? false);
                    if ($aRed !== $bRed) return $aRed ? 1 : -1;
                    return (float) ($b['price'] ?? 0) <=> (float) ($a['price'] ?? 0);
                });

                // Inline fallback for events that pre-date Phase B and didn't
                // get sync'd (or for promos that aren't persisted yet because
                // the observer fires only on save).
                $deriveFallbackRange = function (string $seriesStart, string $codeSuffix, int $qtyInTier): array {
                    if ($qtyInTier <= 0 || $seriesStart === '') {
                        return ['', ''];
                    }
                    if (!preg_match('/^(.*?)(\d+)$/', $seriesStart, $sm)) {
                        return [$seriesStart, $seriesStart];
                    }
                    $prefix = trim($sm[1]);
                    $startNum = (int) $sm[2];
                    $padLen = strlen($sm[2]);
                    $effectivePrefix = $codeSuffix === ''
                        ? $prefix
                        : ($prefix !== '' ? $prefix . '-' . $codeSuffix : $codeSuffix);
                    $firstNum = $codeSuffix === '' ? $startNum : 1;
                    $lastNum = $firstNum + $qtyInTier - 1;
                    return [
                        $effectivePrefix . str_pad((string) $firstNum, $padLen, '0', STR_PAD_LEFT),
                        $effectivePrefix . str_pad((string) $lastNum, $padLen, '0', STR_PAD_LEFT),
                    ];
                };

                $rowNum = 1;
                foreach ($splitRows as $row) {
                    $ttId = $row['ticket_type_id'] ?? null;
                    $ttName = (string) ($row['ticket_type_name'] ?? '');
                    $isReduced = (bool) ($row['is_reduced'] ?? false);
                    $promoCode = (string) ($row['promo_code'] ?? '');
                    $qty = (int) ($row['qty'] ?? 0);
                    $price = (float) ($row['price'] ?? 0);
                    $total = $price * $qty;

                    $deLa = '';
                    $panaLa = '';

                    // Invitations render with a placeholder series
                    // ("Invitatie-01" .. "Invitatie-N") so the row count
                    // shows up in the document even though invitations
                    // carry no AMB-… series of their own. Keeps the
                    // declaratie impozite total bilete aligned with the
                    // payout's bilete total (which includes invitations).
                    if ($isInvitationRow($row)) {
                        $deLa = 'Invitatie-' . str_pad('1', 2, '0', STR_PAD_LEFT);
                        $panaLa = 'Invitatie-' . str_pad((string) $qty, 2, '0', STR_PAD_LEFT);
                        $taxSituationTotalQty += $qty;
                        $taxSituationTotalValue += 0.0; // value stays 0 for free invitations
                        $taxSituationRowsHtml .= '<tr>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . $rowNum . '</td>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . htmlspecialchars($deLa) . '</td>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . htmlspecialchars($panaLa) . '</td>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">' . $qty . '</td>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">0.00</td>'
                            . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">0.00</td>'
                            . '</tr>';
                        $rowNum++;
                        continue;
                    }

                    // Look up persisted allocation by discount_code (universal
                    // key across organizer promos + coupons + parent).
                    $lookupCode = $isReduced ? ($promoCode !== '' ? $promoCode : 'RED') : '';
                    $isRedTier = $isReduced && $promoCode === '';
                    $allocKey = $ttId . '|' . $lookupCode . '|' . ($isRedTier ? 'RED' : 'STD');
                    $allocation = $allocationByKey[$allocKey] ?? null;

                    if ($allocation && $allocation->series_prefix !== '') {
                        // Determine padding from the parent series_start if
                        // available; default to 3 digits otherwise. The split
                        // table doesn't carry padding info on its own.
                        $padLen = 3;
                        $seriesStart = '';
                        if ($ttId) {
                            $ttRow = $event->ticketTypes->firstWhere('id', $ttId);
                            if ($ttRow) {
                                $seriesStart = (string) ($ttRow->series_start ?? '');
                                if ($seriesStart !== '' && preg_match('/^.*?(\d+)$/', $seriesStart, $m)) {
                                    $padLen = strlen($m[1]);
                                }
                            }
                        }
                        // Parent (non-reduced, no code) rows continue the
                        // existing numbering from series_start. Promo /
                        // coupon / RED rows start a fresh counter from 1.
                        $startNum = 1;
                        if (!$isReduced && ($allocation->discount_code ?? '') === '' && !$allocation->is_intrinsic_red) {
                            if ($seriesStart !== '' && preg_match('/^.*?(\d+)$/', $seriesStart, $m)) {
                                $startNum = (int) $m[1];
                            }
                        }
                        $endNum = $startNum + max(0, $qty - 1);
                        $deLa = $allocation->series_prefix . str_pad((string) $startNum, $padLen, '0', STR_PAD_LEFT);
                        $panaLa = $allocation->series_prefix . str_pad((string) $endNum, $padLen, '0', STR_PAD_LEFT);
                    } else {
                        // No persisted allocation — fall back to on-the-fly
                        // derivation from the ticket type's series_start.
                        $seriesStart = '';
                        if ($ttId) {
                            $ttRow = $event->ticketTypes->firstWhere('id', $ttId);
                            if ($ttRow) {
                                $seriesStart = (string) ($ttRow->series_start ?? '');
                            }
                        }
                        $codeSuffix = $isReduced
                            ? ($promoCode !== '' ? strtoupper($promoCode) : 'RED')
                            : '';
                        [$deLa, $panaLa] = $deriveFallbackRange($seriesStart, $codeSuffix, $qty);
                    }

                    // Fallback to type name + code when nothing else resolves.
                    if ($deLa === '' && $panaLa === '') {
                        $deLa = $ttName . ($isReduced ? ' - ' . ($promoCode ?: 'redus') : '');
                        $panaLa = '';
                    }

                    $taxSituationTotalQty += $qty;
                    $taxSituationTotalValue += $total;

                    $taxSituationRowsHtml .= '<tr>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . $rowNum . '</td>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . htmlspecialchars($deLa) . '</td>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:center;">' . htmlspecialchars($panaLa) . '</td>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">' . $qty . '</td>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">' . number_format($price, 2) . '</td>'
                        . '<td style="border:1px solid #000; padding:3px 4px; text-align:right;">' . number_format($total, 2) . '</td>'
                        . '</tr>';
                    $rowNum++;
                }
            }
            $variables['tax_situation_table_rows'] = $taxSituationRowsHtml;
            $variables['tax_situation_total_tickets'] = $taxSituationTotalQty;
            $variables['tax_situation_total_value'] = number_format($taxSituationTotalValue, 2);

            // PV Distrugere variables — unsold tickets (excluding subscriptions)
            $variables['unsold_tickets_rows'] = $unsoldRowsHtml;
            $variables['total_unsold_tickets'] = $totalUnsold;
            $variables['total_unsold_value'] = number_format($totalUnsoldValue, 2);

            // PV Distrugere variables — unsold subscriptions
            $variables['unsold_subscriptions_rows'] = $unsoldSubscriptionsRowsHtml;
            $variables['total_unsold_subscriptions'] = $totalUnsoldSubscriptions;
            $variables['total_unsold_subscriptions_value'] = number_format($totalUnsoldSubscriptionsValue, 2);

            // === Phase B: ticket_types_with_series_rows ===
            // Cerere de avizare generator backed by EventTicketTypePromoSeries.
            // Emits one row per active allocation (parent + RED + each
            // applicable promo / coupon). qty per row uses the PERSISTED
            // qty_allocated (which respects usage_limit / max_uses_total on
            // discount codes) rather than the legacy ticket_types_rows that
            // hard-coded quota_total for every row.
            //
            // Reduced-tier prices are computed at render time by looking up
            // the discount source (MarketplaceOrganizerPromoCode for
            // 'organizer_promo', Coupon\CouponCode for 'coupon', or the
            // ticket type's discount_percent for 'intrinsic_red').
            //
            // Series ranges use the persisted series_prefix + qty so the
            // cerere avizare allocations stay consistent with the
            // declaratie impozite tax_situation_table_rows.
            $ttsRowsHtml = '';
            $ttsTotalTicketsQty = 0;
            $ttsTotalTicketsValue = 0.0;
            $ttsTotalSubscriptionsQty = 0;
            $ttsTotalSubscriptionsValue = 0.0;

            if ($event) {
                $eventTtIds = $event->ticketTypes->pluck('id');

                // Auto-sync on first access — same pattern as tax_situation_table_rows.
                try {
                    $hasAlloc = \App\Models\EventTicketTypePromoSeries::query()
                        ->whereIn('ticket_type_id', $eventTtIds)
                        ->exists();
                    if (!$hasAlloc) {
                        app(\App\Services\Marketplace\SeriesAllocator::class)->syncForEvent($event);
                    }
                } catch (\Throwable $e) {
                    // Non-fatal — fall through to a possibly empty allocation list.
                }

                try {
                    $allocations = app(\App\Services\Marketplace\SeriesAllocator::class)
                        ->getForEvent($event);
                } catch (\Throwable $e) {
                    $allocations = collect();
                }

                // Bulk-preload discount sources by uppercased code for fast
                // unit-price resolution per row.
                $orgPromosByCode = collect();
                $couponsByCode = collect();
                if ($event->marketplace_client_id) {
                    try {
                        $orgPromosByCode = \App\Models\MarketplaceOrganizerPromoCode::query()
                            ->where('marketplace_client_id', $event->marketplace_client_id)
                            ->get(['id', 'code', 'type', 'value'])
                            ->keyBy(fn ($p) => strtoupper((string) $p->code));
                    } catch (\Throwable $e) {}
                    try {
                        $couponsByCode = \App\Models\Coupon\CouponCode::query()
                            ->where('marketplace_client_id', $event->marketplace_client_id)
                            ->get(['id', 'code', 'discount_type', 'discount_value'])
                            ->keyBy(fn ($c) => strtoupper((string) $c->code));
                    } catch (\Throwable $e) {}
                }

                foreach ($allocations as $alloc) {
                    $qty = (int) $alloc->qty_allocated;
                    if ($qty <= 0) continue; // Skip stale / zeroed rows.

                    $ttRow = $alloc->ticketType;
                    if (!$ttRow) continue;

                    // Skip invitations + non-declarable types — same filter as
                    // the legacy ticket_types_rows.
                    if (isset($ttRow->is_declarable) && $ttRow->is_declarable === false) continue;
                    $ttRowMeta = is_array($ttRow->meta ?? null) ? $ttRow->meta : [];
                    $isInvitation = ($ttRow->name === 'Invitatie') || ($ttRowMeta['is_invitation'] ?? false);
                    if ($isInvitation) continue;

                    $basePrice = (float) ($ttRow->display_price ?? $ttRow->price ?? 0);
                    $unitPrice = $basePrice;
                    $rowLabel = (string) ($ttRow->name ?? '');
                    $isSubscription = (bool) ($ttRow->is_subscription ?? false);

                    if ($alloc->is_intrinsic_red) {
                        $percent = (float) ($ttRow->discount_percent ?? 0);
                        if ($percent > 0) {
                            $unitPrice = max(0.0, $basePrice - ($basePrice * $percent / 100));
                        }
                        $rowLabel .= ' - RED';
                    } elseif ($alloc->discount_source === 'organizer_promo') {
                        $codeKey = strtoupper((string) ($alloc->discount_code ?? ''));
                        $promo = $orgPromosByCode->get($codeKey);
                        if ($promo) {
                            $unitPrice = match($promo->type) {
                                'percentage' => max(0.0, $basePrice - ($basePrice * (float) $promo->value / 100)),
                                'fixed'      => max(0.0, $basePrice - (float) $promo->value),
                                default      => $basePrice,
                            };
                        }
                        $rowLabel .= ' - ' . $alloc->discount_code;
                    } elseif ($alloc->discount_source === 'coupon') {
                        $codeKey = strtoupper((string) ($alloc->discount_code ?? ''));
                        $coupon = $couponsByCode->get($codeKey);
                        if ($coupon) {
                            $unitPrice = match($coupon->discount_type) {
                                'percentage' => max(0.0, $basePrice - ($basePrice * (float) $coupon->discount_value / 100)),
                                'fixed'      => max(0.0, $basePrice - (float) $coupon->discount_value),
                                default      => $basePrice,
                            };
                        }
                        $rowLabel .= ' - ' . $alloc->discount_code;
                    }

                    // Series range from persisted prefix. Parent rows
                    // continue the parent series_start numbering; reduced
                    // rows start a fresh fabricated counter at 1.
                    $padLen = 3;
                    if ($ttRow->series_start && preg_match('/^.*?(\d+)$/', (string) $ttRow->series_start, $sm)) {
                        $padLen = strlen($sm[1]);
                    }
                    $startNum = 1;
                    if (((string) ($alloc->discount_code ?? '')) === '' && !$alloc->is_intrinsic_red) {
                        if ($ttRow->series_start && preg_match('/^.*?(\d+)$/', (string) $ttRow->series_start, $sm2)) {
                            $startNum = (int) $sm2[1];
                        }
                    }
                    $endNum = $startNum + max(0, $qty - 1);
                    $seriesFrom = ((string) $alloc->series_prefix) . str_pad((string) $startNum, $padLen, '0', STR_PAD_LEFT);
                    $seriesTo = ((string) $alloc->series_prefix) . str_pad((string) $endNum, $padLen, '0', STR_PAD_LEFT);
                    $seriesDisplay = $startNum === $endNum
                        ? $seriesFrom
                        : $seriesFrom . ' &gt; ' . $seriesTo;

                    $rowValue = $qty * $unitPrice;
                    if ($isSubscription) {
                        $ttsTotalSubscriptionsQty += $qty;
                        $ttsTotalSubscriptionsValue += $rowValue;
                    } else {
                        $ttsTotalTicketsQty += $qty;
                        $ttsTotalTicketsValue += $rowValue;
                    }

                    // Match the existing ticket_types_rows column layout so
                    // the new variable can drop into the same template
                    // structure (5 cells: name, qty, price, value, series).
                    $ttsRowsHtml .= '<tr>'
                        . '<td class="left-align">' . htmlspecialchars($rowLabel) . '</td>'
                        . '<td>' . $qty . '</td>'
                        . '<td>' . number_format($unitPrice, 2) . '</td>'
                        . '<td>' . number_format($rowValue, 2) . '</td>'
                        . '<td><span class="underline-blue">' . $seriesDisplay . '</span></td>'
                        . '</tr>';
                }
            }

            $variables['ticket_types_with_series_rows'] = $ttsRowsHtml;
            $variables['ticket_types_with_series_total_tickets_qty'] = $ttsTotalTicketsQty;
            $variables['ticket_types_with_series_total_tickets_value'] = number_format($ttsTotalTicketsValue, 2);
            $variables['ticket_types_with_series_total_subscriptions_qty'] = $ttsTotalSubscriptionsQty;
            $variables['ticket_types_with_series_total_subscriptions_value'] = number_format($ttsTotalSubscriptionsValue, 2);

            // Build the matching TOTAL <tr> for Phase B so templates that
            // include it as a single variable (like the cerere avizare
            // layout) don't have to be restructured at the HTML level.
            $ttsGrandQty = $ttsTotalTicketsQty + $ttsTotalSubscriptionsQty;
            $ttsGrandValue = $ttsTotalTicketsValue + $ttsTotalSubscriptionsValue;
            $ttsTotalRowHtml = '<tr class="total-row">'
                . '<td><span class="bold">TOTAL</span></td>'
                . '<td>' . $ttsGrandQty . '</td>'
                . '<td>X</td>'
                . '<td>' . number_format($ttsGrandValue, 2) . '</td>'
                . '<td>X</td>'
                . '</tr>';
            $variables['ticket_types_with_series_total_row'] = $ttsTotalRowHtml;

            // Auto-override the legacy variables when Phase B produced
            // actual rows. This lets templates keep their existing
            // {{ticket_types_total_row}} / {{total_subscriptions_for_sale}}
            // placeholders unchanged while still benefiting from the
            // corrected qty_allocated math (and coupon coverage) introduced
            // by Phase B. Legacy generator output is silently replaced —
            // both halves now align so totals match the rows displayed.
            if ($ttsRowsHtml !== '') {
                $variables['ticket_types_rows'] = $ttsRowsHtml;
                $variables['ticket_types_total_row'] = $ttsTotalRowHtml;
                $variables['total_subscriptions_for_sale'] = $ttsTotalSubscriptionsQty;
                $variables['total_subscriptions_value_for_sale'] = number_format($ttsTotalSubscriptionsValue, 2);
                $variables['total_non_subscription_tickets_for_sale'] = $ttsTotalTicketsQty;
                $variables['total_non_subscription_value_for_sale'] = number_format($ttsTotalTicketsValue, 2);
                $variables['total_tickets_for_sale'] = $ttsGrandQty;
                $variables['total_value_for_sale'] = number_format($ttsGrandValue, 2);
            }
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
            // Compute POS-excluded totals once. All amount-like variables below must
            // use these, since POS/app sales never flow through marketplace — their
            // money and commission are settled separately between organizer and
            // customer, and billed to the organizer via a dedicated POS invoice.
            $posTypeIds = method_exists($payout, 'getPosTicketTypeIds') ? $payout->getPosTicketTypeIds() : [];
            $posTypeIdsSet = array_flip($posTypeIds);
            $grossExclPos = 0.0;
            $commissionExclPos = 0.0;
            $netExclPos = 0.0;
            $hasBreakdown = !empty($payout->ticket_breakdown);
            foreach ($payout->ticket_breakdown ?? [] as $item) {
                $ttId = $item['ticket_type_id'] ?? null;
                if ($ttId && isset($posTypeIdsSet[$ttId])) {
                    continue;
                }
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
                $commPer = (float) ($item['commission_per_ticket'] ?? 0);
                $commission = $commPer * $qty;
                $itemMode = $item['commission_mode'] ?? null;
                $gross = $price * $qty + ($itemMode === 'added_on_top' ? $commission : 0);

                $grossExclPos += $gross;
                $commissionExclPos += $commission;
                $netExclPos += ($gross - $commission);
            }
            // Fall back to stored values if no breakdown (e.g. legacy payouts)
            $payoutGross = $hasBreakdown ? $grossExclPos : (float) ($payout->gross_amount ?? 0);
            $payoutCommission = $hasBreakdown ? $commissionExclPos : (float) ($payout->commission_amount ?? 0);
            $payoutAmount = $hasBreakdown ? $netExclPos : (float) ($payout->amount ?? 0);

            // Prefer the configurable decont series; fall back to the
            // PAY-... reference for older payouts that have no series.
            $variables['decont_series'] = $payout->decont_series ?? '';
            $variables['payout_number'] = $payout->decont_series ?: ($payout->reference ?? '');
            // payout_date follows the payout's created_at (the operator-set
            // "Creat la" override on the manual-create modal) so the printed
            // decont date matches the official date the operator chose, not
            // the moment the PDF was generated.
            $variables['payout_date'] = ($payout->created_at ?? now())->format('d.m.Y');
            $variables['payout_amount'] = number_format($payoutAmount, 2);
            $variables['payout_currency'] = $payout->currency ?? 'RON';
            $variables['payout_gross_amount'] = number_format($payoutGross, 2);
            $variables['payout_commission_amount'] = number_format($payoutCommission, 2);

            // Commission percentage shown in the section-1 header. Skip
            // invitation/fixed-only/zero rows when scanning the breakdown —
            // invitations carry commission_rate=null and would short-circuit
            // the lookup to 0%, even though paid tickets do have a real rate.
            $commissionPercent = null;
            $ticketBreakdownForRate = $payout->ticket_breakdown ?? [];
            if (!empty($ticketBreakdownForRate)) {
                foreach ($ticketBreakdownForRate as $item) {
                    $rate = $item['commission_rate'] ?? $item['commission_percent'] ?? null;
                    if ($rate !== null && (float) $rate > 0) {
                        $commissionPercent = $rate;
                        break;
                    }
                }
            }
            if ($commissionPercent === null && $event) {
                // Try per-ticket-type commission from DB — pick first type
                // with a non-zero rate (invitation types and fixed-only
                // types have commission_rate=null and must be skipped).
                $firstTt = $event->ticketTypes()
                    ->whereNotNull('commission_rate')
                    ->where('commission_rate', '>', 0)
                    ->first();
                if ($firstTt) {
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
            $variables['payout_net_amount'] = number_format($payoutAmount, 2);
            $variables['payout_commission_mode'] = $payout->commission_mode ?? 'included';

            // VAT calculations — use POS-excluded commission so the VAT on the decont
            // only reflects the online-commission portion
            $vatPayer = $organizer?->vat_payer ?? false;
            $vatRate = $vatPayer ? 19 : 0;
            $vatAmount = $vatPayer ? round($payoutCommission * $vatRate / 100, 2) : 0;
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

            // Tickets breakdown label: (50lei*2+60lei*16) format — exclude POS/app rows.
            // Prices keep their decimals when they have any (e.g. 59.50 stays as
            // 59.50, not rounded to 60); integer prices stay clean (60 not 60.00).
            $formatPrice = fn (float $p): string => fmod($p, 1.0) === 0.0
                ? number_format($p, 0, '.', '')
                : number_format($p, 2, '.', '');
            $breakdownParts = [];
            $ticketBreakdown = $payout->ticket_breakdown ?? [];
            $totalTicketsSold = 0;
            $totalTicketsRefunded = 0;

            // Source: ticket_breakdown JSON (the operator's actual selection
            // after the edit-tickets action). Previously this used
            // SalesBreakdownService::buildPayoutSplitTable with period
            // bounds, which re-queried the DB and returned every ticket sold
            // in that window — including the rows the operator removed when
            // they shrank a decont to a subset. The label and totals are
            // now in lock-step with the per-payout snapshot used everywhere
            // else.
            foreach ($ticketBreakdown as $item) {
                $ttId = $item['ticket_type_id'] ?? null;
                if ($ttId && isset($posTypeIdsSet[$ttId])) {
                    continue;
                }
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
                if ($qty <= 0) continue;
                $totalTicketsSold += $qty;
                // Zero-priced rows (invitations) still emit as "0lei*N" so
                // 1b's qty total matches the qty in the label.
                $breakdownParts[] = $formatPrice($price) . 'lei*' . $qty;
            }

            // Wrap every 5 parts with <br> so multi-type deconts don't blow
            // the PDF page width. processTemplate substitutes the value as
            // raw HTML (preg_replace, no escaping), so <br> renders as a
            // real line break in DomPDF.
            if (empty($breakdownParts)) {
                $variables['tickets_breakdown_label'] = '';
            } else {
                $chunks = array_chunk($breakdownParts, 5);
                $joined = implode('<br>+ ', array_map(fn ($chunk) => implode('+', $chunk), $chunks));
                $variables['tickets_breakdown_label'] = ' (' . $joined . ')';
            }
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
            // Refunded tickets aggregate for the decont template (filling
            // section 2 — "Taxe pentru bilete returnate"). Sourced from
            // MarketplaceRefundItem rows attached to refund_requests that
            // are EXPLICITLY LINKED to this payout via
            // marketplace_refund_requests.marketplace_payout_id. Previously
            // every event refund leaked into every payout's PDF — now each
            // refund appears on AT MOST one decont (the one the operator
            // assigned it to in the manual modal / edit-tickets action).
            $refundCount = 0;
            $refundFaceTotal = 0.0;
            $refundCommissionReturned = 0.0;
            $refundedBreakdownParts = [];
            if ($payout->event_id) {
                $refundItems = \App\Models\MarketplaceRefundItem::query()
                    ->whereHas('refundRequest', function ($q) use ($payout) {
                        $q->whereIn('status', ['refunded', 'partially_refunded'])
                          ->where('marketplace_payout_id', $payout->id);
                    })
                    ->where('status', 'refunded')
                    ->get();

                $refundCount = $refundItems->count();
                $refundFaceTotal = (float) $refundItems->sum('face_value');
                $refundCommissionReturned = (float) $refundItems
                    ->where('commission_refunded', true)
                    ->sum('commission_amount');

                // Build "(120lei*1)" style label same as tickets_breakdown_label.
                // Group by exact face_value (rounded to cents) so 59.50 stays
                // visible — the rounding-to-int in the previous cut conflated
                // distinct prices and lost decimals in the label.
                $byPrice = $refundItems->groupBy(fn ($it) => number_format((float) $it->face_value, 2, '.', ''));
                foreach ($byPrice as $price => $set) {
                    $priceFloat = (float) $price;
                    $refundedBreakdownParts[] = $formatPrice($priceFloat) . 'lei*' . $set->count();
                }
            }
            $variables['total_tickets_refunded'] = $refundCount;
            $variables['total_refunded_amount'] = number_format($refundFaceTotal, 2);
            $variables['total_refunded_commission'] = number_format($refundCommissionReturned, 2);
            $variables['refunded_tickets_breakdown_label'] = !empty($refundedBreakdownParts)
                ? ' (' . implode('+', $refundedBreakdownParts) . ')'
                : '';

            // Discount aggregate for the decont template (section 1c).
            // Try the snapshot first (new SalesBreakdownService stores
            // discount per row), fall back to summing order.discount_amount
            // for legacy payouts whose snapshot pre-dates that field. The
            // promo codes themselves always come from the underlying orders
            // — source can sit in three places depending on legacy/origin:
            // order.promo_code string, order.promo_code_id → promo_codes
            // table, or meta.promo_code.code (older WP imports). Orders
            // with a discount but no code surface as "reducere manuală".
            // Snapshots built by SalesBreakdownService write a 'discount'
            // key on every row (zero when no discount). Older snapshots
            // simply omit the key. Distinguish those two cases via
            // array_key_exists — without it, payouts whose new-style
            // snapshot legitimately has zero discount triggered the
            // order-level fallback and reported false discount values
            // (e.g. payout 2921 showed 24 RON discount even though the
            // breakdown was all 0).
            $hasPerRowDiscount = !empty($ticketBreakdown)
                && array_key_exists('discount', $ticketBreakdown[0] ?? []);
            $totalDiscountAmount = 0.0;
            if ($hasPerRowDiscount) {
                foreach ($ticketBreakdown as $item) {
                    $totalDiscountAmount += (float) ($item['discount'] ?? 0);
                }
            }
            $promoCodes = [];
            $orderHasNonCodeDiscount = false;
            $fallbackDiscountFromOrders = 0.0;
            if ($payout->event_id) {
                $ordersQ = \App\Models\Order::query()
                    ->where(fn ($q) => $q->where('event_id', $payout->event_id)
                                          ->orWhere('marketplace_event_id', $payout->event_id))
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where(function ($q) {
                        $q->where('discount_amount', '>', 0)
                          ->orWhere('promo_discount', '>', 0);
                    });
                if ($payout->period_start) {
                    $ordersQ->where('created_at', '>=', $payout->period_start->copy()->startOfDay());
                }
                if ($payout->period_end) {
                    $ordersQ->where('created_at', '<=', $payout->period_end->copy()->endOfDay());
                }
                $orders = $ordersQ->get(['id', 'promo_code', 'promo_code_id', 'meta', 'discount_amount']);

                foreach ($orders as $o) {
                    $fallbackDiscountFromOrders += (float) ($o->discount_amount ?? 0);

                    $code = trim((string) ($o->promo_code ?? ''));
                    if ($code === '' && $o->promo_code_id) {
                        // No Eloquent model for plain promo_codes table — go via DB.
                        $code = trim((string) (\Illuminate\Support\Facades\DB::table('promo_codes')
                            ->where('id', $o->promo_code_id)
                            ->value('code') ?: ''));
                    }
                    if ($code === '' && is_array($o->meta ?? null)) {
                        $code = trim((string) ($o->meta['promo_code']['code'] ?? ''));
                    }
                    if ($code !== '') {
                        $promoCodes[$code] = ($promoCodes[$code] ?? 0) + 1;
                    } else {
                        $orderHasNonCodeDiscount = true;
                    }
                }
            }
            // Only fall back to the order-level sum for legacy snapshots
            // that don't carry the 'discount' key at all. New-style
            // snapshots with explicit zero discount stay at zero — we
            // trust the breakdown over orphan totals on the model
            // (payout->discount_amount stays for legacy bookkeeping but
            // doesn't override what the breakdown encodes).
            if (!$hasPerRowDiscount && $fallbackDiscountFromOrders > 0) {
                $totalDiscountAmount = $fallbackDiscountFromOrders;
            }
            $variables['total_discount_amount'] = number_format($totalDiscountAmount, 2);

            // Both row 1a (payout_net_amount) and the final payable
            // (payout_amount) are now post-discount. The breakdown label at
            // 1b lists each effective-price tier separately, so the gross
            // sum implied by the label already excludes discounts — no need
            // for a separate row 1c. Removing that row keeps the document
            // internally consistent without a discount-aware deduction line.
            if ($totalDiscountAmount > 0) {
                $payoutAmountAfterDiscount = max(0.0, $payoutAmount - $totalDiscountAmount);
                $variables['payout_amount'] = number_format($payoutAmountAfterDiscount, 2);
                $variables['payout_net_amount'] = number_format($payoutAmountAfterDiscount, 2);
            }

            // Apply refund + advance deductions to the FINAL payable line
            // (payout_amount = row E in the decont template, formula
            // "E = A - B - C - D"). Row 1a (payout_net_amount) and row 2a
            // (total_refunded_amount) stay independent — A and B are
            // separate inputs and the template adds them up itself. Without
            // this override the PDF would show E = ticket_net (= A),
            // ignoring the refund the operator linked to this payout.
            $refundDeduction = (float) ($payout->refund_amount ?? 0);
            $advanceDeduction = (float) ($payout->payout_method['advance_amount'] ?? 0);
            if ($refundDeduction > 0 || $advanceDeduction > 0) {
                $currentPayoutAmount = (float) str_replace(',', '', $variables['payout_amount'] ?? '0');
                $finalAmount = max(0.0, $currentPayoutAmount - $refundDeduction - $advanceDeduction);
                $variables['payout_amount'] = number_format($finalAmount, 2);
            }
            // Sortat după număr de utilizări desc, "COD (xN)" format.
            arsort($promoCodes);
            $codeStrings = [];
            foreach ($promoCodes as $code => $count) {
                $codeStrings[] = $count > 1 ? "{$code} (x{$count})" : $code;
            }
            if ($orderHasNonCodeDiscount) {
                $codeStrings[] = 'reducere manuală';
            }
            $variables['promo_codes_used'] = !empty($codeStrings)
                ? ' — coduri: ' . implode(', ', $codeStrings)
                : '';

            // === Per-commission-rule breakdowns for section 1 + section 2 ===
            // The decont's section 1 / section 2 each render one pair of
            // rows (Xa value + Xb ticket list) per distinct commission rule
            // present in the payout. A single-rule payout outputs just
            // 1a/1b (and 2a/2b for refunds). Mixed payouts (e.g. some
            // tickets percentage-based, some fixed) get additional pairs:
            // 1a/1b + 1c/1d + 1e/1f… Letters wrap a/b → c/d → e/f → …
            //
            // The template author replaces the static 1a/1b rows with the
            // single variable {{sales_breakdown_rows}} and the static
            // 2a/2b rows with {{refund_breakdown_rows}}. Line 1 / line 2
            // become pure section headers ("Bilete vândute online" /
            // "Taxe pentru bilete returnate") — the rate label moves into
            // each Xa row so mixed payouts can show different rates
            // alongside their respective values.
            // getVariablesForContext() is static — helpers must be called
            // via self:: too. The earlier $this-> calls blew up at 1909.
            $variables['sales_breakdown_rows'] = self::buildPayoutSalesBreakdownRows($payout, $ticketBreakdown, $posTypeIdsSet, $vatAmount, $formatPrice);
            $variables['refund_breakdown_rows'] = self::buildPayoutRefundBreakdownRows($payout, $formatPrice);

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
     * Group ticket_breakdown rows by their commission rule (type + rate +
     * fixed + mode) and emit a pair of HTML rows for each group: value +
     * ticket list. Letters auto-rotate: a/b for the first group, c/d for
     * the second, e/f for the third. Templates that previously used the
     * static 1a/1b rows now just drop {{sales_breakdown_rows}} and the
     * resolver expands to the right number of rows for the payout's
     * actual mix.
     *
     * @param array<int, array<string, mixed>> $ticketBreakdown
     * @param array<int, mixed> $posTypeIdsSet
     */
    private static function buildPayoutSalesBreakdownRows(MarketplacePayout $payout, array $ticketBreakdown, array $posTypeIdsSet, float $vatAmount, callable $formatPrice): string
    {
        // Expand each saved breakdown row by its `tiers` field when present.
        // buildBreakdownFromSelection writes one tier per distinct paid price
        // (catalog 50 + promo 40 become two tiers totaling the row's qty) so
        // the PDF can render "50lei*2+40lei*2" and the per-tier sum matches
        // the effective organizer net. Rows without tiers (legacy deconturi,
        // manual edits) emit a single tier from price × qty — same as before.
        //
        // Defensive normalize: legacy deconturi saved before the qty/tier
        // invariant was enforced may have Σ tier.qty != row qty (the operator
        // shrank qty manually without re-running the scaler). Re-scale tiers
        // to the row qty here so the rendered PDF total matches the saved
        // selection, not the stale tier sum.
        $tierRows = [];
        foreach ($ticketBreakdown as $item) {
            $tiers = $item['tiers'] ?? null;
            if (is_array($tiers) && !empty($tiers)) {
                $rowQty = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
                $tierSum = 0;
                $tierQty = [];
                foreach ($tiers as $tier) {
                    $priceKey = (string) round((float) ($tier['price'] ?? 0), 2);
                    $tQty = (int) ($tier['qty'] ?? 0);
                    $tierQty[$priceKey] = ($tierQty[$priceKey] ?? 0) + $tQty;
                    $tierSum += $tQty;
                }
                if ($rowQty > 0 && $tierSum > 0 && $tierSum !== $rowQty) {
                    $tiers = \App\Models\MarketplacePayout::scaleTiers($tierQty, $tierSum, $rowQty);
                }
                foreach ($tiers as $tier) {
                    $tierRows[] = array_merge($item, [
                        'price' => (float) ($tier['price'] ?? 0),
                        'unit_price' => (float) ($tier['price'] ?? 0),
                        'qty' => (int) ($tier['qty'] ?? 0),
                        'quantity' => (int) ($tier['qty'] ?? 0),
                    ]);
                }
            } else {
                $tierRows[] = $item;
            }
        }

        // Build qty-per-ticket-type from the saved breakdown so the
        // derivation knows exactly how many tickets belong to THIS payout
        // (the period query alone would catch tickets from prior payouts
        // on the same event when they share period bounds). The derivation
        // returns one tier per distinct effective-paid-price, using only
        // the latest N tickets per type — older ones belong to earlier
        // payouts (which already took them) and are skipped.
        $qtyByType = [];
        foreach ($tierRows as $item) {
            $ttId = (int) ($item['ticket_type_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            if ($ttId > 0 && $qty > 0) {
                $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + $qty;
            }
        }
        $effectiveTiersByTtId = self::deriveEffectiveTiersFromTickets($payout, $posTypeIdsSet, $qtyByType);

        $groups = [];
        foreach ($tierRows as $item) {
            $ttId = $item['ticket_type_id'] ?? null;
            if ($ttId && isset($posTypeIdsSet[$ttId])) {
                continue;
            }
            $catalogPrice = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            if ($qty <= 0 || $catalogPrice <= 0) continue;

            $key = self::commissionGroupKey($item);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'label' => self::commissionRateLabel($item),
                    'mode' => $item['commission_mode'] ?? null,
                    'qty' => 0,
                    'amount' => 0.0,
                    'tierMap' => [], // (price_key) => qty
                ];
            }
            $groups[$key]['qty'] += $qty;

            // Prefer the per-ticket-type effective tiers from actual tickets.
            // Only use them when the derived qty matches THIS row's qty —
            // a mismatch indicates the operator manually shrank the row in
            // the edit-tickets modal, in which case the derived tiers can't
            // be trusted. Fall back to catalog price * qty in that case.
            $rowTierMap = null;
            if ($ttId !== null && isset($effectiveTiersByTtId[$ttId])) {
                $derivedTotal = array_sum($effectiveTiersByTtId[$ttId]);
                if ($derivedTotal === $qty) {
                    $rowTierMap = $effectiveTiersByTtId[$ttId];
                }
            }
            if ($rowTierMap === null) {
                $rowTierMap = [(string) round($catalogPrice, 2) => $qty];
            }

            foreach ($rowTierMap as $priceKey => $tierQty) {
                $tierPrice = (float) $priceKey;
                $groups[$key]['amount'] += $tierPrice * $tierQty;
                $groups[$key]['tierMap'][$priceKey] = ($groups[$key]['tierMap'][$priceKey] ?? 0) + $tierQty;
            }
        }

        if (empty($groups)) return '';

        // Build the per-group priceParts label from tierMap. Sorted by
        // price descending so the higher catalog tier leads the list —
        // matches the visual ordering operators expect in the PDF.
        foreach ($groups as &$gRef) {
            $gRef['priceParts'] = [];
            $tierMap = $gRef['tierMap'];
            krsort($tierMap, SORT_NUMERIC);
            foreach ($tierMap as $priceKey => $tierQty) {
                $gRef['priceParts'][] = $formatPrice((float) $priceKey) . 'lei*' . $tierQty;
            }
        }
        unset($gRef);

        $letterPairs = [['a','b'], ['c','d'], ['e','f'], ['g','h'], ['i','j']];
        $html = '';
        $idx = 0;
        foreach ($groups as $g) {
            [$valueLetter, $listLetter] = $letterPairs[$idx] ?? ['?', '?'];
            $rateLabel = $g['label'];
            $mode = $g['mode'];
            $taxNote = $rateLabel !== ''
                ? ' (taxa de ticketing ' . (in_array($mode, ['added_on_top', 'on_top'], true)
                    ? 'adăugată la prețul biletului'
                    : 'inclusă în prețul biletului') . '): ' . $rateLabel
                : '';
            $amountStr = number_format($g['amount'], 2);
            $vatStr = number_format($vatAmount, 2);
            $listLabel = !empty($g['priceParts'])
                ? ' (' . implode('+', $g['priceParts']) . ')'
                : '';

            // Pre-rendered tax-note suffix: smaller font, gray prefix,
            // red rate value — keeps the row label hierarchy obvious.
            $taxNoteHtml = self::commissionLabelHtml($rateLabel, $mode, 'sale');

            // Value row (1a / 1c / 1e ...).
            $html .= '<tr style="background:#fafafa;">'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#888; font-size:6.5pt;">1' . $valueLetter . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; padding-left:6px;">Valoare bilete v&#xe2;ndute' . $taxNoteHtml . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">lei</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; font-weight:bold;">' . $amountStr . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">' . $vatStr . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; color:#888;">' . $vatStr . '</td>'
                . '</tr>';

            // Ticket list row (1b / 1d / 1f ...).
            $html .= '<tr style="background:#fff;">'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#888; font-size:6.5pt;">1' . $listLetter . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; padding-left:6px; font-size:6.5pt; color:#555;">Nr. bilete v&#xe2;ndute' . htmlspecialchars($listLabel, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">buc</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; font-weight:bold;">' . $g['qty'] . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px;"></td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px;"></td>'
                . '</tr>';

            $idx++;
        }

        return $html;
    }

    /**
     * Walk every paid+confirmed+completed ticket for this payout's event
     * (no period bound — period overlap between payouts on the same event
     * can let the same ticket fall into more than one period) and pick
     * exactly $qtyByType[ttId] of the LATEST tickets per type. The older
     * ones belong to earlier payouts on the same event, which already
     * took them.
     *
     * Per ticket effective paid price = Ticket::getEffectivePrice() which
     * reads meta.discount_amount written at checkout by CheckoutController
     * (precise per-ticket value, integer when promo is a percentage of an
     * integer catalog price). Aggregates into one tier per distinct paid
     * price → the PDF row 1b shows "70lei*18+56lei*10" instead of a
     * single averaged "67.78lei*28".
     *
     * Returns [ticket_type_id => [price_string => qty]] keyed by
     * round(price, 2). Empty when no event_id or no matching tickets.
     */
    private static function deriveEffectiveTiersFromTickets(MarketplacePayout $payout, array $posTypeIdsSet, array $qtyByType = []): array
    {
        if (!$payout->event_id || empty($qtyByType)) return [];

        $tickets = \App\Models\Ticket::with(['order:id,discount_amount,subtotal,created_at'])
            ->whereHas('ticketType', fn ($qq) => $qq->where('event_id', $payout->event_id))
            ->whereIn('ticket_type_id', array_keys($qtyByType))
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($qq) {
                $qq->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'external_import')
                    ->where('source', '!=', 'pos_app');
            })
            ->get(['id', 'ticket_type_id', 'order_id', 'price', 'meta', 'status']);

        if ($tickets->isEmpty()) return [];

        // Sort once globally by order created_at then ticket id (DESC)
        // so taking head N gives the latest tickets — which are this
        // payout's slice when earlier payouts on the same event already
        // took the oldest. orderBy via the JOIN would require a join in
        // the original query; sorting on the collection is cheaper and
        // keeps the query simple.
        $tickets = $tickets->sortByDesc(function ($t) {
            return [$t->order?->created_at?->timestamp ?? 0, $t->id];
        });

        $tiers = [];
        $byType = $tickets->groupBy('ticket_type_id');
        foreach ($byType as $ttId => $typeTickets) {
            $ttId = (int) $ttId;
            if ($ttId <= 0) continue;
            if (isset($posTypeIdsSet[$ttId])) continue;

            $needed = (int) ($qtyByType[$ttId] ?? 0);
            if ($needed <= 0) continue;

            // Take only the latest `needed` tickets — anything earlier
            // belongs to a prior payout on this event.
            $picked = $typeTickets->take($needed);
            foreach ($picked as $t) {
                $effective = $t->getEffectivePrice();
                if ($effective <= 0) continue; // skip invitations / comp tickets
                $priceKey = (string) round($effective, 2);
                $tiers[$ttId][$priceKey] = ($tiers[$ttId][$priceKey] ?? 0) + 1;
            }
        }

        return $tiers;
    }

    /**
     * Render the "(taxa de ticketing …): N%" suffix as inline HTML so
     * the prefix sits in a smaller, gray inline-block and the rate
     * itself is bold + red. Empty when there's no rate.
     */
    private static function commissionLabelHtml(string $rateLabel, ?string $mode, string $context = 'sale'): string
    {
        if ($rateLabel === '') return '';
        $modeText = in_array($mode, ['added_on_top', 'on_top'], true)
            ? 'adăugată la prețul biletului'
            : 'inclusă în prețul biletului';
        $prefix = '(taxa de ticketing ' . $modeText . '): ';
        return ' <span style="font-size:6pt; color:#777;">'
            . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
            . '<strong style="color:#c0392b;">' . htmlspecialchars($rateLabel, ENT_QUOTES, 'UTF-8') . '</strong>'
            . '</span>';
    }

    /**
     * Same idea as buildPayoutSalesBreakdownRows but for refunded tickets,
     * pulling rules from each refund item's ticket type. Returns 2a/2b
     * (and optional 2c/2d, 2e/2f...) HTML, or empty string when nothing
     * was refunded.
     */
    private static function buildPayoutRefundBreakdownRows(MarketplacePayout $payout, callable $formatPrice): string
    {
        if (!$payout->event_id) return '';

        // Same scoping logic as the aggregate vars (total_tickets_refunded
        // etc.): only refunds explicitly assigned to THIS payout via
        // refund_request.marketplace_payout_id. Without it the same refund
        // showed on every later payout for the same event.
        $items = \App\Models\MarketplaceRefundItem::query()
            ->whereHas('refundRequest', function ($q) use ($payout) {
                $q->whereIn('status', ['refunded', 'partially_refunded'])
                  ->where('marketplace_payout_id', $payout->id);
            })
            ->where('status', 'refunded')
            ->with('ticketType:id,name,commission_type,commission_rate,commission_fixed,commission_mode')
            ->get();

        if ($items->isEmpty()) return '';

        $groups = [];
        foreach ($items as $it) {
            $tt = $it->ticketType;
            // Build the same shape commissionGroupKey/Label expects.
            $proxy = [
                'commission_type' => $tt?->commission_type,
                'commission_rate' => $tt?->commission_rate !== null ? (float) $tt->commission_rate : null,
                'commission_fixed' => $tt?->commission_fixed !== null ? (float) $tt->commission_fixed : null,
                'commission_mode' => $tt?->commission_mode,
            ];
            $key = self::commissionGroupKey($proxy);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'label' => self::commissionRateLabel($proxy),
                    'mode' => $tt?->commission_mode,
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = $it;
        }

        $letterPairs = [['a','b'], ['c','d'], ['e','f'], ['g','h'], ['i','j']];
        $html = '';
        $idx = 0;
        foreach ($groups as $g) {
            [$valueLetter, $listLetter] = $letterPairs[$idx] ?? ['?', '?'];
            $rateLabel = $g['label'];
            $mode = $g['mode'];
            $taxNoteHtml = self::commissionLabelHtml($rateLabel, $mode, 'refund');

            $totalFace = 0.0;
            $qty = 0;
            $byPrice = [];
            foreach ($g['items'] as $it) {
                $face = (float) $it->face_value;
                $totalFace += $face;
                $qty++;
                $bucketKey = number_format($face, 2, '.', '');
                $byPrice[$bucketKey] = ($byPrice[$bucketKey] ?? 0) + 1;
            }
            $priceParts = [];
            foreach ($byPrice as $price => $count) {
                $priceParts[] = $formatPrice((float) $price) . 'lei*' . $count;
            }
            $listLabel = !empty($priceParts) ? ' (' . implode('+', $priceParts) . ')' : '';
            $amountStr = number_format($totalFace, 2);

            // 2a mirrors 1a styling (default label font, smaller red rate
            // suffix), 2b mirrors 1b (6.5pt gray label). The previous cut
            // diverged on both rows.
            $html .= '<tr style="background:#fafafa;">'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#888; font-size:6.5pt;">2' . $valueLetter . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; padding-left:6px;">Valoarea total&#x103; a biletelor returnate' . $taxNoteHtml . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">lei</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; font-weight:bold;">' . $amountStr . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">0.00</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; color:#888;">0.00</td>'
                . '</tr>';

            $html .= '<tr style="background:#fff;">'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#888; font-size:6.5pt;">2' . $listLetter . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; padding-left:6px; font-size:6.5pt; color:#555;">Nr. bilete returnate' . htmlspecialchars($listLabel, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:center; color:#555;">buc</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px; text-align:right; font-weight:bold;">' . $qty . '</td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px;"></td>'
                . '<td style="border:1px solid #ddd; padding:2px 5px;"></td>'
                . '</tr>';

            $idx++;
        }

        return $html;
    }

    private static function commissionGroupKey(array|object $item): string
    {
        $a = is_object($item) ? (array) $item : $item;
        return implode('|', [
            $a['commission_type'] ?? '',
            $a['commission_rate'] ?? '',
            $a['commission_fixed'] ?? '',
            $a['commission_mode'] ?? '',
        ]);
    }

    private static function commissionRateLabel(array|object $item): string
    {
        $a = is_object($item) ? (array) $item : $item;
        $type = $a['commission_type'] ?? null;
        $rate = $a['commission_rate'] ?? null;
        $fixed = $a['commission_fixed'] ?? null;

        return match (true) {
            $type === 'percentage' && $rate !== null => $rate . '%',
            $type === 'fixed' && $fixed !== null => number_format((float) $fixed, 2) . ' lei',
            $type === 'both' && ($rate !== null || $fixed !== null) => trim(
                ($rate !== null ? $rate . '%' : '')
                . ($rate !== null && $fixed !== null ? ' + ' : '')
                . ($fixed !== null ? number_format((float) $fixed, 2) . ' lei' : '')
            ),
            // Legacy snapshots without commission_type fall back to rate-only.
            $rate !== null => $rate . '%',
            default => '',
        };
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
