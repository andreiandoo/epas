<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCustomer;
use Illuminate\Console\Command;

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

        $bcryptDirect = 0;
        $phpassStored = 0;
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

            $customer = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->where('email', $email)
                ->first();

            if (!$customer) {
                $notFound++;
                continue;
            }

            // Skip if customer already has a bcrypt password
            if ($customer->password) {
                $alreadyHasPassword++;
                continue;
            }

            // Detect hash type and handle accordingly
            if (str_starts_with($wpHash, '$wp$2y$') || str_starts_with($wpHash, '$wp$2a$')) {
                // WordPress bcrypt with $wp$ prefix — strip prefix and set as password directly
                $cleanHash = substr($wpHash, 3); // Remove '$wp' prefix → '$2y$10$...'
                $bcryptDirect++;
                if (!$dryRun) {
                    $customer->forceFill(['password' => $cleanHash])->saveQuietly();
                }
            } elseif (str_starts_with($wpHash, '$2y$') || str_starts_with($wpHash, '$2a$')) {
                // Standard bcrypt — set as password directly
                $bcryptDirect++;
                if (!$dryRun) {
                    $customer->forceFill(['password' => $wpHash])->saveQuietly();
                }
            } elseif (str_starts_with($wpHash, '$P$') || str_starts_with($wpHash, '$H$')) {
                // Old phpass hash — store in wp_password_hash for runtime migration
                $phpassStored++;
                if (!$dryRun) {
                    $customer->forceFill(['wp_password_hash' => $wpHash])->saveQuietly();
                }
            } else {
                $skipped++;
                continue;
            }
        }

        fclose($handle);

        $mode = $dryRun ? ' (dry run)' : '';
        $total = $bcryptDirect + $phpassStored;
        $this->info("Done{$mode}. Processed {$row} rows:");
        $this->line("  Passwords set (bcrypt):   {$bcryptDirect}");
        $this->line("  WP hash stored (phpass):  {$phpassStored}");
        $this->line("  Already has password:     {$alreadyHasPassword}");
        $this->line("  Not found in DB:          {$notFound}");
        $this->line("  Skipped (bad data):       {$skipped}");
        $this->newLine();
        $this->info("Total customers updated: {$total}");

        return self::SUCCESS;
    }
}
