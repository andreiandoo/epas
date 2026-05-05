<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\ArtistEpk;
use App\Models\ArtistEpkVariant;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceClient;
use App\Services\ExtendedArtist\EpkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Artist-side EPK editor endpoints.
 *
 * Toate endpoint-urile presupun:
 *   - middleware auth:sanctum injectează $request->user() ca MarketplaceArtistAccount
 *   - middleware extended.artist a verificat că artistul are abonament activ
 *
 * Authorization per-resursă:
 *   - varianta trebuie să aparțină EPK-ului artistului ($account->artist_id)
 */
class EpkController extends BaseController
{
    public function __construct(private readonly EpkService $epkService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $epk = ArtistEpk::getOrCreateForArtist($artist);
        $epk->load('variants', 'activeVariant');

        return $this->success($this->presentEpk($epk, $artist, $this->requireClient($request)));
    }

    public function createVariant(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $epk = ArtistEpk::getOrCreateForArtist($artist);

        if ($epk->variants()->count() >= ArtistEpkVariant::MAX_VARIANTS_PER_EPK) {
            return $this->error('Limita de ' . ArtistEpkVariant::MAX_VARIANTS_PER_EPK . ' variante a fost atinsă.', 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'target' => 'nullable|string|max:100',
            'clone_from_variant_id' => 'nullable|integer|exists:artist_epk_variants,id',
        ]);

        $variant = DB::transaction(function () use ($epk, $artist, $validated) {
            $sections = ArtistEpkVariant::defaultSections($artist);
            $accent = '#A51C30';
            $template = 'modern';

            if (!empty($validated['clone_from_variant_id'])) {
                $source = ArtistEpkVariant::where('artist_epk_id', $epk->id)
                    ->find($validated['clone_from_variant_id']);
                if ($source) {
                    $sections = $source->sections ?? $sections;
                    $accent = $source->accent_color;
                    $template = $source->template;
                }
            }

            return $epk->variants()->create([
                'name' => $validated['name'],
                'target' => $validated['target'] ?? null,
                'slug' => ArtistEpkVariant::uniqueSlugForEpk($epk->id, $validated['name']),
                'accent_color' => $accent,
                'template' => $template,
                'sections' => $sections,
            ]);
        });

        return $this->success($this->presentVariant($variant), 'Variantă creată.', 201);
    }

