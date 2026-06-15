<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Models\TicketType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk-generate free tickets for an event.
 *
 * Groups tickets into orders of configurable size (default 50/order),
 * each order is created with status=completed, total=0, source=bulk_admin.
 * After creating the records, optionally renders one PDF per ticket using
 * the event's ticket_template_id (same logic as the /marketplace/tickets/{id}/download-pdf route)
 * and saves them to storage/app/bulk-tickets/event-{id}/.
 *
 * Why this exists: organizer-facing invitation flow caps at 50 recipients per
 * batch and requires per-recipient data. For 600+ identical free passes from
 * the same email, we need a CLI-only path that bypasses the invitation UI.
 */
class BulkGenerateFreeTickets extends Command
{
    protected $signature = 'tickets:bulk-generate-free
        {--event= : Event ID (required)}
        {--ticket-type=* : Format ticket_type_id:quantity, repeat for multiple types}
        {--per-order=50 : Tickets grouped per order}
        {--email=contact@ambilet.ro : Customer email used on every order}
        {--first-name=Ambilet : Billing first name}
        {--last-name=Bulk : Billing last name}
        {--source=bulk_admin : Value written to orders.source}
        {--pdf-dir= : Override target directory for PDFs (default: storage/app/bulk-tickets/event-{eventId})}
        {--no-pdf : Skip per-ticket PDF generation}
        {--dry-run : Print plan without writing anything}';

    protected $description = 'Bulk-generate free tickets + orders for an event, with per-ticket PDFs saved to disk';

