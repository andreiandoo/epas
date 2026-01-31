<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketTemplate;
use Illuminate\Http\Request;

class TicketCustomizerController extends Controller
{
    /**
     * Show the visual editor for a ticket template
     */
    public function edit(TicketTemplate $template)
    {
        // Load the template with tenant relation
        $template->load('tenant');

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
        ]);
    }

    /**
     * Save the template data via AJAX
     */
    public function update(Request $request, TicketTemplate $template)
    {
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

        $template->update([
            'template_data' => $request->input('template_data'),
        ]);

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
