<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceEvent;
use App\Models\Event;
use App\Models\MarketplaceTaxTemplate;
use App\Models\MarketplaceTaxRegistry;
use App\Models\OrganizerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentController extends BaseController
{
    /**
     * Get list of documents for organizer
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
            ->with(['event']);

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        // Filter by document type
        if ($request->has('type')) {
            $query->where('document_type', $request->type);
        }

        $documents = $query->orderBy('issued_at', 'desc')->get();

        // Calculate stats
        $allDocs = OrganizerDocument::where('marketplace_organizer_id', $organizer->id);
        $stats = [
            'total' => $allDocs->count(),
            'cerere_avizare' => OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->where('document_type', 'cerere_avizare')->count(),
            'declaratie_impozite' => OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->where('document_type', 'declaratie_impozite')->count(),
            'this_month' => OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->whereMonth('issued_at', now()->month)
                ->whereYear('issued_at', now()->year)
                ->count(),
        ];

        return $this->success([
            'documents' => $documents->map(function ($doc) {
                // Get localized event title
                $eventTitle = '-';
                if ($doc->event) {
                    $eventTitle = $doc->event->getTranslation('title', 'ro')
                        ?: $doc->event->getTranslation('title', 'en')
                        ?: $doc->event->getTranslation('title')
                        ?: '-';
                }

                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'type' => $doc->document_type,
                    'type_label' => $doc->document_type_label,
                    'event_id' => $doc->event_id,
                    'event_name' => $eventTitle,
                    'issued_at' => $doc->issued_at?->format('Y-m-d H:i'),
                    'file_size' => $doc->formatted_file_size,
                    'download_url' => $doc->download_url,
                ];
            }),
            'stats' => $stats,
        ]);
    }

    /**
     * Get documents for a specific event (for document generation page)
     */
    public function forEvent(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Verify event belongs to organizer (check Event model)
        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Evenimentul nu a fost gasit', 404);
        }

        $documents = OrganizerDocument::where('event_id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->get()
            ->keyBy('document_type');

        // Map Event model status
        $status = $event->is_cancelled ? 'cancelled' :
            ($event->is_published ? 'published' : 'draft');

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $event->event_date?->format('Y-m-d H:i'),
                'venue_name' => $event->venue_name,
                'venue_city' => $event->venue_city,
                'status' => $status,
            ],
            'documents' => [
                'cerere_avizare' => $documents->get('cerere_avizare') ? [
                    'id' => $documents->get('cerere_avizare')->id,
                    'title' => $documents->get('cerere_avizare')->title,
                    'issued_at' => $documents->get('cerere_avizare')->issued_at?->format('Y-m-d H:i'),
                    'download_url' => $documents->get('cerere_avizare')->download_url,
                ] : null,
                'declaratie_impozite' => $documents->get('declaratie_impozite') ? [
                    'id' => $documents->get('declaratie_impozite')->id,
                    'title' => $documents->get('declaratie_impozite')->title,
                    'issued_at' => $documents->get('declaratie_impozite')->issued_at?->format('Y-m-d H:i'),
                    'download_url' => $documents->get('declaratie_impozite')->download_url,
                ] : null,
            ],
        ]);
    }

    /**
     * Generate a document for an event
     */
    public function generate(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'document_type' => 'required|in:cerere_avizare,declaratie_impozite',
        ]);

        // Verify event belongs to organizer (check Event model)
        $event = Event::where('id', $validated['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with(['ticketTypes', 'venue'])
            ->first();

        if (!$event) {
            return $this->error('Evenimentul nu a fost gasit', 404);
        }

        // Check if document already exists
        $existingDoc = OrganizerDocument::where('event_id', $event->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('document_type', $validated['document_type'])
            ->first();

        if ($existingDoc) {
            return $this->success([
                'document' => [
                    'id' => $existingDoc->id,
                    'title' => $existingDoc->title,
                    'download_url' => $existingDoc->download_url,
                ],
                'message' => 'Documentul exista deja',
            ]);
        }

        // Get template for this document type
        $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('type', $validated['document_type'])
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return $this->error('Nu exista template pentru acest tip de document. Contacteaza administratorul.', 404);
        }

        // Get tax registry for the marketplace
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

        // Ensure proper UTF-8 encoding for diacritics
        // Wrap content if it doesn't have a proper HTML structure
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
            // If HTML exists but missing charset, add it after <head>
            if (stripos($htmlContent, 'charset') === false) {
                $htmlContent = preg_replace(
                    '/<head>/i',
                    '<head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>',
                    $htmlContent
                );
            }
            // Add DejaVu Sans font if no font-family specified
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

        // Set orientation based on template settings
        if ($template->page_orientation === 'landscape') {
            $pdf->setPaper('A4', 'landscape');
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        $pdfContent = $pdf->output();

        // Generate unique filename
        $fileName = sprintf(
            '%s_%s_%s_%s.pdf',
            $validated['document_type'],
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

        // Create document record
        $document = OrganizerDocument::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'tax_template_id' => $template->id,
            'title' => $template->name,
            'document_type' => $validated['document_type'],
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => strlen($pdfContent),
            'html_content' => $htmlContent,
            'document_data' => [
                'event_name' => $event->name,
                'event_date' => $event->starts_at?->format('Y-m-d H:i'),
                'organizer_name' => $organizer->company_name ?? $organizer->name,
                'template_name' => $template->name,
                'variables' => $variables,
            ],
            'issued_at' => now(),
        ]);

        return $this->success([
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->document_type,
                'download_url' => $document->download_url,
                'issued_at' => $document->issued_at->format('Y-m-d H:i'),
            ],
            'message' => 'Documentul a fost generat cu succes',
        ]);
    }

    /**
     * Download a document
     */
    public function download(Request $request, int $documentId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $document = OrganizerDocument::where('id', $documentId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$document) {
            return $this->error('Documentul nu a fost gasit', 404);
        }

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return $this->error('Fisierul nu este disponibil', 404);
        }

        return $this->success([
            'url' => $document->download_url,
            'file_name' => $document->file_name,
        ]);
    }

    /**
     * View document (returns HTML content)
     */
    public function view(Request $request, int $documentId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $document = OrganizerDocument::where('id', $documentId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$document) {
            return $this->error('Documentul nu a fost gasit', 404);
        }

        return $this->success([
            'url' => $document->download_url,
            'html_content' => $document->html_content,
        ]);
    }

    /**
     * Get events with document status for generation page
     */
    public function eventsWithDocuments(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Get all events for organizer from events table (Event model)
        $events = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with(['venue', 'marketplaceCity'])
            ->orderBy('event_date', 'desc')
            ->get();

        // Get all documents for these events
        $documents = OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->get()
            ->groupBy('event_id');

        $eventsWithDocs = $events->map(function ($event) use ($documents) {
            $eventDocs = $documents->get($event->id, collect());
            $cerereAvizare = $eventDocs->firstWhere('document_type', 'cerere_avizare');
            $declaratieImpozite = $eventDocs->firstWhere('document_type', 'declaratie_impozite');

            // Map Event model status to document status labels
            $status = $event->is_cancelled ? 'cancelled' :
                ($event->is_published ? 'published' : 'draft');

            // Get localized event title
            $eventTitle = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: $event->getTranslation('title')
                ?: 'Eveniment';

            // Get venue name (may be translatable)
            $venueName = null;
            if ($event->venue) {
                $venueName = $event->venue->getTranslation('name', 'ro')
                    ?? $event->venue->getTranslation('name')
                    ?? $event->venue->name ?? null;
            }

            // Get city from marketplaceCity or venue
            $venueCity = $event->marketplaceCity?->name ?? $event->venue?->city ?? null;

            return [
                'id' => $event->id,
                'name' => $eventTitle,
                'starts_at' => $event->event_date?->format('Y-m-d H:i'),
                'venue_name' => $venueName,
                'venue_city' => $venueCity,
                'status' => $status,
                'status_label' => $this->getStatusLabel($status),
                'cerere_avizare' => $cerereAvizare ? [
                    'id' => $cerereAvizare->id,
                    'title' => $cerereAvizare->title,
                    'issued_at' => $cerereAvizare->issued_at?->format('d.m.Y'),
                    'download_url' => $cerereAvizare->download_url,
                ] : null,
                'declaratie_impozite' => $declaratieImpozite ? [
                    'id' => $declaratieImpozite->id,
                    'title' => $declaratieImpozite->title,
                    'issued_at' => $declaratieImpozite->issued_at?->format('d.m.Y'),
                    'download_url' => $declaratieImpozite->download_url,
                ] : null,
            ];
        })->values();

        return $this->success([
            'events' => $eventsWithDocs,
            'debug' => [
                'organizer_id' => $organizer->id,
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'total_events' => $events->count(),
            ],
        ]);
    }

    /**
     * Regenerate an existing document
     */
    public function regenerate(Request $request, int $documentId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        // Find existing document
        $existingDoc = OrganizerDocument::where('id', $documentId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$existingDoc) {
            return $this->error('Documentul nu a fost gasit', 404);
        }

        // Get the event (check Event model)
        $event = Event::where('id', $existingDoc->event_id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with(['ticketTypes', 'venue'])
            ->first();

        if (!$event) {
            return $this->error('Evenimentul nu a fost gasit', 404);
        }

        // Get template
        $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('type', $existingDoc->document_type)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return $this->error('Nu exista template pentru acest tip de document.', 404);
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
        if ($existingDoc->file_path && Storage::disk('public')->exists($existingDoc->file_path)) {
            Storage::disk('public')->delete($existingDoc->file_path);
        }

        // Generate new filename
        $fileName = sprintf(
            '%s_%s_%s_%s.pdf',
            $existingDoc->document_type,
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
        $existingDoc->update([
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

        return $this->success([
            'document' => [
                'id' => $existingDoc->id,
                'title' => $existingDoc->title,
                'type' => $existingDoc->document_type,
                'download_url' => $existingDoc->download_url,
                'issued_at' => $existingDoc->issued_at->format('Y-m-d H:i'),
            ],
            'message' => 'Documentul a fost regenerat cu succes',
        ]);
    }

    /**
     * Get status label
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'published' => 'Live',
            'pending_review' => 'In asteptare',
            'ended' => 'Incheiat',
            'draft' => 'Ciorna',
            'cancelled' => 'Anulat',
            default => $status,
        };
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
