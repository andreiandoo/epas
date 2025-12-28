<?php

namespace App\Services\Seating;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * GeometryStorage Service
 *
 * Handles storage and retrieval of seating geometry with CDN-ready abstraction
 */
class GeometryStorage
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('seating.storage_disk', 'public');
    }

    /**
     * Get render-ready geometry for an event
     */
    public function getGeometry(int $eventSeatingId): array
    {
        $layout = EventSeatingLayout::findOrFail($eventSeatingId);

        $geometry = $layout->json_geometry;

        // Resolve background image URL if present
        if (!empty($geometry['background_image'])) {
            $geometry['background_url'] = $this->getBackgroundImageUrl($geometry['background_image']);
        }

        return $geometry;
    }

    /**
     * Get background image URL (CDN-ready)
     */
    public function getBackgroundImageUrl(string $path): string
    {
        // Check if CDN URL is configured
        $cdnUrl = config('seating.widget.cdn_url');

        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        // Fallback to storage disk URL
        try {
            return Storage::disk($this->disk)->url($path);
        } catch (\Exception $e) {
            Log::warning("GeometryStorage: Failed to get URL for path: {$path}", [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Store background image
     */
    public function storeBackgroundImage($file, int $tenantId): string
    {
        $directory = config('seating.background_images.directory', 'seating/backgrounds');
        $path = $file->store("{$directory}/tenant_{$tenantId}", $this->disk);

        Log::info("GeometryStorage: Stored background image", [
            'tenant_id' => $tenantId,
            'path' => $path,
        ]);

        return $path;
    }

    /**
     * Delete background image
     */
    public function deleteBackgroundImage(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            Log::error("GeometryStorage: Failed to delete background image", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate geometry snapshot from seating layout
     */
    public function generateGeometrySnapshot(SeatingLayout $layout): array
    {
        $sections = [];

        foreach ($layout->sections as $section) {
            $rows = [];

            foreach ($section->rows as $row) {
                $seats = [];

                foreach ($row->seats as $seat) {
                    $seats[] = [
                        'seat_uid' => $seat->seat_uid,
                        'label' => $seat->label,
                        'x' => (float) $seat->x,
                        'y' => (float) $seat->y,
                        'angle' => (float) $seat->angle,
                        'shape' => $seat->shape,
                    ];
                }

                $rows[] = [
                    'label' => $row->label,
                    'y' => (float) $row->y,
                    'rotation' => (float) $row->rotation,
                    'seat_count' => $row->seat_count,
                    'seats' => $seats,
                ];
            }

            $sections[] = [
                'name' => $section->name,
                'color' => $section->color_hex,
                'rows' => $rows,
                'meta' => $section->meta,
            ];
        }

        $geometry = [
            'canvas' => [
                'width' => $layout->canvas_w,
                'height' => $layout->canvas_h,
            ],
            'background_image' => $layout->background_image_path,
            'sections' => $sections,
            'version' => $layout->version,
            'layout_id' => $layout->id,
            'layout_name' => $layout->name,
        ];

        Log::info("GeometryStorage: Generated snapshot", [
            'layout_id' => $layout->id,
            'sections' => count($sections),
        ]);

        return $geometry;
    }

    /**
     * Extract seat UIDs from geometry
     */
    public function extractSeatUids(array $geometry): array
    {
        $seatUids = [];

        foreach ($geometry['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                foreach ($row['seats'] ?? [] as $seat) {
                    $seatUids[] = $seat['seat_uid'];
                }
            }
        }

        return $seatUids;
    }

    /**
     * Validate geometry structure
     */
    public function validateGeometry(array $geometry): array
    {
        $errors = [];

        // Check required keys
        if (!isset($geometry['canvas'])) {
            $errors[] = 'Missing canvas configuration';
        }

        if (!isset($geometry['sections']) || !is_array($geometry['sections'])) {
            $errors[] = 'Missing or invalid sections array';
        }

        // Validate canvas
        if (isset($geometry['canvas'])) {
            $canvas = $geometry['canvas'];

            if (!isset($canvas['width']) || !isset($canvas['height'])) {
                $errors[] = 'Canvas must have width and height';
            }

            $maxW = config('seating.canvas.max_width');
            $maxH = config('seating.canvas.max_height');

            if (($canvas['width'] ?? 0) > $maxW) {
                $errors[] = "Canvas width exceeds maximum ({$maxW}px)";
            }

            if (($canvas['height'] ?? 0) > $maxH) {
                $errors[] = "Canvas height exceeds maximum ({$maxH}px)";
            }
        }

        // Validate sections
        $seatUids = [];
        $maxSections = config('seating.validation.max_sections_per_layout');

        if (count($geometry['sections'] ?? []) > $maxSections) {
            $errors[] = "Too many sections (max {$maxSections})";
        }

        foreach ($geometry['sections'] ?? [] as $sectionIdx => $section) {
            if (!isset($section['name'])) {
                $errors[] = "Section {$sectionIdx} missing name";
            }

            foreach ($section['rows'] ?? [] as $rowIdx => $row) {
                foreach ($row['seats'] ?? [] as $seatIdx => $seat) {
                    $uid = $seat['seat_uid'] ?? null;

                    if (!$uid) {
                        $errors[] = "Seat missing UID at section {$sectionIdx}, row {$rowIdx}, seat {$seatIdx}";
                        continue;
                    }

                    if (in_array($uid, $seatUids)) {
                        $errors[] = "Duplicate seat UID: {$uid}";
                    }

                    $seatUids[] = $uid;

                    // Validate seat UID pattern
                    $pattern = config('seating.validation.seat_uid_pattern');
                    if ($pattern && !preg_match($pattern, $uid)) {
                        $errors[] = "Invalid seat UID format: {$uid}";
                    }
                }
            }
        }

        // Check total seat count
        $maxSeats = config('seating.validation.max_total_seats_per_layout');
        if (count($seatUids) > $maxSeats) {
            $errors[] = "Too many seats (max {$maxSeats}, found " . count($seatUids) . ")";
        }

        return $errors;
    }
}
