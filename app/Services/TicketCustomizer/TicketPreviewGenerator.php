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
        $svg = $this->generateSVG($templateData, $data, $widthPx, $heightPx);

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

        // Note: For production, you would convert SVG to PNG using:
        // - Intervention Image with Imagick
        // - Puppeteer/Headless Chrome
        // - Server-side SVG rasterizer
        // This is left as a placeholder for actual implementation
    }

    /**
     * Generate SVG from template data
     *
     * @param array $templateData
     * @param array $data
     * @param int $width
     * @param int $height
     * @return string SVG content
     */
    private function generateSVG(array $templateData, array $data, int $width, int $height): string
    {
        $meta = $templateData['meta'] ?? [];
        $layers = $templateData['layers'] ?? [];

        // Sort layers by z-index
        usort($layers, fn($a, $b) => ($a['z'] ?? 0) <=> ($b['z'] ?? 0));

        $dpi = $meta['dpi'] ?? 300;
        $scale = $dpi / 25.4; // mm to px conversion

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
      text { font-family: Arial, sans-serif; }
    </style>
  </defs>
  <rect width="100%" height="100%" fill="white"/>

SVG;

        // Render each layer
        foreach ($layers as $layer) {
            $svg .= $this->renderLayer($layer, $data, $scale);
        }

        $svg .= "</svg>";

        return $svg;
    }

    /**
     * Render a single layer to SVG
     */
    private function renderLayer(array $layer, array $data, float $scale): string
    {
        $type = $layer['type'] ?? 'text';
        $frame = $layer['frame'] ?? [];

        // Convert mm to px
        $x = ($frame['x'] ?? 0) * $scale;
        $y = ($frame['y'] ?? 0) * $scale;
        $w = ($frame['w'] ?? 0) * $scale;
        $h = ($frame['h'] ?? 0) * $scale;

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
                return $this->renderTextLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform);
            case 'shape':
                return $this->renderShapeLayer($layer, $x, $y, $w, $h, $opacity, $transform);
            case 'qr':
            case 'barcode':
                return $this->renderCodeLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform, $type);
            case 'image':
                return $this->renderImageLayer($layer, $x, $y, $w, $h, $opacity, $transform);
            default:
                return '';
        }
    }

    /**
     * Render text layer
     */
    private function renderTextLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        $props = $layer['props'] ?? [];
        $content = $props['content'] ?? '';

        // Replace placeholders
        $content = $this->replacePlaceholders($content, $data);

        $fontSize = ($props['size_pt'] ?? 12) * 1.33; // pt to px
        $color = $props['color'] ?? '#000000';
        $align = $props['align'] ?? 'left';
        $fontWeight = $props['weight'] ?? 'normal';

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

        // Escape HTML entities
        $content = htmlspecialchars($content, ENT_XML1);

        return <<<SVG
  <text x="{$textX}" y="{$y}"
        font-size="{$fontSize}"
        fill="{$color}"
        opacity="{$opacity}"
        text-anchor="{$textAnchor}"
        font-weight="{$fontWeight}"
        {$transform}
        dominant-baseline="hanging">
    {$content}
  </text>

SVG;
    }

    /**
     * Render shape layer
     */
    private function renderShapeLayer(array $layer, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        $props = $layer['props'] ?? [];
        $kind = $props['kind'] ?? 'rect';
        $fill = $props['fill'] ?? '#000000';
        $stroke = $props['stroke'] ?? 'none';
        $strokeWidth = $props['stroke_width'] ?? 1;

        switch ($kind) {
            case 'rect':
                return <<<SVG
  <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}"
        fill="{$fill}" stroke="{$stroke}" stroke-width="{$strokeWidth}"
        opacity="{$opacity}" {$transform}/>

SVG;
            case 'circle':
                $cx = $x + $w / 2;
                $cy = $y + $h / 2;
                $r = min($w, $h) / 2;
                return <<<SVG
  <circle cx="{$cx}" cy="{$cy}" r="{$r}"
          fill="{$fill}" stroke="{$stroke}" stroke-width="{$strokeWidth}"
          opacity="{$opacity}" {$transform}/>

SVG;
            case 'line':
                $x2 = $x + $w;
                $y2 = $y + $h;
                return <<<SVG
  <line x1="{$x}" y1="{$y}" x2="{$x2}" y2="{$y2}"
        stroke="{$stroke}" stroke-width="{$strokeWidth}"
        opacity="{$opacity}" {$transform}/>

SVG;
            default:
                return '';
        }
    }

    /**
     * Render QR/Barcode layer (placeholder)
     */
    private function renderCodeLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, string $type): string
    {
        $props = $layer['props'] ?? [];
        $codeData = $props['data'] ?? '';

        // Replace placeholders
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Placeholder representation
        $label = $type === 'qr' ? 'QR Code' : 'Barcode';

        return <<<SVG
  <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}"
        fill="#eeeeee" stroke="#333333" stroke-width="1"
        opacity="{$opacity}" {$transform}/>
  <text x="{$x}" y="{$y}" font-size="10" fill="#666666"
        dominant-baseline="hanging">
    {$label}
  </text>
  <text x="{$x}" y="{$y}" dy="12" font-size="8" fill="#999999"
        dominant-baseline="hanging">
    {$codeData}
  </text>

SVG;
        // Note: Real implementation would use a barcode/QR library
        // to generate actual barcode images
    }

    /**
     * Render image layer (placeholder)
     */
    private function renderImageLayer(array $layer, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        $props = $layer['props'] ?? [];
        $assetId = $props['asset_id'] ?? '';

        // Placeholder
        return <<<SVG
  <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}"
        fill="#dddddd" stroke="#999999" stroke-width="1"
        opacity="{$opacity}" {$transform}/>
  <text x="{$x}" y="{$y}" font-size="10" fill="#666666"
        dominant-baseline="hanging">
    Image: {$assetId}
  </text>

SVG;
        // Note: Real implementation would embed actual images
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
        $preview = $this->generatePreview($template->template_data);
        $template->update(['preview_image' => $preview['path']]);
    }
}
