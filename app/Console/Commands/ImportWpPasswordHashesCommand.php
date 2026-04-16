<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWpPasswordHashesCommand extends Command
{
    protected $signature = 'import:wp-password-hashes
        {file : Path to CSV file with columns: user_email, user_pass}
        {--marketplace-client-id=1 : Marketplace client ID}
        {--dry-run : Show what would be updated without saving}';

    protected $description = 'Import WordPress password hashes from CSV so customers can login with their old WP password';

    public function handle(): int
    {
        $file = $this->argument('file');
        $clientId = (int) $this->option('marketplace-client-id');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("Cannot open file: {$file}");
            return self::FAILURE;
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            $this->error('Empty CSV file');
            return self::FAILURE;
        }

        $header = array_map('trim', array_map('strtolower', $header));
        $emailCol = array_search('user_email', $header);
        $passCol = array_search('user_pass', $header);

        if ($emailCol === false || $passCol === false) {
            $this->error('CSV must have columns: user_email, user_pass');
            $this->line('Found columns: ' . implode(', ', $header));
            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $alreadyHasPassword = 0;
        $row = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $email = trim($data[$emailCol] ?? '');
            $wpHash = trim($data[$passCol] ?? '');

            if (empty($email) || empty($wpHash)) {
                $skipped++;
                continue;
            }

            // Only import phpass hashes
            if (!str_starts_with($wpHash, '$P$') && !str_starts_with($wpHash, '$H$')) {
                $skipped++;
                continue;
            }

            $customer = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->where('email', $email)
                ->first();

            if (!$customer) {
                $notFound++;
                continue;
            }

            // Skip if customer already has a bcrypt password (already migrated/registered)
            if ($customer->password) {
                $alreadyHasPassword++;
                continue;
            }

            $updated++;
            if (!$dryRun) {
                $customer->forceFill(['wp_password_hash' => $wpHash])->saveQuietly();
            }
        }

        fclose($handle);

        $mode = $dryRun ? ' (dry run)' : '';
        $this->info("Done{$mode}. Processed {$row} rows:");
        $this->line("  Updated:              {$updated}");
        $this->line("  Already has password:  {$alreadyHasPassword}");
        $this->line("  Not found in DB:      {$notFound}");
        $this->line("  Skipped (bad data):   {$skipped}");

        return self::SUCCESS;
    }
}
