<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class ServerStats extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static \UnitEnum|string|null $navigationGroup = 'Operational';
    protected static ?int $navigationSort = 91;
    protected static ?string $title = 'Server Stats';
    protected static ?string $navigationLabel = 'Server Stats';
    protected string $view = 'filament.pages.server-stats';

    // Cheap, always-on metrics (collected on mount / refresh).
    public array $disk = [];
    public array $mounts = [];
    public array $memory = [];
    public array $swap = [];
    public array $load = [];
    public array $cpu = [];
    public array $processes = [];
    public ?string $uptime = null;
    public ?string $phpMemoryLimit = null;
    public bool $shellAvailable = true;

    // Database size (cheap pg catalog queries — no shell/root needed).
    public array $database = [];

    // Expensive, opt-in directory breakdown.
    public array $dirSizes = [];
    public bool $dirScanned = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->collectStats();
        // Surface a previously computed directory scan (cached) without re-running du.
        if (($cached = Cache::get($this->dirCacheKey())) !== null) {
            $this->dirSizes = $cached;
            $this->dirScanned = true;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Reîmprospătează')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->collectStats()),

            Action::make('analyze_dirs')
                ->label('Analizează directoare')
                ->icon('heroicon-o-folder-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Analiză spațiu pe directoare')
                ->modalDescription('Scanează directorul aplicației cu `du` (poate dura câteva secunde și încarcă discul). Rezultatul se păstrează în cache 15 minute.')
                ->action(fn () => $this->analyzeDirectories()),
        ];
    }

    /**
     * Collect the cheap metrics: disk, RAM/swap, load, CPU, uptime, top procs.
     * All are /proc reads or PHP built-ins except `ps`/`df` (sub-10ms snapshots).
     */
    public function collectStats(): void
    {
        $this->shellAvailable = $this->shellAvailable();
        $this->phpMemoryLimit = (string) ini_get('memory_limit');

        // --- All mounted filesystems (df) ---
        $this->mounts = $this->readMounts();

        // --- Disk (filesystem containing the app) ---
        // Prefer the df row for the app's mount so the card and the df table
        // agree. The statvfs fallback (disk_total/free) computes used = total −
        // available, which WRONGLY counts the ~5% root-reserved space as
        // "used" (disk_free_space returns space available to non-root), so the
        // card read ~10 GB higher than df. df's Used column excludes the
        // reserve — that is the real "occupied by files" figure.
        $this->disk = $this->diskForApp($this->mounts);

        // --- Memory + swap (/proc/meminfo) ---
        $mem = $this->readMeminfo();
        $memTotal = $mem['MemTotal'] ?? 0;
        $memAvail = $mem['MemAvailable'] ?? ($mem['MemFree'] ?? 0);
        $memUsed = max(0, $memTotal - $memAvail);
        $this->memory = [
            'total' => $memTotal,
            'available' => $memAvail,
            'used' => $memUsed,
            'free' => $mem['MemFree'] ?? 0,
            'buffers' => $mem['Buffers'] ?? 0,
            'cached' => $mem['Cached'] ?? 0,
            'percent' => $memTotal > 0 ? round($memUsed / $memTotal * 100, 1) : 0,
            'total_h' => $this->humanSize($memTotal),
            'used_h' => $this->humanSize($memUsed),
            'available_h' => $this->humanSize($memAvail),
        ];
        $swapTotal = $mem['SwapTotal'] ?? 0;
        $swapFree = $mem['SwapFree'] ?? 0;
        $swapUsed = max(0, $swapTotal - $swapFree);
        $this->swap = [
            'total' => $swapTotal,
            'used' => $swapUsed,
            'percent' => $swapTotal > 0 ? round($swapUsed / $swapTotal * 100, 1) : 0,
            'total_h' => $this->humanSize($swapTotal),
            'used_h' => $this->humanSize($swapUsed),
        ];

        // --- Load average + CPU cores ---
        $la = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
        $cores = $this->cpuCores();
        $this->load = [
            '1' => round($la[0] ?? 0, 2),
            '5' => round($la[1] ?? 0, 2),
            '15' => round($la[2] ?? 0, 2),
            'per_core' => $cores > 0 ? round(($la[0] ?? 0) / $cores * 100, 0) : null,
        ];
        $this->cpu = ['cores' => $cores];

        // --- Uptime ---
        $this->uptime = $this->readUptime();

        // --- Top processes by memory ---
        $this->processes = $this->topProcesses();

        // --- PostgreSQL database size + top tables ---
        $this->database = $this->readDatabaseSizes();
    }

    /**
     * PostgreSQL live size (a big chunk of /var/lib/postgresql) + the largest
     * tables incl. their indexes/toast. Cheap system-catalog reads over the
     * existing connection — no shell, no filesystem, no root.
     */
    protected function readDatabaseSizes(): array
    {
        if (config('database.default') !== 'pgsql') {
            return [];
        }
        try {
            $meta = \DB::selectOne('select current_database() as db, pg_database_size(current_database()) as size');
            $tables = \DB::select(
                "select c.relname as name, pg_total_relation_size(c.oid) as size
                 from pg_class c
                 join pg_namespace n on n.oid = c.relnamespace
                 where n.nspname = 'public' and c.relkind = 'r'
                 order by pg_total_relation_size(c.oid) desc
                 limit 12"
            );

            return [
                'name' => $meta->db ?? '',
                'size' => (int) ($meta->size ?? 0),
                'size_h' => $this->humanSize((int) ($meta->size ?? 0)),
                'tables' => array_map(fn ($t) => [
                    'name' => $t->name,
                    'size_h' => $this->humanSize((int) $t->size),
                ], $tables),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Opt-in: size the app directory's immediate subdirectories with `du`.
     * Result cached 15 min so repeated views don't re-scan.
     */
    public function analyzeDirectories(): void
    {
        abort_unless(static::canAccess(), 403);

        if (!$this->shellAvailable()) {
            Notification::make()->title('Shell indisponibil')->body('Funcția shell_exec este dezactivată — nu pot rula du.')->danger()->send();
            return;
        }

        $base = base_path();
        // -b bytes, --max-depth=1 immediate children only (single tree pass),
        // stderr suppressed (permission-denied on odd subdirs is non-fatal).
        $raw = $this->shell('du -b --max-depth=1 ' . escapeshellarg($base) . ' 2>/dev/null | sort -rn | head -n 20');

        $rows = [];
        foreach (preg_split('/\r?\n/', (string) $raw) as $line) {
            if (!preg_match('/^(\d+)\s+(.+)$/', trim($line), $m)) {
                continue;
            }
            $bytes = (int) $m[1];
            $path = $m[2];
            $label = $path === $base ? '(total aplicație)' : basename($path);
            $rows[] = ['label' => $label, 'bytes' => $bytes, 'size' => $this->humanSize($bytes)];
        }

        Cache::put($this->dirCacheKey(), $rows, now()->addMinutes(15));
        $this->dirSizes = $rows;
        $this->dirScanned = true;

        Notification::make()->title('Analiză finalizată')->success()->send();
    }

    // ---------------------------------------------------------------------
    // Collectors
    // ---------------------------------------------------------------------

    protected function readMeminfo(): array
    {
        $out = [];
        if (is_readable('/proc/meminfo')) {
            foreach (file('/proc/meminfo') ?: [] as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s*kB/i', $line, $m)) {
                    $out[$m[1]] = (int) $m[2] * 1024; // bytes
                }
            }
        }
        return $out;
    }

    protected function readMounts(): array
    {
        $raw = $this->shell('df -B1 -P 2>/dev/null');
        if (!$raw) {
            return [];
        }
        $rows = [];
        $lines = preg_split('/\r?\n/', trim($raw));
        array_shift($lines); // header
        foreach ($lines as $line) {
            $p = preg_split('/\s+/', trim($line));
            if (count($p) < 6) {
                continue;
            }
            [$fs, $total, $used, $avail, $pct, $mount] = [$p[0], (int) $p[1], (int) $p[2], (int) $p[3], $p[4], $p[5]];
            // Skip pseudo/virtual filesystems — only real storage is interesting.
            if (preg_match('#^(tmpfs|devtmpfs|overlay|shm|udev|none)$#i', $fs) || str_starts_with($mount, '/dev') || str_starts_with($mount, '/sys') || str_starts_with($mount, '/proc') || str_starts_with($mount, '/run')) {
                continue;
            }
            if ($total <= 0) {
                continue;
            }
            $rows[] = [
                'fs' => $fs,
                'mount' => $mount,
                'total_b' => $total,
                'used_b' => $used,
                'avail_b' => $avail,
                'total_h' => $this->humanSize($total),
                'used_h' => $this->humanSize($used),
                'avail_h' => $this->humanSize($avail),
                'percent' => (float) rtrim($pct, '%'),
            ];
        }
        return $rows;
    }

    /**
     * Resolve the disk-usage card from the df row of the filesystem that
     * actually holds the app (longest mount path that prefixes base_path).
     * Falls back to statvfs when df is unavailable — noting that the fallback
     * over-reports "used" by the root-reserved space.
     */
    protected function diskForApp(array $mounts): array
    {
        $base = base_path();
        $best = null;
        $bestLen = -1;
        foreach ($mounts as $m) {
            $mp = rtrim($m['mount'], '/');
            $isMatch = $m['mount'] === '/' || $base === $mp || str_starts_with($base, $mp . '/');
            if ($isMatch && strlen($mp) > $bestLen) {
                $best = $m;
                $bestLen = strlen($mp);
            }
        }

        if ($best) {
            return [
                'total' => $best['total_b'],
                'free' => $best['avail_b'],
                'used' => $best['used_b'],
                'percent' => $best['percent'],
                'total_h' => $this->humanSize($best['total_b']),
                'free_h' => $this->humanSize($best['avail_b']),
                'used_h' => $this->humanSize($best['used_b']),
                'source' => 'df',
            ];
        }

        // Fallback (df unavailable): statvfs. NOTE: 'used' here includes the
        // ~5% root reserve, so it reads slightly higher than df would.
        $total = @disk_total_space($base) ?: 0;
        $free = @disk_free_space($base) ?: 0;
        $used = max(0, $total - $free);
        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'percent' => $total > 0 ? round($used / $total * 100, 1) : 0,
            'total_h' => $this->humanSize($total),
            'free_h' => $this->humanSize($free),
            'used_h' => $this->humanSize($used),
            'source' => 'statvfs',
        ];
    }

    protected function topProcesses(): array
    {
        // args LAST + un-truncated: for PostgreSQL the process title carried in
        // args reveals what each backend is actually doing (idle client,
        // autovacuum, checkpointer, ...) — `comm` only ever says "postgres".
        $raw = $this->shell('ps -eo pid,user,comm,pmem,rss,args --sort=-rss 2>/dev/null | head -n 11');
        if (!$raw) {
            return [];
        }
        $rows = [];
        $lines = preg_split('/\r?\n/', trim($raw));
        array_shift($lines); // header
        foreach ($lines as $line) {
            $p = preg_split('/\s+/', trim($line), 6);
            if (count($p) < 6) {
                continue;
            }
            $rows[] = [
                'pid' => $p[0],
                'user' => $p[1],
                'command' => $p[2],
                'mem_pct' => (float) $p[3],
                'rss_h' => $this->humanSize((int) $p[4] * 1024),
                'description' => $this->describeProcess($p[2], $p[5]),
            ];
        }
        return $rows;
    }

    /**
     * Human explanation of a process from its comm + full args. PostgreSQL sets
     * its process title (visible in args) to describe each backend's job, so we
     * decode that; other well-known daemons get a static label.
     */
    protected function describeProcess(string $comm, string $args): string
    {
        $args = trim($args);
        $lower = strtolower($comm . ' ' . $args);

        if ($comm === 'postgres' || str_starts_with($args, 'postgres:')) {
            $title = strtolower(trim((string) preg_replace('/^postgres:\s*[\d\/a-z]*:?\s*/i', '', $args)));

            return match (true) {
                str_contains($title, 'checkpointer')                 => 'PostgreSQL — checkpointer (scrie periodic datele pe disc)',
                str_contains($title, 'background writer')            => 'PostgreSQL — background writer (golește bufferele modificate)',
                str_contains($title, 'walwriter')                    => 'PostgreSQL — WAL writer (jurnalul de tranzacții)',
                str_contains($title, 'autovacuum launcher')          => 'PostgreSQL — autovacuum launcher',
                str_contains($title, 'autovacuum worker')            => 'PostgreSQL — autovacuum worker (curăță/analizează tabele)',
                str_contains($title, 'logical replication launcher') => 'PostgreSQL — logical replication launcher',
                str_contains($title, 'walsender')                    => 'PostgreSQL — WAL sender (replicare)',
                str_contains($title, 'walreceiver')                  => 'PostgreSQL — WAL receiver (replicare)',
                str_contains($title, 'archiver')                     => 'PostgreSQL — WAL archiver',
                str_contains($title, 'startup')                      => 'PostgreSQL — startup / recovery',
                str_contains($title, 'parallel worker')              => 'PostgreSQL — parallel query worker',
                str_contains($title, 'idle in transaction')          => 'PostgreSQL — conexiune client (idle in transaction — ⚠️ ține locks)',
                str_contains($title, 'idle')                         => 'PostgreSQL — conexiune client inactivă (idle)',
                $title !== ''                                        => 'PostgreSQL — conexiune client activă: ' . \Illuminate\Support\Str::limit($title, 70),
                default                                              => 'PostgreSQL — proces server',
            };
        }

        return match (true) {
            str_contains($lower, 'mysqld')                                => 'MySQL/MariaDB — server de baze de date (alt serviciu/site)',
            str_contains($lower, 'php-fpm')                               => 'PHP-FPM — worker care rulează aplicația web (Laravel)',
            $comm === 'php'                                               => 'PHP — worker/CLI (web, sau artisan/queue/scheduler)',
            str_contains($lower, 'nginx')                                 => 'Nginx — server web (servește request-urile HTTP)',
            str_contains($lower, 'apache') || str_contains($lower, 'httpd') => 'Apache — server web',
            str_contains($lower, 'redis')                                 => 'Redis — cache / cozi',
            str_contains($lower, 'node')                                  => 'Node.js — proces JS (build/asset/serviciu)',
            str_contains($lower, 'supervisor')                            => 'Supervisor — manager procese (queue workers)',
            str_contains($lower, 'sshd')                                  => 'SSH daemon',
            str_contains($lower, 'systemd')                               => 'systemd — manager de sistem',
            default                                                       => $comm,
        };
    }

    protected function cpuCores(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            $count = preg_match_all('/^processor\s*:/mi', (string) file_get_contents('/proc/cpuinfo'));
            if ($count > 0) {
                return $count;
            }
        }
        $n = (int) trim((string) $this->shell('nproc 2>/dev/null'));
        return $n > 0 ? $n : 1;
    }

    protected function readUptime(): ?string
    {
        if (!is_readable('/proc/uptime')) {
            return null;
        }
        $seconds = (int) floatval(explode(' ', (string) file_get_contents('/proc/uptime'))[0] ?? 0);
        if ($seconds <= 0) {
            return null;
        }
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        return trim(($d > 0 ? "{$d}z " : '') . "{$h}h {$m}m");
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function shellAvailable(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('shell_exec', $disabled, true);
    }

    protected function shell(string $cmd): ?string
    {
        if (!$this->shellAvailable()) {
            return null;
        }
        try {
            return shell_exec($cmd);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function dirCacheKey(): string
    {
        return 'server_stats:dir_sizes';
    }

    public function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power > 0 ? 2 : 0) . ' ' . $units[$power];
    }
}
