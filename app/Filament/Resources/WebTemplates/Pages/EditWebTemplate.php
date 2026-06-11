<?php

namespace App\Filament\Resources\WebTemplates\Pages;

use App\Filament\Resources\WebTemplates\WebTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditWebTemplate extends EditRecord
{
    protected static string $resource = WebTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('previewDemo')
                ->label('Preview Demo')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => route('web-template.preview', [
                    'templateSlug' => $this->record->slug,
                ]))
                ->openUrlInNewTab(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
