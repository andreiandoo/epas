<?php

namespace App\DTOs\Seating;

class ImportedSection
{
    /**
     * @param array<array{0: float, 1: float}> $points Polygon points as [[x, y], [x, y], ...]
     * @param ImportedSeat[] $seats
     */
    public function __construct(
        public string $externalId,
        public array $points,
        public ?string $categoryId = null,
        public bool $isSelectable = true,
        public ?string $name = null,
        public array $seats = [],
        public ?float $boundingX = null,
        public ?float $boundingY = null,
        public ?float $boundingWidth = null,
        public ?float $boundingHeight = null,
    ) {
        $this->calculateBoundingBox();
    }

    public static function fromPathElement(\DOMElement $path): self
    {
        $d = $path->getAttribute('d');
        $points = self::parseSvgPath($d);
        $categoryId = $path->getAttribute('data-seat-category-id');

        return new self(
            externalId: $categoryId !== '' ? $categoryId : uniqid('section_'),
            points: $points,
            categoryId: $categoryId !== '' ? $categoryId : null, // Preserve "0" as valid
            isSelectable: $path->getAttribute('data-is-selectable') !== '0',
        );
    }

    /**
     * Parse SVG path "d" attribute to array of points
     */
    public static function parseSvgPath(string $d): array
    {
        $points = [];
        $currentX = 0;
        $currentY = 0;

        // Split by command letters
        $commands = preg_split('/(?=[MLHVCSQTAZ])/i', $d, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($commands as $cmd) {
            $cmd = trim($cmd);
            if (empty($cmd)) continue;

            $type = $cmd[0];
            $isRelative = ctype_lower($type);
            $type = strtoupper($type);

            // Extract coordinates
            $coordString = trim(substr($cmd, 1));
            if (empty($coordString) && $type !== 'Z') continue;

            $coords = preg_split('/[\s,]+/', $coordString, -1, PREG_SPLIT_NO_EMPTY);
            $coords = array_map('floatval', $coords);

            switch ($type) {
                case 'M': // moveTo
                case 'L': // lineTo
                    for ($i = 0; $i < count($coords); $i += 2) {
                        if (isset($coords[$i], $coords[$i + 1])) {
                            if ($isRelative) {
                                $currentX += $coords[$i];
                                $currentY += $coords[$i + 1];
                            } else {
                                $currentX = $coords[$i];
                                $currentY = $coords[$i + 1];
                            }
                            $points[] = [$currentX, $currentY];
                        }
                    }
                    break;

                case 'H': // horizontal lineTo
                    foreach ($coords as $x) {
                        $currentX = $isRelative ? $currentX + $x : $x;
                        $points[] = [$currentX, $currentY];
                    }
                    break;

                case 'V': // vertical lineTo
                    foreach ($coords as $y) {
                        $currentY = $isRelative ? $currentY + $y : $y;
                        $points[] = [$currentX, $currentY];
                    }
                    break;

                case 'Z': // closePath
                    // Close path - no additional points needed
                    break;

                // For curves (C, S, Q, T, A), we'll just use the end points
                // This is a simplification - proper curve handling would be more complex
                case 'C': // cubic bezier
                    for ($i = 0; $i < count($coords); $i += 6) {
                        if (isset($coords[$i + 4], $coords[$i + 5])) {
                            if ($isRelative) {
                                $currentX += $coords[$i + 4];
                                $currentY += $coords[$i + 5];
                            } else {
                                $currentX = $coords[$i + 4];
                                $currentY = $coords[$i + 5];
                            }
                            $points[] = [$currentX, $currentY];
                        }
                    }
                    break;

                case 'Q': // quadratic bezier
                    for ($i = 0; $i < count($coords); $i += 4) {
                        if (isset($coords[$i + 2], $coords[$i + 3])) {
                            if ($isRelative) {
                                $currentX += $coords[$i + 2];
                                $currentY += $coords[$i + 3];
                            } else {
                                $currentX = $coords[$i + 2];
                                $currentY = $coords[$i + 3];
                            }
                            $points[] = [$currentX, $currentY];
                        }
                    }
                    break;
            }
        }

        return $points;
    }

    public function calculateBoundingBox(): void
    {
        if (empty($this->points)) return;

        $xs = array_column($this->points, 0);
        $ys = array_column($this->points, 1);

        $this->boundingX = min($xs);
        $this->boundingY = min($ys);
        $this->boundingWidth = max($xs) - $this->boundingX;
        $this->boundingHeight = max($ys) - $this->boundingY;
    }

    /**
     * Get points normalized to bounding box origin (0,0)
     */
    public function getNormalizedPoints(): array
    {
        if ($this->boundingX === null || $this->boundingY === null) {
            return $this->points;
        }

        return array_map(function ($point) {
            return [
                $point[0] - $this->boundingX,
                $point[1] - $this->boundingY,
            ];
        }, $this->points);
    }

    /**
     * Get flattened points array for Konva.js Line
     * Returns: [x1, y1, x2, y2, ...]
     */
    public function getFlattenedPoints(bool $normalized = true): array
    {
        $points = $normalized ? $this->getNormalizedPoints() : $this->points;
        $flattened = [];

        foreach ($points as $point) {
            $flattened[] = $point[0];
            $flattened[] = $point[1];
        }

        return $flattened;
    }

    public function addSeat(ImportedSeat $seat): void
    {
        $this->seats[] = $seat;
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'points' => $this->points,
            'normalized_points' => $this->getNormalizedPoints(),
            'flattened_points' => $this->getFlattenedPoints(),
            'category_id' => $this->categoryId,
            'is_selectable' => $this->isSelectable,
            'name' => $this->name,
            'bounding_x' => $this->boundingX,
            'bounding_y' => $this->boundingY,
            'bounding_width' => $this->boundingWidth,
            'bounding_height' => $this->boundingHeight,
            'seats' => array_map(fn($s) => $s->toArray(), $this->seats),
            'seat_count' => count($this->seats),
        ];
    }
}
