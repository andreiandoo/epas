<?php

namespace App\Services\Seating;

use App\DTOs\Seating\ImportedLayout;
use App\DTOs\Seating\ImportedSection;
use App\DTOs\Seating\ImportedSeat;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingSection;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use DOMDocument;
use DOMXPath;

class SVGImportService
{
    /**
     * Parse HTML content from iabilet-style seat map
     */
    public function parseIabiletHtml(string $html): ImportedLayout
    {
        $dom = new DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $layout = new ImportedLayout();

        // 1. Extract viewBox from areas layer SVG
        $areasLayerNodes = $xpath->query('//svg[@data-is-areas-layer="1"]');
        if ($areasLayerNodes && $areasLayerNodes->length > 0) {
            $areasLayer = $areasLayerNodes->item(0);
            $viewBoxStr = $areasLayer->getAttribute('viewBox');
            if ($viewBoxStr) {
                $layout->viewBox = ImportedLayout::parseViewBox($viewBoxStr);
            }
        }

        // 2. Parse sections (path elements with data-is-area="1")
        $sections = $this->parseSections($xpath);
        foreach ($sections as $section) {
            $layout->addSection($section);
        }

        // 3. Parse seats (circle elements in seats layer)
        $seats = $this->parseSeats($xpath);

        // 4. Associate seats with sections based on category ID or position
        $this->associateSeatsToSections($layout->sections, $seats);

        // 5. Group seats into rows within each section
        foreach ($layout->sections as $section) {
            $this->groupSeatsIntoRows($section);
        }

        // 6. Extract background image if present
        $layout->backgroundUrl = $this->extractBackgroundImage($xpath);

        return $layout;
    }

    /**
     * Parse section paths from the areas layer
     */
    protected function parseSections(DOMXPath $xpath): array
    {
        $sections = [];
        $sectionIndex = 1;

        // Find all path elements with data-is-area="1"
        $pathNodes = $xpath->query('//svg[@data-is-areas-layer="1"]//path[@data-is-area="1"]');

        if ($pathNodes) {
            foreach ($pathNodes as $path) {
                $section = ImportedSection::fromPathElement($path);
                $section->name = "Section {$sectionIndex}";
                $sections[] = $section;
                $sectionIndex++;
            }
        }

        // Also try generic paths in any SVG with areas layer
        if (empty($sections)) {
            $pathNodes = $xpath->query('//svg//path[contains(@d, "M") or contains(@d, "m")]');
            if ($pathNodes) {
                foreach ($pathNodes as $path) {
                    $d = $path->getAttribute('d');
                    if (strlen($d) > 10) { // Skip very short paths
                        $section = ImportedSection::fromPathElement($path);
                        $section->name = "Section {$sectionIndex}";
                        $sections[] = $section;
                        $sectionIndex++;
                    }
                }
            }
        }

        return $sections;
    }

    /**
     * Parse seat circles from the seats layer
     */
    protected function parseSeats(DOMXPath $xpath): array
    {
        $seats = [];

        // Find all circle elements in seats layer
        $circleNodes = $xpath->query('//svg[@data-is-seats-layer="1"]//circle');

        if ($circleNodes && $circleNodes->length > 0) {
            foreach ($circleNodes as $circle) {
                $seats[] = ImportedSeat::fromCircleElement($circle);
            }
        }

        // Also try circles with data-seat-id attribute anywhere
        if (empty($seats)) {
            $circleNodes = $xpath->query('//circle[@data-seat-id]');
            if ($circleNodes) {
                foreach ($circleNodes as $circle) {
                    $seats[] = ImportedSeat::fromCircleElement($circle);
                }
            }
        }

        // Fallback: any circles in SVG
        if (empty($seats)) {
            $circleNodes = $xpath->query('//svg//circle[@cx][@cy]');
            if ($circleNodes) {
                foreach ($circleNodes as $circle) {
                    $seats[] = ImportedSeat::fromCircleElement($circle);
                }
            }
        }

        return $seats;
    }

