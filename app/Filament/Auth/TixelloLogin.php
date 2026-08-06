<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Split-screen login screen shared by every Tixello panel.
 *
 * Only the presentation is replaced: the form, validation, rate limiting,
 * multi-factor challenge and redirect behaviour all stay on Filament's
 * stock Login page.
 */
class TixelloLogin extends Login
{
    protected string $view = 'filament.auth.login';

    protected static string $layout = 'filament.auth.layouts.split';

    /**
     * The brand column renders its own logo lockup.
     */
    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * Copy and accent palette for the panel this screen belongs to.
     *
     * @return array<string, mixed>
     */
    public function getBrand(): array
    {
        $brand = array_replace(
            config('tixello-auth.default', []),
            config('tixello-auth.panels.' . filament()->getId(), []),
        );

        $accents = config('tixello-auth.accents', []);
        $accent = $brand['accent'] ?? 'indigo';

        $brand['palette'] = $accents[$accent] ?? $accents['indigo'] ?? [];

        return $brand;
    }

    public function getTitle(): string | Htmlable
    {
        return $this->getBrand()['title'] ?? parent::getTitle();
    }

    public function getHeading(): string | Htmlable | null
    {
        return $this->getBrand()['greeting'] ?? parent::getHeading();
    }

    public function getSubheading(): string | Htmlable | null
    {
        return $this->getBrand()['greeting_sub'] ?? null;
    }
}
