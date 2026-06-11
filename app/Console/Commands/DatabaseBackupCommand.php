<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:backup
        {--full : Perform a full backup}
        {--verify : Verify backup integrity after creation}
        {--upload : Upload to remote storage}
        {--keep-local : Keep local copy after upload}';

    /**
     * The console command description.
     */
    protected $description = 'Create a database backup with optional encryption and remote upload';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('backup.enabled')) {
            $this->warn('Database backup is disabled in configuration.');
            return self::SUCCESS;
        }

        $this->info('Starting database backup...');

        try {
            // Generate backup filename
            $timestamp = now()->format('Y-m-d_His');
            $filename = "backup_{$timestamp}";

            // Create backup directory if needed
            $localPath = config('backup.storage.local_path');
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }

            // Perform backup based on database driver
            $driver = config('database.default');
            $backupPath = match ($driver) {
                'pgsql' => $this->backupPostgresql($localPath, $filename),
                'mysql' => $this->backupMysql($localPath, $filename),
                'sqlite' => $this->backupSqlite($localPath, $filename),
                default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
            };

            $this->info("Backup created: {$backupPath}");

            // Compress if enabled
            if (config('backup.compression.enabled')) {
                $backupPath = $this->compressBackup($backupPath);
                $this->info("Backup compressed: {$backupPath}");
            }

            // Encrypt if enabled
            if (config('backup.encryption.enabled')) {
                $backupPath = $this->encryptBackup($backupPath);
                $this->info("Backup encrypted: {$backupPath}");
            }

            // Verify if requested or configured
            if ($this->option('verify') || config('backup.verification.enabled')) {
                $this->verifyBackup($backupPath);
                $this->info('Backup verification passed.');
            }

            // Upload to remote storage
            if ($this->option('upload') || config('backup.storage.remote.enabled')) {
                $this->uploadBackup($backupPath);
                $this->info('Backup uploaded to remote storage.');

                // Clean up local copy if not keeping
                if (!$this->option('keep-local')) {
                    unlink($backupPath);
                    $this->info('Local backup removed.');
                }
            }

            // Clean up old backups
            $this->cleanupOldBackups();

            // Record successful backup
            $this->recordBackupSuccess($backupPath);

            // Ping monitoring service
            $this->pingMonitoringService();

            $this->info('Database backup completed successfully.');
            Log::info('Database backup completed', ['path' => $backupPath]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Backup failed: {$e->getMessage()}");
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification
            $this->sendFailureNotification($e);

            return self::FAILURE;
        }
    }

    /**
     * Backup PostgreSQL database
     */
    protected function backupPostgresql(string $path, string $filename): string
    {
        $config = config('database.connections.pgsql');
        $backupConfig = config('backup.postgresql');

        $outputFile = "{$path}/{$filename}.dump";

        $command = [
            'pg_dump',
            '--host=' . $config['host'],
            '--port=' . ($config['port'] ?? 5432),
            '--username=' . $config['username'],
            '--dbname=' . $config['database'],
            '--format=' . $backupConfig['format'],
            '--jobs=' . $backupConfig['jobs'],
            '--file=' . $outputFile,
        ];

        // Add schema filter if specified
        if (!empty($backupConfig['schemas'])) {
            foreach (explode(',', $backupConfig['schemas']) as $schema) {
                $command[] = '--schema=' . trim($schema);
            }
        }

        // Exclude tables
        if (!empty($backupConfig['exclude_tables'])) {
            foreach (explode(',', $backupConfig['exclude_tables']) as $table) {
                $command[] = '--exclude-table=' . trim($table);
            }
        }

        $process = new Process($command);
        $process->setEnv(['PGPASSWORD' => $config['password']]);
        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputFile;
    }

    /**
     * Backup MySQL database
     */
    protected function backupMysql(string $path, string $filename): string
    {
        $config = config('database.connections.mysql');
        $backupConfig = config('backup.mysql');

        $outputFile = "{$path}/{$filename}.sql";

        $command = [
            'mysqldump',
            '--host=' . $config['host'],
            '--port=' . ($config['port'] ?? 3306),
            '--user=' . $config['username'],
            '--password=' . $config['password'],
            $config['database'],
            '--result-file=' . $outputFile,
        ];

        if ($backupConfig['single_transaction']) {
            $command[] = '--single-transaction';
        }

        if ($backupConfig['quick']) {
            $command[] = '--quick';
        }

        if (!$backupConfig['lock_tables']) {
            $command[] = '--skip-lock-tables';
        }

        // Exclude tables
        if (!empty($backupConfig['exclude_tables'])) {
            foreach (explode(',', $backupConfig['exclude_tables']) as $table) {
                $command[] = '--ignore-table=' . $config['database'] . '.' . trim($table);
            }
        }

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputFile;
    }

    /**
     * Backup SQLite database
     */
    protected function backupSqlite(string $path, string $filename): string
    {
        $config = config('database.connections.sqlite');
        $databasePath = $config['database'];
        $outputFile = "{$path}/{$filename}.sqlite";

        // Use SQLite's backup command for consistency
        $command = ['sqlite3', $databasePath, ".backup '{$outputFile}'"];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            // Fallback to file copy
            copy($databasePath, $outputFile);
        }

        return $outputFile;
    }

    /**
     * Compress backup file
     */
    protected function compressBackup(string $path): string
    {
        $algorithm = config('backup.compression.algorithm');
        $level = config('backup.compression.level');

        $outputPath = match ($algorithm) {
            'gzip' => $path . '.gz',
            'bzip2' => $path . '.bz2',
            'xz' => $path . '.xz',
            default => throw new \RuntimeException("Unsupported compression: {$algorithm}"),
        };

        $command = match ($algorithm) {
            'gzip' => ['gzip', "-{$level}", '-c', $path],
            'bzip2' => ['bzip2', "-{$level}", '-c', $path],
            'xz' => ['xz', "-{$level}", '-c', $path],
        };

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        file_put_contents($outputPath, $process->getOutput());

        // Remove uncompressed file
        unlink($path);

        return $outputPath;
    }

    /**
     * Encrypt backup file
     */
    protected function encryptBackup(string $path): string
    {
        $key = config('backup.encryption.key');
        $algorithm = config('backup.encryption.algorithm');

        if (!$key) {
            throw new \RuntimeException('Backup encryption key not configured');
        }

        $outputPath = $path . '.enc';
        $ivLength = openssl_cipher_iv_length($algorithm);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $content = file_get_contents($path);
        $encrypted = openssl_encrypt($content, $algorithm, $key, OPENSSL_RAW_DATA, $iv);

        // Prepend IV to encrypted data
        file_put_contents($outputPath, $iv . $encrypted);

        // Remove unencrypted file
        unlink($path);

        return $outputPath;
    }

    /**
     * Verify backup integrity
     */
    protected function verifyBackup(string $path): void
    {
        // Check file exists and has content
        if (!file_exists($path)) {
            throw new \RuntimeException('Backup file does not exist');
        }

        $size = filesize($path);
        $minSize = config('backup.monitoring.min_backup_size_mb') * 1024 * 1024;

        if ($size < $minSize) {
            throw new \RuntimeException("Backup file too small: {$size} bytes");
        }

        // For PostgreSQL custom format, verify with pg_restore --list
        if (str_ends_with($path, '.dump')) {
            $process = new Process(['pg_restore', '--list', $path]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Backup verification failed: ' . $process->getErrorOutput());
            }
        }

        $this->line("Backup size: " . number_format($size / 1024 / 1024, 2) . " MB");
    }

    /**
     * Upload backup to remote storage
     */
    protected function uploadBackup(string $localPath): void
    {
        $remoteConfig = config('backup.storage.remote');
        $remotePath = $remoteConfig['path'] . '/' . basename($localPath);

        // Use Laravel's Storage facade with the configured disk
        Storage::disk($remoteConfig['driver'])->put(
            $remotePath,
            file_get_contents($localPath)
        );
    }

    /**
     * Clean up old backups
     */
    protected function cleanupOldBackups(): void
    {
        $localPath = config('backup.storage.local_path');
        $retention = config('backup.retention');

        // Get all backup files
        $files = glob("{$localPath}/backup_*");

        // Sort by modification time (oldest first)
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        // Keep only the configured number of daily backups
        $toKeep = $retention['daily'];
        $toDelete = array_slice($files, 0, max(0, count($files) - $toKeep));

        foreach ($toDelete as $file) {
            unlink($file);
            $this->line("Deleted old backup: " . basename($file));
        }
    }

    /**
     * Record successful backup for monitoring
     */
    protected function recordBackupSuccess(string $path): void
    {
        cache()->put('last_successful_backup', [
            'timestamp' => now()->toIso8601String(),
            'path' => $path,
            'size' => filesize($path),
        ], now()->addDays(7));
    }

    /**
     * Ping external monitoring service
     */
    protected function pingMonitoringService(): void
    {
        $pingUrl = config('backup.monitoring.ping_url');

        if (!$pingUrl) {
            return;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $client->get($pingUrl);
        } catch (\Exception $e) {
            Log::warning('Failed to ping monitoring service', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send failure notification
     */
    protected function sendFailureNotification(\Exception $e): void
    {
        if (!config('backup.notifications.enabled') || !config('backup.notifications.on_failure')) {
            return;
        }

        $recipients = config('backup.notifications.recipients');

        // Log the notification attempt
        Log::error('Database backup failed - notification sent', [
            'error' => $e->getMessage(),
            'recipients' => $recipients,
        ]);

        // Send email notification if configured
        if (!empty($recipients['mail'])) {
            // Use your preferred notification method
            // Notification::route('mail', $recipients['mail'])
            //     ->notify(new BackupFailedNotification($e));
        }
    }
}