    /**
     * Associate seats with sections based on category ID or geometric containment
     *
     * @param ImportedSection[] $sections
     * @param ImportedSeat[] $seats
     */
    protected function associateSeatsToSections(array &$sections, array $seats): void
    {
        foreach ($seats as $seat) {
            $assigned = false;

            // First try to match by category ID
            if ($seat->categoryId !== null) { // Handle category "0" properly
                foreach ($sections as $section) {
                    if ($section->categoryId === $seat->categoryId) {
                        $section->addSeat($seat);
                        $seat->sectionId = $section->externalId;
                        $assigned = true;
                        break;
                    }
                }
            }

            // If not assigned by category, try geometric containment
            if (!$assigned) {
                foreach ($sections as $section) {
                    if ($this->isPointInPolygon($seat->cx, $seat->cy, $section->points)) {
                        $section->addSeat($seat);
                        $seat->sectionId = $section->externalId;
                        $assigned = true;
                        break;
                    }
                }
            }

            // If still not assigned and we have only one section, add to it
            if (!$assigned && count($sections) === 1) {
                $sections[0]->addSeat($seat);
                $seat->sectionId = $sections[0]->externalId;
            }
        }
    }

    /**
     * Check if a point is inside a polygon using ray casting algorithm
     */
    protected function isPointInPolygon(float $x, float $y, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) return false;

        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            if ((($yi > $y) !== ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Group seats into rows based on Y coordinate proximity
     */
    protected function groupSeatsIntoRows(ImportedSection $section, float $tolerance = 15.0): void
    {
        if (empty($section->seats)) return;

        // Sort seats by Y coordinate
        $seats = $section->seats;
        usort($seats, fn($a, $b) => $a->cy <=> $b->cy);

        $rows = [];
        $currentRow = [];
        $lastY = null;
        $rowIndex = 1;

        foreach ($seats as $seat) {
            if ($lastY === null || abs($seat->cy - $lastY) <= $tolerance) {
                $currentRow[] = $seat;
                if ($lastY === null) {
                    $lastY = $seat->cy;
                } else {
                    // Update lastY to be the average of the row
                    $lastY = ($lastY + $seat->cy) / 2;
                }
            } else {
                // New row - sort current row by X and assign labels
                if (!empty($currentRow)) {
                    usort($currentRow, fn($a, $b) => $a->cx <=> $b->cx);
                    $this->assignRowLabels($currentRow, $rowIndex);
                    $rows[] = $currentRow;
                    $rowIndex++;
                }
                $currentRow = [$seat];
                $lastY = $seat->cy;
            }
        }

        // Don't forget the last row
        if (!empty($currentRow)) {
            usort($currentRow, fn($a, $b) => $a->cx <=> $b->cx);
            $this->assignRowLabels($currentRow, $rowIndex);
            $rows[] = $currentRow;
        }

        // Update section seats with row assignments
        $section->seats = array_merge(...$rows);
    }

    /**
     * Assign row and seat labels to a row of seats
     */
    protected function assignRowLabels(array &$seats, int $rowNumber): void
    {
        $rowLabel = (string) $rowNumber;

        foreach ($seats as $index => $seat) {
            $seat->rowLabel = $rowLabel;
            $seat->seatLabel = (string) ($index + 1);
        }
    }

    /**
     * Extract background image URL from the HTML
     */
    protected function extractBackgroundImage(DOMXPath $xpath): ?string
    {
        // Try leaflet image layer
        $imgNodes = $xpath->query('//img[contains(@class, "leaflet-image-layer")]');
        if ($imgNodes && $imgNodes->length > 0) {
            return $imgNodes->item(0)->getAttribute('src');
        }

        // Try any image with background-related attributes
        $imgNodes = $xpath->query('//img[contains(@class, "background") or contains(@id, "background")]');
        if ($imgNodes && $imgNodes->length > 0) {
            return $imgNodes->item(0)->getAttribute('src');
        }

        // Try CSS background-image in style attributes
        $elementsWithStyle = $xpath->query('//*[contains(@style, "background-image")]');
        if ($elementsWithStyle && $elementsWithStyle->length > 0) {
            $style = $elementsWithStyle->item(0)->getAttribute('style');
            if (preg_match('/background-image:\s*url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $style, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Create SeatingLayout and related models from imported data
     */
    public function createLayoutFromImport(
        ImportedLayout $imported,
        SeatingLayout $layout,
        bool $importSeats = true,
        bool $clearExisting = false
    ): array {
        $stats = [
            'sections_created' => 0,
            'rows_created' => 0,
            'seats_created' => 0,
        ];

        // Normalize coordinates if not already done
        if ($imported->canvasWidth === null) {
            $imported->normalizeToCanvas($layout->canvas_w, $layout->canvas_h);
        }

        // Clear existing sections if requested
        if ($clearExisting) {
            $layout->sections()->delete();
        }

        // Create sections
        foreach ($imported->sections as $importedSection) {
            $section = SeatingSection::create([
                'layout_id' => $layout->id,
                'tenant_id' => $layout->tenant_id,
                'name' => $importedSection->name ?? "Imported Section",
                'section_code' => $this->generateSectionCode($importedSection->name ?? 'SEC', $layout->id),
                'section_type' => 'standard',
                'x_position' => (int) ($importedSection->boundingX ?? 100),
                'y_position' => (int) ($importedSection->boundingY ?? 100),
                'width' => (int) ($importedSection->boundingWidth ?? 200),
                'height' => (int) ($importedSection->boundingHeight ?? 150),
                'rotation' => 0,
                'display_order' => $stats['sections_created'],
                'color_hex' => $this->generateColor($stats['sections_created']),
                'seat_color' => $this->generateSeatColor($stats['sections_created']),
                'metadata' => [
                    'shape' => 'polygon',
                    'points' => $importedSection->getFlattenedPoints(true),
                    'imported_from' => 'iabilet',
                    'external_category_id' => $importedSection->categoryId,
                ],
            ]);

            $stats['sections_created']++;

            // Create rows and seats if requested
            if ($importSeats && !empty($importedSection->seats)) {
                $this->createSeatsForSection($section, $importedSection->seats, $stats);
            }
        }

        return $stats;
    }

    /**
     * Create seats for a section, grouped by rows
     */
    protected function createSeatsForSection(SeatingSection $section, array $seats, array &$stats): void
    {
        // Group seats by row label
        $rowGroups = [];
        foreach ($seats as $seat) {
            $rowLabel = $seat->rowLabel ?? 'Manual';
            if (!isset($rowGroups[$rowLabel])) {
                $rowGroups[$rowLabel] = [];
            }
            $rowGroups[$rowLabel][] = $seat;
        }

        // Create rows and seats
        foreach ($rowGroups as $rowLabel => $rowSeats) {
            // Calculate average Y for the row
            $avgY = count($rowSeats) > 0
                ? array_sum(array_map(fn($s) => $s->cy, $rowSeats)) / count($rowSeats)
                : 0;

            $row = SeatingRow::create([
                'section_id' => $section->id,
                'label' => $rowLabel,
                'y' => $avgY - $section->y_position, // Relative to section
                'rotation' => 0,
                'seat_count' => count($rowSeats),
            ]);

            $stats['rows_created']++;

            // Sort seats by X position and create
            usort($rowSeats, fn($a, $b) => $a->cx <=> $b->cx);

            foreach ($rowSeats as $importedSeat) {
                $seatLabel = $importedSeat->seatLabel ?? (string) ($stats['seats_created'] + 1);

                SeatingSeat::create([
                    'row_id' => $row->id,
                    'label' => $seatLabel,
                    'display_name' => $section->generateSeatDisplayName($rowLabel, $seatLabel),
                    'x' => $importedSeat->cx - $section->x_position, // Relative to section
                    'y' => $importedSeat->cy - $section->y_position, // Relative to section
                    'angle' => 0,
                    'shape' => 'circle',
                    'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                ]);

                $stats['seats_created']++;
            }
        }
    }

    /**
     * Generate a unique section code
     */
    protected function generateSectionCode(string $name, int $layoutId): string
    {
        $baseCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($name, 0, 3)));
        if (empty($baseCode)) {
            $baseCode = 'SEC';
        }

        $existingCount = SeatingSection::where('layout_id', $layoutId)
            ->where('section_code', 'like', $baseCode . '%')
            ->count();

        return $baseCode . ($existingCount > 0 ? ($existingCount + 1) : '');
    }

    /**
     * Generate a section background color
     */
    protected function generateColor(int $index): string
    {
        $colors = [
            '#3B82F6', // Blue
            '#10B981', // Green
            '#F59E0B', // Amber
            '#EF4444', // Red
            '#8B5CF6', // Purple
            '#EC4899', // Pink
            '#06B6D4', // Cyan
            '#84CC16', // Lime
        ];

        return $colors[$index % count($colors)];
    }

    /**
     * Generate a seat color (available state)
     */
    protected function generateSeatColor(int $index): string
    {
        $colors = [
            '#22C55E', // Green
            '#3B82F6', // Blue
            '#F59E0B', // Amber
            '#8B5CF6', // Purple
            '#06B6D4', // Cyan
            '#EC4899', // Pink
            '#EF4444', // Red
            '#84CC16', // Lime
        ];

        return $colors[$index % count($colors)];
    }
}
