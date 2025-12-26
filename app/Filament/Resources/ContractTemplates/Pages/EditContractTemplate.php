<?php

namespace App\Filament\Resources\ContractTemplates\Pages;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditContractTemplate extends EditRecord
{
    protected static string $resource = ContractTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview PDF')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => route('admin.contract-template.preview', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function () {
                    $newTemplate = $this->record->replicate();
                    $newTemplate->name = $this->record->name . ' (Copy)';
                    $newTemplate->slug = $this->record->slug . '-copy-' . time();
                    $newTemplate->is_default = false;
                    $newTemplate->save();

                    Notification::make()
                        ->title('Template duplicated')
                        ->success()
                        ->send();

                    return redirect(ContractTemplateResource::getUrl('edit', ['record' => $newTemplate]));
                }),

            Actions\DeleteAction::make()
                ->label('Delete Template')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
