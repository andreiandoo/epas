<?php

namespace App\Console\Commands;

use App\Models\TixelloWidgetToken;
use Illuminate\Console\Command;

/**
 * Generează / listează / revocă token-urile widget-ului de Android.
 *
 *   php artisan tixello:widget-token "Telefon Andrei"
 *   php artisan tixello:widget-token --list
 *   php artisan tixello:widget-token --revoke=3
 */
class TixelloWidgetTokenCommand extends Command
{
    protected $signature = 'tixello:widget-token
        {name? : Numele dispozitivului (ex. "Telefon Andrei")}
        {--list : Listează token-urile existente}
        {--revoke= : ID-ul token-ului de revocat}
        {--days= : Expiră după atâtea zile (implicit: niciodată)}';

    protected $description = 'Token-uri pentru widget-ul Tixello de pe Android';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listTokens();
        }

        if ($revokeId = $this->option('revoke')) {
            return $this->revoke((int) $revokeId);
        }

        $name = $this->argument('name') ?: $this->ask('Numele dispozitivului');

        if (! $name) {
            $this->error('Numele dispozitivului e obligatoriu.');

            return self::FAILURE;
        }

        $days = $this->option('days');
        [$token, $plain] = TixelloWidgetToken::issue(
            $name,
            $days ? now()->addDays((int) $days) : null
        );

        $this->info("Token #{$token->id} pentru „{$name}”:");
        $this->newLine();
        $this->line($plain);
        $this->newLine();
        $this->warn('Se afişează O SINGURĂ DATĂ. În baza de date rămâne doar hash-ul.');
        $this->line('Îl introduci în aplicaţie la „Token", împreună cu adresa serverului.');

        return self::SUCCESS;
    }

    private function listTokens(): int
    {
        $tokens = TixelloWidgetToken::orderByDesc('id')->get();

        if ($tokens->isEmpty()) {
            $this->info('Niciun token emis.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nume', 'Ultima folosire', 'IP', 'Expiră', 'Revocat'],
            $tokens->map(fn (TixelloWidgetToken $t) => [
                $t->id,
                $t->name,
                $t->last_used_at?->toDateTimeString() ?? '—',
                $t->last_used_ip ?? '—',
                $t->expires_at?->toDateString() ?? 'niciodată',
                $t->revoked_at?->toDateTimeString() ?? '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function revoke(int $id): int
    {
        $token = TixelloWidgetToken::find($id);

        if (! $token) {
            $this->error("Nu există token cu ID {$id}.");

            return self::FAILURE;
        }

        $token->revoke();
        $this->info("Token #{$id} („{$token->name}”) revocat.");
        $this->line('Poate rămâne valid până la 5 minute, cât ţine cache-ul de autentificare.');

        return self::SUCCESS;
    }
}
