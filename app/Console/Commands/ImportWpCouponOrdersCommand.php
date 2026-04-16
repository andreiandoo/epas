<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ImportWpCouponOrdersCommand extends Command
{
    protected $signature = 'import:wp-coupon-orders
        {file : Path to CSV with columns: wp_order_id, order_status, coupon_codes, discount_amount, order_total}
        {--dry-run : Preview without saving}';

    protected $description = 'Import WP coupon data into Tixello orders and adjust ticket prices';

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $header = array_map('trim', array_map('strtolower', $header));

        $wpOrderIdCol = array_search('wp_order_id', $header);
        $couponCol = array_search('coupon_codes', $header);
        $discountCol = array_search('discount_amount', $header);
        $totalCol = array_search('order_total', $header);

        if ($wpOrderIdCol === false || $couponCol === false || $discountCol === false) {
            $this->error('CSV must have columns: wp_order_id, coupon_codes, discount_amount');
            return self::FAILURE;
        }

        $updated = 0;
        $notFound = 0;
        $alreadySet = 0;
        $skipped = 0;
        $ticketsFixed = 0;
        $row = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $wpOrderId = trim($data[$wpOrderIdCol] ?? '');
            $couponCodes = trim($data[$couponCol] ?? '');
            $discountAmount = (float) trim($data[$discountCol] ?? '0');
            $orderTotal = (float) trim($data[$totalCol] ?? '0');

            if (empty($wpOrderId) || empty($couponCodes)) {
                $skipped++;
                continue;
            }

            // Find order in Tixello by wp_order_id in meta
            $order = Order::whereJsonContains('meta->wp_order_id', $wpOrderId)->first();
            if (!$order) {
                // Try as integer string
                $order = Order::whereJsonContains('meta->wp_order_id', (int) $wpOrderId)->first();
            }

            if (!$order) {
                $notFound++;
                continue;
            }

            // Skip if already has a promo code set
            if ($order->promo_code) {
                $alreadySet++;
                continue;
            }

            // Skip if no discount
            if ($discountAmount <= 0) {
                $skipped++;
                continue;
            }

            // Calculate discount percentage
            $ticketSum = $order->tickets()->whereIn('status', ['valid', 'used'])->sum('price');
            if ($ticketSum <= 0) {
                $skipped++;
                continue;
            }

            // The discount percentage based on original ticket prices
            $discountPct = min(100, round(($discountAmount / ($ticketSum)) * 100, 2));

            if (!$dryRun) {
                // Update order with coupon info
                $order->forceFill([
                    'promo_code' => $couponCodes,
                    'promo_discount' => (string) $discountPct,
                    'discount_amount' => $discountAmount,
                ])->saveQuietly();

                // Adjust ticket prices proportionally
                // New ticket price = original * (1 - discount_pct/100)
                $ratio = 1 - ($discountAmount / $ticketSum);
                $tickets = $order->tickets()->whereIn('status', ['valid', 'used'])->get();
                foreach ($tickets as $ticket) {
                    $newPrice = round((float) $ticket->price * $ratio, 2);
                    $ticket->forceFill(['price' => $newPrice])->saveQuietly();
                    $ticketsFixed++;
                }
            } else {
                $tickets = $order->tickets()->whereIn('status', ['valid', 'used'])->get();
                $ticketsFixed += $tickets->count();
            }

            $updated++;
        }

        fclose($handle);

        $mode = $dryRun ? ' (dry run)' : '';
        $this->info("Done{$mode}. Processed {$row} rows:");
        $this->line("  Orders updated:     {$updated}");
        $this->line("  Tickets adjusted:   {$ticketsFixed}");
        $this->line("  Already had coupon: {$alreadySet}");
        $this->line("  Not found in DB:    {$notFound}");
        $this->line("  Skipped:            {$skipped}");

        return self::SUCCESS;
    }
}
