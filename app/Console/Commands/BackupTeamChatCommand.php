<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * Salvare săptămânală doar a mesajelor text din chatul intern al echipei.
 * Atașamentele nu intră (se șterg oricum după 30 de zile), iar backup-ul zilnic
 * complet salvează aceste tabele fără date (vezi backup.postgresql.exclude_table_data).
 */
class BackupTeamChatCommand extends Command
{
    protected $signature = 'db:backup-team-chat {--keep=8 : Câte salvări săptămânale păstrăm}';

    protected $description = 'Salvează mesajele text din chatul intern (fără atașamente)';

    private const TABLES = ['team_threads', 'team_thread_members', 'team_messages'];

    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->warn('Doar pentru PostgreSQL.');
            return self::SUCCESS;
        }

        $tables = array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
        if (! $tables) {
            $this->info('Chatul intern nu există încă, nimic de salvat.');
            return self::SUCCESS;
        }

        $path = config('backup.storage.local_path');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $file = $path . '/team_chat_' . now()->format('Y-m-d_His') . '.dump';
        $config = config('database.connections.pgsql');

        $command = [
            'pg_dump',
            '--host=' . $config['host'],
            '--port=' . ($config['port'] ?? 5432),
            '--username=' . $config['username'],
            '--dbname=' . $config['database'],
            '--format=custom',
            '--data-only',
            '--file=' . $file,
        ];
        foreach ($tables as $table) {
            $command[] = '--table=' . $table;
        }

        $process = new Process($command);
        $process->setEnv(['PGPASSWORD' => $config['password']]);
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($file);
            $this->error('pg_dump a eșuat: ' . trim($process->getErrorOutput()));
            return self::FAILURE;
        }

        $this->info('Salvat: ' . $file . ' (' . round(filesize($file) / 1048576, 2) . ' MB)');

        $keep = max(1, (int) $this->option('keep'));
        $old = glob($path . '/team_chat_*.dump') ?: [];
        rsort($old);
        foreach (array_slice($old, $keep) as $stale) {
            @unlink($stale);
        }

        return self::SUCCESS;
    }
}
