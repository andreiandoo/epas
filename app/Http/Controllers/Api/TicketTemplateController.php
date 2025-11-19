<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketVariableService;
use App\Services\TicketCustomizer\TicketTemplateValidator;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketTemplateController extends Controller
{
    public function __construct(
        private TicketVariableService $variableService,
        private TicketTemplateValidator $templateValidator,
        private TicketPreviewGenerator $previewGenerator
    ) {}

    /**
     * Get available variables for templates
     *
     * GET /api/tickets/templates/variables?tenant={id}
     */
    public function getVariables(Request $request)
    {
        $tenantId = $request->query('tenant');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID is required'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $variables = $this->variableService->getAvailableVariables();

        return response()->json([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'variables' => $variables,
            'sample_data' => $this->variableService->getSampleData(),
        ]);
    }

    /**
     * Validate template JSON
     *
     * POST /api/tickets/templates/validate
     * Body: { template_json: {...} }
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_json' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()->all(),
                'warnings' => [],
            ], 422);
        }

        $templateData = $request->input('template_json');

        $result = $this->templateValidator->validate($templateData);

        return response()->json($result);
    }

    /**
     * Generate preview image
     *
     * POST /api/tickets/templates/preview
     * Body: { template_json: {...}, sample_data?: {...}, scale?: 2 }
     */
    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_json' => 'required|array',
            'sample_data' => 'nullable|array',
            'scale' => 'nullable|integer|min:1|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $templateData = $request->input('template_json');
        $sampleData = $request->input('sample_data');
        $scale = $request->input('scale', 2);

        try {
            $preview = $this->previewGenerator->generatePreview($templateData, $sampleData, $scale);

            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Preview generation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List templates for a tenant
     *
     * GET /api/tickets/templates?tenant={id}&status={status}
     */
    public function index(Request $request)
    {
        $tenantId = $request->query('tenant');
        $status = $request->query('status');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID is required'], 400);
        }

        $query = TicketTemplate::where('tenant_id', $tenantId);

        if ($status) {
            $query->where('status', $status);
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'tenant_id' => $tenantId,
            'templates' => $templates,
        ]);
    }

    /**
     * Get a specific template
     *
     * GET /api/tickets/templates/{id}
     */
    public function show(string $id)
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        return response()->json([
            'template' => $template,
        ]);
    }

    /**
     * Create a new template
     *
     * POST /api/tickets/templates
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_data' => 'required|array',
            'status' => 'nullable|in:draft,active,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        // Validate template structure
        $templateData = $request->input('template_data');
        $validation = $this->templateValidator->validate($templateData);

        if (!$validation['ok']) {
            return response()->json([
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 422);
        }

        $template = TicketTemplate::create([
            'tenant_id' => $request->input('tenant_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'draft'),
            'template_data' => $templateData,
        ]);

        // Generate preview
        try {
            $this->previewGenerator->saveTemplatePreview($template);
        } catch (\Exception $e) {
            // Preview generation failed, but template was saved
        }

        return response()->json([
            'success' => true,
            'template' => $template->fresh(),
            'warnings' => $validation['warnings'],
        ], 201);
    }

    /**
     * Update a template
     *
     * PUT /api/tickets/templates/{id}
     */
    public function update(Request $request, string $id)
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'template_data' => 'nullable|array',
            'status' => 'nullable|in:draft,active,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }

        if ($request->has('description')) {
            $updateData['description'] = $request->input('description');
        }

        if ($request->has('status')) {
            $updateData['status'] = $request->input('status');
        }

        if ($request->has('template_data')) {
            $templateData = $request->input('template_data');

            // Validate structure
            $validation = $this->templateValidator->validate($templateData);

            if (!$validation['ok']) {
                return response()->json([
                    'success' => false,
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                ], 422);
            }

            $updateData['template_data'] = $templateData;
        }

        $template->update($updateData);

        // Regenerate preview if template data changed
        if (isset($updateData['template_data'])) {
            try {
                $this->previewGenerator->saveTemplatePreview($template);
            } catch (\Exception $e) {
                // Preview generation failed, but template was updated
            }
        }

        return response()->json([
            'success' => true,
            'template' => $template->fresh(),
        ]);
    }

    /**
     * Delete a template
     *
     * DELETE /api/tickets/templates/{id}
     */
    public function destroy(string $id)
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Soft delete
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Set template as default
     *
     * POST /api/tickets/templates/{id}/set-default
     */
    public function setDefault(string $id)
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        $template->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Template set as default',
            'template' => $template->fresh(),
        ]);
    }

    /**
     * Create a new version of a template
     *
     * POST /api/tickets/templates/{id}/create-version
     */
    public function createVersion(Request $request, string $id)
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'template_data' => 'required|array',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $templateData = $request->input('template_data');
        $name = $request->input('name');

        // Validate structure
        $validation = $this->templateValidator->validate($templateData);

        if (!$validation['ok']) {
            return response()->json([
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 422);
        }

        $newVersion = $template->createVersion($templateData, $name);

        // Generate preview
        try {
            $this->previewGenerator->saveTemplatePreview($newVersion);
        } catch (\Exception $e) {
            // Preview generation failed, but version was created
        }

        return response()->json([
            'success' => true,
            'template' => $newVersion,
            'warnings' => $validation['warnings'],
        ], 201);
    }

    /**
     * Get preset dimensions
     *
     * GET /api/tickets/templates/presets
     */
    public function getPresets()
    {
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
            [
                'id' => 'a4_portrait',
                'name' => 'A4 Portrait (210×297mm)',
                'size_mm' => ['w' => 210, 'h' => 297],
                'orientation' => 'portrait',
                'dpi' => 300,
            ],
            [
                'id' => 'a4_landscape',
                'name' => 'A4 Landscape (297×210mm)',
                'size_mm' => ['w' => 297, 'h' => 210],
                'orientation' => 'landscape',
                'dpi' => 300,
            ],
        ];

        return response()->json([
            'presets' => $presets,
        ]);
    }
}
