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
                        'label' => 'Servicii extra (Leisure)',
                        'description' => 'Lista multi-linie cu serviciile extra cumpărate (rental/activity/parking). Format: "🚣 Plimbare cu barca · ×1 · cod: ABC123". Empty dacă nu există add-on-uri.',
                        'type' => 'text',
                        'example' => "🚣 Plimbare cu barca · ×1 · cod: WLMVWB2G\n🧺 Coș picnic · ×1 · cod: COSPI-AB2",
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
                'price_detail' => 'Preț: 299,00 lei + 5% taxă procesare (313,95 lei)',
                'fees_text' => 'Prețul include 5% Timbru Muzical, 2% Taxa de Monument Istoric',
                'verify_url' => 'https://tickets.example.com/t/WLMVWB2G',
                'description' => 'Acces VIP cu drink de bun venit și loc rezervat în primele rânduri',
                'perks' => '• Acces VIP Lounge • Drink de bun venit • Loc rezervat',
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
                'addon_services' => "🚣 Plimbare cu barca · ×1 · cod: WLMVWB2G\n🧺 Coș picnic · ×1 · cod: COSPI-AB2",
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
     */
    public function resolveTicketData(Ticket $ticket): array
    {
        $order = $ticket->order;
        $ticketType = $ticket->ticketType;
        $event = $ticket->resolveEvent();
        $venue = $event?->venue;
        $marketplaceEvent = $ticket->marketplaceEvent;
        $organizer = $order?->marketplaceOrganizer;
        $meta = $ticket->meta ?? [];
        $orderMeta = $order?->meta ?? [];

        // Resolve event title (translatable)
        $eventTitle = '';
        if ($event) {
            $eventTitle = $event->getTranslation('title', 'ro')
                ?? $event->getTranslation('title', 'en')
                ?? (is_array($event->title) ? (reset($event->title) ?: '') : ($event->title ?? ''));
        }

        // Resolve event subtitle/description
        $eventDescription = '';
        if ($event) {
            $eventDescription = $event->getTranslation('subtitle', 'ro')
                ?? $event->getTranslation('subtitle', 'en')
                ?? $event->getTranslation('short_description', 'ro')
                ?? '';
        }

        // Resolve venue name (translatable)
        $venueName = '';
        if ($venue) {
            $venueName = $venue->getTranslation('name', 'ro')
                ?? $venue->getTranslation('name', 'en')
                ?? '';
        }
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
        $ticketPrice = (float) ($ticket->price ?? $ticketType?->display_price ?? 0);
        $currency = $order?->currency ?? $ticketType?->currency ?? 'RON';

        // Build computed fields
        $priceDetail = $this->buildPriceDetail($ticketPrice, $commissionRate, $commissionMode, $currency);
        $feesText = $this->buildFeesText($event);
        $serial = $this->buildSerialNumber($ticket);

        // Buyer info
        $buyerName = $order?->customer_name ?? $ticket->attendee_name ?? '';
        $nameParts = explode(' ', $buyerName, 2);

        // Leisure venue fields (program + season + addon services)
        $visitDate = $meta['visit_date'] ?? $orderMeta['visit_date'] ?? null;
        if (!$visitDate && $event) {
            $visitDate = $event->event_date?->format('Y-m-d');
        }
        $leisureProgram = $this->buildLeisureProgramFields($event, $visitDate);
        $addonServices = $this->buildAddonServicesText($order, $ticket->id);

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
                'type' => $ticketType?->name ?? $ticket->marketplaceTicketType?->name ?? '',
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
                    ?? $event?->getTranslation('ticket_terms', 'ro')
                    ?? '',
                'disclaimer' => '',
            ],
        ];
    }

    /**
     * Build the smart price detail text based on commission mode
     */
    private function buildPriceDetail(float $price, float $commissionRate, string $commissionMode, string $currency): string
    {
        $currencyLabel = strtolower($currency) === 'ron' ? 'lei' : $currency;
        $formattedPrice = number_format($price, 2, ',', '.');

        if (in_array($commissionMode, ['added_on_top', 'on_top'])) {
            $totalWithCommission = $price * (1 + $commissionRate / 100);
            $formattedTotal = number_format($totalWithCommission, 2, ',', '.');
            $rateFormatted = rtrim(rtrim(number_format($commissionRate, 2), '0'), '.');
            return "Preț: {$formattedPrice} {$currencyLabel} + {$rateFormatted}% taxă procesare ({$formattedTotal} {$currencyLabel})";
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
     * Calculează programul orar al locației pe data biletului + numele sezonului.
     * Citește din event.venue_config.seasons[] cu structura definită în Filament:
     *   { name, start (MM-DD), end (MM-DD), last_entry,
     *     schedule_list: [{ day: mon|tue|..., open: 'HH:MM', close: 'HH:MM' }] }
     *
     * @param  \App\Models\Event|null  $event
     * @param  string|null  $visitDate  YYYY-MM-DD
     * @return array{program_today: string, season_label: string}
     */
    private function buildLeisureProgramFields($event, ?string $visitDate): array
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

        $seasonLabel = (string) ($activeSeason['name'] ?? '');

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
     * Construiește lista multi-linie cu add-on services din comandă.
     * Exclude tipul de bilet curent (biletul "principal") + categoria 'access'
     * + serviciile considerate add-on inline (fără propriul tichet emis).
     *
     * Format pe linie: "{icon} {nume produs} · ×{qty} · cod: {ticket.code}"
     * Returnează empty string dacă nu există add-on-uri.
     */
    private function buildAddonServicesText(?Order $order, ?int $excludeTicketId = null): string
    {
        if (!$order) return '';
        $items = $order->items()->with(['ticketType', 'tickets'])->get();
        if ($items->isEmpty()) return '';

        $emojiMap = [
            'access' => '🎟️', 'parking' => '🅿️', 'rental' => '🛶',
            'activity' => '🎯', 'extra' => '➕', 'package' => '🎁',
        ];
        $lines = [];
        foreach ($items as $oi) {
            $tt = $oi->ticketType;
            $cat = $tt?->service_category ?? ($oi->meta['service_category'] ?? null);
            // Excludem categoria 'access' (e biletul principal)
            if ($cat === 'access' || $cat === null) continue;
            $icon = $emojiMap[$cat] ?? '✨';
            $name = $oi->name ?: ($tt && $tt->name ? (is_array($tt->name) ? ($tt->name['ro'] ?? reset($tt->name)) : $tt->name) : 'Serviciu');
            // Lista codurilor biletelor emise pe această linie (DOAR primul, restul implicat de ×qty)
            $relatedTickets = $oi->tickets ?? collect();
            $firstCode = '';
            foreach ($relatedTickets as $rt) {
                if ($excludeTicketId && $rt->id === $excludeTicketId) continue;
                $firstCode = $rt->code ?? '';
                if ($firstCode) break;
            }
            $codeFragment = $firstCode ? ' · cod: ' . $firstCode : '';
            $lines[] = sprintf('%s %s · ×%d%s', $icon, $name, (int) $oi->quantity, $codeFragment);
        }
        return implode("\n", $lines);
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
