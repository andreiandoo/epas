# Leisure Venue — câmpuri backend necesare

Designul `sf-ana.html` cere informații care nu există încă în backend.
Toate intră în **`events.venue_config`** (jsonb, deja cast `array` pe `Event`),
DECI **nu** avem nevoie de coloane DB noi sau migrații — doar de UI Filament
(panou admin Tixello) **și** un panou self-service pe ambilet pentru administratorii
rezervației.

Structura completă propusă pentru `venue_config`:

```json
{
  "title_primary":   "Lacul Sfânta Ana",
  "title_secondary": "& Tinovul Mohoș",
  "hero_kicker":     "Rezervație naturală protejată",
  "hero_badges":     ["🌿 Sit Natura 2000", "🏔️ Altitudine 950m", "Jud. Harghita"],
  "hero_images":     ["events/4234/hero.jpg"],

  "amenities": ["Piscina", "Restaurant", "Ghid tur"],
  "contact_phone": "+40 752 171 050",
  "directions_url": "https://maps.app.goo.gl/...",
  "max_advance_days": 90,
  "rules_html": "<p>Reguli...</p>",

  "quick_stats": [
    { "icon": "🐻", "label": "Atenție", "value": "Zonă cu urși · ghid obligatoriu" }
  ],

  "seasons": [
    {
      "name": "Vara",
      "start": "04-15", "end": "10-15",
      "schedule": {
        "mon": { "open": "09:00", "close": "19:00" },
        "tue": { "open": "09:00", "close": "19:00" },
        "wed": { "open": "09:00", "close": "19:00" },
        "thu": { "open": "09:00", "close": "19:00" },
        "fri": { "open": "09:00", "close": "19:00" },
        "sat": { "open": "08:00", "close": "20:00" },
        "sun": { "open": "08:00", "close": "20:00" }
      },
      "last_entry": "18:30",
      "note": "Tururi Tinov: 10:00, 11:00, 12:00, 13:00, 14:00, 15:00, 16:00"
    },
    {
      "name": "Iarna",
      "start": "10-16", "end": "04-14",
      "schedule": {
        "fri": { "open": "10:00", "close": "16:00" },
        "sat": { "open": "09:00", "close": "16:00" },
        "sun": { "open": "09:00", "close": "16:00" }
      },
      "note": "Tinovul Mohoș: suspendat iarna."
    }
  ],

  "closed_dates": ["2026-12-25", "2027-01-01"],

  "pricing_rules": [
    { "label": "Sambata +25%", "days": ["sat"], "type": "percent", "value": 25 },
    { "label": "Super Miercuri!", "days": ["wed"], "type": "fixed", "value": -20 }
  ],

  "about_title": "Două cratere, o poveste",
  "attractions": [
    {
      "name": "Lacul Sfânta Ana",
      "badge": "💧 Lac vulcanic",
      "badge_bg": "#CFFAFE", "badge_color": "#155E75",
      "blob_gradient": "linear-gradient(135deg, #A5F3FC, #22D3EE)",
      "description": "Singurul lac vulcanic din Europa Central-Estică...",
      "bullets": [
        "Plimbare cu barca disponibilă pe lac",
        "Capela Sfânta Ana în apropiere",
        "Tur al lacului ~30 min"
      ]
    },
    {
      "name": "Tinovul Mohoș",
      "badge": "🌿 Turbărie protejată",
      "badge_bg": "#DCF2E3", "badge_color": "#1F4E37",
      "blob_gradient": "linear-gradient(135deg, #BBE5C9, #2D7A4F)",
      "description": "Mlaștină de turbă în al doilea crater al Ciomatului...",
      "bullets": [
        "Plante carnivore: Roua cerului",
        "Pin pitic și mesteacăn pitic",
        "Vizitare doar cu ghid, ~40 min"
      ]
    }
  ],

  "stats_highlights": [
    { "value": "30k",   "label": "Ani de la formare" },
    { "value": "950m",  "label": "Altitudine" },
    { "value": "240ha", "label": "Suprafață protejată" },
    { "value": "17",    "label": "Ochiuri de apă în Tinov" }
  ],

  "flora": [
    { "emoji": "🌿", "name": "Roua cerului",    "latin": "Drosera rotundifolia" },
    { "emoji": "🌲", "name": "Pin silvestru",   "latin": "Pinus sylvestris" },
    { "emoji": "🌱", "name": "Mesteacăn pitic", "latin": "Betula nana" },
    { "emoji": "🐻", "name": "Urs brun",        "latin": "Ursus arctos" }
  ],

  "trails": [
    {
      "name": "Băile Tușnad → Lacul Sf. Ana",
      "description": "Poteca clasică pe viroaga Komlós...",
      "marker": "Cruce roșie",
      "marker_symbol": "✚",
      "difficulty": "Mediu",
      "length": "6.7 km",
      "duration": "2.5 – 3h",
      "elevation": "+450m",
      "start_point": "Centrul Băile Tușnad",
      "polyline": [[46.150, 25.847], [46.140, 25.870], [46.128, 25.886]]
    }
  ],

  "safety_warning": {
    "icon": "🐻",
    "title": "Important: zonă cu urși",
    "body":  "În zonă trăiesc 19 urși. Recomandăm drumeții în grup..."
  },

  "getting_there": [
    {
      "icon": "🚗", "icon_bg": "#1F4E37",
      "title": "Cu mașina",
      "description": "București → Brașov → ...",
      "note": "Parcare la 1.400m de lac."
    },
    {
      "icon": "🚶", "icon_bg": "#0891B2",
      "title": "Pe jos / bicicletă",
      "description": "De la Băile Tușnad pe traseul...",
      "note": "Vezi traseele detaliate mai sus."
    },
    {
      "icon": "🏕️", "icon_bg": "#B89968",
      "title": "Cazare aproape",
      "description": "Camping pe site (75 RON/noapte)...",
      "note": "Camping cu gard electric, toalete."
    }
  ],

  "map_config": {
    "center": [46.1287, 25.8867],
    "zoom": 12,
    "pois": [
      { "lat": 46.1287, "lng": 25.8867, "label": "Lacul Sf. Ana",  "color": "#06B6D4" },
      { "lat": 46.1357, "lng": 25.8979, "label": "Tinovul Mohoș",  "color": "#3D9663" },
      { "lat": 46.1505, "lng": 25.8475, "label": "Băile Tușnad",   "color": "#E5D8C0" }
    ]
  },

  "faqs": [
    { "q": "Pot vizita Tinovul Mohoș fără ghid?", "a": "Nu. Tinovul este..." },
    { "q": "Câinii sunt permiși?",                "a": "Da, dar doar în lesă scurtă..." }
  ]
}
```

