<?php

namespace App\Services\TicketCustomizer;

use App\Models\TicketTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * Ticket Preview Generator
 *
 * Generates PNG/PDF previews of ticket templates with sample or real data
 */
class TicketPreviewGenerator
{
    public function __construct(
        private TicketVariableService $variableService
    ) {}

    /**
     * Generate preview image from template
     *
     * @param array $templateData Template JSON
     * @param array|null $data Custom data or use sample data
     * @param int $scale Scale factor (1 = 1:1, 2 = 2x for retina)
     * @return array ['path' => string, 'url' => string, 'width' => int, 'height' => int]
     */
    public function generatePreview(array $templateData, ?array $data = null, int $scale = 2): array
    {
        $data = $data ?? $this->variableService->getSampleData();

        // Extract metadata
        $meta = $templateData['meta'] ?? [];
        $dpi = $meta['dpi'] ?? 300;
        $sizeW = $meta['size_mm']['w'] ?? 80;
        $sizeH = $meta['size_mm']['h'] ?? 200;

        // Convert mm to pixels at given DPI
        // Formula: pixels = (mm / 25.4) * DPI
        $widthPx = (int) round(($sizeW / 25.4) * $dpi * $scale);
        $heightPx = (int) round(($sizeH / 25.4) * $dpi * $scale);

        // Create SVG representation (can be converted to PNG later)
        $svg = $this->generateSVG($templateData, $data, $widthPx, $heightPx, $scale);

        // Save SVG
        $filename = 'previews/' . uniqid('ticket_preview_', true) . '.svg';
        Storage::disk('public')->put($filename, $svg);

        return [
            'path' => $filename,
            'url' => Storage::disk('public')->url($filename),
            'width' => $widthPx,
            'height' => $heightPx,
            'format' => 'svg',
        ];
    }

    /**
     * Generate SVG from template data
     */
    private function generateSVG(array $templateData, array $data, int $width, int $height, int $scale): string
    {
        $meta = $templateData['meta'] ?? [];
        $layers = $templateData['layers'] ?? [];

        // Sort layers by z-index
        usort($layers, fn($a, $b) => ($a['z'] ?? 0) <=> ($b['z'] ?? 0));

        $dpi = $meta['dpi'] ?? 300;
        // Scale factor: mm to px conversion with scale
        $pxPerMm = ($dpi / 25.4) * $scale;

        // Background settings
        $background = $meta['background'] ?? [];
        $bgColor = $background['color'] ?? '#ffffff';
        $bgImage = $background['image'] ?? '';

        // Start SVG
        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink"
     width="{$width}"
     height="{$height}"
     viewBox="0 0 {$width} {$height}">
  <defs>
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Roboto:wght@400;500;700&amp;family=Open+Sans:wght@400;600;700&amp;family=Lato:wght@400;700&amp;family=Montserrat:wght@400;500;600;700&amp;family=Poppins:wght@400;500;600;700&amp;family=Playfair+Display:wght@400;700&amp;family=Oswald:wght@400;500;700&amp;display=swap');
    </style>
  </defs>
  <!-- Background -->
  <rect width="100%" height="100%" fill="{$bgColor}"/>

SVG;

        // Background image if present
        if (!empty($bgImage)) {
            $svg .= <<<SVG
  <image href="{$bgImage}" x="0" y="0" width="{$width}" height="{$height}" preserveAspectRatio="xMidYMid slice"/>

SVG;
        }

        // Render each layer
        foreach ($layers as $layer) {
            if (isset($layer['visible']) && $layer['visible'] === false) {
                continue;
            }
            $svg .= $this->renderLayer($layer, $data, $pxPerMm);
        }

        $svg .= "</svg>";

        return $svg;
    }

