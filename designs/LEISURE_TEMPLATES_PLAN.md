# Plan — template-uri customizabile per client Leisure

## Problema

`leisure-venue.php` actual e **un template unic** cu un layout fix:
hero → calendar+bilete → servicii → trasee → despre → program → cum ajungi → FAQ → galerie → video → sticky cart.

E perfect pentru "Lacul Sf. Ana" (rezervație naturală), DAR alte locații de agrement vor cere alt layout:
- Aquapark: mai puține trasee, mai multe servicii + galerie video mare
- Castel/muzeu: tur ghidat ca atracție principală, fără calendar zilnic, fără trasee
- Karting/parc aventură: focus pe pachete + fotograf, fără despre-istoric
- Camping standalone: rezervare camping prioritar, fără tururi

Nu vrem **un singur PHP** care să devină un monstru cu zeci de `if (...)` pentru fiecare client.

## Soluție recomandată — sistem de **layout variants** + **sectiuni opt-in**

### Două axe de customizare:

**Axa 1 — variant template** (selectabil prin Filament):
- `leisure_default` — layout-ul curent (rezervație/parc natural)
- `leisure_aquapark` — variantă pentru aquapark/strand
- `leisure_castle` — variantă pentru muzeu/castel/monument
- `leisure_adventure` — variantă pentru parc aventură/karting
- `leisure_camping` — variantă cu focus camping
- ... putem adăuga pe parcurs

Fiecare variant = un **fișier PHP shell** care orchestrează ce secțiuni se renderizează și în ce ordine. Toate secțiunile sunt **partiale reutilizabile**.

**Axa 2 — sectiuni opt-in per client** (toggles în venue_config):
În orice variant, clientul poate **ascunde/afișa** secțiuni individuale:
```json
"sections": {
  "hero": true,
  "quick_stats_bar": true,
  "booking": true,
  "services": true,
  "upsell_cta": true,
  "trails": true,
  "attractions": true,
  "stats_highlights": true,
  "flora": true,
  "schedule": true,
  "getting_there": true,
  "gallery": true,
  "video": false,
  "faq": true
}
```

Plus **reordering** prin drag&drop în Filament:
```json
"section_order": [
  "hero", "quick_stats_bar", "booking", "upsell_cta", "services",
  "gallery", "video", "attractions", "stats_highlights", "trails",
  "schedule", "getting_there", "faq"
]
```

## Arhitectura tehnică propusă

### 1. Restructurare fișiere

```
epas/resources/marketplaces/ambilet/
├── leisure-venue.php                          # Dispatcher (alege variantul)
├── leisure-templates/
│   ├── _shared/                               # Sectiuni partiale (reutilizabile)
│   │   ├── nav.php
│   │   ├── hero.php
│   │   ├── quick-stats-bar.php
│   │   ├── booking-calendar-tickets.php
│   │   ├── upsell-cta.php
│   │   ├── services.php
│   │   ├── trails.php
│   │   ├── attractions.php
│   │   ├── stats-highlights.php
│   │   ├── flora.php
│   │   ├── schedule.php
│   │   ├── getting-there.php
│   │   ├── faq.php
│   │   ├── gallery.php
│   │   ├── video.php
│   │   ├── sticky-cart.php
│   │   └── footer.php
│   ├── variants/
│   │   ├── leisure-default.php                # Lacul Sf. Ana (current)
│   │   ├── leisure-aquapark.php
│   │   ├── leisure-castle.php
│   │   ├── leisure-adventure.php
│   │   └── leisure-camping.php
│   └── themes/
│       ├── _base.css                          # CSS partial (paleta Tailwind, lv-btn, etc.)
│       ├── forest-lake.css                    # Tema Sf. Ana (forest/lake/sand)
│       ├── aqua-tropical.css                  # Tema aquapark (cyan/sand bright)
│       ├── castle-royal.css                   # Tema castel (gold/burgundy)
│       └── adventure-orange.css               # Tema parc aventură
```

### 2. Câmpuri noi pe `events.venue_config`

