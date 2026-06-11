<?php

namespace App\Filament\Marketplace\Resources\BlogArticleResource\Pages;

use App\Filament\Marketplace\Resources\BlogArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class EditBlogArticle extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = BlogArticleResource::class;

    /** Public frontend URL of the guide on the marketplace domain, or null. */
    protected function frontendUrl(): ?string
    {
        $slug = $this->record->slug ?? null;
        if (! $slug) {
            return null;
        }

        $domain = static::getMarketplaceClient()?->domain;
        $domain = preg_replace('#^https?://#i', '', trim((string) $domain));
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            return null;
        }

        return 'https://' . $domain . '/ghiduri/' . $slug;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_frontend')
                ->label('Vizualizează')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->frontendUrl(), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->status === 'published' && filled($this->frontendUrl())),

            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'published')
                ->requiresConfirmation()
                ->modalHeading('Publish Article')
                ->modalDescription('Are you sure you want to publish this article? It will become visible to all visitors.')
                ->action(function () {
                    $this->record->publish();
                    Notification::make()
                        ->success()
                        ->title('Article Published')
                        ->body('The article is now live.')
                        ->send();
                }),

            Actions\Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'published')
                ->requiresConfirmation()
                ->modalHeading('Unpublish Article')
                ->modalDescription('Are you sure you want to unpublish this article? It will no longer be visible to visitors.')
                ->action(function () {
                    $this->record->unpublish();
                    Notification::make()
                        ->success()
                        ->title('Article Unpublished')
                        ->body('The article has been moved to draft.')
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
            $marketplace = static::getMarketplaceClient();
            $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';
            $content = $data['content'][$marketplaceLanguage] ?? '';
            $wordCount = str_word_count(strip_tags($content));
            $data['reading_time_minutes'] = max(1, (int) ceil($wordCount / 200));
            $data['word_count'] = $wordCount;
        }

        return $data;
    }
}
