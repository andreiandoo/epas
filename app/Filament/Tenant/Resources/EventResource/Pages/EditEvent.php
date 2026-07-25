<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redirect hosted events to view-guest page (can't edit events you don't own)
        $tenant = auth()->user()?->tenant;
        if ($this->record->tenant_id !== $tenant?->id) {
            redirect(EventResource::getUrl('view-guest', ['record' => $this->record]));
        }
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
            ->label('Statistics')
            ->icon('heroicon-o-chart-bar')
            ->color('info')
            ->url(fn () => EventResource::getUrl('statistics', ['record' => $this->record]));

        if ($hasInvitations) {
            $actions[] = Actions\Action::make('invitations')
                ->label('Create Invitations')
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
