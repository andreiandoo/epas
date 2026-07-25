<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Concerns\AutoFillsEventSeo;
use App\Filament\Tenant\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    use AutoFillsEventSeo;

    protected static string $resource = EventResource::class;

    protected function afterSave(): void
    {
        $this->autoFillSeoKeys();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redirect hosted events to view-guest page (can't edit events you don't own)
        $tenant = auth()->user()?->tenant;
        if ($this->record->tenant_id !== $tenant?->id) {
            redirect(EventResource::getUrl('view-guest', ['record' => $this->record]));
        }
    }

    /**
     * Defensive: FileUpload components (poster_url/hero_image_url/gallery) expect
     * paths on the 'public' disk. Legacy/seeded rows may hold full external URLs,
     * which break the uploader on load. Strip those from the FORM only (DB value
     * is untouched until an explicit save) so the edit page never 500s.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['poster_url', 'hero_image_url'] as $f) {
            if (!empty($data[$f]) && is_string($data[$f]) && preg_match('#^https?://#', $data[$f])) {
                $data[$f] = null;
            }
        }
        if (!empty($data['gallery']) && is_array($data['gallery'])) {
            $data['gallery'] = array_values(array_filter(
                $data['gallery'],
                fn ($p) => is_string($p) && !preg_match('#^https?://#', $p)
            ));
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        $tenant = auth()->user()->tenant;

        // Check if invitations microservice is active
        $hasInvitations = $tenant?->microservices()
            ->where('microservices.slug', 'invitations')
            ->wherePivot('is_active', true)
            ->exists() ?? false;

        $actions = [];

        // Statistics button - always visible
        $actions[] = Actions\Action::make('statistics')
            ->label('Statistici')
            ->icon('heroicon-o-chart-bar')
            ->color('info')
            ->url(fn () => EventResource::getUrl('statistics', ['record' => $this->record]));

        if ($hasInvitations) {
            $actions[] = Actions\Action::make('invitations')
                ->label('Creează invitații')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->url(fn () => route('filament.tenant.pages.invitations') . '?event=' . $this->record->id);
        }

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $tenant = auth()->user()?->tenant;
        $lang = $tenant?->locale ?? $tenant?->language ?? 'ro';

        $title = $this->record->getTranslation('title', $lang)
            ?? $this->record->getTranslation('title', 'ro')
            ?? $this->record->getTranslation('title', 'en')
            ?? '';

        $parts = [];
        $city = $this->record->city ?? $this->record->venue?->city ?? null;
        if ($city) {
            $parts[] = $city;
        }
        $dateLabel = method_exists($this->record, 'displayDateLabel') ? $this->record->displayDateLabel() : null;
        if ($dateLabel) {
            $parts[] = $dateLabel;
        }
        if (!empty($parts)) {
            $title .= ' (' . implode(', ', $parts) . ')';
        }

        $css = '<style>.fi-header{flex-direction:column;align-items:start;}</style>';

        return new \Illuminate\Support\HtmlString(e($title ?: 'Editare eveniment') . $css);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
