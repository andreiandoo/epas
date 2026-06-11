# Ticket Customizer Component

WYSIWYG editor for designing ticket templates with drag-and-drop, real-time preview, and variable placeholders.

## Overview

This React/TypeScript component provides a complete ticket design interface that connects to the Laravel backend REST API. It allows users to create custom ticket templates with text, images, QR codes, barcodes, and shapes.

## Installation

### 1. Install Dependencies

First, add React and required dependencies to your `package.json`:

```bash
npm install react react-dom @types/react @types/react-dom
npm install axios
npm install --save-dev @vitejs/plugin-react
```

For a complete implementation, you would also want:

```bash
npm install react-draggable
npm install fabric
npm install react-color
npm install @dnd-kit/core @dnd-kit/sortable
```

### 2. Configure Vite

Update `vite.config.js` to include React support:

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

## Usage

### Basic Example

```tsx
import React from 'react';
import TicketCustomizer from './components/TicketCustomizer';
import type { TemplateData } from './components/TicketCustomizer';

function App() {
  const handleSave = async (templateData: TemplateData) => {
    // Save template to backend
    await ticketCustomizerAPI.create({
      tenant_id: 'tenant-123',
      name: 'My Ticket Template',
      description: 'Custom event ticket',
      template_data: templateData,
      status: 'draft',
    });
  };

  return (
    <TicketCustomizer
      tenantId="tenant-123"
      onSave={handleSave}
      onCancel={() => window.history.back()}
    />
  );
}
```

### Editing Existing Template

```tsx
<TicketCustomizer
  tenantId="tenant-123"
  templateId="template-456"
  onSave={handleSave}
/>
```

### Using the API Directly

```tsx
import { ticketCustomizerAPI } from './components/TicketCustomizer';

// Get available variables
const vars = await ticketCustomizerAPI.getVariables('tenant-123');

// Validate template
const validation = await ticketCustomizerAPI.validate(templateData);

// Generate preview
const preview = await ticketCustomizerAPI.preview(templateData);

// List templates
const templates = await ticketCustomizerAPI.list('tenant-123', 'active');
```

## Component Structure

```
TicketCustomizer/
├── types.ts              # TypeScript type definitions
├── api.ts                # API client for backend communication
├── TicketCustomizer.tsx  # Main component
├── index.ts              # Entry point
└── README.md            # This file
```

## Features

### Current Implementation (Scaffold)

- ✅ TypeScript type definitions matching backend schema
- ✅ Complete API client with all endpoints
- ✅ Basic component structure with three-panel layout
- ✅ Layer management (add, update, delete, reorder)
- ✅ Real-time validation
- ✅ Preview generation
- ✅ Variable placeholders display
- ✅ Zoom controls (25%-800%)
- ✅ Template metadata (size, DPI, orientation)

### Recommended Enhancements

For a production-ready implementation, consider adding:

1. **Canvas Manipulation**
   - Use Fabric.js or Konva for advanced canvas features
   - Drag-and-drop layer positioning
   - Resize handles and rotation controls
   - Snap to grid and guides

2. **Advanced UI**
   - Color picker for text/shapes
   - Font selector with Google Fonts integration
   - Image upload and asset management
   - Layer grouping and alignment tools

3. **State Management**
   - Use Zustand or Redux for complex state
   - Undo/redo with history tracking
   - Autosave functionality

4. **Export Features**
   - PDF export
   - High-resolution PNG export
   - Print-ready file generation

## Template JSON Schema

Templates follow this structure:

```json
{
  "meta": {
    "dpi": 300,
    "size_mm": { "w": 80, "h": 200 },
    "orientation": "portrait",
    "bleed_mm": 3,
    "safe_area_mm": 5
  },
  "assets": [
    {
      "id": "asset-1",
      "filename": "logo.png",
      "mime_type": "image/png",
      "url": "https://..."
    }
  ],
  "layers": [
    {
      "id": "layer-1",
      "name": "Event Name",
      "type": "text",
      "frame": { "x": 10, "y": 20, "w": 60, "h": 10 },
      "z": 1,
      "opacity": 1,
      "rotation": 0,
      "props": {
        "content": "{{event.name}}",
        "size_pt": 18,
        "color": "#000000",
        "align": "center",
        "weight": "bold"
      }
    }
  ]
}
```

