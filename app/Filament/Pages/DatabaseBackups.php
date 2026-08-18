<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DatabaseBackups extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static \UnitEnum|string|null $navigationGroup = 'Operational';
    protected static ?int $navigationSort = 90;
    protected static ?string $title = 'Database Backups';
    protected static ?string $navigationLabel = 'Database Backups';
    protected string $view = 'filament.pages.database-backups';

    /** @var array<int, array<string, mixed>> */
    public array $backups = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadBackups();
    }

    /**
     * Scan the local backups directory for backup artifacts, newest first.
     */
    public function loadBackups(): void
    {
        $this->backups = [];
        $dir = $this->backupDir();

        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*') ?: [];
        // Newest first.
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name = basename($file);
            // Only surface actual backup artifacts, not stray files.
            if (!preg_match('/\.(dump|sql|sqlite|gz|bz2|xz|enc)$/i', $name)) {
                continue;
            }

            $bytes = (int) filesize($file);
            $this->backups[] = [
                'name' => $name,
                'size' => $this->humanSize($bytes),
                'bytes' => $bytes,
                'modified' => date('d.m.Y H:i', filemtime($file)),
                // 'backup_*' are the scheduled daily dumps; anything else
                // (e.g. pre-reconcile-*.dump) was created manually.
                'is_auto' => str_starts_with($name, 'backup_'),
                'download_url' => route('admin.database-backups.download', ['filename' => $name]),
            ];
        }
    }

    /**
     * Delete a backup file. Small filesystem op — safe to run inline via
     * Livewire (unlike the download, which streams a huge file).
     */
    public function deleteBackup(string $name): void
    {
        abort_unless(static::canAccess(), 403);

        $dir = realpath($this->backupDir());
        $name = basename($name);
        $path = $dir !== false ? realpath($dir . DIRECTORY_SEPARATOR . $name) : false;

        if ($path !== false && str_starts_with($path, $dir . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
            Notification::make()->title('Backup șters: ' . $name)->success()->send();
        } else {
            Notification::make()->title('Fișier inexistent')->danger()->send();
        }

        $this->loadBackups();
    }

    public function getTotalSize(): string
    {
        return $this->humanSize((int) array_sum(array_column($this->backups, 'bytes')));
    }

    protected function backupDir(): string
    {
        return (string) config('backup.storage.local_path', storage_path('backups'));
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power > 0 ? 2 : 0) . ' ' . $units[$power];
    }
}
