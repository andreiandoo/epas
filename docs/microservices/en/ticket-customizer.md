# Ticket Customizer Component

## Short Presentation

Create stunning, professional tickets that reflect your brand identity with the Ticket Customizer Component. Gone are the days of generic, forgettable tickets. With our powerful WYSIWYG editor, designing beautiful tickets is as easy as dragging and dropping.

The visual editor puts you in complete control. Add text with custom fonts and colors, place your logo and event imagery, position QR codes and barcodes exactly where you want them. See your changes in real-time as you design, with precise millimeter measurements ensuring your tickets print perfectly every time.

Variable placeholders make every ticket unique. Insert dynamic content like {attendee_name}, {event_date}, {seat_number} that automatically populates with actual data when tickets are generated. No more manual editing for each attendee.

Choose from preset dimensions for standard tickets, A6, or A4 formats, or create custom sizes for your specific needs. Add shapes, dividers, and background colors to create truly distinctive designs. The built-in print guides show bleed and safe areas so your tickets look professional whether viewed on screen or in hand.

Save your designs as templates for future events. Manage multiple versions, set defaults, and duplicate existing templates to speed up your workflow. With the Ticket Customizer, your tickets become an extension of your event experience.

One-time purchase. Unlimited creativity.

---

## Detailed Description

The Ticket Customizer Component is a comprehensive WYSIWYG (What You See Is What You Get) editor designed specifically for creating custom ticket templates. It provides event organizers with professional-grade design tools without requiring graphic design expertise.

### Visual Editor

The editor interface presents a canvas representing your ticket, with precise measurements in millimeters for print accuracy. All editing happens visually - click to select elements, drag to reposition, resize with handles, and see changes instantly.

### Layer System

Templates are built using a layer-based system similar to professional design software:

- **Text Layers**: Typography with font selection, sizing, colors, and alignment
- **Image Layers**: Upload and position logos, photos, and graphics
- **QR Code Layers**: Dynamic QR codes with configurable error correction
- **Barcode Layers**: Support for Code128, EAN-13, and PDF417 formats
- **Shape Layers**: Rectangles, circles, and lines with fill and stroke options

Each layer has properties for position, size, rotation, and opacity. The z-index controls stacking order.

### Variable System

The template supports dynamic variable placeholders that are replaced with actual data when tickets are rendered:

**Event Variables**: {{event.name}}, {{event.date}}, {{event.time}}
**Venue Variables**: {{venue.name}}, {{venue.address}}
**Ticket Variables**: {{ticket.section}}, {{ticket.row}}, {{ticket.seat}}
**Buyer Variables**: {{buyer.name}}, {{buyer.email}}
**Order Variables**: {{order.number}}, {{order.date}}
**Code Variables**: {{codes.qr}}, {{codes.barcode}}

### Print-Ready Output

Templates are designed with print production in mind:
- Bleed area visualization
- Safe area guides
- DPI-aware measurements
- High-resolution export (@2x)
- SVG preview generation

---

## Features

### Core Features
- WYSIWYG visual editor with drag-and-drop
- Real mm/DPI measurements for print accuracy
- Multiple layer types: text, images, QR codes, barcodes, shapes
- Layer management: z-index, lock/unlock, visibility toggle
- Print guides: bleed and safe area visualization

### Variable System
- Variable placeholders: {{event.name}}, {{ticket.section}}, etc.
- Comprehensive variable categories: event, venue, date, ticket, buyer, order, codes, organizer
- Real-time variable preview with sample data

### Canvas Features
- Zoom controls (25%-800%)
- Ruler display in millimeters
- Snap to grid functionality
- Frame-based positioning (x, y, width, height)
- Rotation and opacity controls

### Layer Types
- Text: fonts, sizes, colors, alignment, weight
- Images: upload and position logos/graphics
- QR codes: dynamic generation with error correction levels
- Barcodes: Code128, EAN-13, PDF417 support
- Shapes: rectangles, circles, lines with fill/stroke

### Export & Preview
- SVG preview generation
- Template JSON export
- High-resolution preview (@2x)
- Print-ready file preparation

### Template Management
- Save and load templates
- Template versioning
- Set default templates per tenant
- Status workflow: draft → active → archived
- Soft delete with restore capability
- Template duplication

### Preset Dimensions
- Standard Ticket (80×200mm)
- Landscape Ticket (200×80mm)
- A6 Portrait (105×148mm)
- A6 Landscape (148×105mm)
- A4 Portrait (210×297mm)
- A4 Landscape (297×210mm)

### API Features
- Complete REST API for integration
- Real-time validation endpoint
- Preview generation API
- Variable listing with sample data
- Template CRUD operations

