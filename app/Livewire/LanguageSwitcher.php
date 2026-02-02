<?php

namespace App\Livewire;

use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $currentLocale;
    public array $availableLocales = ['ro', 'en'];
    public array $localeNames = [
        'en' => 'English',
        'ro' => 'Romana',
    ];
    public array $localeFlags = [];

    public function mount(): void
    {
        $this->currentLocale = app()->getLocale();
        $this->localeFlags = [
            'en' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg"><clipPath id="s"><path d="M0,0 v30 h60 v-30 z"/></clipPath><clipPath id="t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><g clip-path="url(#s)"><path d="M0,0 v30 h60 v-30 z" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#t)" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>',
            'ro' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg"><rect width="1" height="2" x="0" fill="#002B7F"/><rect width="1" height="2" x="1" fill="#FCD116"/><rect width="1" height="2" x="2" fill="#CE1126"/></svg>',
        ];
    }

    public function setLocale(string $locale): void
    {
        if (!in_array($locale, $this->availableLocales)) {
            return;
        }

        // Get the authenticated user from the appropriate guard
        $user = $this->getAuthenticatedUser();

        if ($user) {
            // Save to database
            $user->locale = $locale;
            $user->save();
        }

        // Set the application locale
        app()->setLocale($locale);
        session(['locale' => $locale]);
        $this->currentLocale = $locale;

        // Refresh the page to apply the new locale
        $this->redirect(request()->header('Referer', url()->current()), navigate: true);
    }

    protected function getAuthenticatedUser(): mixed
    {
        // Check Filament's auth first
        if (function_exists('filament') && filament()->auth()->check()) {
            return filament()->auth()->user();
        }

        // Check marketplace_admin guard
        if (auth('marketplace_admin')->check()) {
            return auth('marketplace_admin')->user();
        }

        // Check web guard
        if (auth('web')->check()) {
            return auth('web')->user();
        }

        return null;
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
