<?php

namespace App\Services\TicketCustomizer;

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
                        'example' => '99.99 EUR',
                    ],
                    [
                        'path' => 'ticket.section',
                        'label' => 'Section',
                        'description' => 'Seating section',
                        'type' => 'string',
                        'example' => 'Section A',
                    ],
                    [
                        'path' => 'ticket.row',
                        'label' => 'Row',
                        'description' => 'Seating row',
                        'type' => 'string',
                        'example' => 'Row 5',
                    ],
                    [
                        'path' => 'ticket.seat',
                        'label' => 'Seat Number',
                        'description' => 'Seat number',
                        'type' => 'string',
                        'example' => 'Seat 12',
                    ],
                    [
                        'path' => 'ticket.number',
                        'label' => 'Ticket Number',
                        'description' => 'Unique ticket number',
                        'type' => 'string',
                        'example' => 'TKT-2025-001234',
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
                        'example' => 'EventPilot ePas',
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
                'price' => '299.00 EUR',
                'section' => 'Section A',
                'row' => 'Row 5',
                'seat' => 'Seat 12',
                'number' => 'TKT-2025-001234',
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
                'name' => 'EventPilot ePas',
                'website' => 'https://eventpilot.ro',
                'phone' => '+40 123 456 789',
                'email' => 'contact@eventpilot.ro',
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
}