```json
{
  "template_variant": "leisure_default",        // string: alege variant
  "template_theme":   "forest-lake",            // string: alege tema CSS

  "sections": {                                 // toggles opt-in
    "hero": true,
    "booking": true,
    "services": true,
    "trails": true,
    // ... toate sectiunile
  },

  "section_order": [                            // array de string-uri, drag&drop
    "hero", "quick_stats_bar", "booking", ...
  ],

  // Restul venue_config existent: hero_badges, attractions, trails, etc.
}
```

### 3. Dispatcher (`leisure-venue.php`)

```php
<?php
// (load $ev, $venueConfig, $issuers, etc. — la fel ca acum)

$variant = $venueConfig['template_variant'] ?? 'leisure_default';
$theme = $venueConfig['template_theme'] ?? 'forest-lake';

$variantFile = __DIR__ . '/leisure-templates/variants/' . $variant . '.php';
if (!file_exists($variantFile)) {
    $variantFile = __DIR__ . '/leisure-templates/variants/leisure-default.php';
}

// Variantul are acces la $ev, $venueConfig, $issuers, etc.
include $variantFile;
```

### 4. Variant exemplu (`variants/leisure-default.php`)

```php
<?php
$themeFile = __DIR__ . '/../themes/' . ($theme ?? 'forest-lake') . '.css';
$sections = $venueConfig['sections'] ?? []; // default all true
$sectionOrder = $venueConfig['section_order'] ?? [
    'nav', 'hero', 'quick_stats_bar', 'booking', 'upsell_cta',
    'services', 'trails', 'attractions', 'schedule',
    'getting_there', 'gallery', 'video', 'faq', 'sticky_cart', 'footer',
];

require_once __DIR__ . '/../../includes/head.php';
?>
<style><?= file_get_contents(__DIR__ . '/../themes/_base.css') ?></style>
<style><?= file_get_contents($themeFile) ?></style>

<div x-data="reservationPage()" x-cloak>
<?php
foreach ($sectionOrder as $sectionKey) {
    if (isset($sections[$sectionKey]) && !$sections[$sectionKey]) continue; // skip
    $sectionFile = __DIR__ . '/../_shared/' . str_replace('_', '-', $sectionKey) . '.php';
    if (file_exists($sectionFile)) {
        include $sectionFile;
    }
}
?>
</div>

<?php
require_once __DIR__ . '/../_shared/_alpine-component.php'; // JS reservationPage()
require_once __DIR__ . '/../../includes/footer.php';
require_once __DIR__ . '/../../includes/scripts.php';
```

### 5. UI Filament — selector variant + theme + sectiuni

În tab "Configurare Locație", **secțiune nouă la TOP**:

```php
SC\Section::make('🎨 Template & aspect')
    ->description('Alege layoutul si tema vizuala. Toate datele raman intacte cand schimbi varianta.')
    ->schema([
        Forms\Components\Select::make('venue_config.template_variant')
            ->label('Variant template')
            ->options([
                'leisure_default'  => 'Rezervație naturală / Parc (Sf. Ana style)',
                'leisure_aquapark' => 'Aquapark / Ștrand',
                'leisure_castle'   => 'Castel / Muzeu',
                'leisure_adventure'=> 'Parc aventură / Karting',
                'leisure_camping'  => 'Camping standalone',
            ])
            ->default('leisure_default'),
        Forms\Components\Select::make('venue_config.template_theme')
            ->label('Temă vizuală (culori)')
            ->options([
                'forest-lake'     => 'Forest & Lake (verde + cyan, Sf. Ana)',
                'aqua-tropical'   => 'Aqua Tropical (cyan + galben)',
                'castle-royal'    => 'Castle Royal (gold + burgundy)',
                'adventure-orange'=> 'Adventure Orange (portocaliu + negru)',
            ])
            ->default('forest-lake'),
    ]);

SC\Section::make('Sectiuni vizibile')
    ->description('Activeaza/dezactiveaza ce sectiuni apar pe pagina publica.')
    ->collapsed()
    ->schema([
        // 14 toggle-uri, unul per sectiune
        ...
    ]);

SC\Section::make('Ordine sectiuni')
    ->description('Trage si plaseaza ca sa schimbi ordinea pe pagina.')
    ->collapsed()
    ->schema([
        Forms\Components\Repeater::make('venue_config.section_order')
            ->reorderable()
            ->minItems(0)
            ->disableItemDeletion()  // doar reordering, nu remove
            ->disableItemCreation()
            ->itemLabel(fn (array $state) => $sectionLabelsRo[$state['key']] ?? $state['key']),
    ]);
```

