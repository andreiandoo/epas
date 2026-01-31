<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ChangelogEntry extends Model
{
    protected $fillable = [
        'commit_hash',
        'short_hash',
        'type',
        'scope',
        'module',
        'message',
        'description',
        'author_name',
        'author_email',
        'committed_at',
        'files_changed',
        'additions',
        'deletions',
        'is_breaking',
        'is_visible',
    ];

    protected $casts = [
        'committed_at' => 'datetime',
        'files_changed' => 'array',
        'is_breaking' => 'boolean',
        'is_visible' => 'boolean',
    ];

    /**
     * Module mappings - maps scopes/paths to human-readable module names
     */
    public const MODULE_MAPPINGS = [
        // Scopes from commit messages
        'marketplace' => 'Marketplace Platform',
        'ambilet' => 'AmBilet Frontend',
        'organizer' => 'Portal Organizator',
        'seating' => 'Seating Designer',
        'analytics' => 'Analytics Dashboard',
        'kb' => 'Knowledge Base',
        'gamification' => 'Gamification',
        'shop' => 'Shop Module',
        'tax' => 'Tax Module',
        'payment' => 'Plăți & Facturare',
        'email' => 'Email & Notificări',
        'media' => 'Media Library',
        'tenant' => 'Tenant Management',
        'affiliate' => 'Affiliate Tracking',
        'invoice' => 'Invoices',
        'ticket' => 'Tickets',
        'event' => 'Evenimente',
        'venue' => 'Venues',
        'artist' => 'Artists',
        'customer' => 'Customers',
        'order' => 'Orders',
        'api' => 'API',
        'auth' => 'Authentication',
        'filament' => 'Admin Panel',
        'migration' => 'Database',
        'seeder' => 'Database Seeders',
        'docs' => 'Documentation',
    ];

    /**
     * Commit type labels
     */
    public const TYPE_LABELS = [
        'feat' => 'Funcționalitate Nouă',
        'fix' => 'Remediere',
        'refactor' => 'Refactorizare',
        'docs' => 'Documentație',
        'style' => 'Stilizare',
        'test' => 'Teste',
        'chore' => 'Mentenanță',
        'perf' => 'Performanță',
        'build' => 'Build',
        'ci' => 'CI/CD',
        'other' => 'Altele',
    ];

    /**
     * Scope to visible
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope by module
     */
    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by date range
     */
    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('committed_at', [$from, $to]);
    }

    /**
     * Get human-readable type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Get human-readable module label
     */
    public function getModuleLabelAttribute(): string
    {
        return self::MODULE_MAPPINGS[$this->module] ?? ucfirst($this->module ?? 'General');
    }

    /**
     * Detect module from commit message, scope, or changed files
     */
    public static function detectModule(?string $scope, ?string $message, ?array $files = []): string
    {
        // First, check scope
        if ($scope && isset(self::MODULE_MAPPINGS[$scope])) {
            return $scope;
        }

        // Check message for patterns
        $message = strtolower($message ?? '');

        $patterns = [
            'seating' => ['seating', 'seat', 'layout'],
            'marketplace' => ['marketplace', 'ambilet'],
            'organizer' => ['organizer', 'organizator'],
            'analytics' => ['analytics', 'stats', 'metrics', 'dashboard'],
            'kb' => ['knowledge base', 'kb ', 'article', 'faq'],
            'gamification' => ['gamification', 'points', 'rewards', 'badges', 'xp'],
            'shop' => ['shop', 'product', 'cart', 'shipping'],
            'tax' => ['tax', 'vat', 'fiscal'],
            'payment' => ['payment', 'stripe', 'netopia', 'checkout', 'payout'],
            'email' => ['email', 'notification', 'mail', 'newsletter'],
            'media' => ['media', 'image', 'upload', 'compress'],
            'tenant' => ['tenant', 'domain', 'package'],
            'affiliate' => ['affiliate', 'referral'],
            'invoice' => ['invoice', 'billing', 'proforma'],
            'ticket' => ['ticket', 'bilet'],
            'event' => ['event', 'eveniment'],
            'venue' => ['venue', 'locatie', 'location'],
            'artist' => ['artist'],
            'customer' => ['customer', 'user', 'account'],
            'order' => ['order', 'comanda'],
            'api' => ['api ', 'endpoint'],
            'auth' => ['auth', 'login', 'register', 'password'],
            'filament' => ['filament', 'admin panel', 'resource'],
        ];

        foreach ($patterns as $module => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    return $module;
                }
            }
        }

        // Check file paths
        if (!empty($files)) {
            $pathPatterns = [
                'seating' => 'Seating',
                'marketplace' => 'Marketplace',
                'organizer' => 'Organizer',
                'gamification' => 'Gamification',
                'shop' => 'Shop',
                'tax' => 'Tax',
                'affiliate' => 'Affiliate',
                'invoice' => 'Invoice',
                'tenant' => 'Tenant',
            ];

            foreach ($files as $file) {
                foreach ($pathPatterns as $module => $pattern) {
                    if (str_contains($file, $pattern)) {
                        return $module;
                    }
                }
            }
        }

        return 'general';
    }

    /**
     * Parse conventional commit message
     */
    public static function parseCommitMessage(string $message): array
    {
        $type = 'other';
        $scope = null;
        $description = $message;
        $isBreaking = str_contains($message, '!:') || str_contains(strtolower($message), 'breaking');

        // Pattern: type(scope): description or type: description
        if (preg_match('/^(feat|fix|docs|style|refactor|perf|test|build|ci|chore)(?:\(([^)]+)\))?!?:\s*(.+)$/i', $message, $matches)) {
            $type = strtolower($matches[1]);
            $scope = $matches[2] ?: null;
            $description = $matches[3];
        }
        // Pattern: Add/Fix/Update at start
        elseif (preg_match('/^(Add|Fix|Update|Remove|Improve|Enhance|Refactor|Create)\s+(.+)$/i', $message, $matches)) {
            $action = strtolower($matches[1]);
            $description = $matches[2];

            $type = match($action) {
                'add', 'create', 'enhance', 'improve' => 'feat',
                'fix' => 'fix',
                'update', 'refactor' => 'refactor',
                'remove' => 'chore',
                default => 'other',
            };
        }

        return [
            'type' => $type,
            'scope' => $scope,
            'description' => $description,
            'is_breaking' => $isBreaking,
        ];
    }

    /**
     * Check if commit should be visible in changelog
     */
    public static function shouldBeVisible(string $message): bool
    {
        $hiddenPatterns = [
            '/^Deploy\s+marketplace/i',
            '/^Merge\s+(branch|remote)/i',
            '/^WIP/i',
            '/^temp:/i',
            '/^debug:/i',
            '/^chore:\s*bump/i',
        ];

        foreach ($hiddenPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return false;
            }
        }

        return true;
    }
}
