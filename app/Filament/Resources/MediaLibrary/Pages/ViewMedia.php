<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use App\Models\MediaLibrary;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaLibraryResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->filename ?? 'View Media';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn () => $this->record->url)
                ->openUrlInNewTab(),

            Actions\Action::make('copy_url')
                ->label('Copy URL')
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->action(function () {
                    // This will be handled by JavaScript
                    Notification::make()
                        ->title('URL Copied')
                        ->body('The file URL has been copied to clipboard.')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-on:click' => "navigator.clipboard.writeText('" . ($this->record->url ?? '') . "')",
                ]),

            Actions\EditAction::make()
                ->visible(false), // We use inline editing in the form

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Media')
                ->modalDescription('Are you sure you want to delete this media file? This action cannot be undone.')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->before(function (MediaLibrary $record) {
                    // Optionally delete from disk
                    // $record->deleteWithFile();
                }),
        ];
    }
}