### 6. Migrarea graduală

**Faza A** (nu strica nimic, ~1 zi):
- Extract sectiunile din `leisure-venue.php` actual în partiale în `_shared/`
- Crează `variants/leisure-default.php` care reproduce **exact** comportamentul actual
- `leisure-venue.php` devine dispatcher; default variant face același lucru ca azi

**Faza B** (~1 zi per variant):
- Crează `variants/leisure-aquapark.php` cu secțiunile relevante + tema aqua
- Pentru fiecare client nou Leisure, decidi care variant + ce sectiuni opt-in

**Faza C** (~1 zi):
- UI Filament selector + toggle-uri + reordering

**Faza D** (~3-4 zile total):
- Teme CSS separate: extract `--color-primary`, `--color-accent`, `--font-display` etc. în CSS variables
- `themes/_base.css` definește layout-ul, `themes/{theme}.css` doar overide-uie paleta

### 7. Beneficii arhitectură

- **Zero schimbări breaking**: variantul default reproduce 1:1 ce e azi.
- **Adăugare variant nou = ~1 fișier PHP** (variants/leisure-X.php) + opțional 1 fișier CSS (themes/X.css). Fără atins shared.
- **Per-client granular**: 2 clienți cu **același variant** pot să-și ascundă/reordoneze secțiuni diferit.
- **Marketplace level overrides**: putem adăuga la nivel de `MarketplaceClient` un set de variante "permise" — Ambilet ca marketplace decide ce template-uri sunt disponibile (gating).
- **Backend rămâne unic** (acelaș Filament tab, aceleași API-uri). Doar render-ul frontend e variabil.

### 8. Edge cases și răspunsuri

- **JS reservationPage()** rămâne unic (`_shared/_alpine-component.php`); toate secțiunile fac binding la aceeași stare Alpine.
- **Capacitate / API**: niciun impact — toate variantele folosesc aceleași endpoint-uri.
- **Cart**: sticky-cart e o sectiune partial; orice variant îl poate include sau ascunde (rar va vrea cineva fără cart).
- **SEO/meta**: head.php deja primește `$pageTitle`/`$pageDescription` — neschimbat.
- **Custom CSS per client** (la nivel de individual customization): viitor F+, putem adăuga câmp `venue_config.custom_css` (text area) injectat la final ca `<style>`. Risc de abuz; whitelist proprietăți.

### 9. Estimare totală implementare

| Fază | Conținut | Estimare |
|---|---|---|
| A | Extract partiale + dispatcher + default variant | 1 zi |
| B | UI Filament selector + toggles + reordering | 1 zi |
| C | Themes via CSS variables (extract _base + 1 alt theme) | 0.5 zi |
| D | 1 variant nou ex aquapark cu sectiuni custom + tema | 1 zi |
| E | Per-marketplace gating (Ambilet decide variante disponibile) | 0.5 zi |
| **Total MVP** | Default funcțional + 1 variant nou + UI + themes | **~4 zile** |

Plus ~1 zi per fiecare variant suplimentar.

## Recomandare ordine

1. **F5** (mai întâi): panou self-service organizator. Dă valoare imediată user-ului.
2. **F4.2** (după F5): extract partiale + dispatcher cu **doar default variant** — refactor pur, fără variante noi. Asta e pre-requisitul pentru orice variant nou.
3. **F4.3**: UI Filament selector + toggle-uri + reordering. Atunci utilizatorul poate experimenta să ascundă secțiuni pe Sf. Ana.
4. **F4.4**: Primul variant nou (aquapark sau castle), când avem un al doilea client Leisure real care îl cere.

Dacă vrei să prioritizezi diferit (ex: să faci direct refactor + variante înainte de F5), spune-mi și schimb ordinea.
