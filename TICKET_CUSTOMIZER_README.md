# Ticket Customizer Component - Complete Documentation

**Microservice Price:** 30 EUR (one-time payment)
**Category:** Design & Customization
**Version:** 1.0.0

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Installation](#installation)
5. [Usage](#usage)
   - [Admin Panel](#admin-panel)
   - [Visual Editor](#visual-editor)
   - [API Integration](#api-integration)
6. [Template JSON Schema](#template-json-schema)
7. [Variable System](#variable-system)
8. [Layer Types](#layer-types)
9. [API Reference](#api-reference)
10. [Frontend Integration](#frontend-integration)
11. [Advanced Usage](#advanced-usage)
12. [Troubleshooting](#troubleshooting)

---

## Overview

The **Ticket Customizer Component** is a comprehensive WYSIWYG editor for designing custom ticket templates with print-ready output. It provides a complete solution for creating professional ticket designs with drag-and-drop functionality, real-time preview, and dynamic variable placeholders.

### Key Capabilities

- **Visual Design**: Drag-and-drop WYSIWYG editor with real mm/DPI measurements
- **Print-Ready**: Professional output with bleed, safe area, and high-resolution preview
- **Dynamic Content**: Variable placeholders that populate with real event/ticket data
- **Multi-Tenant**: Full tenant isolation with per-tenant templates and defaults
- **Versioning**: Template version control with history tracking
- **API-First**: Complete REST API for headless integration

---

## Features

### Core Features

✅ **WYSIWYG Visual Editor**
- Drag-and-drop interface for positioning elements
- Real-time canvas preview
- Zoom controls (25%-800%)
- Rulers and guides in millimeters
- Snap to grid functionality

✅ **Print Accuracy**
- Real mm/DPI measurements
- Configurable DPI (72-600)
- Bleed area visualization
- Safe area guides
- Print-ready dimensions

✅ **Layer Management**
- Multiple layer types: text, image, QR, barcode, shape
- Z-index ordering (bring to front/send to back)
- Lock/unlock layers
- Show/hide layers
- Layer grouping and selection

✅ **Variable Placeholders**
- Dynamic content injection: `{{event.name}}`, `{{ticket.section}}`, etc.
- 9 variable categories with 30+ variables
- Real-time preview with sample data
- Nested variable support

✅ **Template Management**
- Save, load, and duplicate templates
- Version control with parent tracking
- Set default templates per tenant
- Status workflow: draft → active → archived
- Soft delete with restore

✅ **Export & Preview**
- SVG preview generation
- High-resolution PNG export (@2x)
- Template JSON export
- Print-ready file preparation

### Layer Types

1. **Text Layers**
   - Multiple fonts and sizes
   - Color, alignment, weight
   - Line height and spacing
   - Variable placeholder support

2. **Image Layers**
   - Upload logos and graphics
   - Fit modes: cover, contain, fill
   - Asset management

3. **QR Code Layers**
   - Dynamic QR generation
   - Error correction levels (L, M, Q, H)
   - Variable data support

4. **Barcode Layers**
   - Code128, EAN-13, PDF417
   - Configurable dimensions
   - Variable data support

5. **Shape Layers**
   - Rectangles, circles, lines
   - Fill and stroke colors
   - Border width control

---

## Architecture

### Backend Components

```
app/
├── Models/
│   └── TicketTemplate.php              # Eloquent model with versioning
├── Http/Controllers/Api/
│   └── TicketTemplateController.php    # REST API controller
├── Services/TicketCustomizer/
│   ├── TicketVariableService.php       # Variable definitions & resolution
│   ├── TicketTemplateValidator.php     # JSON schema validation
│   └── TicketPreviewGenerator.php      # SVG/PNG preview generation
├── Filament/Resources/TicketTemplates/
│   ├── TicketTemplateResource.php      # Admin interface
│   └── Pages/
│       ├── ListTicketTemplates.php
│       ├── CreateTicketTemplate.php
│       └── EditTicketTemplate.php
```

### Frontend Components

```
resources/js/components/TicketCustomizer/
├── types.ts                # TypeScript type definitions
├── api.ts                  # API client
├── TicketCustomizer.tsx    # Main WYSIWYG component
├── index.ts                # Entry point
└── README.md              # Frontend documentation
```

### Database Schema

```sql
ticket_templates
├── id (uuid, primary key)
├── tenant_id (foreign key to tenants)
├── name (string)
├── description (text, nullable)
├── status (enum: draft, active, archived)
├── template_data (json)
├── preview_image (string, nullable)
├── version (integer)
├── parent_id (uuid, nullable, self-reference)
├── is_default (boolean)
├── created_at, updated_at
└── deleted_at (soft delete)
```

---

## Installation

### Prerequisites

- PHP 8.2+
- Laravel 12+
- Node.js 18+ and npm
- GD or Imagick extension
- Storage disk configured

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `ticket_templates` table.

### Step 2: Install Frontend Dependencies

```bash
npm install react react-dom @types/react @types/react-dom axios
npm install --save-dev @vitejs/plugin-react
```

### Step 3: Configure Vite

Update `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        react(),
    ],
});
```

### Step 4: Link Storage

```bash
php artisan storage:link
```

### Step 5: Seed Microservice

```bash
php artisan db:seed --class=TicketCustomizerMicroserviceSeeder
```

### Step 6: Build Assets

```bash
npm run build
```

---

## Usage

### Admin Panel

Access the Filament admin panel to manage templates:

**URL:** `/admin/ticket-templates`

#### Creating a Template

1. Navigate to **Ticket Templates** in the admin panel
2. Click **Create**
3. Fill in basic information:
   - Tenant
   - Template name
   - Description (optional)
   - Status (draft, active, archived)
4. Configure canvas settings:
   - Orientation (portrait/landscape)
   - DPI (default: 300)
   - Width and height in mm
   - Bleed and safe area
5. Click **Create**
6. Click **"Open Visual Editor"** to design the template

#### Managing Templates

- **List View**: See all templates with preview thumbnails
- **Filters**: Filter by status, tenant, default flag
- **Actions**:
  - Edit (admin panel)
  - Edit (visual editor)
  - Set as default
  - Create version
  - Delete

#### Template Versioning

To create a new version:

1. Open an existing template
2. Click **"Create Version"** button
3. Enter version name (e.g., "V2 - Updated Logo")
4. New template is created with incremented version number
5. Parent template link is preserved

---

### Visual Editor

The WYSIWYG editor provides a complete design environment.

**URL:** `/ticket-customizer/{template-id}`

#### Interface Layout

```
┌─────────────────────────────────────────────────────────────┐
│  [Tools & Layers]  │     [Canvas]      │  [Properties]     │
│                    │                    │                    │
│  • Add Text        │  ┌──────────────┐ │  Layer Properties  │
│  • Add Image       │  │              │ │  • Name            │
│  • Add QR Code     │  │   Canvas     │ │  • Position (x,y)  │
│  • Add Barcode     │  │   Preview    │ │  • Size (w,h)      │
│  • Add Shape       │  │              │ │  • Type-specific   │
│                    │  └──────────────┘ │                    │
│  Layers:           │                    │  Variables:        │
│  □ Layer 3 (text)  │  Zoom: [====] 100%│  {{event.name}}   │
│  □ Layer 2 (img)   │                    │  {{ticket.price}}  │
│  ☑ Layer 1 (qr)    │  [Validate] [Save]│  ...               │
└─────────────────────────────────────────────────────────────┘
│              [Cancel]  [Save Template]                       │
└─────────────────────────────────────────────────────────────┘
```

#### Workflow

1. **Add Layers**: Click layer type buttons to add elements
2. **Position**: Drag layers on canvas or edit x/y coordinates
3. **Resize**: Drag handles or edit width/height
4. **Configure**: Edit layer properties in right sidebar
5. **Insert Variables**: Click variable placeholders to copy
6. **Preview**: Click "Generate Preview" to see rendered output
7. **Validate**: Click "Validate" to check for errors
8. **Save**: Click "Save Template" to persist changes

---

### API Integration

The REST API allows headless integration and programmatic template management.

#### Authentication

All API endpoints require tenant authentication. Pass `tenant` query parameter or use tenant-scoped middleware.

#### Basic Usage

```javascript
import { ticketCustomizerAPI } from './api';

// Get available variables
const vars = await ticketCustomizerAPI.getVariables('tenant-123');

// Create template
const template = await ticketCustomizerAPI.create({
  tenant_id: 'tenant-123',
  name: 'Summer Concert Ticket',
  description: 'Template for summer events',
  status: 'draft',
  template_data: {
    meta: {
      dpi: 300,
      size_mm: { w: 80, h: 200 },
      orientation: 'portrait',
      bleed_mm: 3,
      safe_area_mm: 5
    },
    assets: [],
    layers: []
  }
});

// Validate template
const validation = await ticketCustomizerAPI.validate(templateData);
if (!validation.ok) {
  console.error('Validation errors:', validation.errors);
}

// Generate preview
const preview = await ticketCustomizerAPI.preview(templateData, sampleData, 2);
console.log('Preview URL:', preview.preview.url);
```

---

## Template JSON Schema

Templates are stored as JSON with the following structure:

```json
{
  "meta": {
    "dpi": 300,
    "size_mm": {
      "w": 80,
      "h": 200
    },
    "orientation": "portrait",
    "bleed_mm": 3,
    "safe_area_mm": 5
  },
  "assets": [
    {
      "id": "asset-1",
      "filename": "logo.png",
      "mime_type": "image/png",
      "size_bytes": 15420,
      "url": "https://cdn.example.com/logo.png"
    }
  ],
  "layers": [
    {
      "id": "layer-1",
      "name": "Event Name",
      "type": "text",
      "frame": {
        "x": 10,
        "y": 20,
        "w": 60,
        "h": 10
      },
      "z": 1,
      "opacity": 1,
      "rotation": 0,
      "locked": false,
      "visible": true,
      "props": {
        "content": "{{event.name}}",
        "size_pt": 18,
        "color": "#000000",
        "align": "center",
        "weight": "bold",
        "font_family": "Arial"
      }
    }
  ]
}
```

### Schema Validation

The `TicketTemplateValidator` service validates:

- **Structure**: Required fields (meta, layers)
- **Meta**: DPI, size, orientation, bleed, safe area
- **Layers**: Type-specific properties
- **Frame**: Positioning within canvas bounds
- **Assets**: Referenced assets exist

**Warnings:**
- Font size < 4pt
- Z-index conflicts
- Layers outside safe area

---

## Variable System

### Available Variables

Variables use double curly braces: `{{category.field}}`

#### Event Variables
```
{{event.name}}          - Event name
{{event.description}}   - Event description
{{event.category}}      - Event category
```

#### Venue Variables
```
{{venue.name}}          - Venue name
{{venue.address}}       - Full address
{{venue.city}}          - City
{{venue.state}}         - State/province
{{venue.country}}       - Country
{{venue.postal_code}}   - Postal/ZIP code
```

#### Date & Time Variables
```
{{date.start}}          - Start date (YYYY-MM-DD)
{{date.end}}            - End date
{{date.time}}           - Time (HH:MM)
{{date.doors_open}}     - Doors open time
{{date.day_name}}       - Day of week (Monday)
{{date.month_name}}     - Month name (January)
```

#### Ticket Variables
```
{{ticket.type}}         - Ticket type (VIP, General)
{{ticket.price}}        - Price with currency
{{ticket.section}}      - Section
{{ticket.row}}          - Row
{{ticket.seat}}         - Seat number
{{ticket.number}}       - Unique ticket number
```

#### Buyer Variables
```
{{buyer.name}}          - Full name
{{buyer.email}}         - Email address
```

#### Order Variables
```
{{order.code}}          - Order reference code
{{order.date}}          - Order date
{{order.total}}         - Total amount
```

#### Code Variables
```
{{codes.barcode}}       - Unique barcode
{{codes.qrcode}}        - QR code data
```

#### Organizer Variables
```
{{organizer.name}}      - Organizer name
{{organizer.website}}   - Website
{{organizer.phone}}     - Contact phone
{{organizer.email}}     - Contact email
```

#### Legal Variables
```
{{legal.terms}}         - Terms and conditions
{{legal.disclaimer}}    - Disclaimer text
```

### Sample Data

```php
$sampleData = [
    'event' => [
        'name' => 'Summer Music Festival 2025',
        'description' => 'A celebration of music and art',
        'category' => 'Music'
    ],
    'venue' => [
        'name' => 'Central Park Arena',
        'address' => '123 Park Avenue',
        'city' => 'New York',
        'state' => 'NY',
        'country' => 'USA'
    ],
    'ticket' => [
        'type' => 'VIP',
        'price' => '$150.00',
        'section' => 'A',
        'row' => '5',
        'seat' => '12'
    ],
    // ... more categories
];
```

---

## Layer Types

### Text Layer

**Purpose:** Display text with formatting

```typescript
{
  type: 'text',
  props: {
    content: string,              // Text content or variable
    size_pt: number,              // Font size in points
    color: string,                // Hex color (#000000)
    align: 'left' | 'center' | 'right',
    weight: 'normal' | 'bold' | 'light',
    font_family?: string          // Font name (default: Arial)
  }
}
```

**Example:**
```json
{
  "id": "text-1",
  "name": "Event Title",
  "type": "text",
  "frame": { "x": 10, "y": 30, "w": 60, "h": 15 },
  "z": 2,
  "props": {
    "content": "{{event.name}}",
    "size_pt": 24,
    "color": "#1E40AF",
    "align": "center",
    "weight": "bold"
  }
}
```

---

### Image Layer

**Purpose:** Display logos, graphics, or photos

```typescript
{
  type: 'image',
  props: {
    asset_id: string,            // Reference to asset in assets array
    fit: 'cover' | 'contain' | 'fill'  // Image scaling mode
  }
}
```

**Example:**
```json
{
  "id": "img-1",
  "name": "Event Logo",
  "type": "image",
  "frame": { "x": 25, "y": 10, "w": 30, "h": 15 },
  "z": 1,
  "props": {
    "asset_id": "asset-logo-1",
    "fit": "contain"
  }
}
```

---

### QR Code Layer

**Purpose:** Generate dynamic QR codes

```typescript
{
  type: 'qr',
  props: {
    data: string,                         // QR data or variable
    error_correction: 'L' | 'M' | 'Q' | 'H'  // Error correction level
  }
}
```

**Error Correction Levels:**
- **L** (Low): 7% recovery
- **M** (Medium): 15% recovery
- **Q** (Quartile): 25% recovery
- **H** (High): 30% recovery

**Example:**
```json
{
  "id": "qr-1",
  "name": "Ticket QR Code",
  "type": "qr",
  "frame": { "x": 60, "y": 160, "w": 30, "h": 30 },
  "z": 3,
  "props": {
    "data": "{{codes.qrcode}}",
    "error_correction": "M"
  }
}
```

---

### Barcode Layer

**Purpose:** Generate 1D barcodes

```typescript
{
  type: 'barcode',
  props: {
    data: string,                         // Barcode data or variable
    format: 'code128' | 'ean13' | 'pdf417'  // Barcode format
  }
}
```

**Supported Formats:**
- **Code128**: Alphanumeric, variable length
- **EAN-13**: 13-digit product codes
- **PDF417**: 2D stacked barcode, high capacity

**Example:**
```json
{
  "id": "barcode-1",
  "name": "Ticket Barcode",
  "type": "barcode",
  "frame": { "x": 15, "y": 180, "w": 50, "h": 10 },
  "z": 3,
  "props": {
    "data": "{{codes.barcode}}",
    "format": "code128"
  }
}
```

---

### Shape Layer

**Purpose:** Decorative shapes and dividers

```typescript
{
  type: 'shape',
  props: {
    kind: 'rect' | 'circle' | 'line',  // Shape type
    fill?: string,                      // Fill color (hex)
    stroke?: string,                    // Border color (hex)
    stroke_width?: number               // Border width in mm
  }
}
```

**Example:**
```json
{
  "id": "shape-1",
  "name": "Background Rectangle",
  "type": "shape",
  "frame": { "x": 0, "y": 0, "w": 80, "h": 50 },
  "z": 0,
  "props": {
    "kind": "rect",
    "fill": "#F3F4F6",
    "stroke": "#9CA3AF",
    "stroke_width": 0.5
  }
}
```

---

## API Reference

Base URL: `/api/tickets/templates`

### GET /variables

Get available variables and sample data.

**Query Parameters:**
- `tenant` (required): Tenant ID

**Response:**
```json
{
  "tenant_id": "tenant-123",
  "tenant_name": "Acme Events",
  "variables": [
    {
      "category": "Event",
      "variables": [
        {
          "key": "event.name",
          "label": "Event Name",
          "placeholder": "{{event.name}}",
          "description": "The name of the event"
        }
      ]
    }
  ],
  "sample_data": { ... }
}
```

---

### POST /validate

Validate template JSON structure.

**Request Body:**
```json
{
  "template_json": { ... }
}
```

**Response:**
```json
{
  "ok": true,
  "errors": [],
  "warnings": [
    "Font size 3pt is very small (< 4pt recommended)"
  ]
}
```

---

### POST /preview

Generate preview image.

**Request Body:**
```json
{
  "template_json": { ... },
  "sample_data": { ... },  // Optional
  "scale": 2               // Optional, default 2
}
```

**Response:**
```json
{
  "success": true,
  "preview": {
    "path": "previews/ticket_preview_123.svg",
    "url": "https://cdn.example.com/previews/ticket_preview_123.svg",
    "width": 945,
    "height": 2362,
    "format": "svg"
  }
}
```

---

### GET /presets

Get preset dimensions.

**Response:**
```json
{
  "presets": [
    {
      "id": "ticket_standard",
      "name": "Standard Ticket (80×200mm)",
      "size_mm": { "w": 80, "h": 200 },
      "orientation": "portrait",
      "dpi": 300
    }
  ]
}
```

---

### GET /

List templates for a tenant.

**Query Parameters:**
- `tenant` (required): Tenant ID
- `status` (optional): Filter by status (draft, active, archived)

**Response:**
```json
{
  "tenant_id": "tenant-123",
  "templates": [
    {
      "id": "tmpl-456",
      "tenant_id": "tenant-123",
      "name": "VIP Ticket",
      "status": "active",
      "is_default": true,
      "preview_image": "previews/tmpl-456.svg",
      "version": 2,
      "created_at": "2025-11-16T10:00:00Z"
    }
  ]
}
```

---

### POST /

Create a new template.

**Request Body:**
```json
{
  "tenant_id": "tenant-123",
  "name": "Summer Concert Ticket",
  "description": "Template for summer events",
  "template_data": { ... },
  "status": "draft"  // Optional
}
```

**Response:**
```json
{
  "success": true,
  "template": { ... },
  "warnings": []
}
```

---

### GET /{id}

Get a specific template.

**Response:**
```json
{
  "template": { ... }
}
```

---

### PUT /{id}

Update a template.

**Request Body:**
```json
{
  "name": "Updated Name",
  "description": "New description",
  "template_data": { ... },
  "status": "active"
}
```

**Response:**
```json
{
  "success": true,
  "template": { ... }
}
```

---

### DELETE /{id}

Delete a template (soft delete).

**Response:**
```json
{
  "success": true,
  "message": "Template deleted successfully"
}
```

---

### POST /{id}/set-default

Set template as default for tenant.

**Response:**
```json
{
  "success": true,
  "message": "Template set as default",
  "template": { ... }
}
```

---

### POST /{id}/create-version

Create a new version of a template.

**Request Body:**
```json
{
  "template_data": { ... },
  "name": "V2 - Updated Logo"  // Optional
}
```

**Response:**
```json
{
  "success": true,
  "template": { ... },
  "warnings": []
}
```

---

## Frontend Integration

### React Component Usage

```tsx
import React from 'react';
import TicketCustomizer from '@/components/TicketCustomizer';

function App() {
  const handleSave = async (templateData) => {
    await ticketCustomizerAPI.create({
      tenant_id: 'tenant-123',
      name: 'My Template',
      template_data: templateData,
    });
  };

  return (
    <TicketCustomizer
      tenantId="tenant-123"
      templateId="tmpl-456"  // Optional, for editing
      onSave={handleSave}
      onCancel={() => window.history.back()}
    />
  );
}
```

### Embedding in Blade

```blade
@vite(['resources/js/app.js'])

<div id="ticket-customizer-root"
     data-tenant-id="{{ $tenantId }}"
     data-template-id="{{ $templateId ?? '' }}">
</div>

<script type="module">
  import TicketCustomizer from './components/TicketCustomizer';
  import { createRoot } from 'react-dom/client';

  const container = document.getElementById('ticket-customizer-root');
  const root = createRoot(container);

  root.render(
    <TicketCustomizer
      tenantId={container.dataset.tenantId}
      templateId={container.dataset.templateId || undefined}
    />
  );
</script>
```

---

## Advanced Usage

### Custom Variable Resolution

Extend the `TicketVariableService` to add custom variables:

```php
namespace App\Services\TicketCustomizer;

class CustomVariableService extends TicketVariableService
{
    public function getAvailableVariables(): array
    {
        $base = parent::getAvailableVariables();

        $base[] = [
            'category' => 'Custom',
            'variables' => [
                [
                    'key' => 'custom.field',
                    'label' => 'Custom Field',
                    'placeholder' => '{{custom.field}}',
                    'description' => 'Your custom field'
                ]
            ]
        ];

        return $base;
    }

    public function getSampleData(): array
    {
        $base = parent::getSampleData();
        $base['custom'] = ['field' => 'Custom Value'];
        return $base;
    }
}
```

Register in `AppServiceProvider`:

```php
$this->app->singleton(TicketVariableService::class, CustomVariableService::class);
```

---

### Custom Preview Renderer

For PNG export instead of SVG:

```php
namespace App\Services\TicketCustomizer;

use Intervention\Image\ImageManager;

class PngPreviewGenerator extends TicketPreviewGenerator
{
    public function generatePreview(array $templateData, ?array $data = null, int $scale = 2): array
    {
        // Generate SVG first
        $svgResult = parent::generatePreview($templateData, $data, $scale);

        // Convert SVG to PNG using Imagick or Puppeteer
        $manager = new ImageManager(['driver' => 'imagick']);
        $image = $manager->make($svgResult['path']);

        $pngPath = str_replace('.svg', '.png', $svgResult['path']);
        $image->save(storage_path('app/public/' . $pngPath));

        return [
            'path' => $pngPath,
            'url' => Storage::disk('public')->url($pngPath),
            'width' => $svgResult['width'],
            'height' => $svgResult['height'],
            'format' => 'png',
        ];
    }
}
```

---

## Troubleshooting

### Issue: Preview not generating

**Symptoms:** Preview generation fails or returns empty image

**Solutions:**
1. Check storage permissions: `storage/app/public` must be writable
2. Ensure storage is linked: `php artisan storage:link`
3. Verify GD/Imagick extension is installed: `php -m | grep -i imagick`
4. Check logs: `storage/logs/laravel.log`

---

### Issue: Variables not resolving

**Symptoms:** `{{event.name}}` appears as literal text

**Solutions:**
1. Check variable service is registered in `AppServiceProvider`
2. Verify variable syntax: `{{category.field}}` (no spaces)
3. Ensure data is passed to preview generation
4. Check variable exists in `getSampleData()` output

---

### Issue: Validation errors

**Symptoms:** Template validation fails with errors

**Solutions:**
1. Check JSON structure matches schema
2. Verify all required fields: `meta`, `meta.dpi`, `meta.size_mm`
3. Ensure layer types are valid: text, image, qr, barcode, shape
4. Check frame coordinates are within canvas bounds
5. Use `/api/tickets/templates/validate` endpoint to debug

---

### Issue: Filament admin not showing

**Symptoms:** Admin panel doesn't list templates

**Solutions:**
1. Clear Filament cache: `php artisan filament:cache-clear`
2. Verify resource is registered (auto-discovered in `app/Filament/Resources`)
3. Check user has access to "Design" navigation group
4. Clear Laravel cache: `php artisan optimize:clear`

---

### Issue: React component not loading

**Symptoms:** WYSIWYG editor doesn't render

**Solutions:**
1. Install React dependencies: `npm install react react-dom`
2. Configure Vite with React plugin
3. Build assets: `npm run build` or `npm run dev`
4. Check browser console for errors
5. Verify Vite manifest exists: `public/build/manifest.json`

---

## Performance Optimization

### Caching Templates

Cache compiled templates for faster rendering:

```php
use Illuminate\Support\Facades\Cache;

$template = Cache::remember("template:{$id}", 3600, function () use ($id) {
    return TicketTemplate::find($id);
});
```

### Lazy Load Previews

Generate previews asynchronously:

```php
use App\Jobs\GenerateTemplatePreview;

// Dispatch job instead of immediate generation
GenerateTemplatePreview::dispatch($template);
```

### Optimize Asset Storage

Use CDN for assets and previews:

```php
'disks' => [
    'templates' => [
        'driver' => 's3',
        'bucket' => env('AWS_TEMPLATES_BUCKET'),
        'url' => env('AWS_TEMPLATES_URL'),
    ],
],
```

---

## Security Considerations

### Input Validation

All template JSON is validated before saving:

- Schema structure validation
- XSS prevention in text content
- File type validation for assets
- Size limits on template complexity

### Tenant Isolation

Templates are strictly isolated by tenant:

```php
// All queries are scoped
TicketTemplate::where('tenant_id', $tenantId)->get();

// Model uses tenant scope
protected static function booted()
{
    static::addGlobalScope('tenant', function (Builder $builder) {
        if (auth()->check()) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    });
}
```

### Asset Security

- Assets are validated for type and size
- Uploaded files are scanned for malware
- Public URLs use signed URLs for private assets
- CDN URLs use SRI (Subresource Integrity)

---

## Roadmap & Future Enhancements

Planned features for future versions:

- [ ] Real-time collaborative editing
- [ ] Template marketplace with pre-built designs
- [ ] Advanced typography (kerning, tracking)
- [ ] Gradient and pattern fills
- [ ] Shadow and glow effects
- [ ] PDF direct export (no conversion)
- [ ] Mobile-responsive editor
- [ ] AI-powered design suggestions
- [ ] Template analytics (usage tracking)
- [ ] Multi-page ticket support (front/back)

---

## Support & Contact

**Documentation:** `/docs/microservices/ticket-customizer`
**Email:** support@epas.ro
**GitHub Issues:** https://github.com/epas/issues

**Version:** 1.0.0
**Last Updated:** November 16, 2025
**Author:** EPAS Development Team

---

## License

Proprietary - Part of EPAS Ticketing System
© 2025 EPAS. All rights reserved.
