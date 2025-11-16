<?php

namespace App\Services\TicketCustomizer;

/**
 * Ticket Template Validator
 *
 * Validates ticket template JSON schema and content
 */
class TicketTemplateValidator
{
    private array $warnings = [];
    private array $errors = [];

    /**
     * Validate template JSON
     *
     * @param array $template Template data
     * @return array ['ok' => bool, 'warnings' => [], 'errors' => []]
     */
    public function validate(array $template): array
    {
        $this->warnings = [];
        $this->errors = [];

        // Validate structure
        $this->validateStructure($template);

        // Validate metadata
        if (isset($template['meta'])) {
            $this->validateMeta($template['meta']);
        }

        // Validate layers
        if (isset($template['layers'])) {
            $this->validateLayers($template['layers']);
        }

        // Validate assets
        if (isset($template['assets'])) {
            $this->validateAssets($template['assets']);
        }

        return [
            'ok' => empty($this->errors),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }

    /**
     * Validate template structure
     */
    private function validateStructure(array $template): void
    {
        $requiredKeys = ['meta', 'layers'];

        foreach ($requiredKeys as $key) {
            if (!isset($template[$key])) {
                $this->errors[] = "Missing required key: {$key}";
            }
        }

        if (!isset($template['assets'])) {
            $this->warnings[] = "No assets defined (images/fonts)";
        }
    }

    /**
     * Validate metadata
     */
    private function validateMeta(array $meta): void
    {
        // Required meta fields
        $required = ['version', 'dpi', 'size_mm', 'orientation'];

        foreach ($required as $field) {
            if (!isset($meta[$field])) {
                $this->errors[] = "Missing required meta field: {$field}";
            }
        }

        // Validate DPI
        if (isset($meta['dpi'])) {
            $dpi = $meta['dpi'];
            if (!is_numeric($dpi) || $dpi < 72 || $dpi > 600) {
                $this->warnings[] = "Unusual DPI value: {$dpi}. Recommended: 300 for print, 72-150 for digital.";
            }
        }

        // Validate size
        if (isset($meta['size_mm'])) {
            if (!isset($meta['size_mm']['w']) || !isset($meta['size_mm']['h'])) {
                $this->errors[] = "size_mm must have 'w' and 'h' properties";
            } else {
                $w = $meta['size_mm']['w'];
                $h = $meta['size_mm']['h'];

                if ($w <= 0 || $h <= 0) {
                    $this->errors[] = "Size dimensions must be positive";
                }

                if ($w > 300 || $h > 500) {
                    $this->warnings[] = "Unusually large ticket size: {$w}x{$h}mm";
                }
            }
        }

        // Validate orientation
        if (isset($meta['orientation']) && !in_array($meta['orientation'], ['portrait', 'landscape'])) {
            $this->errors[] = "Invalid orientation. Must be 'portrait' or 'landscape'";
        }

        // Validate bleed if present
        if (isset($meta['bleed_mm'])) {
            $bleed = $meta['bleed_mm'];
            if (!is_array($bleed)) {
                $this->errors[] = "bleed_mm must be an object with top/right/bottom/left";
            }
        }
    }

    /**
     * Validate layers
     */
    private function validateLayers(array $layers): void
    {
        if (empty($layers)) {
            $this->warnings[] = "Template has no layers (empty design)";
            return;
        }

        $layerIds = [];

        foreach ($layers as $index => $layer) {
            $this->validateLayer($layer, $index);

            // Check for duplicate IDs
            if (isset($layer['id'])) {
                if (in_array($layer['id'], $layerIds)) {
                    $this->errors[] = "Duplicate layer ID: {$layer['id']}";
                }
                $layerIds[] = $layer['id'];
            }
        }

        // Check z-index ordering
        $this->validateZIndexOrdering($layers);
    }

    /**
     * Validate individual layer
     */
    private function validateLayer(array $layer, int $index): void
    {
        // Required fields
        $required = ['id', 'type', 'frame'];

        foreach ($required as $field) {
            if (!isset($layer[$field])) {
                $this->errors[] = "Layer {$index}: Missing required field '{$field}'";
            }
        }

        // Validate type
        $validTypes = ['text', 'image', 'qr', 'barcode', 'shape'];
        if (isset($layer['type']) && !in_array($layer['type'], $validTypes)) {
            $this->errors[] = "Layer {$index}: Invalid type '{$layer['type']}'";
        }

        // Validate frame
        if (isset($layer['frame'])) {
            $this->validateFrame($layer['frame'], $index);
        }

        // Validate props based on type
        if (isset($layer['type']) && isset($layer['props'])) {
            $this->validateLayerProps($layer['type'], $layer['props'], $index);
        }

        // Validate opacity
        if (isset($layer['opacity'])) {
            $opacity = $layer['opacity'];
            if ($opacity < 0 || $opacity > 1) {
                $this->errors[] = "Layer {$index}: Opacity must be between 0 and 1";
            }
        }

        // Validate rotation
        if (isset($layer['rotation'])) {
            $rotation = $layer['rotation'];
            if ($rotation < -360 || $rotation > 360) {
                $this->warnings[] = "Layer {$index}: Unusual rotation value: {$rotation}°";
            }
        }
    }

    /**
     * Validate layer frame (position and size)
     */
    private function validateFrame(array $frame, int $layerIndex): void
    {
        $required = ['x', 'y', 'w', 'h'];

        foreach ($required as $field) {
            if (!isset($frame[$field])) {
                $this->errors[] = "Layer {$layerIndex} frame: Missing '{$field}'";
            }
        }

        // Check for negative or zero dimensions
        if (isset($frame['w']) && $frame['w'] <= 0) {
            $this->errors[] = "Layer {$layerIndex}: Width must be positive";
        }

        if (isset($frame['h']) && $frame['h'] <= 0) {
            $this->errors[] = "Layer {$layerIndex}: Height must be positive";
        }
    }

    /**
     * Validate layer properties based on type
     */
    private function validateLayerProps(string $type, array $props, int $layerIndex): void
    {
        switch ($type) {
            case 'text':
                $this->validateTextProps($props, $layerIndex);
                break;
            case 'image':
                $this->validateImageProps($props, $layerIndex);
                break;
            case 'qr':
                $this->validateQRProps($props, $layerIndex);
                break;
            case 'barcode':
                $this->validateBarcodeProps($props, $layerIndex);
                break;
            case 'shape':
                $this->validateShapeProps($props, $layerIndex);
                break;
        }
    }

    /**
     * Validate text layer properties
     */
    private function validateTextProps(array $props, int $layerIndex): void
    {
        if (!isset($props['content'])) {
            $this->errors[] = "Layer {$layerIndex}: Text layer missing 'content'";
        }

        if (!isset($props['font_family'])) {
            $this->warnings[] = "Layer {$layerIndex}: No font family specified";
        }

        // Check font size
        if (isset($props['size_pt'])) {
            $size = $props['size_pt'];
            if ($size < 4) {
                $this->warnings[] = "Layer {$layerIndex}: Very small font size ({$size}pt) - may not be legible";
            }
            if ($size > 144) {
                $this->warnings[] = "Layer {$layerIndex}: Very large font size ({$size}pt)";
            }
        }

        // Validate alignment
        if (isset($props['align']) && !in_array($props['align'], ['left', 'center', 'right', 'justify'])) {
            $this->errors[] = "Layer {$layerIndex}: Invalid text alignment";
        }
    }

    /**
     * Validate image layer properties
     */
    private function validateImageProps(array $props, int $layerIndex): void
    {
        if (!isset($props['asset_id'])) {
            $this->errors[] = "Layer {$layerIndex}: Image layer missing 'asset_id'";
        }

        if (isset($props['fit']) && !in_array($props['fit'], ['cover', 'contain', 'fill'])) {
            $this->warnings[] = "Layer {$layerIndex}: Unknown fit mode '{$props['fit']}'";
        }
    }

    /**
     * Validate QR code properties
     */
    private function validateQRProps(array $props, int $layerIndex): void
    {
        if (!isset($props['data'])) {
            $this->errors[] = "Layer {$layerIndex}: QR code missing 'data'";
        }

        if (isset($props['ec_level']) && !in_array($props['ec_level'], ['L', 'M', 'Q', 'H'])) {
            $this->warnings[] = "Layer {$layerIndex}: Invalid QR error correction level";
        }
    }

    /**
     * Validate barcode properties
     */
    private function validateBarcodeProps(array $props, int $layerIndex): void
    {
        if (!isset($props['data'])) {
            $this->errors[] = "Layer {$layerIndex}: Barcode missing 'data'";
        }

        if (!isset($props['symbology'])) {
            $this->errors[] = "Layer {$layerIndex}: Barcode missing 'symbology'";
        } else {
            $validSymbologies = ['code128', 'ean13', 'pdf417', 'qr', 'datamatrix'];
            if (!in_array(strtolower($props['symbology']), $validSymbologies)) {
                $this->warnings[] = "Layer {$layerIndex}: Unknown barcode symbology '{$props['symbology']}'";
            }
        }
    }

    /**
     * Validate shape properties
     */
    private function validateShapeProps(array $props, int $layerIndex): void
    {
        if (!isset($props['kind'])) {
            $this->errors[] = "Layer {$layerIndex}: Shape missing 'kind'";
        } else {
            $validKinds = ['rect', 'line', 'circle', 'ellipse'];
            if (!in_array($props['kind'], $validKinds)) {
                $this->errors[] = "Layer {$layerIndex}: Unknown shape kind '{$props['kind']}'";
            }
        }
    }

    /**
     * Validate z-index ordering
     */
    private function validateZIndexOrdering(array $layers): void
    {
        $zIndexes = array_map(fn($layer) => $layer['z'] ?? 0, $layers);

        // Check for conflicts (multiple layers with same z-index)
        $counts = array_count_values($zIndexes);
        foreach ($counts as $z => $count) {
            if ($count > 1) {
                $this->warnings[] = "Multiple layers ({$count}) have z-index {$z} - render order may be unpredictable";
            }
        }
    }

    /**
     * Validate assets
     */
    private function validateAssets(array $assets): void
    {
        foreach ($assets as $index => $asset) {
            if (!isset($asset['id'])) {
                $this->errors[] = "Asset {$index}: Missing 'id'";
            }

            if (!isset($asset['kind'])) {
                $this->errors[] = "Asset {$index}: Missing 'kind'";
            } else {
                $validKinds = ['image', 'font'];
                if (!in_array($asset['kind'], $validKinds)) {
                    $this->errors[] = "Asset {$index}: Invalid kind '{$asset['kind']}'";
                }
            }

            if (!isset($asset['src'])) {
                $this->errors[] = "Asset {$index}: Missing 'src'";
            }
        }
    }
}
