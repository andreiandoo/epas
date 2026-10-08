<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use App\Models\MarketplaceAdmin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

    /**
     * Marketplace panel: a deactivated account is told so instead of getting
     * the generic "wrong credentials" — but only when the password is right,
     * so the message can't be used to probe which emails have accounts.
     */
    protected function throwFailureValidationException(): never
    {
        if (filament()->getId() === 'marketplace') {
            $email = (string) ($this->data['email'] ?? '');
            $password = (string) ($this->data['password'] ?? '');

            $deactivated = $email !== '' && $password !== '' && MarketplaceAdmin::query()
                ->where('email', $email)
                ->where('status', '!=', 'active')
                ->get()
                ->contains(fn (MarketplaceAdmin $admin): bool => Hash::check($password, $admin->password));

            if ($deactivated) {
                throw ValidationException::withMessages([
                    'data.email' => 'Contul tău a fost dezactivat. Contactează un administrator al platformei.',
                ]);
            }
        }

        parent::throwFailureValidationException();
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