    /**
     * Render a single layer to SVG
     */
    private function renderLayer(array $layer, array $data, float $pxPerMm): string
    {
        $type = $layer['type'] ?? 'text';
        $frame = $layer['frame'] ?? [];

        // Convert mm to px
        $x = ($frame['x'] ?? 0) * $pxPerMm;
        $y = ($frame['y'] ?? 0) * $pxPerMm;
        $w = ($frame['w'] ?? 0) * $pxPerMm;
        $h = ($frame['h'] ?? 0) * $pxPerMm;

        $opacity = $layer['opacity'] ?? 1;
        $rotation = $layer['rotation'] ?? 0;

        $transform = '';
        if ($rotation != 0) {
            $cx = $x + $w / 2;
            $cy = $y + $h / 2;
            $transform = "transform=\"rotate({$rotation} {$cx} {$cy})\"";
        }

        switch ($type) {
            case 'text':
                return $this->renderTextLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform, $pxPerMm);
            case 'shape':
                return $this->renderShapeLayer($layer, $x, $y, $w, $h, $opacity, $transform, $pxPerMm);
            case 'qr':
                return $this->renderQRLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform);
            case 'barcode':
                return $this->renderBarcodeLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform);
            case 'image':
                return $this->renderImageLayer($layer, $x, $y, $w, $h, $opacity, $transform);
            default:
                return '';
        }
    }

    /**
     * Render text layer
     */
    private function renderTextLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, float $pxPerMm): string
    {
        // Get content directly from layer (new structure)
        $content = $layer['content'] ?? '';

        // Replace placeholders
        $content = $this->replacePlaceholders($content, $data);

        // Get styles directly from layer (new structure)
        // fontSize in editor is CSS px at zoom=100%, which corresponds to 1px per mm in the editor
        // So fontSize 12 means 12px at 1:1 with mm. Scale to actual output px.
        $fontSize = ($layer['fontSize'] ?? 12) * $pxPerMm;
        $color = $layer['color'] ?? '#000000';
        $align = $layer['textAlign'] ?? 'left';
        $fontWeight = $layer['fontWeight'] ?? 'normal';
        $fontFamily = $layer['fontFamily'] ?? 'Inter';

        // Calculate text anchor based on alignment
        $textAnchor = match($align) {
            'center' => 'middle',
            'right' => 'end',
            default => 'start'
        };

        $textX = match($align) {
            'center' => $x + $w / 2,
            'right' => $x + $w,
            default => $x
        };

        // Center vertically
        $textY = $y + $h / 2;

        // Escape HTML entities
        $content = htmlspecialchars($content, ENT_XML1);
        $fontFamily = htmlspecialchars($fontFamily, ENT_XML1);

        return <<<SVG
  <text x="{$textX}" y="{$textY}"
        font-size="{$fontSize}"
        font-family="'{$fontFamily}', sans-serif"
        fill="{$color}"
        opacity="{$opacity}"
        text-anchor="{$textAnchor}"
        font-weight="{$fontWeight}"
        {$transform}
        dominant-baseline="central">
    {$content}
  </text>

SVG;
    }

    /**
     * Render shape layer
     */
    private function renderShapeLayer(array $layer, float $x, float $y, float $w, float $h, float $opacity, string $transform, float $pxPerMm = 1): string
    {
        // Get shape properties directly from layer (new structure)
        $kind = $layer['shapeKind'] ?? 'rect';
        $fill = $layer['fillColor'] ?? '#e5e7eb';
        $stroke = $layer['borderColor'] ?? '#000000';
        // Scale borderWidth and borderRadius with pxPerMm
        $strokeWidth = ($layer['borderWidth'] ?? 1) * $pxPerMm;
        $borderRadius = ($layer['borderRadius'] ?? 0) * $pxPerMm;

        switch ($kind) {
            case 'rect':
                return <<<SVG
  <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}"
        fill="{$fill}" stroke="{$stroke}" stroke-width="{$strokeWidth}"
        rx="{$borderRadius}" ry="{$borderRadius}"
        opacity="{$opacity}" {$transform}/>

SVG;
            case 'circle':
            case 'ellipse':
                $cx = $x + $w / 2;
                $cy = $y + $h / 2;
                $rx = $w / 2;
                $ry = $h / 2;
                return <<<SVG
  <ellipse cx="{$cx}" cy="{$cy}" rx="{$rx}" ry="{$ry}"
          fill="{$fill}" stroke="{$stroke}" stroke-width="{$strokeWidth}"
          opacity="{$opacity}" {$transform}/>

SVG;
            case 'line':
                // Line is drawn horizontally centered in the frame
                $y1 = $y + $h / 2;
                $y2 = $y + $h / 2;
                $x2 = $x + $w;
                return <<<SVG
  <line x1="{$x}" y1="{$y1}" x2="{$x2}" y2="{$y2}"
        stroke="{$stroke}" stroke-width="{$strokeWidth}"
        opacity="{$opacity}" {$transform}/>

SVG;
            default:
                return '';
        }
    }

    /**
     * Render QR code layer
     */
    private function renderQRLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        $props = $layer['props'] ?? [];
        $codeData = $props['data'] ?? 'QR';

        // Replace placeholders
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Use the smaller dimension for a square QR code, centered in the frame
        $size = min($w, $h);
        $qrX = $x + ($w - $size) / 2;
        $qrY = $y + ($h - $size) / 2;

        // Generate a simple QR-like placeholder pattern
        $modules = 25; // Standard QR code has ~25 modules
        $cellSize = $size / $modules;
        $pattern = '';

        // Create finder patterns (corners) - proper nested squares
        $pattern .= $this->generateFinderPattern($qrX + $cellSize, $qrY + $cellSize, $cellSize * 7);
        $pattern .= $this->generateFinderPattern($qrX + $size - $cellSize * 8, $qrY + $cellSize, $cellSize * 7);
        $pattern .= $this->generateFinderPattern($qrX + $cellSize, $qrY + $size - $cellSize * 8, $cellSize * 7);

        // Add alignment pattern (center-right area)
        $ax = $qrX + $size - $cellSize * 9;
        $ay = $qrY + $size - $cellSize * 9;
        $pattern .= "<rect x=\"{$ax}\" y=\"{$ay}\" width=\"" . ($cellSize * 5) . "\" height=\"" . ($cellSize * 5) . "\" fill=\"#000\"/>";
        $pattern .= "<rect x=\"" . ($ax + $cellSize) . "\" y=\"" . ($ay + $cellSize) . "\" width=\"" . ($cellSize * 3) . "\" height=\"" . ($cellSize * 3) . "\" fill=\"white\"/>";
        $pattern .= "<rect x=\"" . ($ax + $cellSize * 2) . "\" y=\"" . ($ay + $cellSize * 2) . "\" width=\"{$cellSize}\" height=\"{$cellSize}\" fill=\"#000\"/>";

        // Add timing patterns (dotted lines connecting finder patterns)
        for ($i = 8; $i < $modules - 8; $i++) {
            if ($i % 2 === 0) {
                $tx = $qrX + $i * $cellSize;
                $pattern .= "<rect x=\"{$tx}\" y=\"" . ($qrY + $cellSize * 6) . "\" width=\"{$cellSize}\" height=\"{$cellSize}\" fill=\"#000\"/>";
                $pattern .= "<rect x=\"" . ($qrX + $cellSize * 6) . "\" y=\"" . ($qrY + $i * $cellSize) . "\" width=\"{$cellSize}\" height=\"{$cellSize}\" fill=\"#000\"/>";
            }
        }

        // Add deterministic data modules based on codeData
        $seed = crc32($codeData);
        srand($seed);
        for ($row = 9; $row < $modules - 1; $row++) {
            for ($col = 9; $col < $modules - 1; $col++) {
                // Skip areas covered by finder and alignment patterns
                if ($row < 9 && $col > $modules - 9) continue;
                if ($row > $modules - 9 && $col < 9) continue;
                if ($row > $modules - 10 && $col > $modules - 10) continue;

                if (rand(0, 100) > 50) {
                    $mx = $qrX + $col * $cellSize;
                    $my = $qrY + $row * $cellSize;
                    $pattern .= "<rect x=\"{$mx}\" y=\"{$my}\" width=\"{$cellSize}\" height=\"{$cellSize}\" fill=\"#000\"/>";
                }
            }
        }

        return <<<SVG
  <g opacity="{$opacity}" {$transform}>
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" fill="white" stroke="#e5e7eb" stroke-width="1"/>
    {$pattern}
  </g>

