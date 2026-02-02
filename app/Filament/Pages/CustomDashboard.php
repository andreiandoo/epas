<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Log;

class CustomDashboard extends BaseDashboard
{
    /**
     * Override canAccess to add logging and always return true for diagnosis
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        $canAccess = true; // Force true to diagnose

        Log::channel('single')->info('=== DASHBOARD ACCESS CHECK ===', [
            'user_id' => $user?->id ?? null,
            'user_email' => $user?->email ?? null,
            'user_role' => $user?->role ?? null,
            'canAccess' => $canAccess,
            'is_authenticated' => auth()->check(),
            'session_id' => session()->getId(),
        ]);

        return $canAccess;
    }

    /**
     * Override mountCanAuthorizeAccess to add even more logging
     */
    public function mountCanAuthorizeAccess(): void
    {
        Log::channel('single')->info('=== DASHBOARD MOUNT AUTHORIZE ACCESS ===', [
            'user_id' => auth()->id(),
            'about_to_check' => 'static::canAccess()',
        ]);

        $canAccess = static::canAccess();

        Log::channel('single')->info('=== DASHBOARD MOUNT RESULT ===', [
            'canAccess' => $canAccess,
            'will_abort' => !$canAccess,
        ]);

        abort_unless($canAccess, 403);
    }
}
