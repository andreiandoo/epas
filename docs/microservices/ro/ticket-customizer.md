# Componenta de Personalizare Bilete

## Prezentare Scurtă

Creează bilete uimitoare și profesionale care reflectă identitatea brandului tău cu Componenta de Personalizare Bilete. Au trecut zilele biletelor generice și ușor de uitat. Cu editorul nostru puternic WYSIWYG, designul biletelor frumoase este la fel de simplu ca drag and drop.

Editorul vizual îți oferă control complet. Adaugă text cu fonturi și culori personalizate, plasează logo-ul și imaginile evenimentului, poziționează codurile QR și de bare exact unde vrei tu. Vezi-ți modificările în timp real pe măsură ce designezi, cu măsurători precise în milimetri asigurând că biletele tale se printează perfect de fiecare dată.

Variabilele placeholder fac fiecare bilet unic. Inserează conținut dinamic precum {nume_participant}, {data_eveniment}, {numar_loc} care se populează automat cu datele reale când biletele sunt generate. Nu mai e nevoie de editare manuală pentru fiecare participant.

Alege din dimensiuni prestabilite pentru bilete standard, format A6 sau A4, sau creează dimensiuni personalizate pentru nevoile tale specifice. Adaugă forme, separatoare și culori de fundal pentru a crea designuri cu adevărat distinctive. Ghidurile de print integrate arată zonele de bleed și safe astfel încât biletele tale să arate profesional fie că sunt vizualizate pe ecran sau în mână.

Salvează-ți designurile ca template-uri pentru evenimente viitoare. Gestionează versiuni multiple, setează implicit-uri și duplică template-uri existente pentru a-ți accelera workflow-ul. Cu Personalizatorul de Bilete, biletele tale devin o extensie a experienței evenimentului tău.

Achiziție unică. Creativitate nelimitată.

---

## Descriere Detaliată

Componenta de Personalizare Bilete este un editor comprehensiv WYSIWYG (What You See Is What You Get) conceput special pentru crearea template-urilor personalizate de bilete. Oferă organizatorilor de evenimente instrumente de design de nivel profesional fără a necesita expertiză în design grafic.

### Editor Vizual

Interfața editorului prezintă un canvas reprezentând biletul tău, cu măsurători precise în milimetri pentru acuratețe la print. Toată editarea se întâmplă vizual - click pentru a selecta elemente, drag pentru a repoziționa, redimensionare cu handle-uri și vezi schimbările instant.

### Sistem de Layer-uri

Template-urile sunt construite folosind un sistem bazat pe layer-uri similar cu software-ul profesional de design:

- **Layer-uri Text**: Tipografie cu selecție font, dimensionare, culori și aliniere
- **Layer-uri Imagine**: Încarcă și poziționează logo-uri, fotografii și grafice
- **Layer-uri Cod QR**: Coduri QR dinamice cu corecție de eroare configurabilă
- **Layer-uri Cod de Bare**: Suport pentru formate Code128, EAN-13 și PDF417
- **Layer-uri Forme**: Dreptunghiuri, cercuri și linii cu opțiuni de fill și stroke

Fiecare layer are proprietăți pentru poziție, dimensiune, rotație și opacitate. Z-index-ul controlează ordinea de stivuire.

### Sistem de Variabile

Template-ul suportă variabile placeholder dinamice care sunt înlocuite cu date reale când biletele sunt renderizate:

**Variabile Eveniment**: {{event.name}}, {{event.date}}, {{event.time}}
**Variabile Locație**: {{venue.name}}, {{venue.address}}
**Variabile Bilet**: {{ticket.section}}, {{ticket.row}}, {{ticket.seat}}
**Variabile Cumpărător**: {{buyer.name}}, {{buyer.email}}
**Variabile Comandă**: {{order.number}}, {{order.date}}
**Variabile Cod**: {{codes.qr}}, {{codes.barcode}}

### Output Gata de Print

Template-urile sunt designate cu producția print în minte:
- Vizualizare zonă bleed
- Ghiduri zonă safe
- Măsurători conștiente de DPI
- Export high-resolution (@2x)
- Generare preview SVG

---

## Funcționalități

### Funcții de Bază
- Editor vizual WYSIWYG cu drag-and-drop
- Măsurători reale mm/DPI pentru acuratețe print
- Tipuri multiple de layer-uri: text, imagini, coduri QR, coduri de bare, forme
- Gestionare layer-uri: z-index, lock/unlock, toggle vizibilitate
- Ghiduri print: vizualizare zonă bleed și safe

### Sistem de Variabile
- Variabile placeholder: {{event.name}}, {{ticket.section}}, etc.
- Categorii comprehensive de variabile: eveniment, locație, dată, bilet, cumpărător, comandă, coduri, organizator
- Preview variabile în timp real cu date de exemplu

### Funcții Canvas
- Controale zoom (25%-800%)
- Afișare riglă în milimetri
- Funcționalitate snap to grid
- Poziționare bazată pe frame (x, y, lățime, înălțime)
- Controale rotație și opacitate

