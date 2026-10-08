<?php

namespace App\Filament\Tenant\Resources\BlogArticleResource\Pages;

use App\Filament\Tenant\Resources\BlogArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditBlogArticle extends EditRecord
{
    protected static string $resource = BlogArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('publish')
                ->label(__('Publish'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'published')
                ->requiresConfirmation()
                ->modalHeading(__('Publish Article'))
                ->modalDescription(__('Are you sure you want to publish this article? It will become visible to all visitors.'))
                ->action(function () {
                    $this->record->publish();
                    Notification::make()
                        ->success()
                        ->title(__('Article Published'))
                        ->body(__('The article is now live.'))
                        ->send();
                }),

            Actions\Action::make('unpublish')
                ->label(__('Unpublish'))
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'published')
                ->requiresConfirmation()
                ->modalHeading(__('Unpublish Article'))
                ->modalDescription(__('Are you sure you want to unpublish this article? It will no longer be visible to visitors.'))
                ->action(function () {
                    $this->record->unpublish();
                    Notification::make()
                        ->success()
                        ->title(__('Article Unpublished'))
                        ->body(__('The article has been moved to draft.'))
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Auto-calculate reading time if not set
        if (empty($data['reading_time_minutes'])) {
            $tenant = auth()->user()->tenant;
            $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
            $content = $data['content'][$tenantLanguage] ?? '';
            $wordCount = str_word_count(strip_tags($content));
            $data['reading_time_minutes'] = max(1, (int) ceil($wordCount / 200));
            $data['word_count'] = $wordCount;
        }

        return $data;
    }
}