    public function handle(): int
    {
        $eventId = (int) $this->option('event');
        if ($eventId <= 0) {
            $this->error('--event=<id> is required');
            return self::FAILURE;
        }

        $event = Event::with(['ticketTemplate'])->find($eventId);
        if (!$event) {
            $this->error("Event {$eventId} not found");
            return self::FAILURE;
        }

        $rawSpecs = (array) $this->option('ticket-type');
        if (empty($rawSpecs)) {
            $this->error('At least one --ticket-type=ticket_type_id:quantity is required');
            return self::FAILURE;
        }

        $specs = [];
        foreach ($rawSpecs as $spec) {
            if (!str_contains($spec, ':')) {
                $this->error("Bad --ticket-type spec '{$spec}', expected 'id:quantity'");
                return self::FAILURE;
            }
            [$ttId, $qty] = explode(':', $spec, 2);
            $ttId = (int) $ttId;
            $qty = (int) $qty;
            if ($ttId <= 0 || $qty <= 0) {
                $this->error("Bad --ticket-type spec '{$spec}', expected 'id:quantity' with positive integers");
                return self::FAILURE;
            }
            $tt = TicketType::find($ttId);
            if (!$tt) {
                $this->error("TicketType {$ttId} not found");
                return self::FAILURE;
            }
            if ($tt->event_id !== $event->id) {
                $this->error("TicketType {$ttId} does not belong to event {$eventId} (belongs to event {$tt->event_id})");
                return self::FAILURE;
            }
            $specs[] = ['ticket_type' => $tt, 'qty' => $qty];
        }

        $perOrder = max(1, (int) $this->option('per-order'));
        $email = (string) $this->option('email');
        $firstName = (string) $this->option('first-name');
        $lastName = (string) $this->option('last-name');
        $source = (string) $this->option('source');
        $skipPdf = (bool) $this->option('no-pdf');
        $dryRun = (bool) $this->option('dry-run');
        $pdfDir = $this->option('pdf-dir') ?: storage_path("app/bulk-tickets/event-{$eventId}");

        // ---------------------------------------------------------------- plan
        $totalTickets = 0;
        $totalOrders = 0;
        $this->newLine();
        $this->info('=== Plan ===');
        $this->line("Event: #{$event->id} — " . substr((string) $event->title, 0, 80));
        $this->line("Email: {$email}");
        $this->line("Source: {$source}");
        $this->line("Per order: {$perOrder}");
        $this->line('PDF dir: ' . ($skipPdf ? 'SKIPPED (--no-pdf)' : $pdfDir));
        $this->newLine();
        foreach ($specs as $s) {
            $orders = (int) ceil($s['qty'] / $perOrder);
            $price = $s['ticket_type']->price ?? 0;
            $this->line("  TT #{$s['ticket_type']->id} '{$s['ticket_type']->name}' price={$price} qty={$s['qty']} → {$orders} order(s)");
            $totalOrders += $orders;
            $totalTickets += $s['qty'];
        }
        $this->newLine();
        $this->info("Total: {$totalOrders} order(s), {$totalTickets} ticket(s)");
        $this->newLine();

        if ($dryRun) {
            $this->warn('--dry-run — no changes written');
            return self::SUCCESS;
        }

        if (!$this->confirm('Proceed with generation?', false)) {
            $this->warn('Aborted');
            return self::SUCCESS;
        }

        // ---------------------------------------- locate or create customer
        $clientId = (int) $event->marketplace_client_id;
        $organizerId = $event->marketplace_organizer_id;
        $tenantId = $event->tenant_id;

        $customerId = null;
        if ($clientId) {
            $customer = MarketplaceCustomer::firstOrCreate(
                ['marketplace_client_id' => $clientId, 'email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $customerId = $customer->id;
            $this->line("Customer: #{$customerId} ({$email})");
        }

        // ---------------------------------------------------- create records
        $allTicketIds = [];
        $createdOrders = 0;

        foreach ($specs as $s) {
            $tt = $s['ticket_type'];
            $remaining = $s['qty'];
            $chunkIndex = 0;

            while ($remaining > 0) {
                $chunkSize = min($perOrder, $remaining);
                $chunkIndex++;

                DB::transaction(function () use (
                    $event, $tt, $chunkSize, $chunkIndex, $email, $firstName, $lastName,
                    $source, $clientId, $organizerId, $tenantId, $customerId,
                    &$allTicketIds, &$createdOrders
                ) {
                    $orderNumber = 'MKT-' . strtoupper(Str::random(8));
                    $now = now();

                    $orderId = DB::table('orders')->insertGetId([
                        'order_number' => $orderNumber,
                        'event_id' => $event->id,
                        'marketplace_client_id' => $clientId,
                        'marketplace_organizer_id' => $organizerId,
                        'marketplace_customer_id' => $customerId,
                        'tenant_id' => $tenantId,
                        'customer_email' => $email,
                        'customer_name' => trim($firstName . ' ' . $lastName) ?: null,
                        'total' => 0,
                        'subtotal' => 0,
                        'discount_amount' => 0,
                        'commission_rate' => 0,
                        'commission_amount' => 0,
                        'currency' => 'RON',
                        'status' => 'completed',
                        'payment_status' => 'paid',
                        'payment_processor' => 'manual',
                        'source' => $source,
                        'paid_at' => $now,
                        'meta' => json_encode([
                            'bulk_generation' => true,
                            'ticket_type_id' => $tt->id,
                            'chunk_index' => $chunkIndex,
                            'generated_at' => $now->toIso8601String(),
                            'generated_by' => get_current_user(),
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $batch = [];
                    for ($i = 0; $i < $chunkSize; $i++) {
                        $batch[] = [
                            'code' => strtoupper(Str::random(16)),
                            'barcode' => Str::random(64),
                            'ticket_type_id' => $tt->id,
                            'event_id' => $event->id,
                            'order_id' => $orderId,
                            'marketplace_client_id' => $clientId,
                            'status' => 'valid',
                            'price' => 0,
                            'meta' => json_encode([
                                'source' => 'bulk_admin',
                                'generated_at' => $now->toIso8601String(),
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('tickets')->insert($batch);
                    $insertedIds = DB::table('tickets')->where('order_id', $orderId)->pluck('id')->all();
                    $allTicketIds = array_merge($allTicketIds, $insertedIds);

                    // Increment quota_sold atomically
                    DB::table('ticket_types')->where('id', $tt->id)->increment('quota_sold', $chunkSize);

                    $createdOrders++;
                    $this->line("  + order #{$orderId} {$orderNumber}: {$chunkSize} × TT#{$tt->id}");
                });

                $remaining -= $chunkSize;
            }
        }

        $this->newLine();
        $this->info("Created {$createdOrders} order(s), " . count($allTicketIds) . " ticket(s)");

        // ----------------------------------------------------------- PDFs
        if ($skipPdf) {
            $this->warn('PDF generation skipped (--no-pdf)');
            return self::SUCCESS;
        }

        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        $this->newLine();
        $this->info("Generating PDFs to {$pdfDir}");

        $variableService = app(\App\Services\TicketCustomizer\TicketVariableService::class);
        $generator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);

        $template = null;
        if ($event->ticketTemplate && $event->ticketTemplate->status === 'active' && !empty($event->ticketTemplate->template_data)) {
            $template = $event->ticketTemplate;
        } elseif ($clientId) {
            $template = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->first(fn ($t) => !empty($t->template_data['layers'] ?? []));
        }

        if (!$template) {
            $this->warn('No active ticket template found — PDFs will not be generated');
            return self::SUCCESS;
        }

        $generated = 0;
        $failed = 0;
        $startedAt = microtime(true);

        foreach (array_chunk($allTicketIds, 50) as $chunkIds) {
            $tickets = Ticket::with(['order.marketplaceClient', 'ticketType', 'event'])
                ->whereIn('id', $chunkIds)
                ->get();

            foreach ($tickets as $ticket) {
                $code = $ticket->code ?? (string) $ticket->id;
                $pdfPath = $pdfDir . "/bilet-{$code}.pdf";

                try {
                    $locale = $variableService->resolveOrderLocale($ticket);
                    $data = $variableService->resolveTicketData($ticket, $locale);
                    $content = $generator->renderToHtml($template->template_data, $data, $locale);

                    if (empty(trim($content))) {
                        $failed++;
                        continue;
                    }

                    $size = $template->getSize();
                    $widthPt = round($size['width'] * 2.8346, 2);
                    $heightPt = round($size['height'] * 2.8346, 2);
                    $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}body{margin:0;padding:0;width:{$widthPt}pt;height:{$heightPt}pt;background-color:{$bgColor};font-family:'DejaVu Sans',sans-serif;overflow:hidden;}</style></head><body>{$content}</body></html>";

                    $pdf = Pdf::loadHTML($html)
                        ->setPaper([0, 0, $widthPt, $heightPt])
                        ->setOption('isRemoteEnabled', true)
                        ->setOption('isHtml5ParserEnabled', true);

                    file_put_contents($pdfPath, $pdf->output());
                    $generated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  PDF failed for ticket {$code}: " . $e->getMessage());
                }

                if (($generated + $failed) % 50 === 0) {
                    $rate = round(($generated + $failed) / max(0.1, microtime(true) - $startedAt), 1);
                    $this->line("  ... {$generated} ok / {$failed} failed ({$rate}/sec)");
                }
            }
        }

        if ($template && $generated > 0) {
            $template->markAsUsed();
        }

        $this->newLine();
        $this->info("PDFs: {$generated} generated, {$failed} failed in " . round(microtime(true) - $startedAt, 1) . 's');
        $this->newLine();
        $this->line('To package for download:');
        $base = basename($pdfDir);
        $parent = dirname($pdfDir);
        $this->line("  cd {$parent} && tar -czf {$base}.tar.gz {$base}/");
        $this->line("  scp ploi@host:{$parent}/{$base}.tar.gz .");

        return self::SUCCESS;
    }
}