SVG;
    }

    /**
     * Generate QR finder pattern (the large squares in corners)
     */
    private function generateFinderPattern(float $x, float $y, float $size): string
    {
        $cell = $size / 7;
        $innerX = $x + $cell;
        $innerY = $y + $cell;
        $innerSize = $size - $cell * 2;
        $coreX = $x + $cell * 2;
        $coreY = $y + $cell * 2;
        $coreSize = $size - $cell * 4;

        return <<<SVG
    <rect x="{$x}" y="{$y}" width="{$size}" height="{$size}" fill="#000"/>
    <rect x="{$innerX}" y="{$innerY}" width="{$innerSize}" height="{$innerSize}" fill="white"/>
    <rect x="{$coreX}" y="{$coreY}" width="{$coreSize}" height="{$coreSize}" fill="#000"/>
SVG;
    }

    /**
     * Render barcode layer
     */
    private function renderBarcodeLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        $props = $layer['props'] ?? [];
        $codeData = $props['data'] ?? '123456789';

        // Replace placeholders
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Generate barcode-like pattern based on data
        $bars = '';
        $barWidth = $w / 60;
        $seed = crc32($codeData);
        srand($seed);

        $currentX = $x + $barWidth * 2;
        for ($i = 0; $i < 50; $i++) {
            $isBar = rand(0, 2) !== 0;
            $width = $barWidth * (rand(1, 2));

            if ($isBar) {
                $bars .= "<rect x=\"{$currentX}\" y=\"{$y}\" width=\"{$width}\" height=\"" . ($h * 0.85) . "\" fill=\"#000\"/>";
            }
            $currentX += $width;
        }

        // Add text below barcode
        $textY = $y + $h * 0.95;
        $textX = $x + $w / 2;
        $fontSize = $h * 0.12;
        $displayData = htmlspecialchars(substr($codeData, 0, 20), ENT_XML1);

        return <<<SVG
  <g opacity="{$opacity}" {$transform}>
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" fill="white"/>
    {$bars}
    <text x="{$textX}" y="{$textY}" font-size="{$fontSize}" font-family="monospace" text-anchor="middle" fill="#000">{$displayData}</text>
  </g>

