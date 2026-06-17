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
            "seating_pdf_geometry_{$layoutId}_v2",
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
                        'rotation' => (int) ($section->rotation ?? 0),
                        'color_hex' => $section->color_hex,
                        'background_color' => $section->background_color,
                        'corner_radius' => (int) ($section->corner_radius ?? 0),
                        'meta' => is_array($section->meta) ? $section->meta : [],
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

        // First pass — decorative zones (stage, dance floor, etc) drawn
        // BEHIND seats so anything overlapping reads correctly. These have
        // no seats; we treat them as colored rectangles with the section
        // name centered inside.
        foreach ($sections as $section) {
            $type = $section['section_type'] ?? 'standard';
            if (in_array($type, ['decorative', 'stage', 'dance_floor'], true)) {
                $this->appendDecorativeZone($svg, $section);
            } elseif ($type === 'polygon') {
                $this->appendPolygon($svg, $section);
            }
        }

        // Second pass — section name labels for standard sections (above
        // the topmost seat).
        foreach ($sections as $section) {
            $type = $section['section_type'] ?? 'standard';
            if ($type !== 'standard') {
                continue;
            }
            $bbox = $this->sectionBbox($section);
            if (!$bbox) {
                continue;
            }
            $cx = ($bbox['minX'] + $bbox['maxX']) / 2;
            $labelY = $bbox['minY'] - 22;
            if ($labelY < 16) {
                $labelY = $bbox['minY'] + 16;
            }
            $name = htmlspecialchars((string) ($section['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($name !== '') {
                $svg .= '<text x="' . $cx . '" y="' . $labelY . '" '
                    . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" '
                    . 'font-size="22" fill="#374151" font-weight="bold">'
                    . $name . '</text>';
            }
        }

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
                // Left side
                $lx = $sx + $leftSeat['x'] - 18;
                $ly = $sy + $leftSeat['y'] + 5;
                $svg .= '<text x="' . $lx . '" y="' . $ly . '" '
                    . 'text-anchor="end" font-family="Helvetica, Arial, sans-serif" '
                    . 'font-size="11" fill="#6b7280" font-weight="bold">'
                    . $rowLabel . '</text>';
                // Right side (helps when the user is approaching from the other aisle)
                $rx = $sx + $rightSeat['x'] + 18;
                $ry = $sy + $rightSeat['y'] + 5;
                $svg .= '<text x="' . $rx . '" y="' . $ry . '" '
                    . 'text-anchor="start" font-family="Helvetica, Arial, sans-serif" '
                    . 'font-size="11" fill="#6b7280" font-weight="bold">'
                    . $rowLabel . '</text>';
            }
        }

        // Fourth pass — all seats as small grey circles.
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
                    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="6" fill="#d1d5db"/>';
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
     * Append a decorative zone (stage, dance floor, decorative) as a
     * coloured rectangle with the section name centered inside.
     */
    protected function appendDecorativeZone(string &$svg, array $section): void
    {
        $x = $section['x_position'];
        $y = $section['y_position'];
        $w = $section['width'] ?: 200;
        $h = $section['height'] ?: 60;
        $bg = $section['background_color'] ?: ($section['color_hex'] ?: '#9333ea');
        $rx = max(0, min(40, $section['corner_radius'] ?? 0));

        $svg .= '<rect x="' . $x . '" y="' . $y . '" '
            . 'width="' . $w . '" height="' . $h . '" '
            . 'rx="' . $rx . '" ry="' . $rx . '" '
            . 'fill="' . htmlspecialchars($bg, ENT_QUOTES | ENT_SUBSTITUTE) . '" '
            . 'fill-opacity="0.85" stroke="#1f2937" stroke-width="1"/>';

        $name = htmlspecialchars((string) ($section['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($name !== '') {
            // Pick contrasting text color (cheap heuristic on lightness of bg).
            $textColor = $this->contrastTextColor($bg);
            $cx = $x + $w / 2;
            $cy = $y + $h / 2 + 7;
            $fontSize = max(12, min(28, (int) ($h * 0.35)));
            $svg .= '<text x="' . $cx . '" y="' . $cy . '" '
                . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" '
                . 'font-size="' . $fontSize . '" fill="' . $textColor . '" '
                . 'font-weight="bold" letter-spacing="2">'
                . strtoupper($name) . '</text>';
        }
    }

    /**
     * Append a polygon-shaped section. Points stored in section.meta.polygon_points
     * as a list of {x, y} coordinates relative to the section origin.
     */
    protected function appendPolygon(string &$svg, array $section): void
    {
        $points = $section['meta']['polygon_points'] ?? null;
        if (!is_array($points) || empty($points)) {
            return;
        }
        $sx = $section['x_position'];
        $sy = $section['y_position'];
        $coords = [];
        foreach ($points as $p) {
            $px = $sx + (float) ($p['x'] ?? 0);
            $py = $sy + (float) ($p['y'] ?? 0);
            $coords[] = $px . ',' . $py;
        }
        $bg = $section['background_color'] ?: ($section['color_hex'] ?: '#9333ea');
        $svg .= '<polygon points="' . implode(' ', $coords) . '" '
            . 'fill="' . htmlspecialchars($bg, ENT_QUOTES | ENT_SUBSTITUTE) . '" '
            . 'fill-opacity="0.7" stroke="#1f2937" stroke-width="1"/>';
    }

    /**
     * Return a foreground colour that contrasts with the given hex bg.
     * Quick & dirty — sufficient for stage/zone labels.
     */
    protected function contrastTextColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luma = ($r * 0.299 + $g * 0.587 + $b * 0.114) / 255;
        return $luma > 0.62 ? '#111827' : '#ffffff';
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