    public function updateVariant(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'target' => 'sometimes|nullable|string|max:100',
            'slug' => 'sometimes|nullable|string|max:100',
            'accent_color' => 'sometimes|required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'template' => 'sometimes|required|string|max:50',
            'sections' => 'sometimes|required|array',
        ]);

        if (isset($validated['sections'])) {
            $validated['sections'] = $this->sanitizeSections($validated['sections']);
        }

        if (isset($validated['slug']) && $validated['slug'] !== $variant->slug) {
            $validated['slug'] = ArtistEpkVariant::uniqueSlugForEpk(
                $variant->artist_epk_id,
                $validated['slug'],
                $variant->id
            );
        }

        $variant->fill($validated);
        $variant->save();

        $this->epkService->flushVariantCache($variant);
        $this->epkService->flushCacheFor($artist);

        return $this->success($this->presentVariant($variant->fresh()), 'Salvat.');
    }

    public function deleteVariant(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);
        $epk = $variant->artistEpk;

        if ($epk->variants()->count() <= 1) {
            return $this->error('Nu poți șterge ultima variantă.', 422);
        }

        DB::transaction(function () use ($variant, $epk) {
            $wasActive = $epk->active_variant_id === $variant->id;
            $variant->delete();

            if ($wasActive) {
                $next = $epk->variants()->first();
                $epk->update(['active_variant_id' => $next?->id]);
            }
        });

        return $this->success(null, 'Variantă ștearsă.');
    }

    public function activateVariant(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        $variant->artistEpk->update(['active_variant_id' => $variant->id]);
        $this->epkService->flushVariantCache($variant);

        return $this->success($this->presentVariant($variant->fresh()), 'Variantă activată.');
    }

    public function cloneVariant(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $source = $this->findVariantOrFail($id, $artist);
        $epk = $source->artistEpk;

        if ($epk->variants()->count() >= ArtistEpkVariant::MAX_VARIANTS_PER_EPK) {
            return $this->error('Limita de ' . ArtistEpkVariant::MAX_VARIANTS_PER_EPK . ' variante a fost atinsă.', 422);
        }

        $newName = $source->name . ' (copie)';
        $clone = $epk->variants()->create([
            'name' => $newName,
            'target' => $source->target,
            'slug' => ArtistEpkVariant::uniqueSlugForEpk($epk->id, $newName),
            'accent_color' => $source->accent_color,
            'template' => $source->template,
            'sections' => $source->sections,
        ]);

        return $this->success($this->presentVariant($clone), 'Variantă clonată.', 201);
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'type' => 'required|in:hero,gallery,logo',
        ]);

        $directory = "artist-epk/{$variant->id}/{$validated['type']}";
        $path = $request->file('image')->store($directory, 'public');

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'type' => $validated['type'],
        ], 'Imagine încărcată.');
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        $validated = $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $path = $validated['path'];
        // Refuzăm path-uri care ies din directorul variantei (basic path-traversal guard).
        if (!str_starts_with($path, "artist-epk/{$variant->id}/")) {
            return $this->error('Path invalid.', 422);
        }

        Storage::disk('public')->delete($path);

        return $this->success(null, 'Imagine ștearsă.');
    }

    public function uploadRider(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        $validated = $request->validate([
            'rider' => 'required|file|mimes:pdf|max:20480',
        ]);

        $directory = "artist-epk/{$variant->id}/rider";
        $path = $request->file('rider')->store($directory, 'public');

        // Salvăm direct in sections.rider.data.rider_pdf_url
        $rider = $variant->getSection(ArtistEpkVariant::SECTION_RIDER) ?? [
            'id' => ArtistEpkVariant::SECTION_RIDER,
            'enabled' => true,
            'data' => [],
        ];
        $data = $rider['data'] ?? [];
        $data['rider_pdf_url'] = Storage::disk('public')->url($path);
        $data['rider_pdf_path'] = $path;
        $variant->setSection(ArtistEpkVariant::SECTION_RIDER, $data, true);
        $variant->save();
        $this->epkService->flushVariantCache($variant);

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Rider PDF încărcat.');
    }

    /**
     * Redirect către api.qrserver.com pentru generarea QR-ului. Astfel evităm
     * dependența de endroid/qr-code (care necesită extensie GD pe server).
     * Frontend-ul folosește deja URL-ul direct, dar păstrăm endpoint-ul ca
     * safety net pentru link-uri vechi cached în browser.
     */
    public function qr(Request $request, int $id)
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);
        $marketplace = $this->requireClient($request);

        $url = $variant->publicUrl($marketplace);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($url);

        return redirect()->away($qrUrl);
    }

    public function pdf(Request $request, int $id)
    {
        $artist = $this->requireArtist($request);
        $variant = $this->findVariantOrFail($id, $artist);

        try {
            $payload = $this->epkService->buildPublicPayload($variant);
            // Marketplace context pentru template (folosit de "Powered by")
            $payload['marketplace_name'] = 'Tixello';
            $payload['marketplace_url'] = 'https://tixello.com';

            $html = view('public.epk.pdf', $payload)->render();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('A4', 'portrait')
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('chroot', storage_path('app/public'))
                ->setOption('defaultFont', 'DejaVu Sans');

            // Curăță output buffer-ul ca să nu se prependuze warning-uri/notice-uri
            // peste binary-ul PDF (cauzează „nu e PDF" la client).
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filename = "epk-{$artist->slug}-{$variant->slug}.pdf";
            $output = $pdf->output();

            return response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($output),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('EPK PDF generation failed', [
                'variant_id' => $variant->id,
                'artist_id' => $artist->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(8)->map(fn ($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?') . ' ' . ($t['function'] ?? '?'))->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Generarea PDF a eșuat. Admin notificat.',
                'error_id' => uniqid('epk_pdf_'),
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function requireArtist(Request $request): Artist
    {
        $account = $request->user();
        if (!$account instanceof MarketplaceArtistAccount) {
            abort(401, 'Artist account required');
        }
        if (!$account->artist_id) {
            abort(403, 'Profilul tau nu este asociat cu un artist.');
        }
        $artist = Artist::find($account->artist_id);
        if (!$artist) {
            abort(404, 'Artist record not found');
        }
        return $artist;
    }

    protected function findVariantOrFail(int $id, Artist $artist): ArtistEpkVariant
    {
        $variant = ArtistEpkVariant::with('artistEpk')->find($id);
        if (!$variant || $variant->artistEpk?->artist_id !== $artist->id) {
            abort(404, 'Variantă inexistentă');
        }
        return $variant;
    }

    /**
     * Asigură că structura sections e validă: cele 12 id-uri cunoscute,
     * fiecare are `enabled` boolean și `data` array. Drop unknown.
     */
    protected function sanitizeSections(array $sections): array
    {
        $known = ArtistEpkVariant::ALL_SECTIONS;
        $clean = [];
        foreach ($sections as $section) {
            $id = $section['id'] ?? null;
            if (!in_array($id, $known, true)) {
                continue;
            }
            $clean[] = [
                'id' => $id,
                'enabled' => (bool) ($section['enabled'] ?? true),
                'data' => is_array($section['data'] ?? null) ? $section['data'] : [],
            ];
        }
        return $clean;
    }

    protected function presentEpk(ArtistEpk $epk, Artist $artist, MarketplaceClient $marketplace): array
    {
        // Trecem $artist explicit la presentVariant ca să evităm lazy load + ca să
        // nu depindem de relationships (presentVariant e folosit și fără EPK loaded).
        $variants = $epk->variants->map(fn ($v) => $this->presentVariant($v, $artist))->toArray();

        return [
            'id' => $epk->id,
            'artist' => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
            ],
            // artist_profile: oglindă a câmpurilor relevante de pe profil — folosită
            // în editor ca fallback când câmpurile dintr-o secțiune sunt goale.
            'artist_profile' => [
                'main_image_url' => $artist->main_image_full_url,
                'logo_url' => $artist->logo_full_url,
                'portrait_url' => $artist->portrait_full_url,
                'bio_html' => $artist->bio_html ?? [],
                'website' => $artist->website,
                'facebook_url' => $artist->facebook_url,
                'instagram_url' => $artist->instagram_url,
                'tiktok_url' => $artist->tiktok_url,
                'youtube_url' => $artist->youtube_url,
                'spotify_url' => $artist->spotify_url,
                'youtube_videos' => $artist->youtube_videos ?? [],
                'email' => $artist->email,
                'phone' => $artist->phone,
                'city' => $artist->city,
                'state' => $artist->state,
                'country' => $artist->country,
                'founded_year' => $artist->founded_year,
                'genres' => (function () use ($artist) {
                    try {
                        return $artist->artistGenres()->pluck('name')
                            ->map(function ($n) {
                                if (is_string($n)) return trim($n);
                                if (is_array($n)) return trim($n['ro'] ?? $n['en'] ?? (array_values($n)[0] ?? ''));
                                return '';
                            })
                            ->filter(fn ($n) => $n !== '')
                            ->values()
                            ->all();
                    } catch (\Throwable $e) {
                        return [];
                    }
                })(),
                'achievements' => $artist->achievements ?? [],
            ],
            'active_variant_id' => $epk->active_variant_id,
            'variants' => $variants,
            'live_stats' => $this->epkService->computeLiveStats($artist),
            'past_events' => $this->epkService->getPastEventsFor($artist, 50),
            'marketplace_domain' => $marketplace->domain,
            'limits' => [
                'max_variants' => ArtistEpkVariant::MAX_VARIANTS_PER_EPK,
                'max_gallery_images' => ArtistEpkVariant::MAX_GALLERY_IMAGES,
                'max_youtube_videos' => ArtistEpkVariant::MAX_YOUTUBE_VIDEOS,
            ],
        ];
    }

    protected function presentVariant(ArtistEpkVariant $variant, ?Artist $artist = null): array
    {
        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'target' => $variant->target,
            'slug' => $variant->slug,
            'accent_color' => $variant->accent_color,
            'template' => $variant->template,
            // enriched cu fallback-uri din Artist profile (social/contact/hero stage_name + cover + gallery)
            'sections' => $variant->enrichedSections($artist),
            'views_count' => $variant->views_count,
            'conversion_pct' => (float) $variant->conversion_pct,
            'created_at' => $variant->created_at?->toIso8601String(),
            'updated_at' => $variant->updated_at?->toIso8601String(),
        ];
    }
}
