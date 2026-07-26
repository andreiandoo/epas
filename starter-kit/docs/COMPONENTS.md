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
| `schedule-row` | `event` (canonic, req) | Rând orizontal cu bloc de dată; liste de program. |
| `calendar` | `events` (canonic[], req) | Widget Alpine (grilă lună + listă filtrată pe zi). |
| `ticket-card` | `ticket` = {event,venue,date,time,seat_label,code,is_subscription} | Bilet cumpărat (cont). Buton QR → `kitQR.show`. |
| `qr-modal` | — (render o dată/pagină) | Modal partajat pt. QR; deschis cu `kitQR.show(code,title)`. |
| `order-summary` | `lines[]` (input cart-line), `totals`={subtotal,fees,discount,total,currency}, `cta`, `title` | Totaluri cart/checkout/confirmare. |
| `review-card` | `review` = {rating,title,body,author,date,event,status} | Recenzie cu stele. |
| `step-indicator` | `steps[]` (label-uri, req), `current` (1-based) | Progres numerotat pt. wizard/checkout. |
| `search-bar` | `action` (URL, req), `placeholder`,`name`,`value` | Formular de căutare GET, SSR. |
| `auth-widget` | `login_url`,`account_url` | Alpine; link login vs avatar+nume din localStorage. |
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
| `public` | `title`,`description`,`nav`,`extra_styles`,`extra_head`; `slot` (auto) | `<head>`+header+footer, token-driven. Emite `window.KIT` + `kit.js`. Tailwind+Alpine CDN comutabile (`use_tailwind`/`use_alpine`). |
| `account` | `title`,`nav`,`extra_styles`; `slot` (auto) | Shell `cont/*` gated pe auth (redirect la login fără token). Sidebar din `config.account_menu`. Randează `qr-modal` o dată. Paginile se hidratează cu `KitProxy` + Bearer. |

## Helper-e client (kit.js, globale)
`KitCart` (cart localStorage) · `KitAuth` (token) · `KitProxy(action,params,opts)`
(fetch prin proxy, adaugă automat Bearer) · `kitQR.show/hide` · componente Alpine:
`kitTicketSelector`, `kitCalendar`, `kitAuthWidget`, `kitAccountShell`. Plus
fallback de imagine (SVG inline) și hidratare `[data-component="seat-map"]`.
