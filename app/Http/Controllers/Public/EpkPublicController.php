<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistEpkRiderLead;
use App\Models\ArtistEpkVariant;
use App\Models\MarketplaceArtistAccountMicroservice;
use App\Services\ExtendedArtist\EpkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public-facing Smart EPK pages.
 *
 *  GET /epk/{artist_slug}                  -> active variant
 *  GET /epk/{artist_slug}/{variant_slug}   -> specific variant
 *  POST /epk/rider-request                  -> lead capture form (JSON)
 *  GET  /epk/rider-download                 -> signed URL stream PDF
 *
 * Returnează 404 când:
 *  - artist slug inexistent
 *  - artistul nu are EPK creat (active_variant_id null)
 *  - variant slug specific dat dar inexistent
 *  - artistul nu are abonament Extended Artist activ (admin override / trial / paid)
 */
class EpkPublicController extends Controller
{
    public function __construct(private readonly EpkService $epkService)
    {
    }

    public function show(Request $request, string $artistSlug, ?string $variantSlug = null)
    {
        $artist = Artist::where('slug', $artistSlug)->first();
        if (!$artist || !$artist->epk) {
            abort(404);
        }

        if (!$this->artistHasActiveExtendedArtist($artist)) {
            abort(404);
        }

        $epk = $artist->epk()->with('variants')->first();

        $variant = $variantSlug
            ? $epk->variants->firstWhere('slug', $variantSlug)
            : $epk->variants->firstWhere('id', $epk->active_variant_id);

        if (!$variant) {
            abort(404);
        }

        $payload = $this->epkService->buildPublicPayload($variant);
        $payload['marketplace_name'] = $request->attributes->get('marketplace_client')?->name ?? 'EventPilot';

        return Cache::remember(
            $this->epkService->publicCacheKey($variant->id),
            EpkService::PUBLIC_CACHE_TTL,
            fn () => view('public.epk.show', $payload)->render()
        );
    }

    public function riderRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:artist_epk_variants,id',
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:200',
            'company' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:50',
        ]);

        $variant = ArtistEpkVariant::find($validated['variant_id']);
        if (!$variant) {
            return response()->json(['success' => false, 'message' => 'Variant not found'], 404);
        }

        $rider = $variant->getSection(ArtistEpkVariant::SECTION_RIDER);
        if (!$rider || empty($rider['enabled']) || empty($rider['data']['rider_pdf_path'] ?? null)) {
            return response()->json(['success' => false, 'message' => 'Rider PDF not available'], 404);
        }

        $lead = ArtistEpkRiderLead::create([
            'artist_epk_variant_id' => $variant->id,
            'name' => $validated['name'],
            'company' => $validated['company'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'downloaded_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'public.epk.rider.download',
            now()->addMinutes(5),
            ['lead' => $lead->id]
        );

        return response()->json([
            'success' => true,
            'data' => ['download_url' => $signedUrl],
        ]);
    }

    public function riderDownload(Request $request, int $lead): StreamedResponse|Response
    {
        if (!$request->hasValidSignature()) {
            abort(410, 'Link expired');
        }

        $leadModel = ArtistEpkRiderLead::with('artistEpkVariant')->find($lead);
        if (!$leadModel) {
            abort(404);
        }

        $variant = $leadModel->artistEpkVariant;
        $rider = $variant?->getSection(ArtistEpkVariant::SECTION_RIDER);
        $path = $rider['data']['rider_pdf_path'] ?? null;

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->download($path, "rider-{$variant->slug}.pdf");
    }

    /**
     * Verifică că artistul are un cont MarketplaceArtistAccount cu abonament
     * Extended Artist activ. Dacă există măcar un cont activ pentru artist,
     * EPK-ul e public.
     */
    protected function artistHasActiveExtendedArtist(Artist $artist): bool
    {
        return MarketplaceArtistAccountMicroservice::query()
            ->whereHas('artistAccount', fn ($q) => $q->where('artist_id', $artist->id))
            ->whereHas('microservice', fn ($q) => $q->where('slug', 'extended-artist'))
            ->whereIn('status', [
                MarketplaceArtistAccountMicroservice::STATUS_ACTIVE,
                MarketplaceArtistAccountMicroservice::STATUS_TRIAL,
                MarketplaceArtistAccountMicroservice::STATUS_CANCELLED,
            ])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
