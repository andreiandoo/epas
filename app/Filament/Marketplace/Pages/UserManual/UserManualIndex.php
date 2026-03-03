<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class UserManualIndex extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Manual Utilizator';

    protected static \UnitEnum|string|null $navigationGroup = 'Help';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'manual';

    protected string $view = 'filament.marketplace.pages.user-manual.index';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    public function switchLocale(string $locale): void
    {
        $this->locale = in_array($locale, ['ro', 'en']) ? $locale : 'ro';
    }

    public function t(array|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value[$this->locale] ?? $value['ro'] ?? $value['en'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->locale === 'ro' ? 'Manual Utilizator' : 'User Manual';
    }

    public function getViewData(): array
    {
        return [
            'modules' => BaseManualPage::getAllModules(),
            'locale' => $this->locale,
        ];
    }
}