### Tipuri de Layer-uri
- Text: fonturi, dimensiuni, culori, aliniere, grosime
- Imagini: încărcare și poziționare logo-uri/grafice
- Coduri QR: generare dinamică cu nivele de corecție eroare
- Coduri de bare: suport Code128, EAN-13, PDF417
- Forme: dreptunghiuri, cercuri, linii cu fill/stroke

### Export & Preview
- Generare preview SVG
- Export JSON template
- Preview high-resolution (@2x)
- Pregătire fișier gata de print

### Gestionare Template-uri
- Salvare și încărcare template-uri
- Versionare template-uri
- Setare template-uri implicite per tenant
- Flux de status: draft → active → archived
- Ștergere soft cu capabilitate de restaurare
- Duplicare template-uri

### Dimensiuni Prestabilite
- Bilet Standard (80×200mm)
- Bilet Landscape (200×80mm)
- A6 Portrait (105×148mm)
- A6 Landscape (148×105mm)
- A4 Portrait (210×297mm)
- A4 Landscape (297×210mm)

### Funcții API
- API REST complet pentru integrare
- Endpoint validare în timp real
- API generare preview
- Listare variabile cu date de exemplu
- Operațiuni CRUD template-uri

### Funcții Admin
- Integrare panou admin Filament
- Manager vizual template-uri
- Editare directă JSON (utilizatori avansați)
- Suport operațiuni în masă

---

## Cazuri de Utilizare

### Bilete de Eveniment Brandate
Creează bilete care se potrivesc identității vizuale a evenimentului tău. Folosește-ți culorile, fonturile și imaginile pentru a face biletele să se simtă ca parte din experiență.

### Bilete Premium VIP
Designează bilete VIP distinctive cu accente aurii, imagini speciale sau layout-uri unice care fac deținătorii de bilete premium să se simtă speciali.

### Ecusoane de Conferință
Designează ecusoane cu informații participant, logo companie și indicatori acces sesiuni toate într-un singur format printabil.

### Abonamente Multi-Zi
Creează abonamente cu spațiu pentru mai multe date sau un layout stil punch-card pentru acces multi-sesiune.

### Bilete Promoționale
Designează bilete cu logo-uri sponsori, mesaje promoționale sau coduri QR care duc la oferte speciale.

### Bilete Colecționabile
Pentru concerte, evenimente sportive sau ocazii speciale, designează bilete care merită păstrate ca amintiri.

---

## Documentație Tehnică

### Prezentare Generală

Componenta de Personalizare Bilete oferă un editor vizual de template-uri pentru designul layout-urilor personalizate de bilete. Template-urile sunt stocate ca JSON și renderizate în PDF/PNG pentru generarea biletelor.

### Arhitectură

```
Panou Admin → UI Editor Template → JSON Template → Renderer Preview
                                         ↓
                                   Stocare Template
                                         ↓
                              Serviciu Generator Bilete
```

### Structură JSON Template

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

### Endpoint-uri API

#### Listează Template-uri
```
GET /api/ticket-templates
```
Listează toate template-urile pentru tenant.

#### Creează Template
```
POST /api/ticket-templates
```
Creează un nou template de bilet.

#### Obține Template
```
GET /api/ticket-templates/{id}
```
Obține detaliile template-ului și JSON-ul.

#### Actualizează Template
```
PUT /api/ticket-templates/{id}
```
Actualizează JSON-ul și metadatele template-ului.

#### Șterge Template
```
DELETE /api/ticket-templates/{id}
```
Șterge soft un template.

#### Validează Template
```
POST /api/ticket-templates/validate
```
Validează structura JSON a template-ului.

#### Generează Preview
```
POST /api/ticket-templates/{id}/preview
```
Generează preview SVG/PNG cu date de exemplu.

#### Listează Variabile
```
GET /api/ticket-templates/variables
```
Listează variabilele disponibile cu date de exemplu.

### Categorii de Variabile

| Categorie | Variabile |
|-----------|-----------|
| event | name, description, date, time, timezone |
| venue | name, address, city, country |
| ticket | type, section, row, seat, price |
| buyer | name, email, phone |
| order | number, date, total |
| codes | qr, barcode, ticket_ref |
| organizer | name, email, phone, logo |

### Configurare

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

### Exemplu de Integrare

```php
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;

// Obține template
$template = TicketTemplate::find($id);

// Obține variabile cu date de exemplu
$variables = app(TicketVariableService::class)->getSampleData();

// Generează preview
$preview = app(TicketPreviewGenerator::class)
    ->generate($template, $variables);

// Folosește template pentru bilet real
$ticketPdf = $generator->render($template, $actualTicketData);
```

### Tipuri de Layer-uri

| Tip | Proprietăți | Descriere |
|-----|-------------|-----------|
| text | font, size, color, alignment | Text static sau variabil |
| image | src, fit, opacity | Logo-uri, fotografii, grafice |
| qrcode | content, errorCorrection | Coduri QR dinamice |
| barcode | content, format | Code128, EAN-13, PDF417 |
| shape | type, fill, stroke | Dreptunghiuri, cercuri, linii |

### Flux de Status

```
draft → active → archived
          ↓
        default (unul per tenant)
```
