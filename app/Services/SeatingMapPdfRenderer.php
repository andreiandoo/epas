<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingSection;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Renders the seating-map second page for a ticket PDF.
 *
 * Strategy: query the base seating layout (sections → rows → seats) and emit
 * an SVG sized to fit the layout's canvas. Each seat is a small grey circle
 * at its absolute position (section.x_position + seat.x). The buyer's seat
 * is overlaid with a larger red circle + label, so it pops at any zoom.
 *
 * DomPDF supports SVG with basic shapes (circle, rect, text, path) but
 * NOT filters, gradients, or drop-shadows — the visual style here sticks
 * to solid fills and simple strokes.
 *
 * The render is fully wrapped in try/catch and returns null on any error.
 * Callers (the partial) treat null as "skip the second page" — so even a
 * malformed layout cannot break the underlying ticket PDF.
 *
 * Per-event caching: rendering the SVG involves ~3 queries (sections, rows,
 * seats). An order with N tickets to the same event would otherwise repeat
 * all of them N times. We cache the per-event geometry payload for 10
 * minutes — the bulk-PDF flow finishes long before that, and admins
 * regenerating after a layout edit just wait one cache cycle.
 */
class SeatingMapPdfRenderer
{
    /**
     * Render the seating-map payload for a single ticket.
     *
     * @return array{svg:string, seat_label:?string, section_name:?string, row_label:?string, canvas_w:int, canvas_h:int, found:bool}|null
     *         Null when the event has no published seating layout, the ticket
     *         has no seat_uid, or anything errors mid-render.
     */
    public function render(Ticket $ticket, ?Event $event = null): ?array
    {
        try {
            if (empty($ticket->seat_uid)) {
                return null;
            }

            $event ??= $ticket->event;
            if (!$event) {
                return null;
            }

            $eventSeating = EventSeatingLayout::where('event_id', $event->id)
                ->where('status', 'active')
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->first();

            if (!$eventSeating) {
                return null;
            }

            $layoutPayload = $this->loadLayoutGeometry((int) $eventSeating->layout_id);
            if (!$layoutPayload) {
                return null;
            }

            $target = $this->findTarget($layoutPayload['sections'], $ticket->seat_uid);
            $svg = $this->buildSvg(
                $layoutPayload['sections'],
                $layoutPayload['canvas_w'],
                $layoutPayload['canvas_h'],
                $target,
            );

            return [
                'svg' => $svg,
                'seat_label' => $target['seat']['label'] ?? $ticket->seat_label,
                'section_name' => $target['section']['name'] ?? null,
                'row_label' => $target['row']['label'] ?? null,
                'canvas_w' => $layoutPayload['canvas_w'],
                'canvas_h' => $layoutPayload['canvas_h'],
                'found' => $target !== null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SeatingMapPdfRenderer error — falling back to plain PDF', [
                'ticket_id' => $ticket->id ?? null,
                'event_id' => $event?->id ?? $ticket->event_id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Load layout + sections + rows + seats as plain arrays, cached per layout.
     *
     * @return array{canvas_w:int, canvas_h:int, sections:array}|null
     */
    protected function loadLayoutGeometry(int $layoutId): ?array
    {
        return Cache::remember(
            "seating_pdf_geometry_{$layoutId}_v3",
            600,
            function () use ($layoutId) {
                $layout = SeatingLayout::find($layoutId);
                if (!$layout) {
                    return null;
                }

                $sections = SeatingSection::where('layout_id', $layoutId)
                    ->with(['rows.seats'])
                    ->get();

                $sectionsArr = [];
                foreach ($sections as $section) {
                    $rowsArr = [];
                    foreach ($section->rows as $row) {
                        $seatsArr = [];
                        foreach ($row->seats as $seat) {
                            $seatsArr[] = [
                                'seat_uid' => $seat->seat_uid,
                                'label' => $seat->label,
                                'x' => (float) $seat->x,
                                'y' => (float) $seat->y,
                                'status' => $seat->status,
                            ];
                        }
                        $rowsArr[] = [
                            'id' => $row->id,
                            'label' => $row->label,
                            'seats' => $seatsArr,
                        ];
                    }
                    $sectionsArr[] = [
                        'id' => $section->id,
                        'name' => $section->name,
                        'section_type' => $section->section_type ?: 'standard',
                        'section_code' => $section->section_code,
                        'x_position' => (int) $section->x_position,
                        'y_position' => (int) $section->y_position,
                        'width' => (int) ($section->width ?? 0),
                        'height' => (int) ($section->height ?? 0),
                        'rotation' => (float) ($section->rotation ?? 0),
                        'color_hex' => $section->color_hex,
                        'background_color' => $section->background_color,
                        'corner_radius' => (int) ($section->corner_radius ?? 0),
                        // The Konva designer stores extra props (shape,
                        // opacity, points, font*, etc.) under `metadata`.
                        // Legacy data sometimes uses `meta`. Merge both
                        // so older layouts keep rendering.
                        'metadata' => array_merge(
                            is_array($section->meta) ? $section->meta : [],
                            is_array($section->metadata) ? $section->metadata : [],
                        ),
                        'rows' => $rowsArr,
                    ];
                }

                return [
                    'canvas_w' => (int) ($layout->canvas_w ?: 1920),
                    'canvas_h' => (int) ($layout->canvas_h ?: 1080),
                    'sections' => $sectionsArr,
                ];
            }
        );
    }

    /**
     * Locate the buyer's seat in the loaded geometry by seat_uid.
     */
    protected function findTarget(array $sections, string $seatUid): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row['seats'] as $seat) {
                    if ($seat['seat_uid'] === $seatUid) {
                        return [
                            'section' => $section,
                            'row' => $row,
                            'seat' => $seat,
                            'abs_x' => $section['x_position'] + $seat['x'],
                            'abs_y' => $section['y_position'] + $seat['y'],
                        ];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Build the SVG string. Coordinates are absolute (section + seat).
     * Section rotation is intentionally NOT applied — DomPDF's SVG
     * `transform` support is unreliable; an unrotated map is still
     * accurate enough for "find your seat".
     */
    protected function buildSvg(array $sections, int $canvasW, int $canvasH, ?array $target): string
    {
        // XML declaration + explicit width/height (in user units = px) on the
        // <svg> root. DomPDF's data: image renderer requires both — it ignores
        // %-based widths inside an <img> data URL and otherwise renders nothing.
        // The viewBox keeps everything scaling correctly when the consuming
        // <img> CSS sets a different display size.
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'viewBox="0 0 ' . $canvasW . ' ' . $canvasH . '" '
            . 'width="' . $canvasW . '" height="' . $canvasH . '" '
            . 'preserveAspectRatio="xMidYMid meet">';

        // Light canvas background so seat circles read clearly on the page.
        $svg .= '<rect width="' . $canvasW . '" height="' . $canvasH . '" fill="#fafafa"/>';

        // First pass — decorative / stage / dance_floor / polygon zones,
        // drawn BEHIND seats so anything overlapping reads correctly.
        // Style mirrors the Konva designer (designer-konva.blade.php):
        // metadata.shape selects rect / polygon / text / line; defaults
        // to 'rect' for stage and dance_floor types.
        foreach ($sections as $section) {
            $type = $section['section_type'] ?? 'standard';
            if (!in_array($type, ['decorative', 'stage', 'dance_floor'], true)) {
                continue;
            }
            $shape = $section['metadata']['shape'] ?? (in_array($type, ['stage', 'dance_floor'], true) ? 'rect' : 'rect');
            if ($shape === 'rect') {
                $this->appendDecorativeRect($svg, $section);
            } elseif ($shape === 'polygon') {
                $this->appendDecorativePolygon($svg, $section);
            } elseif ($shape === 'text') {
                $this->appendDecorativeText($svg, $section);
            }
            // 'line' — skipped; rare and tricky in static SVG.
        }

        // Note: section name labels for STANDARD (seated) sections are
        // intentionally omitted — the seat itself + row label are enough
        // context, and the section banners just added clutter. Decorative
        // / stage / dance_floor sections still carry their own labels
        // from appendDecorativeRect/Polygon/Text above.

        // Third pass — row labels at the LEFT side of each row, just outside
        // the first seat. Helps a buyer count rows from the aisle.
        foreach ($sections as $section) {
            $sx = $section['x_position'];
            $sy = $section['y_position'];
            foreach ($section['rows'] as $row) {
                if (empty($row['seats']) || empty($row['label'])) {
                    continue;
                }
                $leftSeat = null;
                $rightSeat = null;
                foreach ($row['seats'] as $seat) {
                    if ($leftSeat === null || $seat['x'] < $leftSeat['x']) {
                        $leftSeat = $seat;
                    }
                    if ($rightSeat === null || $seat['x'] > $rightSeat['x']) {
                        $rightSeat = $seat;
                    }
                }
                $rowLabel = htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // DejaVu Sans is DomPDF's only reliably-bundled font with
                // a real bold variant — Helvetica falls back to a faux-bold
                // that DomPDF doesn't actually render. font-weight="900"
                // pushes DomPDF to pick the heaviest available glyph set.
                $leftX = $sx + $leftSeat['x'] - 18;
                $leftY = $sy + $leftSeat['y'] + 5;
                $svg .= '<text x="' . $leftX . '" y="' . $leftY . '" '
                    . 'text-anchor="end" font-family="DejaVu Sans, Helvetica, Arial, sans-serif" '
                    . 'font-size="13" fill="#111827" font-weight="900">'
                    . $rowLabel . '</text>';
                $rightXPos = $sx + $rightSeat['x'] + 18;
                $rightYPos = $sy + $rightSeat['y'] + 5;
                $svg .= '<text x="' . $rightXPos . '" y="' . $rightYPos . '" '
                    . 'text-anchor="start" font-family="DejaVu Sans, Helvetica, Arial, sans-serif" '
                    . 'font-size="13" fill="#111827" font-weight="900">'
                    . $rowLabel . '</text>';
            }
        }

        // Fourth pass — all seats as small grey circles WITH the seat
        // label inside. Helps the buyer count seats on a row even when
        // their own seat is far from the aisle.
        $targetSeatUid = $target['seat']['seat_uid'] ?? null;
        foreach ($sections as $section) {
            $sx = $section['x_position'];
            $sy = $section['y_position'];
            foreach ($section['rows'] as $row) {
                foreach ($row['seats'] as $seat) {
                    if ($seat['seat_uid'] === $targetSeatUid) {
                        continue; // drawn last
                    }
                    $cx = $sx + $seat['x'];
                    $cy = $sy + $seat['y'];
                    // Larger circle so the number fits without crowding
                    // neighbouring seats. r=10 in canvas units; the SVG
                    // viewBox scales to whatever pt size the PDF page
                    // gives us.
                    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="10" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.6"/>';

                    $label = htmlspecialchars((string) ($seat['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if ($label !== '') {
                        $svg .= '<text x="' . $cx . '" y="' . ($cy + 3.5) . '" '
                            . 'text-anchor="middle" font-family="DejaVu Sans, Helvetica, Arial, sans-serif" '
                            . 'font-size="9" font-weight="900" fill="#1f2937">'
                            . $label . '</text>';
                    }
                }
            }
        }

        // Fifth pass — target seat (big red, bold outline, label inside).
        if ($target) {
            $cx = $target['abs_x'];
            $cy = $target['abs_y'];
            // Halo so the target reads at any zoom level.
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="36" fill="#fee2e2" stroke="#dc2626" stroke-width="2" opacity="0.6"/>';
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="20" fill="#dc2626" stroke="#111827" stroke-width="3"/>';

            $label = htmlspecialchars((string) ($target['seat']['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($label !== '') {
                $svg .= '<text x="' . $cx . '" y="' . ($cy + 6) . '" '
                    . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" '
                    . 'font-size="14" fill="#ffffff" font-weight="bold">'
                    . $label . '</text>';
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Decorative rectangle (stage, dance floor, VIP lounge). Mirrors the
     * Konva designer: opacity from metadata.opacity (default 0.3 — NOT
     * the 0.85 we had before, which made every stage look like a solid
     * black block), stroke matches the fill colour rather than a hard
     * black border, label is dark grey at 0.85 opacity (no uppercase,
     * no letter-spacing — the designer keeps it plain).
     */
    protected function appendDecorativeRect(string &$svg, array $section): void
    {
        $x = $section['x_position'];
        $y = $section['y_position'];
        $w = $section['width'] ?: 200;
        $h = $section['height'] ?: 60;
        $color = $section['background_color'] ?: ($section['color_hex'] ?: '#10B981');
        $opacity = isset($section['metadata']['opacity']) ? (float) $section['metadata']['opacity'] : 0.3;
        $cornerR = (int) ($section['corner_radius'] ?: ($section['metadata']['corner_radius'] ?? 0));
        $rotation = (float) ($section['rotation'] ?? 0);

        // Per the designer, rotation pivots around the section's CENTRE.
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $rotAttr = $rotation != 0.0
            ? ' transform="rotate(' . $rotation . ' ' . $cx . ' ' . $cy . ')"'
            : '';

        $fillEsc = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE);

        $svg .= '<g' . $rotAttr . '>';
        $svg .= '<rect x="' . $x . '" y="' . $y . '" '
            . 'width="' . $w . '" height="' . $h . '" '
            . 'rx="' . $cornerR . '" ry="' . $cornerR . '" '
            . 'fill="' . $fillEsc . '" fill-opacity="' . $opacity . '" '
            . 'stroke="' . $fillEsc . '" stroke-width="1.5"/>';

        $labelText = $section['metadata']['label'] ?? $section['name'] ?? '';
        if ($labelText !== '') {
            $labelEsc = htmlspecialchars((string) $labelText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $fontSize = max(11, min(18, (int) round(min($w, $h) * 0.18)));
            // Vertically centred — SVG baseline + small ascent fudge.
            $textY = $y + $h / 2 + $fontSize * 0.35;
            $svg .= '<text x="' . $cx . '" y="' . $textY . '" '
                . 'text-anchor="middle" font-family="Arial, Helvetica, sans-serif" '
                . 'font-size="' . $fontSize . '" font-weight="bold" '
                . 'fill="#1f2937" fill-opacity="0.85">'
                . $labelEsc . '</text>';
        }
        $svg .= '</g>';
    }

    /**
     * Polygon-shaped decorative section. Designer stores points in
     * metadata.points as a FLAT array of absolute coordinates
     * [x1, y1, x2, y2, ...] — same convention used by Konva.Line.
     */
    protected function appendDecorativePolygon(string &$svg, array $section): void
    {
        $points = $section['metadata']['points'] ?? null;
        if (!is_array($points) || count($points) < 4) {
            return;
        }
        $coords = [];
        for ($i = 0; $i + 1 < count($points); $i += 2) {
            $coords[] = ((float) $points[$i]) . ',' . ((float) $points[$i + 1]);
        }
        $color = $section['background_color'] ?: ($section['color_hex'] ?: '#10B981');
        $opacity = isset($section['metadata']['opacity']) ? (float) $section['metadata']['opacity'] : 0.3;
        $fillEsc = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE);

        $svg .= '<polygon points="' . implode(' ', $coords) . '" '
            . 'fill="' . $fillEsc . '" fill-opacity="' . $opacity . '" '
            . 'stroke="' . $fillEsc . '" stroke-width="2"/>';

        $labelText = $section['metadata']['label'] ?? $section['name'] ?? '';
        if ($labelText !== '') {
            // Place label at the centroid of the polygon.
            $xs = [];
            $ys = [];
            for ($i = 0; $i + 1 < count($points); $i += 2) {
                $xs[] = (float) $points[$i];
                $ys[] = (float) $points[$i + 1];
            }
            $cx = (min($xs) + max($xs)) / 2;
            $cy = (min($ys) + max($ys)) / 2 + 5;
            $svg .= '<text x="' . $cx . '" y="' . $cy . '" '
                . 'text-anchor="middle" font-family="Arial, Helvetica, sans-serif" '
                . 'font-size="12" fill="#1f2937" fill-opacity="0.85">'
                . htmlspecialchars((string) $labelText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</text>';
        }
    }

    /**
     * Plain-text decorative element (no rectangle background). Properties
     * come from metadata (text/fontSize/fontFamily/fontWeight); colour is
     * the section's background_color (same as the Konva designer).
     */
    protected function appendDecorativeText(string &$svg, array $section): void
    {
        $text = (string) ($section['metadata']['text'] ?? $section['name'] ?? '');
        if ($text === '') {
            return;
        }
        $x = $section['x_position'];
        $y = $section['y_position'];
        $fontSize = (int) ($section['metadata']['fontSize'] ?? 16);
        $fontFamily = htmlspecialchars((string) ($section['metadata']['fontFamily'] ?? 'Arial'), ENT_QUOTES | ENT_SUBSTITUTE);
        $fontWeight = (string) ($section['metadata']['fontWeight'] ?? 'normal');
        $weight = $fontWeight === 'bold' ? 'bold' : 'normal';
        $color = $section['background_color'] ?: ($section['color_hex'] ?: '#1f2937');
        $rotation = (float) ($section['rotation'] ?? 0);
        $rotAttr = $rotation != 0.0
            ? ' transform="rotate(' . $rotation . ' ' . $x . ' ' . $y . ')"'
            : '';

        $svg .= '<text x="' . $x . '" y="' . ($y + $fontSize) . '"' . $rotAttr . ' '
            . 'font-family="' . $fontFamily . '" font-size="' . $fontSize . '" '
            . 'font-weight="' . $weight . '" '
            . 'fill="' . htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE) . '">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</text>';
    }

    /**
     * Compute absolute bbox of all seats in a section (for label placement).
     *
     * @return array{minX:float,maxX:float,minY:float,maxY:float}|null
     */
    protected function sectionBbox(array $section): ?array
    {
        $sx = $section['x_position'];
        $sy = $section['y_position'];
        $minX = $maxX = $minY = $maxY = null;

        foreach ($section['rows'] as $row) {
            foreach ($row['seats'] as $seat) {
                $ax = $sx + $seat['x'];
                $ay = $sy + $seat['y'];
                $minX = $minX === null ? $ax : min($minX, $ax);
                $maxX = $maxX === null ? $ax : max($maxX, $ax);
                $minY = $minY === null ? $ay : min($minY, $ay);
                $maxY = $maxY === null ? $ay : max($maxY, $ay);
            }
        }

        if ($minX === null) {
            return null;
        }

        return ['minX' => $minX, 'maxX' => $maxX, 'minY' => $minY, 'maxY' => $maxY];
    }
}