## Available Variables

Variables use double curly braces: `{{category.field}}`

### Event
- `{{event.name}}` - Event name
- `{{event.description}}` - Event description
- `{{event.category}}` - Event category

### Venue
- `{{venue.name}}` - Venue name
- `{{venue.address}}` - Full address
- `{{venue.city}}` - City

### Date & Time
- `{{date.start}}` - Start date (YYYY-MM-DD)
- `{{date.time}}` - Time (HH:MM)
- `{{date.day_name}}` - Day of week

### Ticket
- `{{ticket.type}}` - Ticket type
- `{{ticket.price}}` - Price with currency
- `{{ticket.section}}` - Section
- `{{ticket.row}}` - Row
- `{{ticket.seat}}` - Seat number

### Buyer
- `{{buyer.name}}` - Full name
- `{{buyer.email}}` - Email address

### Order
- `{{order.code}}` - Order reference code
- `{{order.date}}` - Order date
- `{{order.total}}` - Total amount

### Codes
- `{{codes.barcode}}` - Unique barcode
- `{{codes.qrcode}}` - QR code data

### Organizer
- `{{organizer.name}}` - Organizer name
- `{{organizer.website}}` - Website
- `{{organizer.phone}}` - Contact phone

## Layer Types

### Text Layer
```typescript
{
  type: 'text',
  props: {
    content: string,
    size_pt: number,
    color: string,
    align: 'left' | 'center' | 'right',
    weight: 'normal' | 'bold' | 'light',
    font_family?: string
  }
}
```

### Image Layer
```typescript
{
  type: 'image',
  props: {
    asset_id: string,
    fit: 'cover' | 'contain' | 'fill'
  }
}
```

### QR Code Layer
```typescript
{
  type: 'qr',
  props: {
    data: string,
    error_correction: 'L' | 'M' | 'Q' | 'H'
  }
}
```

### Barcode Layer
```typescript
{
  type: 'barcode',
  props: {
    data: string,
    format: 'code128' | 'ean13' | 'pdf417'
  }
}
```

### Shape Layer
```typescript
{
  type: 'shape',
  props: {
    kind: 'rect' | 'circle' | 'line',
    fill?: string,
    stroke?: string,
    stroke_width?: number
  }
}
```

## API Endpoints

All endpoints are automatically configured in the API client:

- `GET /api/tickets/templates/variables?tenant={id}` - Get available variables
- `POST /api/tickets/templates/validate` - Validate template
- `POST /api/tickets/templates/preview` - Generate preview
- `GET /api/tickets/templates/presets` - Get preset dimensions
- `GET /api/tickets/templates?tenant={id}` - List templates
- `POST /api/tickets/templates` - Create template
- `GET /api/tickets/templates/{id}` - Get template
- `PUT /api/tickets/templates/{id}` - Update template
- `DELETE /api/tickets/templates/{id}` - Delete template
- `POST /api/tickets/templates/{id}/set-default` - Set as default
- `POST /api/tickets/templates/{id}/create-version` - Create version

## Preset Dimensions

Available presets:

- **Standard Ticket** - 80×200mm (portrait)
- **Landscape Ticket** - 200×80mm (landscape)
- **A6 Portrait** - 105×148mm
- **A6 Landscape** - 148×105mm
- **A4 Portrait** - 210×297mm
- **A4 Landscape** - 297×210mm

All presets default to 300 DPI.

## Integration with Laravel Blade

To embed in a Blade view:

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
      templateId={container.dataset.templateId}
    />
  );
</script>
```

## License

Part of the EPAS ticketing system.
