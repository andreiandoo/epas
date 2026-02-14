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
     * Render template to SVG string (for PDF generation or inline use)
     *
     * @param array $templateData Template JSON
     * @param array|null $data Custom data or use sample data
     * @param int $scale Scale factor (1 = 1:1, 2 = 2x for retina)
     * @return string SVG markup
     */
    public function renderToSvg(array $templateData, ?array $data = null, int $scale = 1): string
    {
        $data = $data ?? $this->variableService->getSampleData();

        $meta = $templateData['meta'] ?? [];
        $dpi = $meta['dpi'] ?? 300;
        $sizeW = $meta['size_mm']['w'] ?? 80;
        $sizeH = $meta['size_mm']['h'] ?? 200;

        $widthPx = (int) round(($sizeW / 25.4) * $dpi * $scale);
        $heightPx = (int) round(($sizeH / 25.4) * $dpi * $scale);

        return $this->generateSVG($templateData, $data, $widthPx, $heightPx, $scale);
    }

    /**
     * Render template to a DomPDF-compatible HTML string.
     * Uses absolute-positioned divs instead of SVG for reliable PDF generation.
     */
    public function renderToHtml(array $templateData, ?array $data = null): string
    {
        $data = $data ?? $this->variableService->getSampleData();

        $meta = $templateData['meta'] ?? [];
        $sizeW = $meta['size_mm']['w'] ?? 80;
        $sizeH = $meta['size_mm']['h'] ?? 200;
        $layers = $templateData['layers'] ?? [];

        // Sort layers by z-index
        usort($layers, fn($a, $b) => ($a['z'] ?? 0) <=> ($b['z'] ?? 0));

        // Background
        $background = $meta['background'] ?? [];
        $bgColor = $background['color'] ?? '#ffffff';
        $bgImage = $background['image'] ?? '';

        $bgStyle = "background-color: {$bgColor};";
        if (!empty($bgImage)) {
            $bgDataUri = $this->fetchImageAsDataUri($bgImage);
            if ($bgDataUri) {
                $posX = $background['positionX'] ?? 50;
                $posY = $background['positionY'] ?? 50;
                $bgStyle .= " background-image: url('{$bgDataUri}'); background-size: cover; background-position: {$posX}% {$posY}%;";
            }
        }

        $html = '';

        foreach ($layers as $index => $layer) {
            if (isset($layer['visible']) && $layer['visible'] === false) {
                continue;
            }
            $html .= $this->renderLayerAsHtml($layer, $data, $index);
        }

        return <<<HTML
<div style="position: relative; width: {$sizeW}mm; height: {$sizeH}mm; overflow: hidden; {$bgStyle}">
{$html}
</div>
HTML;
    }

    /**
     * Render a single layer as HTML/CSS (for DomPDF)
     */
    private function renderLayerAsHtml(array $layer, array $data, int $zIndex): string
    {
        $type = $layer['type'] ?? 'text';
        $frame = $layer['frame'] ?? [];

        $x = $frame['x'] ?? 0;
        $y = $frame['y'] ?? 0;
        $w = $frame['w'] ?? 0;
        $h = $frame['h'] ?? 0;
        $opacity = $layer['opacity'] ?? 1;
        $rotation = $layer['rotation'] ?? 0;

        $transform = $rotation != 0 ? "transform: rotate({$rotation}deg);" : '';

        switch ($type) {
            case 'text':
                return $this->renderTextLayerHtml($layer, $data, $x, $y, $w, $h, $opacity, $transform, $zIndex);
            case 'shape':
                return $this->renderShapeLayerHtml($layer, $x, $y, $w, $h, $opacity, $transform, $zIndex);
            case 'qr':
                return $this->renderQRLayerHtml($layer, $data, $x, $y, $w, $h, $opacity, $transform, $zIndex);
            case 'barcode':
                return $this->renderBarcodeLayerHtml($layer, $data, $x, $y, $w, $h, $opacity, $transform, $zIndex);
            case 'image':
                return $this->renderImageLayerHtml($layer, $data, $x, $y, $w, $h, $opacity, $transform, $zIndex);
            default:
                return '';
        }
    }

    private function renderTextLayerHtml(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, int $zIndex): string
    {
        $content = $layer['content'] ?? '';
        $content = $this->replacePlaceholders($content, $data);
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $fontSize = $layer['fontSize'] ?? 12;
        $color = $layer['color'] ?? '#000000';
        $align = $layer['textAlign'] ?? 'left';
        $fontWeight = $layer['fontWeight'] ?? 'normal';
        $fontFamily = htmlspecialchars($layer['fontFamily'] ?? 'sans-serif', ENT_QUOTES, 'UTF-8');

        // Vertical alignment
        $lineHeight = $h;
        $display = 'flex';

        return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm; overflow: hidden; opacity: {$opacity}; z-index: {$zIndex}; {$transform}
    font-size: {$fontSize}mm; color: {$color}; text-align: {$align}; font-weight: {$fontWeight}; font-family: '{$fontFamily}', sans-serif;
    line-height: {$h}mm; white-space: nowrap;">{$content}</div>

HTML;
    }

    private function renderShapeLayerHtml(array $layer, float $x, float $y, float $w, float $h, float $opacity, string $transform, int $zIndex): string
    {
        $kind = $layer['shapeKind'] ?? 'rect';
        $fill = $layer['fillColor'] ?? '#e5e7eb';
        $stroke = $layer['borderColor'] ?? 'transparent';
        $strokeWidth = $layer['borderWidth'] ?? 0;
        $borderRadius = $layer['borderRadius'] ?? 0;

        $borderStyle = $strokeWidth > 0 ? "border: {$strokeWidth}mm solid {$stroke};" : '';
        $radiusStyle = '';

        if ($kind === 'circle' || $kind === 'ellipse') {
            $radiusStyle = 'border-radius: 50%;';
        } elseif ($borderRadius > 0) {
            $radiusStyle = "border-radius: {$borderRadius}mm;";
        }

        if ($kind === 'line') {
            $lineY = $y + $h / 2;
            $lineH = max($strokeWidth, 0.3);
            return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$lineY}mm; width: {$w}mm; height: {$lineH}mm; background-color: {$stroke}; opacity: {$opacity}; z-index: {$zIndex}; {$transform}"></div>

HTML;
        }

        return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm; background-color: {$fill}; {$borderStyle} {$radiusStyle} opacity: {$opacity}; z-index: {$zIndex}; {$transform}"></div>

