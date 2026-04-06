<?php

namespace App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use App\Models\MarketplaceTaxTemplate;
use App\Models\OrganizerDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Illuminate\Support\Facades\Storage;

class ListOrganizerDocuments extends ListRecords
{
    protected static string $resource = OrganizerDocumentResource::class;

    protected function getHeaderActions(): array
    {
        $marketplace = OrganizerDocumentResource::getMarketplaceClient();

        return [
            Actions\Action::make('create_document')
                ->label('Adaugă document')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('tax_template_id')
                        ->label('Tip document (template)')
                        ->options(function () use ($marketplace) {
                            return MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace?->id)
                                ->where('is_active', true)
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set) {
                            $set('organizer_id', null);
                            $set('event_ids', []);
                        }),

                    Forms\Components\Select::make('organizer_id')
                        ->label('Organizator')
                        ->options(function () use ($marketplace) {
                            return MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                ->orderBy('company_name')
                                ->get()
                                ->mapWithKeys(fn ($org) => [
                                    $org->id => ($org->company_name ?: $org->name) . ' (' . ($org->company_tax_id ?: 'N/A') . ')',
                                ]);
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set) {
                            $set('event_ids', []);
                        }),

                    Forms\Components\Select::make('event_ids')
                        ->label('Evenimente')
                        ->multiple()
                        ->options(function (SGet $get) {
                            $organizerId = $get('organizer_id');
                            if (!$organizerId) {
                                return [];
                            }

                            return Event::where('marketplace_organizer_id', $organizerId)
                                ->where('is_published', true)
                                ->where(function ($q) {
                                    $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
                                })
                                ->orderBy('event_date', 'desc')
                                ->get()
                                ->mapWithKeys(function ($event) {
                                    $title = is_array($event->title)
                                        ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: 'N/A')
                                        : ($event->title ?? 'N/A');
                                    $date = $event->event_date ? $event->event_date->format('d.m.Y') : '';
                                    return [$event->id => $title . ($date ? " ({$date})" : '')];
                                });
                        })
                        ->searchable()
                        ->required()
                        ->visible(fn (SGet $get) => $get('organizer_id'))
                        ->helperText('Selectează unul sau mai multe evenimente'),
                ])
                ->modalHeading('Generare document nou')
                ->modalSubmitActionLabel('Generează')
                ->modalWidth('lg')
                ->action(function (array $data) use ($marketplace) {
                    $template = MarketplaceTaxTemplate::find($data['tax_template_id']);
                    if (!$template) {
                        Notification::make()->title('Eroare')->body('Template-ul nu a fost găsit.')->danger()->send();
                        return;
                    }

                    $organizer = MarketplaceOrganizer::find($data['organizer_id']);
                    if (!$organizer) {
                        Notification::make()->title('Eroare')->body('Organizatorul nu a fost găsit.')->danger()->send();
                        return;
                    }

                    $events = Event::with(['ticketTypes', 'venue'])
                        ->whereIn('id', $data['event_ids'])
                        ->get();

                    if ($events->isEmpty()) {
                        Notification::make()->title('Eroare')->body('Nu s-au găsit evenimentele selectate.')->danger()->send();
                        return;
                    }

                    // Validate: all events belong to same organizer
                    $differentOrganizers = $events->pluck('marketplace_organizer_id')->unique();
                    if ($differentOrganizers->count() > 1 || $differentOrganizers->first() != $data['organizer_id']) {
                        Notification::make()->title('Eroare')->body('Toate evenimentele trebuie să aparțină aceluiași organizator.')->danger()->send();
                        return;
                    }

                    // Validate: all events have same venue/location (if multiple events)
                    if ($events->count() > 1) {
                        $venueNames = $events->map(function ($e) {
                            if ($e->venue) {
                                $name = $e->venue->name;
                                return is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name)) : $name;
                            }
                            return $e->suggested_venue_name ?? $e->venue_name ?? '';
                        })->unique();

                        if ($venueNames->count() > 1) {
                            Notification::make()
                                ->title('Eroare')
                                ->body('Toate evenimentele trebuie să aibă aceeași locație. Locații găsite: ' . $venueNames->implode(', '))
                                ->danger()
                                ->send();
                            return;
                        }
                    }

                    // Get tax registry
                    $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                        ->where('is_active', true)
                        ->first();

                    // Build variables using first event as base
                    $firstEvent = $events->first();
                    $variables = MarketplaceTaxTemplate::getVariablesForContext(
                        $taxRegistry,
                        $marketplace,
                        $organizer,
                        $firstEvent,
                        null,
                        $template->type === 'organizer_contract',
                    );

                    // For multi-event: override event_date and ticket type variables
                    if ($events->count() > 1) {
                        // Aggregate event dates
                        $eventDates = $events->map(function ($event) {
                            if ($event->duration_mode === 'range') {
                                $start = $event->range_start_date ? $event->range_start_date->format('d.m.Y') : '';
                                $end = $event->range_end_date ? $event->range_end_date->format('d.m.Y') : '';
                                return $start && $end ? "{$start} - {$end}" : ($start ?: $end);
                            }
                            return $event->start_date
                                ? $event->start_date->format('d.m.Y H:i')
                                : ($event->event_date ? $event->event_date->format('d.m.Y') : '');
                        })->filter()->unique()->values();
                        $variables['event_date'] = $eventDates->implode(', ');

                        // Aggregate event names
                        $eventNames = $events->map(function ($event) {
                            $title = $event->title ?? $event->name ?? '';
                            if (is_array($title)) {
                                return $title['ro'] ?? $title['en'] ?? reset($title) ?: '';
                            }
                            return $title;
                        })->filter()->values();
                        $variables['event_name'] = $eventNames->implode(', ');

                        // Rebuild ticket type variables from ALL events
                        $this->aggregateTicketTypeVariables($variables, $events);
                    }

                    // Process template
                    $htmlContent = $template->processTemplate($variables);

                    // Handle page 2 if exists
                    $htmlContentPage2 = null;
                    if ($template->html_content_page_2) {
                        $tmpTemplate = new MarketplaceTaxTemplate(['html_content' => $template->html_content_page_2]);
                        $htmlContentPage2 = $tmpTemplate->processTemplate($variables);
                    }

                    // Ensure proper UTF-8 encoding
                    $htmlContent = $this->ensureUtf8Html($htmlContent);
                    if ($htmlContentPage2) {
                        $htmlContentPage2 = $this->ensureUtf8Html($htmlContentPage2);
                    }

                    // Generate PDF
                    $finalHtml = $htmlContentPage2
                        ? $htmlContent . '<div style="page-break-after: always;"></div>' . $htmlContentPage2
                        : $htmlContent;

                    $pdf = Pdf::loadHTML($finalHtml);
                    $orientation = $template->page_orientation === 'landscape' ? 'landscape' : 'portrait';
                    $pdf->setPaper('A4', $orientation);
                    $pdfContent = $pdf->output();

                    // Build filename
                    $eventName = is_array($firstEvent->title)
                        ? ($firstEvent->title['ro'] ?? $firstEvent->title['en'] ?? reset($firstEvent->title) ?: 'event')
                        : ($firstEvent->title ?? 'event');
                    $eventName = mb_substr(preg_replace('/[^\w\s\-]/u', '', $eventName), 0, 50);

                    $fileName = sprintf(
                        '%s_%s_%s_%s.pdf',
                        $template->type,
                        $organizer->id,
                        $firstEvent->id,
                        now()->format('YmdHis')
                    );
                    $filePath = sprintf('organizer-documents/%d/%s', $organizer->id, $fileName);

                    // Save PDF
                    Storage::disk('public')->put($filePath, $pdfContent);

                    // Create document record
                    $document = OrganizerDocument::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $organizer->id,
                        'event_id' => $firstEvent->id,
                        'tax_template_id' => $template->id,
                        'title' => $template->name . ' - ' . $eventName,
                        'document_type' => $template->type,
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_size' => strlen($pdfContent),
                        'html_content' => $finalHtml,
                        'document_data' => [
                            'event_ids' => $events->pluck('id')->toArray(),
                            'event_names' => $events->map(fn ($e) => is_array($e->title) ? ($e->title['ro'] ?? '') : ($e->title ?? ''))->toArray(),
                            'event_name' => $variables['event_name'] ?? '',
                            'event_date' => $variables['event_date'] ?? '',
                            'organizer_name' => $organizer->company_name ?? $organizer->name,
                            'template_name' => $template->name,
                            'variables' => $variables,
                        ],
                        'issued_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Document generat')
                        ->body("Documentul \"{$template->name}\" a fost generat cu succes.")
                        ->success()
                        ->send();

                    $this->redirect(OrganizerDocumentResource::getUrl('view', ['record' => $document]));
                }),
        ];
    }

    /**
     * Aggregate ticket type variables from multiple events
     */
    protected function aggregateTicketTypeVariables(array &$variables, $events): void
    {
        $totalAvailable = 0;
        $totalSold = 0;
        $totalSalesValue = 0;
        $totalForSale = 0;
        $totalValueForSale = 0;
        $currency = 'RON';

        $ticketTypesHtml = '<table style="width:100%; border-collapse: collapse;">';
        $ticketTypesHtml .= '<thead><tr>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Ticket Type</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Price</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Available</th>';
        $ticketTypesHtml .= '<th style="border:1px solid #ddd; padding:8px; text-align:right;">Sold</th>';
        $ticketTypesHtml .= '</tr></thead><tbody>';

        $ticketSeriesList = [];
        $ticketRowsHtml = '';

        foreach ($events as $event) {
            if (!$event->ticketTypes) {
                continue;
            }

            foreach ($event->ticketTypes as $ticketType) {
                // Skip non-declarable ticket types
                if (isset($ticketType->is_declarable) && $ticketType->is_declarable === false) {
                    continue;
                }
                $available = (int) ($ticketType->quota_total ?? $ticketType->capacity ?? 0);
                $sold = (int) ($ticketType->quota_sold ?? 0);
                $price = (float) ($ticketType->display_price ?? $ticketType->price ?? 0);
                $currency = $ticketType->currency ?? 'RON';
                $seriesStart = $ticketType->series_start ?? '';
                $seriesEnd = $ticketType->series_end ?? '';
                $ticketName = htmlspecialchars($ticketType->name ?? '');

                $totalAvailable += $available;
                $totalSold += $sold;
                $totalSalesValue += ($sold * $price);
                $totalForSale += $available;
                $totalValueForSale += ($available * $price);

                $ticketTypesHtml .= '<tr>';
                $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px;">' . $ticketName . '</td>';
                $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . number_format($price, 2) . ' ' . $currency . '</td>';
                $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $available . '</td>';
                $ticketTypesHtml .= '<td style="border:1px solid #ddd; padding:8px; text-align:right;">' . $sold . '</td>';
                $ticketTypesHtml .= '</tr>';

                if ($seriesStart || $seriesEnd) {
                    $ticketSeriesList[] = $ticketName . ': ' . $seriesStart . ' - ' . $seriesEnd;
                }

                $seriesDisplay = ($seriesStart || $seriesEnd) ? $seriesStart . ' - ' . $seriesEnd : '-';
                $ticketRowsHtml .= '<tr>';
                $ticketRowsHtml .= '<td class="left-align">' . $ticketName . '</td>';
                $ticketRowsHtml .= '<td>' . $available . '</td>';
                $ticketRowsHtml .= '<td>' . number_format($price, 2) . '</td>';
                $ticketRowsHtml .= '<td>' . number_format($available * $price, 2) . '</td>';
                $ticketRowsHtml .= '<td><span class="underline-blue">' . $seriesDisplay . '</span></td>';
                $ticketRowsHtml .= '</tr>';
            }
        }

        $ticketTypesHtml .= '</tbody></table>';

        $totalRowHtml = '<tr class="total-row">';
        $totalRowHtml .= '<td><span class="bold">TOTAL</span></td>';
        $totalRowHtml .= '<td>' . $totalForSale . '</td>';
        $totalRowHtml .= '<td>X</td>';
        $totalRowHtml .= '<td>' . number_format($totalValueForSale, 2) . '</td>';
        $totalRowHtml .= '<td>X</td>';
        $totalRowHtml .= '</tr>';

        $variables['ticket_types_table'] = $ticketTypesHtml;
        $variables['ticket_types_series'] = implode("\n", $ticketSeriesList);
        $variables['ticket_types_rows'] = $ticketRowsHtml;
        $variables['ticket_types_total_row'] = $totalRowHtml;
        $variables['total_tickets_for_sale'] = $totalForSale;
        $variables['total_value_for_sale'] = number_format($totalValueForSale, 2);
        $variables['total_tickets_available'] = $totalAvailable;
        $variables['total_tickets_sold'] = $totalSold;
        $variables['total_sales_value'] = number_format($totalSalesValue, 2);
        $variables['total_sales_currency'] = $currency;
    }

    /**
     * Ensure HTML has proper UTF-8 encoding and DejaVu Sans font
     */
    protected function ensureUtf8Html(string $htmlContent): string
    {
        // Decode HTML entities to UTF-8 characters for DomPDF compatibility
        $htmlContent = html_entity_decode($htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace comma-below variants with cedilla variants for DomPDF
        $htmlContent = str_replace(
            ['ș', 'Ș', 'ț', 'Ț'],
            ['ş', 'Ş', 'ţ', 'Ţ'],
            $htmlContent
        );

        // Strip CSS properties not supported by DomPDF
        $htmlContent = preg_replace('/writing-mode\s*:\s*[^;"]+;?/', '', $htmlContent);
        $htmlContent = preg_replace('/transform\s*:\s*[^;"]+;?/', '', $htmlContent);

        if (stripos($htmlContent, '<html') === false) {
            $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        *, th, td, thead th, thead td { font-family: DejaVu Sans, sans-serif !important; }
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
                    '<style>*, th, td, thead th, thead td { font-family: DejaVu Sans, sans-serif !important; }</style></head>',
                    $htmlContent
                );
            }
        }

        return $htmlContent;
    }
}
