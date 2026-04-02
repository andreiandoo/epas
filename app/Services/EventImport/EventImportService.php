<?php

namespace App\Services\EventImport;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\EventImport\DTOs\ImportedRow;
use App\Services\EventImport\DTOs\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventImportService
{
    /**
     * Process parsed rows and create all records.
     *
     * @param ImportedRow[] $rows
     * @param array $eventConfig Form data from Stage 1
     * @param int $tenantId
     * @param string $sourceKey e.g. 'iabilet'
     */
    public function process(array $rows, array $eventConfig, int $tenantId, string $sourceKey = 'iabilet'): ImportResult
    {
        $errors = [];
        $customersCreated = 0;
        $customersEnriched = 0;
        $anonymousOrders = 0;

        return DB::transaction(function () use ($rows, $eventConfig, $tenantId, $sourceKey, &$errors, &$customersCreated, &$customersEnriched, &$anonymousOrders) {

            $isExternalImport = !empty($eventConfig['existing_event_id']);
            $externalPlatform = $eventConfig['external_platform_name'] ?? $sourceKey;

            // 1. Create or use existing Event
            if ($isExternalImport) {
                $event = Event::findOrFail($eventConfig['existing_event_id']);
                // Override source key for external imports
                $sourceKey = $externalPlatform;
            } else {
                $event = $this->createEvent($eventConfig, $tenantId, $sourceKey);
            }

            // 2. Discover and create TicketTypes (with platform prefix for external imports)
            $ticketTypeMap = $this->createTicketTypes($rows, $event, $tenantId, $isExternalImport ? $externalPlatform : null);

            // 3. Group rows by orderId and process
            $orderGroups = $this->groupByOrder($rows);

            $totalTickets = 0;
            $totalOrders = 0;

            foreach ($orderGroups as $orderId => $orderRows) {
                try {
                    $result = $this->processOrderGroup(
                        $orderId,
                        $orderRows,
                        $event,
                        $tenantId,
                        $ticketTypeMap,
                        $sourceKey,
                        $eventConfig,
                        $customersCreated,
                        $customersEnriched,
                        $anonymousOrders,
                        $isExternalImport,
                    );

                    $totalTickets += $result['tickets'];
                    $totalOrders++;
                } catch (\Throwable $e) {
                    $errors[] = "Order {$orderId}: {$e->getMessage()}";
                }
            }

            // Build ticket types summary
            $ticketTypesSummary = [];
            foreach ($ticketTypeMap as $name => $ttData) {
                $ticketTypesSummary[] = [
                    'name' => $name,
                    'count' => $ttData['count'],
                    'price' => $ttData['price'],
                ];
            }

            return new ImportResult(
                eventId: $event->id,
                totalTickets: $totalTickets,
                totalOrders: $totalOrders,
                customersCreated: $customersCreated,
                customersEnriched: $customersEnriched,
                ticketTypesCreated: count($ticketTypeMap),
                ticketTypesSummary: $ticketTypesSummary,
                anonymousOrders: $anonymousOrders,
                errors: $errors,
            );
        });
    }

    protected function createEvent(array $config, int $tenantId, string $sourceKey): Event
    {
        $event = Event::create([
            'tenant_id' => $tenantId,
            'title' => ['ro' => $config['title']],
            'slug' => Str::slug($config['title']),
            'description' => isset($config['description']) ? ['ro' => $config['description']] : null,
            'venue_id' => $config['venue_id'] ?? null,
            'duration_mode' => 'single_day',
            'event_date' => $config['event_date'] ?? null,
            'start_time' => $config['start_time'] ?? null,
            'end_time' => $config['end_time'] ?? null,
            'commission_mode' => $config['commission_mode'] ?? null,
            'commission_rate' => $config['commission_rate'] ?? null,
            'is_published' => false,
            'status' => $config['event_status'] === 'completed' ? 'completed' : 'active',
            'admin_notes' => "Imported from {$sourceKey} on " . now()->format('Y-m-d H:i'),
        ]);

        // Attach artists
        if (!empty($config['artist_ids'])) {
            $syncData = [];
            foreach ($config['artist_ids'] as $i => $artistId) {
                $syncData[$artistId] = ['sort_order' => $i];
            }
            $event->artists()->sync($syncData);
        }

        // Attach event types
        if (!empty($config['event_type_ids'])) {
            $event->eventTypes()->sync($config['event_type_ids']);
        }

        // Attach event genres
        if (!empty($config['event_genre_ids'])) {
            $event->eventGenres()->sync($config['event_genre_ids']);
        }

        return $event;
    }

    /**
     * Discover unique ticket types from rows and create TicketType records.
     *
     * @return array<string, array{id: int, count: int, price: float}>
     */
    protected function createTicketTypes(array $rows, Event $event, int $tenantId, ?string $externalPlatform = null): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $name = $row->ticketTypeName ?? 'General';
            if (!isset($groups[$name])) {
                $groups[$name] = [
                    'count' => 0,
                    'prices' => [],
                ];
            }
            $groups[$name]['count']++;
            if ($row->ticketPrice !== null) {
                $groups[$name]['prices'][] = $row->ticketPrice;
            }
        }

        $map = [];
        $sortOrder = $event->ticketTypes()->max('sort_order') ?? 0;

        foreach ($groups as $name => $data) {
            // Use most common price, fallback to first
            $price = $this->mostCommonValue($data['prices']) ?? 0;

            // Prefix ticket type name with platform for external imports
            $displayName = $externalPlatform ? "{$name} ({$externalPlatform})" : $name;

            $meta = [];
            if ($externalPlatform) {
                $meta['external_platform'] = $externalPlatform;
                $meta['is_external_sale'] = true;
            }

            $ticketType = TicketType::create([
                'event_id' => $event->id,
                'name' => $displayName,
                'price_cents' => (int) round($price * 100),
                'quota_total' => $data['count'],
                'quota_sold' => $data['count'], // historical import — all sold
                'status' => 'active',
                'sort_order' => ++$sortOrder,
                'currency' => 'RON',
                'meta' => !empty($meta) ? $meta : null,
            ]);

            $map[$name] = [
                'id' => $ticketType->id,
                'count' => $data['count'],
                'price' => $price,
            ];
        }

        return $map;
    }

    /**
     * Group rows by orderId. Rows without orderId get individual groups.
     */
    protected function groupByOrder(array $rows): array
    {
        $groups = [];
        $unnamedIndex = 0;

        foreach ($rows as $row) {
            $key = $row->orderId ?? '__no_order_' . ($unnamedIndex++);
            $groups[$key][] = $row;
        }

        return $groups;
    }

    protected function processOrderGroup(
        string $orderId,
        array $orderRows,
        Event $event,
        int $tenantId,
        array $ticketTypeMap,
        string $sourceKey,
        array $eventConfig,
        int &$customersCreated,
        int &$customersEnriched,
        int &$anonymousOrders,
        bool $isExternalImport = false,
    ): array {
        // Find customer info from the first row that has email
        $customerRow = null;
        foreach ($orderRows as $row) {
            if ($row->email) {
                $customerRow = $row;
                break;
            }
        }

        // Fallback: use first row with any name
        if (!$customerRow) {
            foreach ($orderRows as $row) {
                if ($row->clientName) {
                    $customerRow = $row;
                    break;
                }
            }
        }

        $firstRow = $orderRows[0];

        // Customer deduplication
        $customerId = null;
        $customerEmail = $customerRow?->email ?? $firstRow->email;
        $customerName = $customerRow?->clientName ?? $firstRow->clientName;
        $customerPhone = $customerRow?->phone ?? $firstRow->phone;
        [$firstName, $lastName] = ($customerRow ?? $firstRow)->splitName();

        if ($customerEmail) {
            $customer = Customer::where('email', $customerEmail)->first();

            if ($customer) {
                // Enrich with missing data
                $updated = false;
                if (!$customer->phone && $customerPhone) {
                    $customer->phone = $customerPhone;
                    $updated = true;
                }
                if (!$customer->first_name && $firstName) {
                    $customer->first_name = $firstName;
                    $updated = true;
                }
                if (!$customer->last_name && $lastName) {
                    $customer->last_name = $lastName;
                    $updated = true;
                }
                if ($updated) {
                    $customer->save();
                    $customersEnriched++;
                }

                // Ensure tenant pivot
                if (!$customer->tenants()->where('tenants.id', $tenantId)->exists()) {
                    $customer->tenants()->attach($tenantId);
                }

                $customerId = $customer->id;
            } else {
                // Create new customer
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'primary_tenant_id' => $tenantId,
                    'email' => $customerEmail,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $customerPhone,
                ]);
                $customer->tenants()->attach($tenantId);
                $customerId = $customer->id;
                $customersCreated++;
            }
        } else {
            $anonymousOrders++;
        }

        // Calculate order total
        $orderTotal = 0;
        foreach ($orderRows as $row) {
            $orderTotal += $row->isInvitation ? 0 : ($row->ticketPrice ?? 0);
        }

        // External imports: no commission (not sold via Tixello)
        $commissionRate = $isExternalImport ? 0 : (float) ($eventConfig['commission_rate'] ?? 0);
        $commissionAmount = $commissionRate > 0 ? round($orderTotal * ($commissionRate / 100), 2) : 0;

        // Map order status
        [$orderStatus, $paymentStatus, $paidAt] = $this->mapOrderStatus($firstRow->orderStatus, $firstRow->orderDate);

        // Create Order — set customer_id to avoid auto-create in saving hook
        $order = new Order();
        $order->tenant_id = $tenantId;
        $order->event_id = $event->id;
        $order->customer_id = $customerId;
        $order->order_number = strtoupper(substr($sourceKey, 0, 3)) . '-' . $orderId;
        $order->customer_email = $customerEmail;
        $order->customer_name = $customerName;
        $order->customer_phone = $customerPhone;
        $order->total = $orderTotal;
        $order->subtotal = $orderTotal;
        $order->discount_amount = 0;
        $order->commission_rate = $commissionRate;
        $order->commission_amount = $commissionAmount;
        $order->currency = $firstRow->currency ?? 'RON';
        $order->status = $orderStatus;
        $order->payment_status = $paymentStatus;
        $order->source = $isExternalImport ? 'external_import' : 'legacy_import';
        $order->paid_at = $paidAt;
        $orderMeta = [
            'imported_from' => $sourceKey,
            'original_order_id' => $orderId,
        ];
        if ($isExternalImport) {
            $orderMeta['is_external_sale'] = true;
            $orderMeta['external_platform'] = $sourceKey;
        }
        $order->meta = json_encode($orderMeta);
        $order->created_at = $firstRow->orderDate ?? now();
        $order->updated_at = $firstRow->orderDate ?? now();
        $order->save();

        // Group rows by ticket type for OrderItems
        $ticketsByType = [];
        foreach ($orderRows as $row) {
            $typeName = $row->ticketTypeName ?? 'General';
            $ticketsByType[$typeName][] = $row;
        }

        $orderItemMap = []; // typeName => orderItem
        foreach ($ticketsByType as $typeName => $typeRows) {
            $ttData = $ticketTypeMap[$typeName] ?? null;
            $quantity = count($typeRows);
            $unitPrice = $typeRows[0]->ticketPrice ?? 0;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'ticket_type_id' => $ttData ? $ttData['id'] : null,
                'name' => $typeName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $unitPrice * $quantity,
            ]);

            $orderItemMap[$typeName] = $orderItem;
        }

        // Create individual Tickets
        $ticketCount = 0;
        foreach ($orderRows as $row) {
            $typeName = $row->ticketTypeName ?? 'General';
            $ttData = $ticketTypeMap[$typeName] ?? null;
            $orderItem = $orderItemMap[$typeName] ?? null;

            $ticketStatus = $this->mapTicketStatus($row->ticketStatus);
            $checkedInAt = null;

            if ($row->validated && trim($row->validated) !== '') {
                $checkedInAt = $row->orderDate ?? now();
            }

            $price = $row->isInvitation ? 0 : ($row->ticketPrice ?? 0);

            Ticket::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem?->id,
                'ticket_type_id' => $ttData ? $ttData['id'] : null,
                'event_id' => $event->id,
                'tenant_id' => $tenantId,
                'code' => $row->barcode,
                'barcode' => $row->barcode,
                'status' => $ticketStatus,
                'price' => $price,
                'seat_label' => $row->seatLabel,
                'attendee_name' => $row->clientName,
                'attendee_email' => $row->email,
                'checked_in_at' => $checkedInAt,
                'meta' => array_filter([
                    'fiscal_series' => $row->fiscalSeries,
                    'import_source' => $sourceKey,
                    'is_invitation' => $row->isInvitation,
                    'original_ticket_status' => $row->ticketStatus,
                    'is_external_sale' => $isExternalImport ?: null,
                    'external_platform' => $isExternalImport ? $sourceKey : null,
                ]),
            ]);

            $ticketCount++;
        }

        return ['tickets' => $ticketCount];
    }

    /**
     * Map iabilet order status to [orderStatus, paymentStatus, paidAt].
     */
    protected function mapOrderStatus(?string $iabiletStatus, ?string $orderDate): array
    {
        $normalized = mb_strtolower(trim($iabiletStatus ?? ''));

        // Remove diacritics for matching
        $ascii = Str::ascii($normalized);

        return match (true) {
            str_contains($ascii, 'finalizat') => ['completed', 'paid', $orderDate],
            str_contains($ascii, 'anulat') => ['cancelled', 'failed', null],
            str_contains($ascii, 'asteptare') => ['pending', 'pending', null],
            str_contains($ascii, 'rambursat') => ['refunded', 'refunded', $orderDate],
            default => ['completed', 'paid', $orderDate],
        };
    }

    /**
     * Map iabilet ticket status to our ticket status.
     */
    protected function mapTicketStatus(?string $iabiletStatus): string
    {
        $normalized = mb_strtolower(trim($iabiletStatus ?? ''));
        $ascii = Str::ascii($normalized);

        return match (true) {
            str_contains($ascii, 'anulat') => 'cancelled',
            default => 'valid',
        };
    }

    /**
     * Find the most common value in an array.
     */
    protected function mostCommonValue(array $values): mixed
    {
        if (empty($values)) {
            return null;
        }

        $counts = array_count_values(array_map('strval', $values));
        arsort($counts);

        return (float) array_key_first($counts);
    }
}