HTML;
    }

    private function renderQRLayerHtml(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, int $zIndex): string
    {
        $codeData = $layer['qrData'] ?? $layer['props']['data'] ?? 'QR';
        $codeData = $this->replacePlaceholders($codeData, $data);

        $size = min($w, $h);
        $qrX = $x + ($w - $size) / 2;
        $qrY = $y + ($h - $size) / 2;

        // Fetch real QR code image as base64 data URI
        $qrDataUri = $this->fetchQrAsDataUri($codeData);

        return <<<HTML
<div style="position: absolute; left: {$qrX}mm; top: {$qrY}mm; width: {$size}mm; height: {$size}mm; opacity: {$opacity}; z-index: {$zIndex}; {$transform}">
    <img src="{$qrDataUri}" style="width: 100%; height: 100%;">
</div>

HTML;
    }

    private function renderBarcodeLayerHtml(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, int $zIndex): string
    {
        $codeData = $layer['barcodeData'] ?? $layer['props']['data'] ?? '123456789';
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Fetch barcode image as base64 data URI
        $barcodeDataUri = $this->fetchBarcodeAsDataUri($codeData);

        return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm; opacity: {$opacity}; z-index: {$zIndex}; {$transform} text-align: center;">
    <img src="{$barcodeDataUri}" style="width: 100%; height: 80%;">
    <div style="font-family: 'Courier New', monospace; font-size: 2.5mm; text-align: center; margin-top: 0.5mm;">{$codeData}</div>
</div>

HTML;
    }

    private function renderImageLayerHtml(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform, int $zIndex): string
    {
        $src = $layer['src'] ?? '';
        $objectFit = $layer['objectFit'] ?? 'contain';

        if (!empty($src)) {
            $src = $this->replacePlaceholders($src, $data);
        }

        if (empty($src)) {
            return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm; background-color: #f3f4f6; border: 0.3mm solid #d1d5db; opacity: {$opacity}; z-index: {$zIndex}; {$transform}"></div>

HTML;
        }

        // Try to fetch image as data URI for reliable PDF embedding
        $dataUri = $this->fetchImageAsDataUri($src);
        $imgSrc = $dataUri ?: $src;

        return <<<HTML
<div style="position: absolute; left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm; overflow: hidden; opacity: {$opacity}; z-index: {$zIndex}; {$transform}">
    <img src="{$imgSrc}" style="width: 100%; height: 100%; object-fit: {$objectFit};">
</div>

HTML;
    }

    /**
     * Fetch an image URL and return as base64 data URI
     */
    private function fetchImageAsDataUri(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Already a data URI
        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $imageData = @file_get_contents($url, false, $context);
            if ($imageData !== false) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($imageData) ?: 'image/png';
                return "data:{$mimeType};base64," . base64_encode($imageData);
            }
        } catch (\Throwable $e) {
            // Fail silently
        }

        return null;
    }

    /**
     * Fetch a QR code image as base64 data URI
     */
    private function fetchQrAsDataUri(string $data): string
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '400x400',
            'data' => $data,
            'color' => '000000',
            'margin' => '0',
            'format' => 'png',
        ]);

        $result = $this->fetchImageAsDataUri($url);
        if ($result) {
            return $result;
        }

        // Fallback: simple black square placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="#f3f4f6"/><text x="100" y="100" text-anchor="middle" font-size="14" fill="#666">QR</text></svg>'
        );
    }

    /**
     * Fetch a barcode image as base64 data URI
     */
    private function fetchBarcodeAsDataUri(string $data): string
    {
        $url = 'https://barcodeapi.org/api/128/' . urlencode($data);

        $result = $this->fetchImageAsDataUri($url);
        if ($result) {
            return $result;
        }

        // Fallback placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><rect width="300" height="100" fill="#f3f4f6"/><text x="150" y="50" text-anchor="middle" font-size="14" fill="#666">' . htmlspecialchars($data) . '</text></svg>'
        );
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
            $posX = $background['positionX'] ?? 50;
            $posY = $background['positionY'] ?? 50;
            // Map percentage position to SVG preserveAspectRatio values
            $xAlign = $posX <= 25 ? 'xMin' : ($posX >= 75 ? 'xMax' : 'xMid');
            $yAlign = $posY <= 25 ? 'YMin' : ($posY >= 75 ? 'YMax' : 'YMid');
            $preserveAspectRatio = "{$xAlign}{$yAlign} slice";

            $svg .= <<<SVG
  <image href="{$bgImage}" x="0" y="0" width="{$width}" height="{$height}" preserveAspectRatio="{$preserveAspectRatio}"/>

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
                return $this->renderImageLayer($layer, $data, $x, $y, $w, $h, $opacity, $transform);
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
        // Support both old (props.data) and new (qrData) structure
        $codeData = $layer['qrData'] ?? $layer['props']['data'] ?? 'QR';
        $foreground = $layer['qrForeground'] ?? '#000000';
        $background = $layer['qrBackground'] ?? '#ffffff';

        // Replace placeholders
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Use the smaller dimension for a square QR code, centered in the frame
        $size = min($w, $h);
        $qrX = $x + ($w - $size) / 2;
        $qrY = $y + ($h - $size) / 2;

        // Generate QR code matrix
        $modules = 25;
        $matrix = $this->generateQRMatrix($codeData, $modules);
        $cellSize = $size / $modules;

        // Build SVG path for all black modules (more efficient than individual rects)
        $pathData = '';
        for ($row = 0; $row < $modules; $row++) {
            for ($col = 0; $col < $modules; $col++) {
                if ($matrix[$row][$col]) {
                    $mx = $qrX + $col * $cellSize;
                    $my = $qrY + $row * $cellSize;
                    $pathData .= "M{$mx},{$my}h{$cellSize}v{$cellSize}h-{$cellSize}Z ";
                }
            }
        }

        return <<<SVG
  <g opacity="{$opacity}" {$transform}>
    <rect x="{$qrX}" y="{$qrY}" width="{$size}" height="{$size}" fill="{$background}"/>
    <path d="{$pathData}" fill="{$foreground}"/>
  </g>

