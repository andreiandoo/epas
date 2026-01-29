<?php

namespace App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use App\Models\MarketplaceTaxTemplate;
use App\Models\MarketplaceTaxRegistry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ViewOrganizerDocument extends ViewRecord
{
    protected static string $resource = OrganizerDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => $this->record->download_url)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->file_path),

            Actions\Action::make('regenerate')
                ->label('Regenerate')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Document')
                ->modalDescription('This will regenerate the document with current data and replace the existing file. Are you sure?')
                ->modalSubmitActionLabel('Yes, Regenerate')
                ->action(function () {
                    $document = $this->record;
                    $organizer = $document->organizer;
                    $marketplace = $document->marketplaceClient;
                    $event = $document->event;

                    if (!$event) {
                        Notification::make()
                            ->title('Error')
                            ->body('Event not found')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Load event relationships
                    $event->load(['ticketTypes', 'venue']);

                    // Get template
                    $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                        ->where('type', $document->document_type)
                        ->where('is_active', true)
                        ->first();

                    if (!$template) {
                        Notification::make()
                            ->title('Error')
                            ->body('No active template found for this document type')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Get tax registry
                    $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                        ->where('is_active', true)
                        ->first();

                    // Get template variables
                    $variables = MarketplaceTaxTemplate::getVariablesForContext(
                        $taxRegistry,
                        $marketplace,
                        $organizer,
                        $event
                    );

                    // Process template
                    $htmlContent = $template->processTemplate($variables);

                    // Ensure proper UTF-8 encoding
                    if (stripos($htmlContent, '<html') === false) {
                        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
    </style>
</head>
<body>' . $htmlContent . '</body>
</html>';
                    } else {
                        if (stripos($htmlContent, 'charset') === false) {
                            $htmlContent = preg_replace(
                                '/<head>/i',
                                '<head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>',
                                $htmlContent
                            );
                        }
                        if (stripos($htmlContent, 'font-family') === false) {
                            $htmlContent = preg_replace(
                                '/<\/head>/i',
                                '<style>body { font-family: DejaVu Sans, sans-serif; }</style></head>',
                                $htmlContent
                            );
                        }
                    }

                    // Generate PDF
                    $pdf = Pdf::loadHTML($htmlContent);
                    if ($template->page_orientation === 'landscape') {
                        $pdf->setPaper('A4', 'landscape');
                    } else {
                        $pdf->setPaper('A4', 'portrait');
                    }
                    $pdfContent = $pdf->output();

                    // Delete old file if exists
                    if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                        Storage::disk('public')->delete($document->file_path);
                    }

                    // Generate new filename
                    $fileName = sprintf(
                        '%s_%s_%s_%s.pdf',
                        $document->document_type,
                        $organizer->id,
                        $event->id,
                        now()->format('YmdHis')
                    );
                    $filePath = sprintf(
                        'organizer-documents/%d/%s',
                        $organizer->id,
                        $fileName
                    );

                    // Save to storage
                    Storage::disk('public')->put($filePath, $pdfContent);

                    // Update document record
                    $document->update([
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_size' => strlen($pdfContent),
                        'html_content' => $htmlContent,
                        'tax_template_id' => $template->id,
                        'title' => $template->name,
                        'document_data' => [
                            'event_name' => $event->name,
                            'event_date' => $event->starts_at?->format('Y-m-d H:i'),
                            'organizer_name' => $organizer->company_name ?? $organizer->name,
                            'template_name' => $template->name,
                            'variables' => $variables,
                        ],
                        'issued_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Success')
                        ->body('Document has been regenerated successfully')
                        ->success()
                        ->send();

                    // Redirect to refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $document]));
                }),
        ];
    }
}
