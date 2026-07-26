# Catalog de componente

Fiecare componentă = `kit/components/<nume>.php`, pură (date in → HTML out),
stilizată din `tokens.css` cu clase `.kit-*`. Cheam-o cu
`component('<nume>', [...])` sau `component_html('<nume>', [...])` pentru string.

Legendă: **canonic** = view-model din `viewmodel.php` (produs de `kit_*` din
`data.php`). „req” = obligatoriu.

| Componentă | Input | Note |
|---|---|---|
| `event-card` | `event` (canonic, req), `variant`=`grid`\|`horizontal`\|`poster`\|`compact` | Cardul dominant. Badge-uri status + preț „de la”. |
| `event-grid` | `events` (canonic[], req), `variant`, `empty`, `columns` | Grid responsiv de `event-card`; empty-state dacă gol. |
| `event-hero` | `event` (canonic, req), `slot` (HTML, ex. ticket-selector) | Header de pagină single-event. |
| `ticket-selector` | `event` (canonic cu `ticket_types[]`, req) | Alpine (`kitTicketSelector`); adaugă în cart. GA, nu seating. |
| `seat-map` | `event` (canonic, req), `checkout_url` | Mount `data-component="seat-map"`; hidratat de `kit.js` via proxy. |
| `artist-card` | `artist` (canonic, req) | Portret + rol + nume. |
| `venue-card` | `venue` (canonic, req) | Imagine + oraș + nr. evenimente. |
| `category-card` | `item` (taxonomy canonic, req) | Refolosit pentru genuri/orașe. Suportă imagine de fundal sau icon. |
| `subscription-card` | `plan` = {name,price,currency,subtitle,is_featured,benefits[],cta_label,cta_url,badge} | Card de pricing/abonament. |
| `cart-line` | `line` = {title,image,ticket,seats[],qty,price,currency,url} | O linie în cart/checkout. GA sau seating. |
| `filters` | `action` (URL), `fields[]` = {name,label,options[[val,lab]],value,placeholder} | Bară de filtrare GET, SSR, fără JS. |
| `pagination` | `current`,`last`,`base`,`param`,`query` | Paginare cu link-uri simple, SSR. |
| `breadcrumb` | `items[]` = {label,url} (ultimul = curent, fără url) | — |
| `hero` | `title` (req), `eyebrow`,`subtitle`,`image`,`actions[]`={label,url,style} | Hero generic de landing. |
| `cta` | `title`,`text`,`action`={label,url} | Bandă call-to-action. |
| `stat-tile` | `num`,`label`,`hint` | Tile de statistică (dashboard/cont). |
| `empty-state` | `message`,`icon`,`action`={label,url} | Stare goală / negăsit. |

## Layout-uri
| Layout | Vars | Note |
|---|---|---|
| `public` | `title`,`description`,`nav`,`extra_styles`,`extra_head`; `slot` (auto) | `<head>`+header+footer, driven de config+tokens. Include Tailwind+Alpine CDN (comutabile via `use_tailwind`/`use_alpine`). |

## De adăugat (rețeta 5.1 din STARTER-KIT.md)
`schedule-row`, `calendar`, `ticket-card`, `qr-modal`, `order-summary`,
`review-card`, `step-indicator`, `search-bar`, `auth-widget`, `nav-megamenu`,
plus `layout('account')` pentru zona `cont/*`.
