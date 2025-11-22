<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TenantPackage extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain_id',
        'version',
        'package_hash',
        'integrity_hash',
        'status',
        'config_snapshot',
        'enabled_modules',
        'theme_config',
        'file_path',
        'file_size',
        'download_count',
        'generated_at',
        'last_downloaded_at',
        'expires_at',
    ];

    protected $casts = [
        'config_snapshot' => 'encrypted:array',
        'enabled_modules' => 'array',
        'theme_config' => 'array',
        'generated_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_INVALIDATED = 'invalidated';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeForDomain($query, int $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->file_path || !$this->isReady()) {
            return null;
        }

        return route('api.tenant.package.download', [
            'package' => $this->id,
            'hash' => $this->package_hash,
        ]);
    }

    public function getInstallationCode(): string
    {
        $encryptedConfig = base64_encode(encrypt([
            'tenant_id' => $this->tenant_id,
            'domain_id' => $this->domain_id,
            'package_hash' => $this->package_hash,
        ]));

        return sprintf(
            '<!-- Tixello Event Platform -->
<div id="tixello-app"></div>
<script src="%s"
        data-config="%s"
        integrity="%s"
        crossorigin="anonymous"></script>',
            $this->getScriptUrl(),
            $encryptedConfig,
            $this->integrity_hash
        );
    }

    public function getScriptUrl(): string
    {
        return config('app.url') . '/tixello-client/' . $this->package_hash . '/loader.min.js';
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    public function invalidate(): void
    {
        $this->update(['status' => self::STATUS_INVALIDATED]);

        if ($this->file_path && Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