### Admin Features
- Filament admin panel integration
- Visual template manager
- Direct JSON editing (advanced users)
- Bulk operations support

---

## Use Cases

### Branded Event Tickets
Create tickets that match your event's visual identity. Use your colors, fonts, and imagery to make tickets feel like part of the experience.

### Premium VIP Tickets
Design distinctive VIP tickets with gold accents, special imagery, or unique layouts that make premium ticket holders feel special.

### Conference Badges
Design name badges with attendee information, company logo, and session access indicators all in one printable format.

### Multi-Day Passes
Create passes with space for multiple dates or a punch-card style layout for multi-session access.

### Promotional Tickets
Design tickets with sponsor logos, promotional messages, or QR codes linking to special offers.

### Collectible Tickets
For concerts, sports events, or special occasions, design tickets worth keeping as memorabilia.

---

## Technical Documentation

### Overview

The Ticket Customizer Component provides a visual template editor for designing custom ticket layouts. Templates are stored as JSON and rendered to PDF/PNG for ticket generation.

### Architecture

```
Admin Panel → Template Editor UI → Template JSON → Preview Renderer
                                         ↓
                                   Template Storage
                                         ↓
                              Ticket Generator Service
```

### Template JSON Structure

```json
{
  "version": "1.0",
  "dimensions": {
    "width": 200,
    "height": 80,
    "unit": "mm",
    "dpi": 300
  },
  "background": {
    "color": "#FFFFFF",
    "image": null
  },
  "layers": [
    {
      "id": "layer_1",
      "type": "text",
      "content": "{{event.name}}",
      "frame": {"x": 10, "y": 10, "width": 100, "height": 20},
      "style": {
        "fontFamily": "Helvetica",
        "fontSize": 24,
        "fontWeight": "bold",
        "color": "#000000",
        "alignment": "left"
      },
      "zIndex": 1,
      "locked": false,
      "visible": true
    },
    {
      "id": "layer_2",
      "type": "qrcode",
      "content": "{{codes.qr}}",
      "frame": {"x": 160, "y": 20, "width": 30, "height": 30},
      "options": {
        "errorCorrection": "M"
      },
      "zIndex": 2
    }
  ],
  "guides": {
    "bleed": 3,
    "safe": 5
  }
}
```

### API Endpoints

#### List Templates
```
GET /api/ticket-templates
```
List all templates for the tenant.

#### Create Template
```
POST /api/ticket-templates
```
Create a new ticket template.

#### Get Template
```
GET /api/ticket-templates/{id}
```
Retrieve template details and JSON.

#### Update Template
```
PUT /api/ticket-templates/{id}
```
Update template JSON and metadata.

#### Delete Template
```
DELETE /api/ticket-templates/{id}
```
Soft delete a template.

#### Validate Template
```
POST /api/ticket-templates/validate
```
Validate template JSON structure.

#### Generate Preview
```
POST /api/ticket-templates/{id}/preview
```
Generate SVG/PNG preview with sample data.

#### List Variables
```
GET /api/ticket-templates/variables
```
List available variables with sample data.

### Variable Categories

| Category | Variables |
|----------|-----------|
| event | name, description, date, time, timezone |
| venue | name, address, city, country |
| ticket | type, section, row, seat, price |
| buyer | name, email, phone |
| order | number, date, total |
| codes | qr, barcode, ticket_ref |
| organizer | name, email, phone, logo |

### Configuration

```php
'ticket_customizer' => [
    'storage_disk' => 'templates',
    'preview_quality' => 2, // @2x
    'default_dpi' => 300,
    'max_layers' => 50,
    'supported_fonts' => ['Helvetica', 'Arial', 'Georgia', 'Courier'],
    'barcode_formats' => ['CODE128', 'EAN13', 'PDF417'],
]
```

### Integration Example

```php
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;

// Get template
$template = TicketTemplate::find($id);

// Get variables with sample data
$variables = app(TicketVariableService::class)->getSampleData();

// Generate preview
$preview = app(TicketPreviewGenerator::class)
    ->generate($template, $variables);

// Use template for actual ticket
$ticketPdf = $generator->render($template, $actualTicketData);
```

### Layer Types

| Type | Properties | Description |
|------|------------|-------------|
| text | font, size, color, alignment | Static or variable text |
| image | src, fit, opacity | Logos, photos, graphics |
| qrcode | content, errorCorrection | Dynamic QR codes |
| barcode | content, format | Code128, EAN-13, PDF417 |
| shape | type, fill, stroke | Rectangles, circles, lines |

### Status Workflow

```
draft → active → archived
          ↓
        default (one per tenant)
```