SVG;
    }

    /**
     * Render image layer
     */
    private function renderImageLayer(array $layer, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        // Get src directly from layer (new structure)
        $src = $layer['src'] ?? '';
        $objectFit = $layer['objectFit'] ?? 'contain';

        if (empty($src)) {
            // Placeholder for missing image
            return <<<SVG
  <g opacity="{$opacity}" {$transform}>
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" fill="#f3f4f6" stroke="#d1d5db" stroke-width="1"/>
    <text x="{$x}" y="{$y}" dx="{$w}" dy="{$h}" font-size="12" fill="#9ca3af" text-anchor="end" dominant-baseline="text-after-edge">No Image</text>
  </g>

SVG;
        }

        $preserveAspectRatio = match($objectFit) {
            'cover' => 'xMidYMid slice',
            'fill' => 'none',
            default => 'xMidYMid meet'
        };

        return <<<SVG
  <image href="{$src}" x="{$x}" y="{$y}" width="{$w}" height="{$h}"
         preserveAspectRatio="{$preserveAspectRatio}" opacity="{$opacity}" {$transform}/>

SVG;
    }

    /**
     * Replace placeholders in content with actual data
     */
    private function replacePlaceholders(string $content, array $data): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($data) {
            $path = trim($matches[1]);
            $value = $this->variableService->resolveVariable($path, $data);
            return $value ?? $matches[0]; // Keep placeholder if not found
        }, $content);
    }

    /**
     * Save preview for a template model
     */
    public function saveTemplatePreview(TicketTemplate $template): void
    {
        if (empty($template->template_data)) {
            return;
        }

        $preview = $this->generatePreview($template->template_data);
        $template->update(['preview_image' => $preview['path']]);
    }
}
