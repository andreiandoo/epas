<?php

namespace App\Http\Controllers\Api\Shorts;

use App\Http\Controllers\Controller;
use App\Models\Short;
use App\Services\Video\VideoProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Hands out a direct-upload session so the client can push the file straight to
 * the video provider. The file never passes through this server — zero egress
 * and zero CPU spent on video bytes.
 *
 * The short row is created up front (status=draft, ready=false) so the upload
 * has somewhere to land; publishing stays a separate, deliberate action.
 */
class ShortUploadController extends Controller
{
    public function __construct(private readonly VideoProvider $provider) {}

    /**
     * POST tenant/shorts/upload-url
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer'],
            'owner_type' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer'],
        ]);

        if (! $this->provider->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Video provider is not configured on this environment.',
            ], 503);
        }

        try {
            $session = $this->provider->createDirectUpload([
                'title' => $validated['title'] ?? 'short',
            ]);
        } catch (\Throwable $e) {
            Log::error('Shorts: direct upload session failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not start the upload session.',
            ], 502);
        }

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'video_provider' => $this->provider->name(),
            'provider_asset_id' => $session['asset_id'],
            'title' => $validated['title'] ?? null,
            'event_id' => $validated['event_id'] ?? null,
            'owner_type' => $validated['owner_type'] ?? null,
            'owner_id' => $validated['owner_id'] ?? null,
            'tenant_id' => $request->user()?->tenant_id,
            'status' => Short::STATUS_DRAFT,
            'ready' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['short_id' => $short->id] + $session,
        ], 201);
    }
}
