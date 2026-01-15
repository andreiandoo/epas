<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventGeneratedDocument extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'event_id',
        'marketplace_tax_template_id',
        'filename',
        'file_path',
        'file_size',
        'generated_by_type',
        'generated_by_id',
        'generated_by_name',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'file_size' => 'integer',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTaxTemplate::class, 'marketplace_tax_template_id');
    }

    public function generatedBy(): MorphTo
    {
        return $this->morphTo('generated_by');
    }

    // =========================================
    // Accessors
    // =========================================

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    // =========================================
    // Static Methods
    // =========================================

    public static function generateDocument(
        Event $event,
        MarketplaceTaxTemplate $template,
        ?object $generatedBy = null
    ): self {
        $marketplaceClientId = $event->marketplace_client_id;
        $marketplace = MarketplaceClient::find($marketplaceClientId);

        // Get organizer
        $organizer = null;
        if ($event->marketplace_organizer_id) {
            $organizer = MarketplaceOrganizer::find($event->marketplace_organizer_id);
        }

        // Get tax registry if exists
        $taxRegistry = null;
        if ($marketplace) {
            $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where('is_active', true)
                ->first();
        }

        // Build variables for the template
        $variables = self::buildTemplateVariables($event, $marketplace, $organizer, $taxRegistry);

        // Process the template
        $htmlContent = $template->processTemplate($variables);

        // Process page 2 if exists
        $htmlContentPage2 = null;
        if ($template->html_content_page_2) {
            $htmlContentPage2 = $template->html_content_page_2;
            foreach ($variables as $key => $value) {
                $htmlContentPage2 = preg_replace(
                    '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                    $value ?? '',
                    $htmlContentPage2
                );
            }
        }

        // Generate PDF using DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);

        // Set orientation for page 1
        $orientation = $template->page_orientation === 'landscape' ? 'landscape' : 'portrait';
        $pdf->setPaper('a4', $orientation);

        // Get event name (handle translatable field)
        $eventName = is_array($event->title)
            ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?? 'event')
            : ($event->title ?? 'event');

        // Generate filename
        $timestamp = now()->format('Y-m-d_His');
        $filename = sprintf(
            '%s_%s_%s.pdf',
            Str::slug($eventName),
            Str::slug($template->name),
            $timestamp
        );

        // Save to storage
        $directory = "documents/events/{$event->id}";
        $filePath = "{$directory}/{$filename}";

        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put($filePath, $pdf->output());

        // Get file size
        $fileSize = Storage::disk('public')->size($filePath);

        // Create record
        return self::create([
            'marketplace_client_id' => $marketplaceClientId,
            'event_id' => $event->id,
            'marketplace_tax_template_id' => $template->id,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'generated_by_type' => $generatedBy ? get_class($generatedBy) : null,
            'generated_by_id' => $generatedBy?->id,
            'generated_by_name' => $generatedBy?->name ?? 'System',
            'meta' => [
                'template_name' => $template->name,
                'template_type' => $template->type,
                'orientation' => $orientation,
                'variables_used' => array_keys($variables),
            ],
        ]);
    }

    /**
     * Build template variables from Event model
     */
    protected static function buildTemplateVariables(
        Event $event,
        ?MarketplaceClient $marketplace,
        ?MarketplaceOrganizer $organizer,
        ?MarketplaceTaxRegistry $taxRegistry
    ): array {
        $variables = [];

        // Tax Registry variables
        if ($taxRegistry) {
            $variables['tax_registry_country'] = $taxRegistry->country ?? '';
            $variables['tax_registry_county'] = $taxRegistry->county ?? '';
            $variables['tax_registry_city'] = $taxRegistry->city ?? '';
            $variables['tax_registry_name'] = $taxRegistry->name ?? '';
            $variables['tax_registry_subname'] = $taxRegistry->subname ?? '';
            $variables['tax_registry_address'] = $taxRegistry->address ?? '';
            $variables['tax_registry_phone'] = $taxRegistry->phone ?? '';
            $variables['tax_registry_email'] = $taxRegistry->email ?? '';
            $variables['tax_registry_cif'] = $taxRegistry->cif ?? '';
            $variables['tax_registry_iban'] = $taxRegistry->iban ?? '';
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

        // Event variables (handle translatable title)
        $eventTitle = is_array($event->title)
            ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?? '')
            : ($event->title ?? '');
        $variables['event_name'] = $eventTitle;

        // Event date
        if ($event->event_date) {
            $variables['event_date'] = $event->event_date->format('d.m.Y') .
                ($event->start_time ? ' ' . $event->start_time : '');
        } else {
            $variables['event_date'] = '';
        }

        // Venue info
        $venue = $event->venue;
        if ($venue) {
            $variables['venue_name'] = $venue->name ?? '';
            $variables['venue_address'] = $venue->address ?? '';
        } else {
            $variables['venue_name'] = '';
            $variables['venue_address'] = '';
        }

        // Calculate ticket totals
        $totalAvailable = 0;
        $totalSold = 0;
        $totalSalesValue = 0;
        $currency = 'RON';

        // Build ticket types table
        $ticketTypesHtml = '<table style="width:100%; border-collapse: collapse;">';
        $ticketTypesHtml .= '<thead><tr>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Tip bilet</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Preț</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Disponibile</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Vândute</th>';
        $ticketTypesHtml .= '</tr></thead><tbody>';

        $ticketTypes = $event->ticketTypes ?? collect();
        foreach ($ticketTypes as $ticketType) {
            $available = (int) ($ticketType->quantity ?? 0);
            $sold = (int) ($ticketType->quantity_sold ?? 0);
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
        $ticketTypesHtml .= '</tbody></table>';

        $variables['ticket_types_table'] = $ticketTypesHtml;
        $variables['total_tickets_available'] = $totalAvailable;
        $variables['total_tickets_sold'] = $totalSold;
        $variables['total_sales_value'] = number_format($totalSalesValue, 2);
        $variables['total_sales_currency'] = $currency;

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
}
