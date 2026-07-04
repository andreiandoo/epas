<?php

namespace App\Services\TicketCustomizer;

use App\Models\Event;
use App\Models\Order;
use App\Models\Tax\GeneralTax;
use App\Models\Ticket;

/**
 * Ticket Variable Service
 *
 * Provides available variables (placeholders) for ticket template customization
 */
class TicketVariableService
{
    /**
     * Get all available variables for ticket templates
     *
     * @return array
     */
    public function getAvailableVariables(): array
    {
        return [
            // Event information
            'event' => [
                'label' => 'Event Information',
                'variables' => [
                    [
                        'path' => 'event.name',
                        'label' => 'Event Name',
                        'description' => 'Name of the event',
                        'type' => 'string',
                        'example' => 'Summer Concert 2025',
                    ],
                    [
                        'path' => 'event.description',
                        'label' => 'Event Description',
                        'description' => 'Event description or subtitle',
                        'type' => 'text',
                        'example' => 'A night of classical music',
                    ],
                    [
                        'path' => 'event.category',
                        'label' => 'Event Category',
                        'description' => 'Event category/genre',
                        'type' => 'string',
                        'example' => 'Concert',
                    ],
                    [
                        'path' => 'event.image',
                        'label' => 'Event Image',
                        'description' => 'Event poster/cover image URL (use in image layer)',
                        'type' => 'image_url',
                        'example' => 'https://example.com/events/summer-concert-2025.jpg',
                    ],
                ],
            ],

            // Venue information
            'venue' => [
                'label' => 'Venue Information',
                'variables' => [
                    [
                        'path' => 'venue.name',
                        'label' => 'Venue Name',
                        'description' => 'Name of the venue',
                        'type' => 'string',
                        'example' => 'Concert Hall Arena',
                    ],
                    [
                        'path' => 'venue.address',
                        'label' => 'Venue Address',
                        'description' => 'Full venue address',
                        'type' => 'text',
                        'example' => '123 Main Street, City',
                    ],
                    [
                        'path' => 'venue.city',
                        'label' => 'City',
                        'description' => 'Venue city',
                        'type' => 'string',
                        'example' => 'Bucharest',
                    ],
                    [
                        'path' => 'venue.program_today',
                        'label' => 'Program azi (Leisure)',
                        'description' => 'Orele de funcționare pe data biletului (ex: "09:00 – 19:00"). Calculat din venue_config.seasons + ziua biletului. Empty dacă nu există sezon activ.',
                        'type' => 'string',
                        'example' => '09:00 – 19:00',
                    ],
                    [
                        'path' => 'venue.season_label',
                        'label' => 'Sezon activ (Leisure)',
                        'description' => 'Numele sezonului în care se află data biletului (ex: "Sezon vară"). Empty dacă nu există sezon configurat pentru data respectivă.',
                        'type' => 'string',
                        'example' => 'Sezon vară',
                    ],
                ],
            ],

            // Date & Time
            'date' => [
                'label' => 'Date & Time',
                'variables' => [
                    [
                        'path' => 'date.start',
                        'label' => 'Event Date',
                        'description' => 'Event start date',
                        'type' => 'date',
                        'example' => '2025-07-15',
                        'format' => 'Y-m-d',
                    ],
                    [
                        'path' => 'date.start_formatted',
                        'label' => 'Event Date (Formatted)',
                        'description' => 'Smart: shows range "23–25 iunie 2026" for range events, or single date "23 iunie 2026"',
                        'type' => 'string',
                        'example' => '23–25 iunie 2026',
                    ],
                    [
                        'path' => 'date.end',
                        'label' => 'Event End Date',
                        'description' => 'Range end date (empty for single-day events)',
                        'type' => 'date',
                        'example' => '2025-07-17',
                        'format' => 'Y-m-d',
                    ],
                    [
                        'path' => 'date.end_formatted',
                        'label' => 'Event End Date (Formatted)',
                        'description' => 'Range end date formatted (empty for single-day events)',
                        'type' => 'string',
                        'example' => '17 July 2025',
                    ],
                    [
                        'path' => 'date.time',
                        'label' => 'Event Time',
                        'description' => 'Event start time',
                        'type' => 'time',
                        'example' => '20:00',
                        'format' => 'H:i',
                    ],
                    [
                        'path' => 'date.end_time',
                        'label' => 'Event End Time',
                        'description' => 'Event end time (empty if not set)',
                        'type' => 'time',
                        'example' => '23:00',
                        'format' => 'H:i',
                    ],
                    [
                        'path' => 'date.time_range',
                        'label' => 'Event Time (Smart)',
                        'description' => 'Smart: "19:00 – 22:00" when both set, just "19:00" when only start, empty when none',
                        'type' => 'string',
                        'example' => '19:00 – 22:00',
                    ],
                    [
                        'path' => 'date.time_label',
                        'label' => 'Event Time (with label)',
                        'description' => 'Labelled time: "Ora: 19:00 – 22:00" when time exists, empty string when it does not. Put on its own line in the template — disappears cleanly when missing.',
                        'type' => 'string',
                        'example' => 'Ora: 19:00 – 22:00',
                    ],
                    [
                        'path' => 'date.doors_open',
                        'label' => 'Doors Open Time',
                        'description' => 'Time when doors open',
                        'type' => 'time',
                        'example' => '19:00',
                    ],
                    [
                        'path' => 'date.doors_label',
                        'label' => 'Doors (with label)',
                        'description' => 'Labelled doors time: "Doors: 18:00" when set, empty string when not. Put on its own line — disappears cleanly when missing.',
                        'type' => 'string',
                        'example' => 'Doors: 18:00',
                    ],
                    [
                        'path' => 'date.day_name',
                        'label' => 'Day Name',
                        'description' => 'Day of week (empty for range events)',
                        'type' => 'string',
                        'example' => 'Saturday',
                    ],
                    [
                        'path' => 'date.full_text',
                        'label' => 'Full Date Line (Smart)',
                        'description' => 'Smart combined line: "Sâmbătă | 23 iunie 2026 | Ora: 19:00 | Doors: 18:00" for single day, "23–25 iunie 2026" for range. Empty parts are skipped automatically.',
                        'type' => 'string',
                        'example' => 'Sâmbătă | 23 iunie 2026 | Ora: 19:00 | Doors: 18:00',
                    ],
                ],
            ],

            // Ticket information
            'ticket' => [
                'label' => 'Ticket Information',
                'variables' => [
                    [
                        'path' => 'ticket.type',
                        'label' => 'Ticket Type',
                        'description' => 'Type/category of ticket',
                        'type' => 'string',
                        'example' => 'VIP',
                    ],
                    [
                        'path' => 'ticket.price',
                        'label' => 'Ticket Price',
                        'description' => 'Price of the ticket',
                        'type' => 'currency',
                        'example' => '99.99 RON',
                    ],
                    [
                        'path' => 'ticket.section',
                        'label' => 'Section',
                        'description' => 'Seating section (prefixed with "Sectiunea ")',
                        'type' => 'string',
                        'example' => 'Sectiunea A',
                    ],
                    [
                        'path' => 'ticket.row',
                        'label' => 'Row',
                        'description' => 'Seating row (prefixed with "Randul ")',
                        'type' => 'string',
                        'example' => 'Randul 5',
                    ],
                    [
                        'path' => 'ticket.seat',
                        'label' => 'Seat Number',
                        'description' => 'Seat number (prefixed with "Locul ")',
                        'type' => 'string',
                        'example' => 'Locul 12',
                    ],
                    [
                        'path' => 'ticket.number',
                        'label' => 'Ticket Number',
                        'description' => 'Unique ticket number',
                        'type' => 'string',
                        'example' => 'TKT-2025-001234',
                    ],
                    [
                        'path' => 'ticket.code_short',
                        'label' => 'Short Code',
                        'description' => 'Short alphanumeric ticket code (8 characters)',
                        'type' => 'code',
                        'example' => 'WLMVWB2G',
                    ],
                    [
                        'path' => 'ticket.code_long',
                        'label' => 'Long Code (UUID)',
                        'description' => 'Full UUID ticket code',
                        'type' => 'code',
                        'example' => 'b35b6fb0-52e7-43d3-88d9-3ccdc5e874a8',
                    ],
                    [
                        'path' => 'ticket.serial',
                        'label' => 'Serial Number',
                        'description' => 'Ticket series/serial number (e.g. AMB-123-0001)',
                        'type' => 'string',
                        'example' => 'AMB-42-0001',
                    ],
                    [
                        'path' => 'ticket.is_insured',
                        'label' => 'Insurance Status',
                        'description' => 'Whether ticket has return insurance (true/false)',
                        'type' => 'boolean',
                        'example' => 'true',
                    ],
                    [
                        'path' => 'ticket.insurance_badge',
                        'label' => 'Insurance Badge',
                        'description' => 'Shows shield icon text when insured, empty if not',
                        'type' => 'string',
                        'example' => '🛡️',
                    ],
                    [
                        'path' => 'ticket.insurance_label',
                        'label' => 'Insurance Label',
                        'description' => '"Bilet asigurat" when insured, empty if not',
                        'type' => 'string',
                        'example' => 'Bilet asigurat',
                    ],
                    [
                        'path' => 'ticket.price_detail',
                        'label' => 'Price + Commission Detail',
                        'description' => 'Smart price text: "Preț: 60 lei + 5% taxă procesare (63 lei)" or "Preț: 60 lei (taxă procesare inclusă)"',
                        'type' => 'text',
                        'example' => 'Preț: 60,00 lei + 5% taxă procesare (63,00 lei)',
                    ],
                    [
                        'path' => 'ticket.fees_text',
                        'label' => 'Included Fees Text',
                        'description' => 'Text listing all included taxes/fees (e.g. "Prețul include 5% Timbru Muzical, 2% Taxa Monument Istoric")',
                        'type' => 'text',
                        'example' => 'Prețul include 5% Timbru Muzical, 2% Taxa de Monument Istoric',
                    ],
                    [
                        'path' => 'ticket.verify_url',
                        'label' => 'Verification URL',
                        'description' => 'Public URL for ticket verification (use as QR code data)',
                        'type' => 'url',
                        'example' => 'https://tickets.example.com/t/WLMVWB2G',
                    ],
                    [
                        'path' => 'ticket.description',
                        'label' => 'Ticket Description',
                        'description' => 'Description of the ticket type',
                        'type' => 'text',
                        'example' => 'Acces VIP cu drink de bun venit și loc rezervat în primele rânduri',
                    ],
                    [
                        'path' => 'ticket.perks',
                        'label' => 'Perks / Conditions',
                        'description' => 'List of perks/conditions for this ticket type (joined by bullet points)',
                        'type' => 'text',
                        'example' => '• Acces VIP Lounge • Drink de bun venit • Loc rezervat',
                    ],
                    [
                        'path' => 'ticket.includes',
                        'label' => 'Include (bullet list)',
                        'description' => 'Lista "Include" din produsul leisure — un element per linie. Multi-locale: se traduce automat pe locale-ul biletului cand meta.translations.includes.{locale} exista. Format: fiecare linie prefixata cu "• ".',
                        'type' => 'text',
                        'example' => "• Acces toată ziua\n• Hartă tipărită\n• Apă gratuită",
                    ],
                    [
                        'path' => 'ticket.includes_raw',
                        'label' => 'Include (text simplu)',
                        'description' => 'Aceeasi lista ca ticket.includes dar fara bullet-uri — doar cu newline intre linii. Util cand vrei formatare custom in template.',
                        'type' => 'text',
                        'example' => "Acces toată ziua\nHartă tipărită\nApă gratuită",
                    ],
                    [
                        'path' => 'ticket.usage_terms',
                        'label' => 'Termeni utilizare',
                        'description' => 'Textul din campul "Termeni utilizare" al produsului leisure. Multi-locale: traduce automat pe locale-ul biletului cand meta.translations.usage_terms.{locale} exista.',
                        'type' => 'text',
                        'example' => 'Biletul este valabil doar pentru data si ora selectate. Nu se ramburseaza.',
                    ],
                ],
            ],

            // Buyer information
            'buyer' => [
                'label' => 'Buyer Information',
                'variables' => [
                    [
                        'path' => 'buyer.name',
                        'label' => 'Buyer Name',
                        'description' => 'Full name of ticket buyer',
                        'type' => 'string',
                        'example' => 'John Doe',
                    ],
                    [
                        'path' => 'buyer.first_name',
                        'label' => 'First Name',
                        'description' => 'Buyer first name',
                        'type' => 'string',
                        'example' => 'John',
                    ],
                    [
                        'path' => 'buyer.last_name',
                        'label' => 'Last Name',
                        'description' => 'Buyer last name',
                        'type' => 'string',
                        'example' => 'Doe',
                    ],
                    [
                        'path' => 'buyer.email',
                        'label' => 'Email',
                        'description' => 'Buyer email address',
                        'type' => 'email',
                        'example' => 'john@example.com',
                    ],
                ],
            ],

            // Order information
            'order' => [
                'label' => 'Order Information',
                'variables' => [
                    [
                        'path' => 'order.code',
                        'label' => 'Order Code',
                        'description' => 'Unique order reference',
                        'type' => 'string',
                        'example' => 'ORD-2025-123456',
                    ],
                    [
                        'path' => 'order.date',
                        'label' => 'Purchase Date',
                        'description' => 'Date of purchase',
                        'type' => 'date',
                        'example' => '2025-06-01',
                    ],
                    [
                        'path' => 'order.total',
                        'label' => 'Order Total',
                        'description' => 'Total order amount',
                        'type' => 'currency',
                        'example' => '199.99 EUR',
                    ],
                    [
                        'path' => 'order.addon_services',
                        'label' => 'Servicii extra (Leisure) — text',
                        'description' => 'Lista multi-linie cu serviciile extra atribuite acestui bilet acces (rental/activity/parking). Distribuție automată per bilet acces (adult/copil). Format pe linie: "🛶 Plimbare cu barca · cod: ABC123". Empty pe bilete non-access.',
                        'type' => 'text',
                        'example' => "🛶 Plimbare cu barca · cod: WLMVWB2G\n🎯 Coș picnic · cod: COSPI-AB2",
                    ],
                    [
                        'path' => 'order.addon_services_html',
                        'label' => 'Servicii extra (Leisure) — cu QR-uri vizuale',
                        'description' => 'Variantă HTML cu cod QR vizual mic pentru fiecare add-on (data URI inline). Folosește într-un layer text mare pentru a permite afișarea QR-urilor (placeholder _html sare peste escape).',
                        'type' => 'text',
                        'example' => 'HTML cu <img> QR + nume produs',
                    ],
                ],
            ],

            // Barcodes & QR codes
            'codes' => [
                'label' => 'Barcodes & QR Codes',
                'variables' => [
                    [
                        'path' => 'barcode',
                        'label' => 'Barcode Data',
                        'description' => 'Ticket barcode value (use in barcode element)',
                        'type' => 'code',
                        'example' => 'TKT2025001234',
                    ],
                    [
                        'path' => 'qrcode',
                        'label' => 'QR Code Data',
                        'description' => 'Ticket QR code value (use in QR element)',
                        'type' => 'code',
                        'example' => 'https://verify.example.com/TKT2025001234',
                    ],
                ],
            ],

            // Organizer information
            'organizer' => [
                'label' => 'Organizer Information',
                'variables' => [
                    [
                        'path' => 'organizer.name',
                        'label' => 'Organizer Name',
                        'description' => 'Name of event organizer',
                        'type' => 'string',
                        'example' => 'Live Nation Romania',
                    ],
                    [
                        'path' => 'organizer.company_name',
                        'label' => 'Company Name',
                        'description' => 'Legal company name of the organizer',
                        'type' => 'string',
                        'example' => 'Live Nation Entertainment SRL',
                    ],
                    [
                        'path' => 'organizer.tax_id',
                        'label' => 'CUI / Tax ID',
                        'description' => 'Company fiscal identification number (CUI)',
                        'type' => 'string',
                        'example' => 'RO12345678',
                    ],
                    [
                        'path' => 'organizer.company_address',
                        'label' => 'Company Address',
                        'description' => 'Legal address of the organizer company',
                        'type' => 'text',
                        'example' => 'Strada Victoriei 25, Sector 1',
                    ],
                    [
                        'path' => 'organizer.city',
                        'label' => 'City',
                        'description' => 'Organizer city',
                        'type' => 'string',
                        'example' => 'București',
                    ],
                    [
                        'path' => 'organizer.website',
                        'label' => 'Website',
                        'description' => 'Organizer website',
                        'type' => 'url',
                        'example' => 'https://example.com',
                    ],
                    [
                        'path' => 'organizer.phone',
                        'label' => 'Contact Phone',
                        'description' => 'Organizer contact phone',
                        'type' => 'string',
                        'example' => '+40 123 456 789',
                    ],
                    [
                        'path' => 'organizer.email',
                        'label' => 'Contact Email',
                        'description' => 'Organizer contact email',
                        'type' => 'email',
                        'example' => 'contact@example.com',
                    ],
                    [
                        'path' => 'organizer.ticket_terms',
                        'label' => 'Ticket Terms & Conditions',
                        'description' => 'Organizer-specific ticket terms and conditions',
                        'type' => 'text',
                        'example' => 'Biletul nu este returnabil. Accesul cu act de identitate valid. Organizatorul își rezervă dreptul de a modifica programul.',
                    ],
                ],
            ],

            // Legal & terms
            'legal' => [
                'label' => 'Legal & Terms',
                'variables' => [
                    [
                        'path' => 'legal.terms',
                        'label' => 'Terms & Conditions',
                        'description' => 'Terms and conditions text',
                        'type' => 'text',
                        'example' => 'Non-refundable. Valid ID required.',
                    ],
                    [
                        'path' => 'legal.disclaimer',
                        'label' => 'Disclaimer',
                        'description' => 'Legal disclaimer',
                        'type' => 'text',
                        'example' => 'Event details subject to change.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get flat list of all variable paths
     *
     * @return array
     */
    public function getVariablePaths(): array
    {
        $paths = [];
        $variables = $this->getAvailableVariables();

        foreach ($variables as $group) {
            foreach ($group['variables'] as $variable) {
                $paths[] = $variable['path'];
            }
        }

        return $paths;
    }

    /**
     * Get sample data for preview
     *
     * @return array
     */
    public function getSampleData(): array
    {
        return [
            'event' => [
                'name' => 'Summer Music Festival 2025',
                'description' => 'The biggest outdoor music festival of the year',
                'category' => 'Music Festival',
                'image' => 'https://placehold.co/800x400/1a1a2e/ffffff?text=Event+Image',
            ],
            'venue' => [
                'name' => 'Arena Națională',
                'address' => 'Strada Basarabia 37-39, Sector 2',
                'city' => 'București',
                'program_today' => '09:00 – 19:00',
                'season_label' => 'Sezon vară',
            ],
            'date' => [
                'start' => '2025-07-15',
                'start_formatted' => '15 iulie 2025',
                'end' => '',
                'end_formatted' => '',
                'time' => '20:00',
                'end_time' => '23:00',
                'time_range' => '20:00 – 23:00',
                'time_label' => 'Ora: 20:00 – 23:00',
                'doors_open' => '18:00',
                'doors_label' => 'Doors: 18:00',
                'day_name' => 'Sâmbătă',
                'full_text' => 'Sâmbătă | 15 iulie 2025 | Ora: 20:00 – 23:00 | Doors: 18:00',
            ],
            'ticket' => [
                'type' => 'VIP Access',
                'price' => '299.00 RON',
                'section' => 'Sectiunea A',
                'row' => 'Randul 5',
                'seat' => 'Locul 12',
                'number' => 'TKT-2025-001234',
                'code_short' => 'WLMVWB2G',
                'code_long' => 'b35b6fb0-52e7-43d3-88d9-3ccdc5e874a8',
                'serial' => 'AMB-42-0001',
                'is_insured' => 'true',
                'insurance_badge' => "\u{1F6E1}\u{FE0F}",
                'insurance_label' => 'Bilet asigurat',
                'price_detail' => 'Preț: 299,00 lei + 14,95 lei taxă procesare (313,95 lei)',
                'fees_text' => 'Prețul include 5% Timbru Muzical, 2% Taxa de Monument Istoric',
                'verify_url' => 'https://tickets.example.com/t/WLMVWB2G',
                'description' => 'Acces VIP cu drink de bun venit și loc rezervat în primele rânduri',
                'perks' => '• Acces VIP Lounge • Drink de bun venit • Loc rezervat',
                'includes' => "• Acces toată ziua\n• Hartă tipărită\n• Apă gratuită",
                'includes_raw' => "Acces toată ziua\nHartă tipărită\nApă gratuită",
                'usage_terms' => 'Biletul este valabil doar pentru data selectată. Nu se rambursează.',
            ],
            'buyer' => [
                'name' => 'Ion Popescu',
                'first_name' => 'Ion',
                'last_name' => 'Popescu',
                'email' => 'ion.popescu@example.com',
            ],
            'order' => [
                'code' => 'ORD-2025-789456',
                'date' => '2025-06-01',
                'total' => '299.00 EUR',
                'addon_services' => "🛶 Plimbare cu barca · cod: WLMVWB2G\n🎯 Vaporaș 30min · cod: VAP-A1B2",
                'addon_services_html' => '<div>🛶 <strong>Plimbare cu barca</strong> · cod: WLMVWB2G</div><div>🎯 <strong>Vaporaș 30min</strong> · cod: VAP-A1B2</div>',
            ],
            'barcode' => 'TKT2025001234',
            'qrcode' => 'https://tickets.example.com/verify/TKT2025001234',
            'organizer' => [
                'name' => 'Live Nation Romania',
                'company_name' => 'Live Nation Entertainment SRL',
                'tax_id' => 'RO12345678',
                'company_address' => 'Strada Victoriei 25, Sector 1',
                'city' => 'București',
                'website' => 'https://eventpilot.ro',
                'phone' => '+40 123 456 789',
                'email' => 'contact@eventpilot.ro',
                'ticket_terms' => 'Biletul nu este returnabil. Accesul cu act de identitate valid. Organizatorul își rezervă dreptul de a modifica programul.',
            ],
            'legal' => [
                'terms' => 'Non-refundable. Valid ID required at entry. Event may be rescheduled.',
                'disclaimer' => 'Event details subject to change without notice.',
            ],
        ];
    }

    /**
     * Resolve variable value from sample data
     *
     * @param string $path Variable path (e.g., "event.name")
     * @param array|null $data Custom data or use sample data
     * @return mixed
     */
    public function resolveVariable(string $path, ?array $data = null)
    {
        $data = $data ?? $this->getSampleData();

        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Build the {{date.*}} block for a ticket / invitation / preview.
     *
     * Smart resolution rules:
     *  - single_day:  use event.event_date + start_time / end_time
     *  - range:       full date range by default (covers invitations,
     *                 subscriptions, anything without a per-day valid_date);
     *                 a ticket type with valid_date renders just that day
     *  - multi_day:   use the performance's starts_at when provided;
     *                 otherwise event.event_date as fallback
     *
     * Returned shape matches what the editor variable picker and
     * getSampleData advertise — every key is always present (empty string
     * when no data) so templates can drop in any {{date.*}} without a
     * missing-path placeholder leaking through.
     *
     * Reused by InvitationsController so invitations get the same smart
     * range / labelled / Romanian-day-name treatment as ticket PDFs.
     */
    public function buildDateBlock($event, $ticketType = null, $performance = null): array
    {
        $durationMode = $event?->duration_mode ?? 'single_day';
        $eventDate = null;
        $endDate = null;
        $startTime = '';
        $endTime = '';
        $doorTime = '';
        $isRangeDisplay = false;

        switch ($durationMode) {
            case 'range':
                if ($ticketType?->valid_date && !$ticketType?->is_subscription) {
                    // Single-day ticket within range: show the valid_date
                    $eventDate = $ticketType->valid_date;
                } else {
                    // Subscription, invitation, or anything else — show full
                    // range. isRangeDisplay flips on when we actually have
                    // both endpoints; otherwise we degrade to a single date.
                    $eventDate = $event?->range_start_date;
                    $endDate = $event?->range_end_date;
                    $isRangeDisplay = $endDate !== null;
                }
                $startTime = $event?->range_start_time ?? '';
                $endTime = $event?->range_end_time ?? '';
                $doorTime = $event?->door_time ?? '';
                break;

            case 'multi_day':
                if ($performance?->starts_at) {
                    $eventDate = $performance->starts_at->copy()->startOfDay();
                    $startTime = $performance->starts_at->format('H:i');
                    $doorTime = $performance->door_time ?? '';
                } else {
                    $eventDate = $event?->event_date;
                    $startTime = $event?->start_time ?? '';
                    $doorTime = $event?->door_time ?? '';
                }
                break;

            default: // single_day, recurring
                $eventDate = $event?->event_date;
                $startTime = $event?->start_time ?? '';
                $endTime = $event?->end_time ?? '';
                $doorTime = $event?->door_time ?? '';
                break;
        }

        // Normalize HH:MM (DB sometimes stores HH:MM:SS or full datetimes).
        $startTime = $startTime ? substr((string) $startTime, 0, 5) : '';
        $endTime = $endTime ? substr((string) $endTime, 0, 5) : '';
        $doorTime = $doorTime ? substr((string) $doorTime, 0, 5) : '';

        // Smart formatter for date or range. Collapses redundant tokens so
        //   same month+year  -> "23–25 iunie 2026"
        //   same year only   -> "23 iunie – 5 iulie 2026"
        //   different years  -> "23 dec 2026 – 5 ian 2027"
        //   no end / same day-> "23 iunie 2026"
        $formatDateOrRange = function ($start, $end) {
            if (!$start) return '';
            if (!$end || $start->isSameDay($end)) {
                return $start->locale('ro')->translatedFormat('j F Y');
            }
            if ($start->year !== $end->year) {
                return $start->locale('ro')->translatedFormat('j F Y')
                    . ' – ' . $end->locale('ro')->translatedFormat('j F Y');
            }
            if ($start->month !== $end->month) {
                return $start->locale('ro')->translatedFormat('j F')
                    . ' – ' . $end->locale('ro')->translatedFormat('j F Y');
            }
            return $start->format('j') . '–' . $end->locale('ro')->translatedFormat('j F Y');
        };

        $startFormatted = $formatDateOrRange($eventDate, $isRangeDisplay ? $endDate : null);
        $endFormatted = $endDate ? $endDate->locale('ro')->translatedFormat('j F Y') : '';
        $dateStartRaw = $eventDate ? $eventDate->format('Y-m-d') : '';
        $dateEndRaw = $endDate ? $endDate->format('Y-m-d') : '';

        // Carbon's ->locale('ro')->dayName silently falls back to English on
        // some setups (depends on intl + ICU data being loaded). Explicit
        // map guarantees Romanian regardless of server config.
        $dayNamesRo = [
            'Monday' => 'Luni', 'Tuesday' => 'Marți', 'Wednesday' => 'Miercuri',
            'Thursday' => 'Joi', 'Friday' => 'Vineri', 'Saturday' => 'Sâmbătă', 'Sunday' => 'Duminică',
        ];
        $dayName = ($eventDate && !$isRangeDisplay)
            ? ($dayNamesRo[$eventDate->format('l')] ?? '')
            : '';

        if ($startTime && $endTime && $startTime !== $endTime) {
            $timeRange = $startTime . ' – ' . $endTime;
        } elseif ($startTime) {
            $timeRange = $startTime;
        } else {
            $timeRange = '';
        }

        $timeLabel = $timeRange !== '' ? 'Ora: ' . $timeRange : '';
        $doorsLabel = $doorTime !== '' ? 'Doors: ' . $doorTime : '';

        $fullTextParts = [];
        if ($dayName) {
            $fullTextParts[] = mb_convert_case($dayName, MB_CASE_TITLE, 'UTF-8');
        }
        if ($startFormatted) {
            $fullTextParts[] = $startFormatted;
        }
        if ($timeRange) {
            $fullTextParts[] = 'Ora: ' . $timeRange;
        }
        if ($doorTime) {
            $fullTextParts[] = 'Doors: ' . $doorTime;
        }
        $fullText = implode(' | ', $fullTextParts);

        return [
            'start' => $dateStartRaw,
            'start_formatted' => $startFormatted,
            'end' => $dateEndRaw,
            'end_formatted' => $endFormatted,
            'time' => $startTime,
            'end_time' => $endTime,
            'time_range' => $timeRange,
            'time_label' => $timeLabel,
            'doors_open' => $doorTime,
            'doors_label' => $doorsLabel,
            'day_name' => $dayName,
            'full_text' => $fullText,
        ];
    }

    /**
     * Resolve real ticket data from a Ticket model
     * Maps actual database values to the same variable paths used by getSampleData()
     *
     * @param  Ticket  $ticket
     * @param  ?string $locale  Locale preferat (RO/EN/HU/etc.). NULL = foloseste
     *                          Ticket.locale → Order.locale → 'ro' (backward compat).
     *                          Toate call-site-urile existente fara param continua
     *                          sa primeasca exact aceleasi valori (default 'ro').
     */
    /**
     * Extrage locale-ul efectiv al unui Ticket. Folosit de call-site-urile care
     * randeaza biletul (PDF/SVG/HTML) ca sa-l propage atat la resolveTicketData
     * cat si la TicketPreviewGenerator->renderToHtml (care citeste
     * layer.content_translations[locale]).
     *
     * Cascada: ticket.locale → order.locale → 'ro' (default, backward compat).
     */
    public function resolveOrderLocale(?Ticket $ticket): string
    {
        if (!$ticket) return 'ro';
        return $ticket->locale ?? $ticket->order?->locale ?? 'ro';
    }

    public function resolveTicketData(Ticket $ticket, ?string $locale = null): array
    {
        $order = $ticket->order;
        $ticketType = $ticket->ticketType;
        $event = $ticket->resolveEvent();
        $venue = $event?->venue;
        $marketplaceEvent = $ticket->marketplaceEvent;
        $organizer = $order?->marketplaceOrganizer;
        $meta = $ticket->meta ?? [];
        $orderMeta = $order?->meta ?? [];

        // Determina locale-ul efectiv. Fallback strict: 'ro' ramane defaultul,
        // deci comenzile vechi (Ticket.locale=NULL si Order.locale=NULL) raman
        // 100% identice cu fluxul actual.
        $effectiveLocale = $locale
            ?? ($ticket->locale ?? null)
            ?? ($order?->locale ?? null)
            ?? 'ro';

        // Resolve event title (translatable) — cu fallback grațios la 'ro' apoi 'en'.
        $eventTitle = $event ? ($this->localizedAttr($event, 'title', $effectiveLocale) ?? '') : '';

        // Resolve event subtitle/description — locale preferat, apoi RO/EN.
        $eventDescription = '';
        if ($event) {
            $eventDescription = $this->localizedAttr($event, 'subtitle', $effectiveLocale)
                ?? $this->localizedAttr($event, 'short_description', $effectiveLocale)
                ?? '';
        }

        // Resolve venue name (translatable)
        $venueName = $venue ? ($this->localizedAttr($venue, 'name', $effectiveLocale) ?? '') : '';
        if (!$venueName) {
            $venueName = $marketplaceEvent?->venue_name ?? '';
        }

        // Event date info — extracted into a public helper so the
        // InvitationsController (which builds its own data array) can
        // produce the exact same {{date.*}} shape without duplicating the
        // range / locale / smart-label logic.
        $dateBlock = $this->buildDateBlock($event, $ticketType, $ticket->performance);

        // Seat details
        $seatDetails = $ticket->getSeatDetails();

        // Insurance
        $isInsured = !empty($meta['has_insurance']) || !empty($orderMeta['ticket_insurance']);

        // Commission — ticket type custom commission takes priority
        if ($ticketType && $ticketType->commission_type) {
            $commissionMode = $ticketType->commission_mode
                ?? $orderMeta['commission_mode']
                ?? $event?->commission_mode
                ?? $organizer?->getEffectiveCommissionMode()
                ?? 'included';
            $commissionRate = (float) ($ticketType->commission_rate ?? 5);
        } else {
            $commissionMode = $orderMeta['commission_mode']
                ?? $event?->commission_mode
                ?? $organizer?->getEffectiveCommissionMode()
                ?? 'included';
            $commissionRate = (float) ($order?->commission_rate
                ?? $event?->commission_rate
                ?? $organizer?->getEffectiveCommissionRate()
                ?? 5);
        }
        // Use the discount-adjusted price for display. getEffectivePrice
        // returns ticket.price unchanged when no order-level discount was
        // applied, so this is safe for the (overwhelmingly) common case.
        $rawTicketPrice = (float) ($ticket->price ?? 0);
        $ticketPrice = $rawTicketPrice > 0
            ? $ticket->getEffectivePrice()
            : (float) ($ticketType?->display_price ?? 0);
        $commissionPerUnit = $rawTicketPrice > 0 ? $ticket->getCommissionPerUnit() : 0.0;
        $currency = $order?->currency ?? $ticketType?->currency ?? 'RON';

        // Build computed fields
        $priceDetail = $this->buildPriceDetail($ticketPrice, $commissionRate, $commissionMode, $currency, $commissionPerUnit);
        $feesText = $this->buildFeesText($event);
        $serial = $this->buildSerialNumber($ticket);

        // Buyer info
        $buyerName = $order?->customer_name ?? $ticket->attendee_name ?? '';
        $nameParts = explode(' ', $buyerName, 2);

        // Leisure venue fields (program + season + addon services) — DOAR pentru
        // evenimente leisure_venue. Pe celelalte tipuri evenimente nu calculăm
        // nimic (rămân empty) ca să nu afectăm template-urile / biletele existente.
        $isLeisure = ($event?->display_template ?? 'standard') === 'leisure_venue';
        $visitDate = $meta['visit_date'] ?? $orderMeta['visit_date'] ?? null;
        if (!$visitDate && $event) {
            $visitDate = $event->event_date?->format('Y-m-d');
        }
        $leisureProgram = $isLeisure
            ? $this->buildLeisureProgramFields($event, $visitDate, $effectiveLocale)
            : ['program_today' => '', 'season_label' => ''];
        $addonServices = $isLeisure ? $this->buildAddonServicesText($order, $ticket->id, $effectiveLocale) : '';
        $addonServicesHtml = $isLeisure ? $this->buildAddonServicesHtml($order, $ticket->id, $effectiveLocale) : '';

        return [
            'event' => [
                'name' => $eventTitle,
                'description' => $eventDescription,
                'category' => $marketplaceEvent?->category ?? '',
                'image' => $this->resolveEventImageUrl($event, $marketplaceEvent),
            ],
            'venue' => [
                'name' => $venueName,
                'address' => $venue?->address ?? $marketplaceEvent?->venue_address ?? '',
                'city' => $venue?->city ?? $marketplaceEvent?->venue_city ?? '',
                'program_today' => $leisureProgram['program_today'],
                'season_label' => $leisureProgram['season_label'],
            ],
            'date' => $dateBlock,
            'ticket' => [
                'type' => $this->localizedTicketTypeName($ticketType, $effectiveLocale)
                    ?? ($ticket->marketplaceTicketType?->name ?? ''),
                'price' => number_format($ticketPrice, 2) . ' ' . $currency,
                // Seat placement variables include their Romanian label in the
                // output so ticket templates can drop them in plain text. When
                // the underlying value is missing, we render an empty string —
                // we don't want a dangling "Sectiunea " with no content.
                'section' => ($seatDetails['section_name'] ?? '') !== '' ? 'Sectiunea ' . $seatDetails['section_name'] : '',
                'row' => ($seatDetails['row_label'] ?? '') !== '' ? 'Randul ' . $seatDetails['row_label'] : '',
                'seat' => ($seatDetails['seat_number'] ?? '') !== '' ? 'Locul ' . $seatDetails['seat_number'] : '',
                'number' => $serial,
                'code_short' => $ticket->code ?? '',
                'code_long' => $ticket->barcode ?? '',
                'serial' => $serial,
                'is_insured' => $isInsured ? 'true' : 'false',
                'insurance_badge' => $isInsured ? "\u{1F6E1}\u{FE0F}" : '',
                'insurance_label' => $isInsured ? 'Bilet asigurat' : '',
                'price_detail' => $priceDetail,
                'fees_text' => $feesText,
                'verify_url' => $ticket->getVerifyUrl(),
                'description' => $this->resolveTicketDescription($ticketType),
                'perks' => $this->resolveTicketPerks($ticketType),
                'includes' => $this->resolveTicketIncludes($ticketType, $effectiveLocale, true),
                'includes_raw' => $this->resolveTicketIncludes($ticketType, $effectiveLocale, false),
                'usage_terms' => $this->resolveTicketUsageTerms($ticketType, $effectiveLocale),
            ],
            'buyer' => [
                'name' => $buyerName,
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'email' => $order?->customer_email ?? $ticket->attendee_email ?? '',
            ],
            'order' => [
                'code' => $order?->order_number ?? '',
                'date' => $order?->created_at?->format('Y-m-d') ?? '',
                'total' => $order ? number_format((float) $order->total, 2) . ' ' . $currency : '',
                'addon_services' => $addonServices,
                'addon_services_html' => $addonServicesHtml,
            ],
            'barcode' => $ticket->barcode ?? $ticket->code ?? '',
            'qrcode' => $ticket->code ?? $ticket->barcode ?? '',
            'organizer' => [
                'name' => $organizer?->name ?? '',
                'company_name' => $organizer?->company_name ?? '',
                'tax_id' => $organizer?->company_tax_id ?? '',
                'company_address' => $organizer?->company_address ?? '',
                'city' => $organizer?->company_city ?? '',
                'website' => $organizer?->website ?? '',
                'phone' => $organizer?->phone ?? '',
                'email' => $organizer?->email ?? '',
                'ticket_terms' => $organizer?->ticket_terms ?? '',
            ],
            'legal' => [
                'terms' => $organizer?->ticket_terms
                    ?? ($event ? ($this->localizedAttr($event, 'ticket_terms', $effectiveLocale) ?? '') : ''),
                'disclaimer' => '',
            ],
        ];
    }

    /**
     * Build the smart price detail text based on commission mode.
     *
     * @param  float  $price  The (possibly discount-adjusted) per-ticket price.
     * @param  float  $commissionRate  Display rate, used only when no actual
     *                                 commission amount is available (preview).
     * @param  string $commissionMode  on_top / added_on_top / included.
     * @param  string $currency  Order currency code.
     * @param  float  $commissionPerTicket  Actual per-ticket commission charged
     *         at sale time. Passed by resolveTicketData(); 0 when unknown
     *         (preview / template editor) — in which case we fall back to
     *         rate × price so the preview still renders a plausible number.
     */
    private function buildPriceDetail(float $price, float $commissionRate, string $commissionMode, string $currency, float $commissionPerTicket = 0.0): string
    {
        $currencyLabel = strtolower($currency) === 'ron' ? 'lei' : $currency;
        $formattedPrice = number_format($price, 2, ',', '.');

        if (in_array($commissionMode, ['added_on_top', 'on_top'])) {
            // Use the commission actually charged at sale time when known
            // (so the line matches what the customer paid even after a
            // promo-code discount shrinks the displayed base price).
            $commission = $commissionPerTicket > 0
                ? $commissionPerTicket
                : round($price * $commissionRate / 100, 2);
            $totalWithCommission = $price + $commission;
            $formattedTotal = number_format($totalWithCommission, 2, ',', '.');
            $formattedCommission = number_format($commission, 2, ',', '.');
            return "Preț: {$formattedPrice} {$currencyLabel} + {$formattedCommission} {$currencyLabel} taxă procesare ({$formattedTotal} {$currencyLabel})";
        }

        return "Preț: {$formattedPrice} {$currencyLabel} (taxă procesare inclusă)";
    }

    /**
     * Build the fees text listing all visible-on-ticket taxes
     */
    private function buildFeesText(?Event $event): string
    {
        if (!$event) {
            return '';
        }

        $taxes = collect();

        if ($event->tenant_id) {
            // Tenant context: query taxes by tenant
            $taxes = GeneralTax::query()
                ->forTenant($event->tenant_id)
                ->active()
                ->visibleOnTicket()
                ->get();
        } else {
            // Marketplace context: query global taxes by event types
            $eventTypeIds = $event->eventTypes?->pluck('id')->all() ?? [];
            if (!empty($eventTypeIds)) {
                $taxes = GeneralTax::query()
                    ->whereNull('tenant_id')
                    ->active()
                    ->visibleOnTicket()
                    ->forEventTypes($eventTypeIds)
                    ->get()
                    ->unique('id');
            }
        }

        if ($taxes->isEmpty()) {
            return '';
        }

        // Mirror the filtering in EventResource "Taxe Aplicabile":
        //   - skip Monument Istoric unless venue has the flag enabled
        //   - skip TVA unless the marketplace organizer is a VAT payer
        // Without these filters every ticket showed 'Taxa de Monument
        // Istoric' / 'TVA' regardless of whether they actually applied.
        $venueHasMonumentTax = (bool) ($event->venue?->has_historical_monument_tax ?? false);
        $isVatPayer = (bool) ($event->marketplaceClient?->vat_payer ?? false);

        $taxes = $taxes->filter(function ($tax) use ($venueHasMonumentTax, $isVatPayer) {
            $nameLower = strtolower($tax->name ?? '');

            if (str_contains($nameLower, 'monument') && !$venueHasMonumentTax) {
                return false;
            }

            if ((str_contains($nameLower, 'tva') || str_contains($nameLower, 'vat')) && !$isVatPayer) {
                return false;
            }

            return true;
        });

        if ($taxes->isEmpty()) {
            return '';
        }

        $parts = $taxes->map(fn ($tax) => $tax->getFormattedValue() . ' ' . $tax->name)->all();

        return 'Prețul include ' . implode(', ', $parts);
    }

    /**
     * Resolve event image URL (marketplace image → hero → poster).
     * Public so controllers that render tickets without a Ticket record
     * (e.g. organizer invitations) can populate `event.image` too.
     */
    public function resolveEventImageUrl($event, $marketplaceEvent = null): string
    {
        // Try marketplace event image first
        if ($marketplaceEvent?->image_url) {
            $url = $marketplaceEvent->image_url;
            if (str_starts_with($url, 'http')) return $url;
            return \Illuminate\Support\Facades\Storage::disk('public')->url($url);
        }

        // Try event hero image
        if ($event?->hero_image_url) {
            $url = $event->hero_image_url;
            if (str_starts_with($url, 'http')) return $url;
            return \Illuminate\Support\Facades\Storage::disk('public')->url($url);
        }

        // Try event poster
        if ($event?->poster_url) {
            $url = $event->poster_url;
            if (str_starts_with($url, 'http')) return $url;
            return \Illuminate\Support\Facades\Storage::disk('public')->url($url);
        }

        return '';
    }

    /**
     * Resolve ticket type description (translatable or plain)
     */
    private function resolveTicketDescription($ticketType): string
    {
        if (!$ticketType) return '';

        $desc = $ticketType->description ?? '';

        if (is_array($desc)) {
            return $desc['ro'] ?? $desc['en'] ?? (reset($desc) ?: '');
        }

        return (string) $desc;
    }

    /**
     * Resolve ticket type perks/conditions list (joined as bullet text)
     */
    private function resolveTicketPerks($ticketType): string
    {
        if (!$ticketType) return '';

        $perks = $ticketType->perks ?? [];

        if (!is_array($perks) || empty($perks)) {
            return '';
        }

        // Each perk may be a string or an array with 'text' / 'value'
        $items = array_map(function ($perk) {
            if (is_string($perk)) return trim($perk);
            if (is_array($perk)) {
                return trim($perk['text'] ?? $perk['value'] ?? $perk['name'] ?? '');
            }
            return '';
        }, $perks);

        $items = array_filter($items, fn ($v) => $v !== '');

        if (empty($items)) return '';

        return '• ' . implode(' • ', $items);
    }

    /**
     * Rezolva lista "Include" a produsului leisure (meta.includes).
     * Suporta multi-locale prin meta.translations.includes.{locale}. Cand traducerea
     * lipseste, cade pe RO (meta.includes) apoi EN.
     *
     * @param  bool  $bulleted  true -> fiecare linie prefixata cu "• "; false -> plain lines
     */
    private function resolveTicketIncludes($ticketType, string $locale, bool $bulleted): string
    {
        if (!$ticketType) return '';
        $meta = $ticketType->meta ?? null;
        if (!is_array($meta)) return '';

        // 1. Locale preferat din meta.translations
        $items = null;
        $tr = $meta['translations']['includes'][$locale] ?? null;
        if ($tr !== null && $tr !== '') {
            $items = is_array($tr) ? $tr : preg_split('/\r?\n/', (string) $tr);
        }
        // 2. Fallback RO/EN in translations, apoi meta.includes (RO default)
        if ($items === null) {
            $trFallback = $meta['translations']['includes']['ro']
                ?? $meta['translations']['includes']['en']
                ?? null;
            if ($trFallback !== null && $trFallback !== '') {
                $items = is_array($trFallback) ? $trFallback : preg_split('/\r?\n/', (string) $trFallback);
            }
        }
        if ($items === null) {
            $raw = $meta['includes'] ?? null;
            if ($raw === null || $raw === '') return '';
            $items = is_array($raw) ? $raw : preg_split('/\r?\n/', (string) $raw);
        }

        $items = array_values(array_filter(array_map(fn ($s) => trim((string) $s), $items), fn ($s) => $s !== ''));
        if (empty($items)) return '';

        if ($bulleted) {
            return implode("\n", array_map(fn ($s) => '• ' . $s, $items));
        }
        return implode("\n", $items);
    }

    /**
     * Rezolva textul "Termeni utilizare" al produsului leisure (TicketType.usage_terms).
     * Suporta multi-locale prin meta.translations.usage_terms.{locale}.
     */
    private function resolveTicketUsageTerms($ticketType, string $locale): string
    {
        if (!$ticketType) return '';
        $meta = $ticketType->meta ?? null;
        if (is_array($meta) && isset($meta['translations']['usage_terms']) && is_array($meta['translations']['usage_terms'])) {
            $tr = $meta['translations']['usage_terms'];
            $value = $tr[$locale] ?? $tr['ro'] ?? $tr['en'] ?? null;
            if ($value !== null && $value !== '') return trim((string) $value);
        }
        $raw = $ticketType->usage_terms ?? '';
        return $raw !== '' ? trim((string) $raw) : '';
    }

    /**
     * Calculează programul orar al locației pe data biletului + numele sezonului.
     * Citește din event.venue_config.seasons[] cu structura definită în Filament:
     *   { name, start (MM-DD), end (MM-DD), last_entry,
     *     schedule_list: [{ day: mon|tue|..., open: 'HH:MM', close: 'HH:MM' }] }
     *
     * @param  \App\Models\Event|null  $event
     * @param  string|null  $visitDate  YYYY-MM-DD
     * @return array{program_today: string, season_label: string}
     */
    private function buildLeisureProgramFields($event, ?string $visitDate, string $locale = 'ro'): array
    {
        $empty = ['program_today' => '', 'season_label' => ''];
        if (!$event || !$visitDate) return $empty;

        $venueConfig = is_array($event->venue_config ?? null) ? $event->venue_config : [];
        $seasons = is_array($venueConfig['seasons'] ?? null) ? $venueConfig['seasons'] : [];
        if (empty($seasons)) return $empty;

        try {
            $dateObj = \Carbon\Carbon::parse($visitDate);
        } catch (\Throwable $e) {
            return $empty;
        }
        $mmdd = $dateObj->format('m-d');
        $dowKey = ['sun','mon','tue','wed','thu','fri','sat'][(int) $dateObj->format('w')];

        // Caut sezonul activ (MM-DD între start și end, cu wrap-around dec→jan)
        $activeSeason = null;
        foreach ($seasons as $s) {
            if (!is_array($s)) continue;
            $start = $s['start'] ?? '01-01';
            $end = $s['end'] ?? '12-31';
            $inSeason = $start <= $end
                ? ($mmdd >= $start && $mmdd <= $end)
                : ($mmdd >= $start || $mmdd <= $end);
            if ($inSeason) {
                $activeSeason = $s;
                break;
            }
        }
        if (!$activeSeason) return $empty;

        // season.name poate fi:
        //   - string simplu ("Sezon vara") → folosit ca atare
        //   - array multi-locale ({"ro":"Sezon vara","hu":"Nyár","en":"Summer"}) → ales pe locale cu fallback
        // Organizatorul completeaza câmpul în Filament; pana introduce variante,
        // folosirea string-ului simplu ramane neschimbata.
        $rawName = $activeSeason['name'] ?? '';
        $seasonLabel = is_array($rawName)
            ? (string) ($rawName[$locale] ?? $rawName['ro'] ?? $rawName['en'] ?? reset($rawName) ?: '')
            : (string) $rawName;

        // Program pe ziua respectivă (DOW)
        $programToday = '';
        $scheduleList = is_array($activeSeason['schedule_list'] ?? null) ? $activeSeason['schedule_list'] : [];
        foreach ($scheduleList as $entry) {
            if (!is_array($entry) || ($entry['day'] ?? null) !== $dowKey) continue;
            $open = $entry['open'] ?? null;
            $close = $entry['close'] ?? null;
            if ($open && $close) {
                $programToday = $open . ' – ' . $close;
            } elseif ($open) {
                $programToday = $open;
            }
            break;
        }

        return ['program_today' => $programToday, 'season_label' => $seasonLabel];
    }

    /**
     * Helper privat — citeste atribut translatable cu cascada de fallback:
     *   locale preferat → 'ro' → 'en' → prima valoare disponibila → null.
     *
     * Trateaza atat modelele cu trait `Translatable` (Event, Venue) cat si
     * modelele cu attribut JSON simplu (TicketType.name daca devine multi-locale
     * in viitor).
     */
    private function localizedAttr($model, string $field, string $locale): ?string
    {
        if (!$model) return null;

        // Cale 1: trait Translatable → getTranslation()
        if (method_exists($model, 'getTranslation')) {
            $value = $model->getTranslation($field, $locale);
            if ($value !== null && $value !== '') return (string) $value;
            // Fallback chain
            $fallback = $model->getTranslation($field, 'ro')
                ?? $model->getTranslation($field, 'en');
            if ($fallback !== null && $fallback !== '') return (string) $fallback;
        }

        // Cale 2: atribut JSON simplu (array) sau string
        $raw = $model->{$field} ?? null;
        if (is_array($raw)) {
            $value = $raw[$locale] ?? $raw['ro'] ?? $raw['en'] ?? null;
            if ($value === null || $value === '') {
                $value = reset($raw) ?: null;
            }
            return $value !== null ? (string) $value : null;
        }
        return $raw !== null && $raw !== '' ? (string) $raw : null;
    }

    /**
     * Numele unui TicketType cu suport multi-locale prin `meta.translations.name`.
     * Cand `meta.translations` lipseste sau locale-ul nu e completat, foloseste
     * string-ul simplu actual (backward compat 100%).
     *
     *   meta.translations: { "name": { "ro": "Bilet adult", "hu": "Felnőtt jegy", "en": "Adult ticket" } }
     */
    private function localizedTicketTypeName($ticketType, string $locale): ?string
    {
        if (!$ticketType) return null;

        $meta = $ticketType->meta ?? null;
        if (is_array($meta) && isset($meta['translations']['name']) && is_array($meta['translations']['name'])) {
            $tt = $meta['translations']['name'];
            $value = $tt[$locale] ?? $tt['ro'] ?? $tt['en'] ?? null;
            if ($value !== null && $value !== '') return (string) $value;
        }

        $name = $ticketType->name ?? null;
        if (is_array($name)) {
            return (string) ($name[$locale] ?? $name['ro'] ?? $name['en'] ?? (reset($name) ?: ''));
        }
        return $name !== null && $name !== '' ? (string) $name : null;
    }

    /**
     * Construiește lista multi-linie cu add-on services atribuite biletului curent.
     *
     * Algoritm distribuție per Order:
     *  1. Toate biletele acces (service_category='access'), sortate adulți prima (is_child_ticket=false),
     *     apoi copii, apoi după id ASC
     *  2. Toate biletele non-access (rental/activity/parking/extra)
     *  3. Round-robin: atribui fiecare bilet extra primului bilet acces compatibil:
     *     - access_requirement='adult_only' → doar pe bilet adult
     *     - access_requirement='any' sau none → orice acces
     *  4. Returnez doar add-on-urile atribuite ticketului curent
     *
     * Pentru biletele non-access (extras propriu-zise), returnez empty (au propriul QR pe bilet).
     *
     * Format pe linie: "{icon} {nume produs} · cod: {ticket.code}"
     * Returnează empty string dacă nu există add-on-uri pentru ticketul curent.
     */
    private function buildAddonServicesText(?Order $order, ?int $currentTicketId = null, string $locale = 'ro'): string
    {
        $assigned = $this->resolveAddonAssignmentsForTicket($order, $currentTicketId, $locale);
        if (empty($assigned)) return '';

        $emojiMap = [
            'access' => '🎟️', 'parking' => '🅿️', 'rental' => '🛶',
            'activity' => '🎯', 'extra' => '➕', 'package' => '🎁',
        ];
        $lines = [];
        foreach ($assigned as $entry) {
            $icon = $emojiMap[$entry['category']] ?? '✨';
            $codeFragment = $entry['code'] ? ' · cod: ' . $entry['code'] : '';
            $lines[] = sprintf('%s %s%s', $icon, $entry['name'], $codeFragment);
        }
        return implode("\n", $lines);
    }

    /**
     * Variantă HTML cu QR-uri vizuale inline pentru fiecare add-on.
     * Folosit prin {{ order.addon_services_html }} (placeholder cu suffix _html
     * detectat în renderTextLayerHtml pentru a sări peste htmlspecialchars).
     */
    private function buildAddonServicesHtml(?Order $order, ?int $currentTicketId = null, string $locale = 'ro'): string
    {
        $assigned = $this->resolveAddonAssignmentsForTicket($order, $currentTicketId, $locale);
        if (empty($assigned)) return '';

        $emojiMap = [
            'access' => '🎟️', 'parking' => '🅿️', 'rental' => '🛶',
            'activity' => '🎯', 'extra' => '➕', 'package' => '🎁',
        ];
        $rows = [];
        foreach ($assigned as $entry) {
            $icon = $emojiMap[$entry['category']] ?? '✨';
            $qrDataUri = $entry['code'] ? $this->buildQrDataUri($entry['code'], 80) : '';
            $qrImg = $qrDataUri
                ? '<img src="' . $qrDataUri . '" style="display:inline-block;vertical-align:middle;width:42pt;height:42pt;margin-right:6pt;">'
                : '';
            $codeText = $entry['code']
                ? '<span style="font-family:DejaVu Sans Mono,monospace;font-size:7pt;color:#256142;">' . htmlspecialchars($entry['code']) . '</span>'
                : '';
            $rows[] = sprintf(
                '<div style="display:block;margin-bottom:4pt;">%s<span style="vertical-align:middle;">%s <strong>%s</strong>%s%s</span></div>',
                $qrImg,
                $icon,
                htmlspecialchars($entry['name']),
                $codeText ? '<br>' : '',
                $codeText
            );
        }
        return implode('', $rows);
    }

    /**
     * Distribuie add-on-urile pe biletele de acces și returnează doar pe cele
     * atribuite biletului curent. Algoritm round-robin cu constraint adult_only.
     *
     * @return array<int,array{category:string,name:string,code:string,ticket_id:int}>
     */
    private function resolveAddonAssignmentsForTicket(?Order $order, ?int $currentTicketId, string $locale = 'ro'): array
    {
        if (!$order || !$currentTicketId) return [];

        $all = $order->tickets()->with(['ticketType'])->orderBy('id')->get();
        if ($all->isEmpty()) return [];

        $current = $all->firstWhere('id', $currentTicketId);
        if (!$current) return [];

        $currentCat = $current->ticketType?->service_category
            ?? ($current->meta['service_category'] ?? 'access');
        // Pentru biletele non-access (e.g. bilet barcă/vaporaș), nu listez add-ons
        // (acel bilet are propriul QR și nu acoperă altele)
        if ($currentCat !== 'access') return [];

        // Sortez biletele acces: adulți primii (is_child_ticket=false), apoi copii, apoi după id ASC
        $access = $all->filter(function ($t) {
            $cat = $t->ticketType?->service_category ?? ($t->meta['service_category'] ?? 'access');
            return $cat === 'access';
        })->sortBy(function ($t) {
            $tt = $t->ticketType;
            $isChild = (bool) ($tt?->meta['is_child_ticket'] ?? false);
            return [$isChild ? 1 : 0, $t->id];
        })->values();

        if ($access->isEmpty()) return [];

        // Extra-uri: tot ce nu e access
        $extras = $all->filter(function ($t) {
            $cat = $t->ticketType?->service_category ?? ($t->meta['service_category'] ?? 'access');
            return $cat !== 'access';
        })->values();

        if ($extras->isEmpty()) return [];

        // Round-robin distribution cu constraint adult_only
        $assignments = []; // accessId => [extra_ticket, ...]
        foreach ($access as $a) {
            $assignments[$a->id] = [];
        }
        $cursor = 0;
        $accessCount = $access->count();
        foreach ($extras as $extra) {
            $tt = $extra->ticketType;
            $req = $tt?->meta['access_requirement']
                ?? (($tt?->requires_access_ticket ?? false) ? 'any' : 'none');

            // Caut prima ținta compatibilă (din cursor, round-robin)
            $tries = 0;
            $assigned = false;
            while ($tries < $accessCount) {
                $candidate = $access[$cursor % $accessCount];
                $cursor++;
                $tries++;
                $isCandChild = (bool) ($candidate->ticketType?->meta['is_child_ticket'] ?? false);
                if ($req === 'adult_only' && $isCandChild) continue;
                $assignments[$candidate->id][] = $extra;
                $assigned = true;
                break;
            }
            // Dacă nu am găsit (e.g. adult_only fără adulți), skip silent
        }

        // Returnez add-on-urile pentru ticketul curent
        $mine = $assignments[$current->id] ?? [];
        $out = [];
        foreach ($mine as $extra) {
            $tt = $extra->ticketType;
            $cat = $tt?->service_category ?? 'extra';
            // Folosim helper-ul cu fallback grațios; daca traducerile lipsesc,
            // intoarce string-ul actual al biletului (zero regresie).
            $name = $this->localizedTicketTypeName($tt, $locale) ?? 'Serviciu';
            $out[] = [
                'category' => $cat,
                'name' => (string) $name,
                'code' => (string) ($extra->code ?? ''),
                'ticket_id' => (int) $extra->id,
            ];
        }
        return $out;
    }

    /**
     * Generează un QR ca data URI base64 PNG (via qrserver.com).
     * Fallback la string gol dacă fetch-ul eșuează.
     */
    private function buildQrDataUri(string $code, int $sizePx = 100): string
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $sizePx . 'x' . $sizePx . '&data=' . urlencode($code);
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $img = @file_get_contents($url, false, $ctx);
            if ($img === false || strlen($img) < 100) return '';
            return 'data:image/png;base64,' . base64_encode($img);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function buildSerialNumber(Ticket $ticket): string
    {
        $ticketType = $ticket->ticketType;
        $mktTicketType = $ticket->marketplaceTicketType;

        $eventSeries = $ticketType?->event_series ?? $mktTicketType?->event_series ?? '';

        // Fall back to the event's event_series + ticket type ID
        if (empty($eventSeries)) {
            $event = $ticket->resolveEvent();
            $eventSeriesPrefix = $event?->event_series ?? '';
            $typeId = $ticketType?->id ?? $mktTicketType?->id ?? '';
            if ($eventSeriesPrefix && $typeId) {
                $eventSeries = $eventSeriesPrefix . '-' . $typeId;
            } elseif ($eventSeriesPrefix) {
                $eventSeries = $eventSeriesPrefix;
            }
        }

        $seriesStart = (int) ($ticketType?->series_start ?? $mktTicketType?->series_start ?? 1);

        // Calculate position based on ticket_type_id or marketplace_ticket_type_id
        if ($ticket->ticket_type_id) {
            $position = Ticket::where('ticket_type_id', $ticket->ticket_type_id)
                ->where('id', '<=', $ticket->id)
                ->count();
        } elseif ($ticket->marketplace_ticket_type_id) {
            $position = Ticket::where('marketplace_ticket_type_id', $ticket->marketplace_ticket_type_id)
                ->where('id', '<=', $ticket->id)
                ->count();
        } else {
            return '';
        }

        $serialNum = $seriesStart + $position - 1;

        if ($eventSeries) {
            return $eventSeries . '-' . str_pad((string) $serialNum, 5, '0', STR_PAD_LEFT);
        }

        return (string) $serialNum;
    }
}
