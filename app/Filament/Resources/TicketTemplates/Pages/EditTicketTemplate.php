<?php

namespace App\Filament\Resources\TicketTemplates\Pages;

use App\Filament\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicketTemplate extends EditRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_visual_editor')
                ->label('Open Visual Editor')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => "/ticket-customizer/{$this->record->id}")
                ->openUrlInNewTab()
                ->color('primary'),

            Actions\Action::make('generate_preview')
                ->label('Generate Preview')
                ->icon('heroicon-o-photo')
                ->action(function () {
                    $previewGenerator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);
                    $previewGenerator->saveTemplatePreview($this->record);
                    $this->record->refresh();
                    $this->notify('success', 'Preview generated successfully');
                })
                ->color('success'),

            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->action(function () {
                    $this->record->setAsDefault();
                    $this->record->refresh();
                    $this->notify('success', 'Template set as default');
                })
                ->visible(fn () => !$this->record->is_default && $this->record->status === 'active')
                ->requiresConfirmation()
                ->color('warning'),

            Actions\Action::make('create_version')
                ->label('Create Version')
                ->icon('heroicon-o-document-duplicate')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Version Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., "V2 - Updated Logo"'),
                ])
                ->action(function (array $data) {
                    $newVersion = $this->record->createVersion($this->record->template_data, $data['name']);
                    $this->notify('success', 'Version created successfully');
                    return redirect($this->getResource()::getUrl('edit', ['record' => $newVersion]));
                })
                ->color('success'),

            Actions\DeleteAction::make(),
        ];
    }
}
