<?php

namespace App\Filament\Marketplace\Pages\UserManual;

trait BaseManualPage
{
    public function t(array|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value[$this->locale] ?? $value['ro'] ?? $value['en'] ?? '';
    }

    public function switchLocale(string $locale): void
    {
        $this->locale = in_array($locale, ['ro', 'en']) ? $locale : 'ro';
    }

    public function getTitle(): string
    {
        $content = $this->getManualContent();

        return $this->t($content['title'] ?? 'Manual');
    }

    public function getBreadcrumbs(): array
    {
        $hubLabel = $this->locale === 'ro' ? 'Manual Utilizator' : 'User Manual';

        return [
            UserManualIndex::getUrl() => $hubLabel,
            '#' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_hub')
                ->label($this->locale === 'ro' ? 'Inapoi la Manual' : 'Back to Manual')
                ->url(UserManualIndex::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function formatStepText(string $text): string
    {
        return preg_replace(
            '/\[([^\]]+)\]/',
            '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">$1</span>',
            e($text)
        );
    }

    public function getViewData(): array
    {
        return [
            'content' => $this->getManualContent(),
            'locale' => $this->locale,
        ];
    }

}