SVG;
    }

    /**
     * Generate a QR code matrix (simplified but realistic-looking)
     */
    private function generateQRMatrix(string $data, int $size): array
    {
        // Initialize matrix with false (white)
        $matrix = array_fill(0, $size, array_fill(0, $size, false));

        // Add finder patterns (top-left, top-right, bottom-left)
        $this->addFinderPattern($matrix, 0, 0);
        $this->addFinderPattern($matrix, $size - 7, 0);
        $this->addFinderPattern($matrix, 0, $size - 7);

        // Add separators around finder patterns (white border)
        $this->addSeparators($matrix, $size);

        // Add timing patterns (alternating black/white lines)
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2 === 0);
            $matrix[$i][6] = ($i % 2 === 0);
        }

        // Add alignment pattern (for version 2+ QR codes)
        if ($size >= 25) {
            $this->addAlignmentPattern($matrix, $size - 9, $size - 9);
        }

        // Add dark module (always present)
        $matrix[$size - 8][8] = true;

        // Fill data area with deterministic pattern based on input data
        $hash = md5($data);
        $hashIndex = 0;

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                // Skip reserved areas
                if ($this->isReservedArea($row, $col, $size)) {
                    continue;
                }

                // Use hash to determine if module is black
                $charValue = hexdec($hash[$hashIndex % 32]);
                $bitPosition = ($row + $col) % 4;
                $matrix[$row][$col] = (($charValue >> $bitPosition) & 1) === 1;
                $hashIndex++;
            }
        }

        return $matrix;
    }

    /**
     * Add finder pattern to matrix
     */
    private function addFinderPattern(array &$matrix, int $startX, int $startY): void
    {
        // Outer black square (7x7)
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                // Outer ring or center square
                $isOuter = ($i === 0 || $i === 6 || $j === 0 || $j === 6);
                $isInner = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                $matrix[$startY + $i][$startX + $j] = $isOuter || $isInner;
            }
        }
    }

    /**
     * Add separators (white space around finder patterns)
     */
    private function addSeparators(array &$matrix, int $size): void
    {
        // Top-left separator
        for ($i = 0; $i < 8; $i++) {
            if ($i < $size) {
                $matrix[7][$i] = false;
                $matrix[$i][7] = false;
            }
        }
        // Top-right separator
        for ($i = 0; $i < 8; $i++) {
            if ($size - 8 + $i < $size) {
                $matrix[7][$size - 8 + $i] = false;
            }
            $matrix[$i][$size - 8] = false;
        }
        // Bottom-left separator
        for ($i = 0; $i < 8; $i++) {
            $matrix[$size - 8][$i] = false;
            if ($size - 8 + $i < $size) {
                $matrix[$size - 8 + $i][7] = false;
            }
        }
    }

    /**
     * Add alignment pattern
     */
    private function addAlignmentPattern(array &$matrix, int $centerX, int $centerY): void
    {
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $isOuter = (abs($i) === 2 || abs($j) === 2);
                $isCenter = ($i === 0 && $j === 0);
                $matrix[$centerY + $i][$centerX + $j] = $isOuter || $isCenter;
            }
        }
    }

    /**
     * Check if position is in a reserved area
     */
    private function isReservedArea(int $row, int $col, int $size): bool
    {
        // Finder pattern areas + separators
        if ($row < 9 && $col < 9) return true; // Top-left
        if ($row < 9 && $col >= $size - 8) return true; // Top-right
        if ($row >= $size - 8 && $col < 9) return true; // Bottom-left

        // Timing patterns
        if ($row === 6 || $col === 6) return true;

        // Alignment pattern area (for version 2+)
        if ($size >= 25 && $row >= $size - 11 && $row <= $size - 7 && $col >= $size - 11 && $col <= $size - 7) {
            return true;
        }

        return false;
    }

    /**
     * Render barcode layer
     */
    private function renderBarcodeLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        // Support both old (props.data) and new (barcodeData) structure
        $codeData = $layer['barcodeData'] ?? $layer['props']['data'] ?? '123456789';
        $foreground = $layer['barcodeForeground'] ?? '#000000';
        $background = $layer['barcodeBackground'] ?? '#ffffff';

        // Replace placeholders
        $codeData = $this->replacePlaceholders($codeData, $data);

        // Generate Code128-style barcode pattern
        $barHeight = $h * 0.80;
        $barY = $y + ($h - $barHeight) * 0.3; // Position bars in upper portion

        // Calculate bar dimensions for a proper barcode
        $quietZone = $w * 0.05; // 5% quiet zone on each side
        $availableWidth = $w - (2 * $quietZone);
        $unitWidth = $availableWidth / 95; // Code128 has ~95 modules for short codes

        // Generate deterministic pattern from data
        $pattern = $this->generateBarcodePattern($codeData);
        $bars = '';
        $currentX = $x + $quietZone;

        foreach ($pattern as $bar) {
            $barW = $bar['width'] * $unitWidth;
            if ($bar['black']) {
                $bars .= "<rect x=\"{$currentX}\" y=\"{$barY}\" width=\"{$barW}\" height=\"{$barHeight}\" fill=\"{$foreground}\"/>";
            }
            $currentX += $barW;
        }

        // Add text below barcode
        $textY = $y + $h - ($h * 0.05);
        $textX = $x + $w / 2;
        $fontSize = min($h * 0.12, 14);
        $displayData = htmlspecialchars(substr($codeData, 0, 20), ENT_XML1);

        return <<<SVG
  <g opacity="{$opacity}" {$transform}>
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" fill="{$background}"/>
    {$bars}
    <text x="{$textX}" y="{$textY}" font-size="{$fontSize}" font-family="'Courier New', monospace" text-anchor="middle" fill="{$foreground}">{$displayData}</text>
  </g>

