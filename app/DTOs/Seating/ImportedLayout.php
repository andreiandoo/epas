<?php

namespace App\DTOs\Seating;

class ImportedLayout
{
    /**
     * @param ImportedSection[] $sections
     * @param array{minX: float, minY: float, width: float, height: float}|null $viewBox
     */
    public function __construct(
        public array $sections = [],
        public ?array $viewBox = null,
        public ?string $backgroundUrl = null,
        public ?float $canvasWidth = null,
        public ?float $canvasHeight = null,
    ) {}

    public function addSection(ImportedSection $section): void
    {
        $this->sections[] = $section;
    }

    public function sectionCount(): int
    {
        return count($this->sections);
    }

    public function seatCount(): int
    {
        return array_sum(array_map(fn($s) => count($s->seats), $this->sections));
    }

    /**
     * Get all unique category IDs found in the layout
     */
    public function getUniqueCategoryIds(): array
    {
        $categoryIds = [];

        foreach ($this->sections as $section) {
            if ($section->categoryId) {
                $categoryIds[$section->categoryId] = true;
            }
            foreach ($section->seats as $seat) {
                if ($seat->categoryId) {
                    $categoryIds[$seat->categoryId] = true;
                }
            }
        }

        return array_keys($categoryIds);
    }

    /**
     * Normalize all coordinates to fit within target canvas dimensions
     */
    public function normalizeToCanvas(int $targetWidth = 1920, int $targetHeight = 1080): self
    {
        if (!$this->viewBox) {
            $this->calculateViewBox();
        }

        if (!$this->viewBox || $this->viewBox['width'] == 0 || $this->viewBox['height'] == 0) {
            return $this;
        }

        // Calculate scale to fit while maintaining aspect ratio
        $scaleX = $targetWidth / $this->viewBox['width'];
        $scaleY = $targetHeight / $this->viewBox['height'];
        $scale = min($scaleX, $scaleY);

        // Calculate offset to center the content
        $offsetX = ($targetWidth - ($this->viewBox['width'] * $scale)) / 2;
        $offsetY = ($targetHeight - ($this->viewBox['height'] * $scale)) / 2;

        foreach ($this->sections as $section) {
            // Transform section points
            $section->points = array_map(function ($point) use ($scale, $offsetX, $offsetY) {
                return [
                    (($point[0] - $this->viewBox['minX']) * $scale) + $offsetX,
                    (($point[1] - $this->viewBox['minY']) * $scale) + $offsetY,
                ];
            }, $section->points);

            // Recalculate bounding box
            $section->calculateBoundingBox();

            // Transform seat positions
            foreach ($section->seats as $seat) {
                $seat->cx = (($seat->cx - $this->viewBox['minX']) * $scale) + $offsetX;
                $seat->cy = (($seat->cy - $this->viewBox['minY']) * $scale) + $offsetY;
            }
        }

        $this->canvasWidth = $targetWidth;
        $this->canvasHeight = $targetHeight;

        return $this;
    }

    /**
     * Calculate viewBox from all section and seat coordinates
     */
    protected function calculateViewBox(): void
    {
        $allX = [];
        $allY = [];

        foreach ($this->sections as $section) {
            foreach ($section->points as $point) {
                $allX[] = $point[0];
                $allY[] = $point[1];
            }

            foreach ($section->seats as $seat) {
                $allX[] = $seat->cx;
                $allY[] = $seat->cy;
            }
        }

        if (empty($allX) || empty($allY)) {
            return;
        }

        $this->viewBox = [
            'minX' => min($allX),
            'minY' => min($allY),
            'width' => max($allX) - min($allX),
            'height' => max($allY) - min($allY),
        ];
    }

    /**
     * Parse viewBox string from SVG
     */
    public static function parseViewBox(string $viewBoxStr): ?array
    {
        $parts = preg_split('/[\s,]+/', trim($viewBoxStr));

        if (count($parts) !== 4) {
            return null;
        }

        return [
            'minX' => (float) $parts[0],
            'minY' => (float) $parts[1],
            'width' => (float) $parts[2],
            'height' => (float) $parts[3],
        ];
    }

    public function toArray(): array
    {
        return [
            'sections' => array_map(fn($s) => $s->toArray(), $this->sections),
            'section_count' => $this->sectionCount(),
            'seat_count' => $this->seatCount(),
            'view_box' => $this->viewBox,
            'background_url' => $this->backgroundUrl,
            'canvas_width' => $this->canvasWidth,
            'canvas_height' => $this->canvasHeight,
            'category_ids' => $this->getUniqueCategoryIds(),
        ];
    }
}
