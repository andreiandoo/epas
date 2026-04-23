<?php

namespace App\Services\TicketCustomizer;

use App\Models\Event;
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
                        'description' => 'Event start date formatted',
                        'type' => 'string',
                        'example' => 'July 15, 2025',
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
                        'path' => 'date.doors_open',
                        'label' => 'Doors Open Time',
                        'description' => 'Time when doors open',
                        'type' => 'time',
                        'example' => '19:00',
                    ],
                    [
                        'path' => 'date.day_name',
                        'label' => 'Day Name',
                        'description' => 'Day of week',
                        'type' => 'string',
                        'example' => 'Saturday',
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
            ],
            'date' => [
                'start' => '2025-07-15',
                'start_formatted' => 'July 15, 2025',
                'time' => '20:00',
                'doors_open' => '18:00',
                'day_name' => 'Saturday',
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

        // Event date info — smart resolution based on duration_mode + ticket type
        $durationMode = $event?->duration_mode ?? 'single_day';
        $performance = $ticket->performance;
        $isRangeDisplay = false;
        $endDate = null;

        switch ($durationMode) {
            case 'range':
                if ($ticketType?->is_subscription) {
                    // Subscription (abonament): show full date range
                    $eventDate = $event?->range_start_date;
                    $endDate = $event?->range_end_date;
                    $isRangeDisplay = true;
                } elseif ($ticketType?->valid_date) {
                    // Single-day ticket within range: show the valid_date
                    $eventDate = $ticketType->valid_date;
                } else {
                    // Fallback: show range start date
                    $eventDate = $event?->range_start_date;
                }
                $startTime = $event?->range_start_time ?? '';
                $doorTime = $event?->door_time ?? '';
                break;

            case 'multi_day':
                if ($performance?->starts_at) {
                    // Performance-specific date/time
                    $eventDate = $performance->starts_at->copy()->startOfDay();
                    $startTime = $performance->starts_at->format('H:i');
                    $doorTime = $performance->door_time ?? '';
                } else {
                    // Fallback to event's base fields
                    $eventDate = $event?->event_date;
                    $startTime = $event?->start_time ?? '';
                    $doorTime = $event?->door_time ?? '';
                }
                break;

            default: // single_day, recurring
                $eventDate = $event?->event_date;
                $startTime = $event?->start_time ?? '';
                $doorTime = $event?->door_time ?? '';
                break;
        }

        // Build computed date display values
        if ($isRangeDisplay && $eventDate && $endDate) {
            $startFormatted = $eventDate->locale('ro')->translatedFormat('j F') . ' – ' . $endDate->locale('ro')->translatedFormat('j F Y');
            $dayName = '';
            $dateStartRaw = $eventDate->format('Y-m-d') . ' – ' . $endDate->format('Y-m-d');
        } else {
            $startFormatted = $eventDate ? $eventDate->locale('ro')->translatedFormat('j F Y') : '';
            $dayName = $eventDate ? $eventDate->locale('ro')->dayName : '';
            $dateStartRaw = $eventDate ? $eventDate->format('Y-m-d') : '';
        }

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
            ],
            'date' => [
                'start' => $dateStartRaw,
                'start_formatted' => $startFormatted,
                'time' => $startTime,
                'doors_open' => $doorTime,
                'day_name' => $dayName,
            ],
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

        $parts = $taxes->map(fn ($tax) => $tax->getFormattedValue() . ' ' . $tax->name)->all();

        return 'Prețul include ' . implode(', ', $parts);
    }

    /**
     * Build serial number from ticket type series configuration
     */
    private function resolveEventImageUrl($event, $marketplaceEvent): string
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
