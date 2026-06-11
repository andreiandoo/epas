<?php

namespace App\Filament\Resources\WebTemplates\Pages;

use App\Filament\Resources\WebTemplates\WebTemplateCustomizationResource;
use Filament\Resources\Pages\EditRecord;

class EditWebTemplateCustomization extends EditRecord
{
    protected static string $resource = WebTemplateCustomizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('openPreview')
                ->label('Deschide Preview')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => route('web-template.customized-preview', [
                    'templateSlug' => $this->record->template->slug,
                    'token' => $this->record->unique_token,
                ]))
                ->openUrlInNewTab(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