## Ce trebuie implementat în Filament admin (F4)

`app/Filament/Marketplace/Resources/EventResource.php`, tab nou **"Locație de agrement — Conținut"**
(vizibil doar pentru `display_template === 'leisure_venue'`):

Folosim `Forms\Components\KeyValue` și `Repeater` peste sub-keys din `venue_config`:

1. **Hero & identitate**
   - `venue_config.title_primary` (TextInput)
   - `venue_config.title_secondary` (TextInput) — apare italic în hero
   - `venue_config.hero_kicker` (TextInput)
   - `venue_config.hero_badges` (TagsInput) — badge-uri overlay pe hero
   - `venue_config.hero_images` (FileUpload, multiple)

2. **Quick stats bar** (Repeater, max 1-2 items)
   - `icon` (TextInput emoji), `label`, `value`

3. **Despre — atracții** (Repeater, 1–4 items)
   - `name`, `badge`, `badge_bg`, `badge_color`, `blob_gradient`, `description` (Textarea),
     `bullets` (TagsInput / Repeater simplu)

4. **Stats highlights** (Repeater, 2–4 items)
   - `value`, `label`

5. **Floră & faună** (Repeater)
   - `emoji`, `name`, `latin`

6. **Trasee turistice** (Repeater)
   - `name`, `description` (Textarea), `marker`, `marker_symbol`, `difficulty` (Select: Ușor/Mediu/Greu),
     `length`, `duration`, `elevation`, `start_point`, `polyline` (Repeater de [lat, lng] sau Textarea JSON)

7. **Safety warning** (Group)
   - `icon`, `title`, `body`

8. **Cum ajungi** (Repeater, 1–4 carduri)
   - `icon`, `icon_bg` (ColorPicker), `title`, `description` (Textarea), `note`

9. **Map config**
   - `center.lat`, `center.lng`, `zoom` (slider 8–18)
   - `pois` (Repeater) — `lat`, `lng`, `label`, `color`

10. **FAQ-uri** (Repeater)
    - `q` (TextInput), `a` (RichEditor)

11. **Despre — meta**
    - `about_title` (TextInput)

12. **Pricing rules** (există deja parțial; verifică & extinde)
    - `label`, `days` (Select multiple), `type` (percent/fixed), `value`

13. **Schedule & sezoane** (există parțial; verifică UI complet)
    - Editor pentru `seasons` cu schedule per zi + last_entry + note

## Ce trebuie implementat în panou self-service organizator (F5)

Extindere pagină `/organizator/leisure` cu **tab-uri editabile**:

```
/organizator/leisure
├── Tab "Acces & bilete" (existing)
├── Tab "Hero & branding"
├── Tab "Atracții & poveste"
├── Tab "Trasee turistice"
├── Tab "Program & sezoane"
├── Tab "Cum ajungi & hartă"
├── Tab "FAQ"
└── Tab "Rapoarte fiscale" (existing)
```

Fiecare tab face PUT la un endpoint nou:

```
PUT /api/marketplace-client/organizer/events/{event}/leisure/venue-config
Body: { "section": "hero", "data": { ... } }
```

Backend: `LeisureController::updateVenueConfig` care merge-uiește data în
`event.venue_config[section]` cu validare per secțiune.

## Validare cerută per secțiune

- Pricing rules: validare ca sa nu fie suprapuse pe aceeași zi
- Seasons: start/end trebuie să acopere fără gap-uri (warning soft)
- Trails polyline: array de tuples [lat,lng] numerice
- Map config: lat în [-90,90], lng în [-180,180]
- FAQs: `a` poate fi HTML rezultat din RichEditor; sanitize
- Hero images: tip image, max 5MB, validare upload

## Faze de implementare

| Fază | Conținut | Estimare |
|---|---|---|
| **F3.5** | (LIVE) leisure-venue.php cu design Sf Ana | DONE |
| **F4** | Filament tab "Locație de agrement — Conținut" cu toate sub-secțiunile | 1-2 zile |
| **F5** | Panou self-service organizator cu editare conținut | 2-3 zile |
| **F5.1** | Endpoint API `updateVenueConfig` + validări | 0.5 zile |

**Pentru a vedea designul cu date reale ACUM**: edit manual din tinker pe core:

```php
$e = \App\Models\Event::find(4234);
$vc = $e->venue_config ?? [];
$vc['title_primary'] = 'Lacul Sfânta Ana';
$vc['title_secondary'] = '& Tinovul Mohoș';
$vc['hero_kicker'] = 'Rezervație naturală protejată';
$vc['hero_badges'] = ['🌿 Sit Natura 2000', '🏔️ Altitudine 950m', 'Jud. Harghita'];
// ... etc (vezi structura completă mai sus)
$e->venue_config = $vc;
$e->save();
```

După F4, toate astea se editează prin UI fără tinker.
