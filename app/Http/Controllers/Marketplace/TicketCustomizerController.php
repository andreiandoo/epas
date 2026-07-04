<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\TicketTemplate;
use Illuminate\Http\Request;

class TicketCustomizerController extends Controller
{
    /**
     * Show the visual editor for a ticket template (marketplace admin access)
     */
    public function edit(TicketTemplate $template, Request $request)
    {
        $user = auth('marketplace_admin')->user();
        $clientId = $user->marketplace_client_id;

        // Verify the template belongs to this marketplace client
        if ($template->marketplace_client_id !== $clientId) {
            abort(403, 'Access denied. This template does not belong to your marketplace.');
        }

        // Load the template
        $template->load('tenant');

        // Multi-page support: ?page=2 editeaza layer-ele paginii 2 (verso).
        // Pagina 2 e stocata in template_data.page_2 = { meta, assets, layers }.
        // Cand nu exista, initializam cu A4 portrait default (297x210mm).
        $editingPage = (int) $request->query('page', 1);
        if ($editingPage !== 2) $editingPage = 1;

        $fullData = is_array($template->template_data) ? $template->template_data : [];
        if ($editingPage === 2) {
            $initialTemplateData = $fullData['page_2'] ?? [];
            // Defaults A4 portrait cand pagina 2 e goala
            if (empty($initialTemplateData['meta'])) {
                $initialTemplateData = array_merge([
                    'meta' => [
                        'version' => '1.0',
                        'dpi' => $fullData['meta']['dpi'] ?? 300,
                        'size_mm' => ['w' => 210, 'h' => 297],
                        'orientation' => 'portrait',
                        'bleed_mm' => ['top' => 3, 'right' => 3, 'bottom' => 3, 'left' => 3],
                        'safe_area_mm' => 5,
                        'background' => ['color' => '#ffffff', 'image' => ''],
                    ],
                    'assets' => [],
                    'layers' => [],
                    'enabled' => $initialTemplateData['enabled'] ?? true,
                ], $initialTemplateData);
            }
        } else {
            // Pagina 1 primeste toata structura EXCLUSIV cheia page_2 (ca sa nu apara
            // in JSON-ul manipulat de editor). Preservarea page_2 la save se face in update().
            $initialTemplateData = $fullData;
            unset($initialTemplateData['page_2']);
        }

        // Get available variables
        $variableService = app(\App\Services\TicketCustomizer\TicketVariableService::class);
        $variables = $variableService->getAvailableVariables();
        $sampleData = $variableService->getSampleData();

        // Get presets
        $presets = [
            [
                'id' => 'ticket_standard',
                'name' => 'Standard Ticket (80×200mm)',
                'size_mm' => ['w' => 80, 'h' => 200],
                'orientation' => 'portrait',
                'dpi' => 300,
            ],
            [
                'id' => 'ticket_landscape',
                'name' => 'Landscape Ticket (200×80mm)',
                'size_mm' => ['w' => 200, 'h' => 80],
                'orientation' => 'landscape',
                'dpi' => 300,
            ],
            [
                'id' => 'a6_portrait',
                'name' => 'A6 Portrait (105×148mm)',
                'size_mm' => ['w' => 105, 'h' => 148],
                'orientation' => 'portrait',
                'dpi' => 300,
            ],
            [
                'id' => 'a6_landscape',
                'name' => 'A6 Landscape (148×105mm)',
                'size_mm' => ['w' => 148, 'h' => 105],
                'orientation' => 'landscape',
                'dpi' => 300,
            ],
        ];

        return view('admin.ticket-customizer.edit', [
            'template' => $template,
            'variables' => $variables,
            'sampleData' => $sampleData,
            'presets' => $presets,
            // Save endpoint pastreaza query-ul ?page ca sa stie unde sa scrie.
            'saveUrl' => "/marketplace/ticket-customizer/{$template->id}/editor" . ($editingPage === 2 ? '?page=2' : ''),
            'backUrl' => "/marketplace/ticket-templates/{$template->id}/edit",
            // Multi-page (opt-in): 1 sau 2. Editorul foloseste initialTemplateData in loc
            // de template.template_data cand se editeaza o pagina specifica.
            'editingPage' => $editingPage,
            'initialTemplateData' => $initialTemplateData,
        ]);
    }

    /**
     * Save the template data via AJAX (marketplace admin access)
     */
    public function update(Request $request, TicketTemplate $template)
    {
        $user = auth('marketplace_admin')->user();
        $clientId = $user->marketplace_client_id;

        // Verify the template belongs to this marketplace client
        if ($template->marketplace_client_id !== $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. This template does not belong to your marketplace.',
            ], 403);
        }

        $request->validate([
            'template_data' => 'required|array',
        ]);

        // Validate the template structure
        $validator = app(\App\Services\TicketCustomizer\TicketTemplateValidator::class);
        $validation = $validator->validate($request->input('template_data'));

        if (!$validation['ok']) {
            return response()->json([
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 422);
        }

        // Multi-page save: cand ?page=2, salvam JSON-ul primit ca sub-cheia page_2
        // fara sa suprascriem pagina 1. Cand page=1 (default), pastram cheia page_2
        // existenta ca sa nu se piarda din pagina 2 la editarea paginii 1.
        $incomingData = $request->input('template_data');
        $editingPage = (int) $request->query('page', 1);
        if ($editingPage === 2) {
            $existing = is_array($template->template_data) ? $template->template_data : [];
            // Snapshot enabled=true la primul save al paginii 2 daca lipsea.
            if (!isset($incomingData['enabled'])) $incomingData['enabled'] = true;
            $existing['page_2'] = $incomingData;
            $template->update(['template_data' => $existing]);
        } else {
            $existing = is_array($template->template_data) ? $template->template_data : [];
            // Editorul paginii 1 nu primeste page_2 in initialTemplateData -> nu-l trimite
            // inapoi. Il pastram din DB pentru a nu-l pierde.
            if (isset($existing['page_2'])) $incomingData['page_2'] = $existing['page_2'];
            $template->update(['template_data' => $incomingData]);
        }

        // Generate preview
        try {
            $previewGenerator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);
            $previewGenerator->saveTemplatePreview($template);
        } catch (\Exception $e) {
            // Preview generation failed, but template was saved
        }

        return response()->json([
            'success' => true,
            'message' => 'Template saved successfully',
            'template' => $template->fresh(),
            'warnings' => $validation['warnings'],
        ]);
    }
}
