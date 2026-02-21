<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportMarketplaceOrganizersCommand extends Command
{
    protected $signature = 'import:marketplace-organizers {file} {--marketplace= : marketplace_client_id to use for all rows (overrides CSV column)}';
    protected $description = 'Import marketplace organizers from CSV file (upsert by email)';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Importing marketplace organizers from {$file}...");

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false || !in_array('email', $header)) {
            $this->error("Invalid CSV format. Must have at least an 'email' column.");
            return 1;
        }

        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['email'])) {
                continue;
            }

            $email = strtolower(trim($data['email']));

            // Resolve marketplace_client_id: --marketplace option overrides CSV columns
            $marketplaceClientId = null;
            if ($this->option('marketplace') && is_numeric($this->option('marketplace'))) {
                $marketplaceClientId = (int) $this->option('marketplace');
            } elseif (!empty($data['marketplace_client_id']) && is_numeric($data['marketplace_client_id'])) {
                $marketplaceClientId = (int) $data['marketplace_client_id'];
            } elseif (!empty($data['marketplace_client_name'])) {
                $client = MarketplaceClient::where('name', $data['marketplace_client_name'])->first();
                $marketplaceClientId = $client?->id;
            }

            if (!$marketplaceClientId) {
                $this->warn("Skipping {$email}: no valid marketplace_client found (provide marketplace_client_id or marketplace_client_name column)");
                $skipped++;
                continue;
            }

            // Skip if email already exists
            if (MarketplaceOrganizer::where('email', $email)->exists()) {
                $this->line("Skipped (already exists): {$email}");
                $skipped++;
                continue;
            }

            $fields = $this->buildFields($data, $marketplaceClientId);

            MarketplaceOrganizer::create($fields);
            $this->line("Created: {$email}");
            $created++;
        }

        fclose($handle);

        $this->info("Import complete! Created: {$created} | Skipped: {$skipped}");

        return 0;
    }

    private function n(?string $value): ?string
    {
        return ($value !== null && $value !== '') ? $value : null;
    }

    private function uniqueSlug(string $base, int $marketplaceClientId): string
    {
        $slug = $base;
        $i = 2;
        while (MarketplaceOrganizer::where('marketplace_client_id', $marketplaceClientId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function buildFields(array $data, int $marketplaceClientId): array
    {
        $name = $this->n($data['name'] ?? null);
        $slug = $name ? $this->uniqueSlug(Str::slug($name), $marketplaceClientId) : null;

        $fields = [
            'marketplace_client_id' => $marketplaceClientId,
            'email'                 => strtolower(trim($data['email'])),
            'name'                  => $name,
            'slug'                  => $slug,
            'contact_name'          => $this->n($data['contact_name'] ?? null),
            'phone'                 => $this->n($data['phone'] ?? null),
            'description'           => $this->n($data['description'] ?? null),
            'website'               => $this->n($data['website'] ?? null),

            // Organizer type
            'person_type'           => $this->n($data['person_type'] ?? null),
            'work_mode'             => $this->n($data['work_mode'] ?? null),
            'organizer_type'        => $this->n($data['organizer_type'] ?? null),

            // Company details
            'company_name'          => $this->n($data['company_name'] ?? null),
            'company_tax_id'        => $this->n($data['company_tax_id'] ?? null),
            'company_registration'  => $this->n($data['company_registration'] ?? null),
            'vat_payer'             => isset($data['vat_payer']) && $data['vat_payer'] === '1',
            'company_address'       => $this->n($data['company_address'] ?? null),
            'company_city'          => $this->n($data['company_city'] ?? null),
            'company_county'        => $this->n($data['company_county'] ?? null),
            'company_zip'           => $this->n($data['company_zip'] ?? null),
            'past_contract'         => $this->n($data['past_contract'] ?? null),
            'representative_first_name' => $this->n($data['representative_first_name'] ?? null),
            'representative_last_name'  => $this->n($data['representative_last_name'] ?? null),

            // Guarantor
            'guarantor_first_name'  => $this->n($data['guarantor_first_name'] ?? null),
            'guarantor_last_name'   => $this->n($data['guarantor_last_name'] ?? null),
            'guarantor_cnp'         => $this->n($data['guarantor_cnp'] ?? null),
            'guarantor_address'     => $this->n($data['guarantor_address'] ?? null),
            'guarantor_city'        => $this->n($data['guarantor_city'] ?? null),
            'guarantor_id_type'     => $this->n($data['guarantor_id_type'] ?? null),
            'guarantor_id_series'   => $this->n($data['guarantor_id_series'] ?? null),
            'guarantor_id_number'   => $this->n($data['guarantor_id_number'] ?? null),
            'guarantor_id_issued_by'=> $this->n($data['guarantor_id_issued_by'] ?? null),
            'guarantor_id_issued_date' => $this->n($data['guarantor_id_issued_date'] ?? null),

            // Location
            'city'                  => $this->n($data['city'] ?? null),
            'state'                 => $this->n($data['state'] ?? null),

            // Financial
            'bank_name'             => $this->n($data['bank_name'] ?? null),
            'iban'                  => $this->n($data['iban'] ?? null),
            'commission_rate'       => isset($data['commission_rate']) && $data['commission_rate'] !== '' ? (float) $data['commission_rate'] : null,
            'fixed_commission_default' => isset($data['fixed_commission_default']) && $data['fixed_commission_default'] !== '' ? (float) $data['fixed_commission_default'] : null,

            // Other
            'ticket_terms'          => $this->n($data['ticket_terms'] ?? null),
            'status'                => $this->n($data['status'] ?? null) ?? 'active',
        ];

        // Plain password for new records — model's 'hashed' cast will hash it on set
        $fields['password'] = Str::random(24);

        // Remove null values so existing column values are not overwritten with null on update
        return array_filter($fields, fn($v) => $v !== null);
    }
}