SVG;
    }

    /**
     * Generate a Code128-like barcode pattern
     */
    private function generateBarcodePattern(string $data): array
    {
        $pattern = [];

        // Start code (Code128 B start pattern: 211214)
        $startPattern = [2, 1, 1, 2, 1, 4];
        foreach ($startPattern as $i => $width) {
            $pattern[] = ['width' => $width, 'black' => ($i % 2 === 0)];
        }

        // Generate data pattern based on string hash
        $hash = md5($data);
        for ($i = 0; $i < min(strlen($data) * 2, 12); $i++) {
            $charCode = hexdec($hash[$i % 32]);

            // Code128 patterns alternate between bars and spaces
            // Width can be 1, 2, 3, or 4 units
            $widths = [
                (($charCode >> 0) & 3) + 1,
                (($charCode >> 2) & 3) + 1,
                (($charCode >> 4) & 3) + 1,
                (($charCode >> 6) & 1) + 1,
                2, // separator
                1, // space
            ];

            foreach ($widths as $j => $width) {
                $pattern[] = ['width' => $width, 'black' => ($j % 2 === 0)];
            }
        }

        // Stop code (Code128 stop pattern: 2331112)
        $stopPattern = [2, 3, 3, 1, 1, 1, 2];
        foreach ($stopPattern as $i => $width) {
            $pattern[] = ['width' => $width, 'black' => ($i % 2 === 0)];
        }

        return $pattern;
    }

    /**
     * Render image layer
     */
    private function renderImageLayer(array $layer, array $data, float $x, float $y, float $w, float $h, float $opacity, string $transform): string
    {
        // Get src directly from layer (new structure)
        $src = $layer['src'] ?? '';
        $objectFit = $layer['objectFit'] ?? 'contain';

        // Replace placeholders in image src (e.g. {{event.image}})
        if (!empty($src)) {
            $src = $this->replacePlaceholders($src, $data);
        }

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
