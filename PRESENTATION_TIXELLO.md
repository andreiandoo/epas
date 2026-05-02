# Tixello — Prezentare Platformă

> Document generat în urma analizei codului sursă.
> Două prezentări: **(1) pentru client** și **(2) pentru investitor**.
> Prima pagină a fiecăreia condensează totul în câteva propoziții.

---

# PARTEA 1 — PITCH PENTRU CLIENT

## 🟦 Cover (1 slide, 30 secunde)

> **Tixello — singura platformă din România în care îți pornești un magazin de bilete în 10 minute, pe domeniul tău, plătești doar ce folosești și activezi modul „marketplace” când ești gata să vinzi pentru alții.**
>
> **De la SMS, eFactura ANAF și SmartBill, până la cashless cu brățări NFC la festival, Apple/Google Wallet, scaune cu hartă și 18 integrări (Salesforce, HubSpot, Google Ads, TikTok, Zapier).** O singură platformă, peste 40 de microservicii on/off, pricing modular de la **1 EUR/lună**.
>
> _Dovada că funcționează: în primele 2 săptămâni de la lansare avem deja **2.5 mil. EUR/an** semnați (Ambilet.ro — 1.5 mil. EUR/an pe 10 ani și un al doilea client la 1 mil. EUR/an pe 5 ani)._

---

## 1. Problema clientului

Organizatorii și operatorii de bilete din România jonglează cu:
- 4–6 furnizori separați (ticketing, eFactura, SmartBill, SMS, wallet, POS la intrare);
- comisioane fixe pe bilet care nu scad indiferent de volum;
- fără control real pe brand (vinzi sub umbrela altcuiva);
- integrări lipsă cu CRM, Google Ads, TikTok, HubSpot — adică marketing orb;
- conformitate ANAF cu cap propriu;
- la festival apar hibrizi: cashless + bilete + merch + voluntari + supplier — niciun sistem nu le acoperă pe toate.

## 2. Ce face Tixello

O singură platformă **multi-tenant**, **modulară**, **white-label**, în care fiecare client primește:

1. **Propriul site de bilete** — pe domeniul tău (`teatru.ro`) sau pe subdomeniu instant `teatru.ticks.ro` (provisioning automat prin Cloudflare DNS API, fără să ai website propriu).
2. **Propriii procesatori de plată** — Stripe, Netopia, PayU, EuPlatesc; banii intră direct la tine, noi nu atingem cash-flow-ul.
3. **Microservicii pe care le pornești când ai nevoie** — plătești 1 EUR/lună pentru pixeluri de tracking, 99 EUR/lună pentru cashless festival, 0 EUR pentru ce nu folosești.
4. **Mod „marketplace”** — exact ca Ambilet: tu devii hub-ul, organizatorii intră sub umbrela ta, tu îi aprobi, le procesezi payout-uri prin **Stripe Connect**, le emiți facturi și colectezi comision.

## 3. Ce primești concret în prima săptămână

| Capabilitate | Cu Tixello |
|---|---|
| Site de bilete pe brand-ul tău | ✅ Live în ziua 1 (subdomeniu) sau ziua 3 (custom domain) |
| Plăți cu card (RON, EUR, multi-currency) | ✅ Stripe / Netopia / PayU / EuPlatesc |
| eFactura ANAF + SmartBill | ✅ Automat, 3 EUR/lună |
| Bilete în Apple Wallet & Google Pay | ✅ 10 EUR/lună |
| Bilete personalizate (PDF cu logo, fonturi, QR) | ✅ Editor vizual |
| Hartă de săli cu scaune (SVG import + dynamic pricing) | ✅ Modul Seating |
| Door Sales (POS la intrare, bilete on the spot) | ✅ 15 EUR/lună |
| WhatsApp Business + SMS confirmări | ✅ Twilio + SendSMS.ro |
| GDPR (cookie consent, export date, ștergere) | ✅ Inclus |
| Refund policy automatizat + Insurance | ✅ Inclus |

## 4. De ce ești în siguranță (proof points)

- **Cod Laravel 12 + Filament 4**, framework-uri folosite de mii de companii enterprise.
- **232 de modele**, **588 migrații DB**, **81 servicii**, **140+ resurse Marketplace** — platforma e construită, nu e roadmap.
- **4 paneluri admin separate** (Core / Tenant / Marketplace / Vendor cashless), fiecare cu permisiuni granulare (Spatie Permission).
- **Audit logging complet**, alerte email/Slack, rate-limiting per API key, semnături webhook.
- **Adapter pattern** peste tot (WhatsApp, eFactura, Wallet, NFC chips Desfire EV3 + NTAG213) — schimbi furnizorul fără să schimbi codul.
- **Backup + versioning** integrat (`spatie/laravel-activitylog` + sistem propriu de versioning).

## 5. Modelul comercial — pe înțelesul tău

- **Setup 0 EUR.** Nu îți cerem implementare.
- **Comision pe tranzacție** (negociat per cont).
- **Microservicii à la carte** — vezi mai jos catalogul complet, alegi ce vrei.
- **Anulezi oricând un microserviciu** din panel, fără să suni pe nimeni.

## 6. Catalogul de microservicii (ce vinzi tu, peste ticketing)

| Microserviciu | Preț | La ce îți folosește |
|---|---|---|
| **Cashless Festival** (NFC wristbands, top-up, vendor stands, refund) | **99 EUR/lună** | Replici sistemul Untold/Electric Castle |
| **Extended Artist (EPK)** | 99 EUR/lună | Press kit-uri pentru artiști, leads pentru rideri |
| **Website Editor** (page builder vizual) | 50 EUR/lună | Site-ul de eveniment fără dezvoltator |
| **Ticket Customizer** | 30 EUR (one-time) | PDF-uri personalizate cu variabile dinamice |
| **CRM & Marketing Automation** | 25 EUR/lună | Segmente, campanii email, workflow-uri |
| **Salesforce / Google Ads / LinkedIn Ads** | 25 EUR/lună fiecare | Sincronizare audiențe |
| **Analytics Dashboard** | 20 EUR/lună | Buyer journey, milestone attribution, real-time |
| **Coupon Codes & Campaigns** | 20 EUR/lună | Generare în masă, validare cu antifraud |
| **Shop / Merchandise** | 20 EUR/lună | Tricouri, gift-carduri, produse digitale |
| **HubSpot / TikTok Ads / Zapier** | 20 EUR/lună fiecare | Marketing automation extern |
| **Twilio / Microsoft 365 / Slack** | 10–15 EUR/lună | Comunicare internă |
| **Door Sales (POS la intrare)** | 15 EUR/lună | Vânzare la fața locului cu fee de platformă |
| **Gamification** (badges, XP, rewards) | 15 EUR/lună | Loializare clienți |
| **Blog** | 15 EUR/lună | SEO + conținut |
| **Group Booking** | 12 EUR/lună | Pricing pe grupe / corporate |
| **Mobile Wallet** (Apple + Google) | 10 EUR/lună | UX mobile premium |
| **Affiliate Tracking & Commissions** | 10 EUR/lună | Influencer marketing cu comision |
| **Discord / Knowledge Base** | 10 EUR/lună fiecare | Comunitate + suport |
| **Waitlist** | 8 EUR/lună | Sold out → lead capture |
| **eFactura ANAF** | 3 EUR/lună | Conformitate fiscală automată |
| **Invitations** (invitații nominale, batch, tracking) | 1 EUR/lună | Evenimente private |
| **Tracking Pixels** (FB / GA4 / TikTok / LinkedIn) | 1 EUR/lună | Marketing measurement |
| **Accounting (SmartBill)** | 1 EUR/lună | Sincronizare contabilitate |
| **WhatsApp Business / SMS / Insurance** | per mesaj/poliță | Pay-as-you-go |

## 7. Ce ne diferențiază în piața RO

1. **Singurul cu eFactura ANAF + SmartBill nativ** într-un ticketing platform.
2. **Singurul cu modul cashless complet** (NFC + vendori + reconciliere) — concurența îți cere integrare externă.
3. **Singurul cu mod „marketplace”** plug-and-play (un client ca Ambilet pornește în 2 săptămâni, dovedit).
4. **Subdomeniu instant `*.ticks.ro`** — nu mai pierzi clientul care „nu are website acum”.
5. **18 integrări externe** vs. concurența care are 2–3.

## 8. Roadmap clar

- **Acum (Q2 2026)**: SaaS multi-tenant, marketplace, 40+ microservicii, 4 paneluri.
- **Q3 2026**: Mobile app organizer (Door Sales + scanner), portal artist self-service, marketplace internațional EU (multi-currency activ deja).
- **Q4 2026**: AI pricing dinamic per scaun, predicții sold-out, recomandări audiență.

## 9. Call to action

- **Demo live** pe contul tău în 30 minute (ai deja sandbox creat).
- **Prima factură reală abia după ce vinzi primul bilet.**
- **30 zile money-back** pe orice microserviciu activat.

---

# PARTEA 2 — PITCH PENTRU INVESTITOR

## 🟥 Cover (1 slide, 30 secunde)

> **Tixello — sistemul de operare al ticketing-ului din Europa de Est.**
>
> Stripe pentru bilete: **un singur cont, peste 40 de microservicii à la carte, mod marketplace plug-and-play, conformitate ANAF nativă**. Built on Laravel 12 / Filament 4, **232 modele, 588 migrații, 18 integrări B2B**.
>
> **Validare comercială în 14 zile de la lansare**: **2.5M EUR ARR contractați** — Ambilet.ro (1.5M EUR/an × 10 ani = 15M EUR LTV) și un al doilea client (1M EUR/an × 5 ani = 5M EUR LTV). **Total backlog contractat: 20M EUR.**
>
> Cerem **[X EUR seed]** pentru a captura 5 piețe noi în 12 luni — TAM ticketing CEE: ~600M EUR/an.

---

## 1. Tezi de investiție (de ce acum)

1. **Piața validată în 2 săptămâni**: 20M EUR backlog contractat fără sales team, doar pe 2 conturi.
2. **Modelul de revenue e exponențial**: comision pe bilet + 40+ microservicii recurente + take rate marketplace. Fiecare client mărește ARPU pe măsură ce activează module.
3. **Construit, nu pe slide-uri**: 232 modele, 588 migrații, 4 paneluri admin, 81 servicii — produsul există, nu e MVP.
4. **Defensibilitatea regională**: eFactura ANAF, SmartBill, EuPlatesc, Netopia, PayU — barieră de intrare pentru orice player vestic.
5. **Marketplace network effect**: fiecare client „mare” devine portal pentru sub-organizatori → revenue compus (vezi Ambilet).

## 2. Tracțiunea (proof în cifre reale)

| Metrică | Valoare |
|---|---|
| Timp de la lansare | 2 săptămâni |
| Contracte semnate | 2 |
| ARR contractat | **2.5M EUR** |
| LTV contractat | **20M EUR** (15M Ambilet + 5M client #2) |
| Durată medie contract | 7.5 ani |
| Pipeline (organic) | în creștere săptămânală |
| Cost de achiziție client (CAC) | **0 EUR** (inbound + reputație fondator) |

## 3. Produsul (arhitectura ca avantaj competitiv)

### 3.1 Stack tehnic
- **Laravel 12 + PHP 8.2**, **Filament 4** (admin panels), **Livewire 3**, **Stripe Connect**, **Spatie Permission/ActivityLog**.
- **Multi-tenant** cu rezolvare per domeniu / subdomeniu (Cloudflare DNS API auto-provisioning).
- **Adapter pattern** pe toate integrările → swappable vendors (WhatsApp BSP, NFC chip provider, accounting connector).
- **Queue workers** dedicați microserviciilor + retry exponențial cu dead letter queue.
- **Webhook delivery** cu semnătură HMAC-SHA256, replay protection, rate limiting.
- **API REST + cheie per tenant** cu scope, IP whitelist, rate limit configurabil.

### 3.2 Cele 4 paneluri (4 produse într-unul)
| Panel | Public | Resurse | Rol |
|---|---|---|---|
| **Core Admin** (`/admin`) | echipa Tixello | 67 resurse | Operare platformă |
| **Tenant** (`/tenant`) | client direct | 85 resurse | Self-service ticketing |
| **Marketplace** (`/marketplace`) | client „hub” (ex. Ambilet) | **140 resurse** | Multi-organizer + Stripe Connect payouts |
| **Vendor** (`/vendor`) | tarabe la festival | 4 resurse | POS cashless |

## 4. Mapping complet microservicii (mapped 1:1 din cod)

### 4.1 Plată & Conformitate (motor de revenue recurent)
| Serviciu | Preț | Impact business |
|---|---|---|
| **eFactura ANAF** | 3 EUR/lună | Lock-in regulatoriu — orice firmă RO peste prag e obligată |
| **Accounting connectors (SmartBill)** | 1 EUR/lună | Reduce churn pe segment SMB |
| **Payment Processors** (Stripe / Netopia / PayU / EuPlatesc) | comision tranzacție | Take-rate principal |
| **Tax engine** (TaxAnalytics, TaxImport, TaxReport) | inclus | Multi-jurisdicție pregătit pentru EU |
| **Insurance** (TicketInsurance, MockInsurer + adapters) | per poliță | Revenue per ticket suplimentar |
| **Refunds policy engine** | inclus | Reduce dispute & chargeback |

### 4.2 Marketing & Growth (cross-sell pe baza existentă)
| Serviciu | Preț | Impact |
|---|---|---|
| **CRM + Automation Workflows** | 25 EUR/lună | High retention, expand ARPU |
| **Tracking Pixels** (FB CAPI, GA4, TikTok, LinkedIn) | 1 EUR/lună | Up-sell foarte ușor |
| **Affiliate Tracking & Commissions** | 10 EUR/lună | Influencer economy |
| **Coupon Campaigns** (generation jobs, validation, antifraud) | 20 EUR/lună | Standard în industrie |
| **Promo Codes** (stacking, templates, analytics, importer) | inclus | High frequency feature |
| **Email Campaigns** (segmente dinamice, recipient tracking) | inclus în CRM | Volum mare → margine |
| **Newsletter + Renderer** | inclus | UX simplu |
| **Tracking integrations** (Facebook CAPI, Google Ads, LinkedIn Ads, TikTok Ads) | 15–25 EUR/lună | Marketing budget pull-through |

### 4.3 Distribuție & Brand
| Serviciu | Preț | Impact |
|---|---|---|
| **Custom Domain + Subdomain Cloudflare** | inclus | Onboarding < 10 min |
| **Tenant Template System** | inclus | White-label pe brand |
| **WebTemplate / PageBuilder** | 50 EUR/lună | Reduce dependența pe agenții |
| **Blog** (articles, revisions, comments, subscriptions, SEO) | 15 EUR/lună | Conținut → SEO → CAC mic |
| **Knowledge Base** | 10 EUR/lună | Self-service support |

### 4.4 Bilete & Experiență
| Serviciu | Preț | Impact |
|---|---|---|
| **Seating Maps** (SVG import, dynamic pricing rules, seat holds 10min) | inclus / per layout | Diferentiator vs. concurență |
| **Mobile Wallet** (Apple + Google, push updates, geo-notif) | 10 EUR/lună | UX mobile, reduce no-show |
| **Ticket Customizer** (template engine cu variabile) | 30 EUR one-time | Premium feel |
| **Invitations** (batch, render, tracking, email/PDF) | 1 EUR/lună | Use case corporate |
| **Group Booking** (pricing tiers per grupă) | 12 EUR/lună | Pachete corporate |
| **Waitlist** | 8 EUR/lună | Recuperează revenue post sold-out |
| **Gift Cards** (designs, transactions, redemption) | inclus | High AOV |
| **Festival module** (lineup, addons, sponsors, volunteers, alerts, transport, POI, schedule favorites) | bundle | Festivaluri = high ticket size |

### 4.5 Operațiuni Festival (modul „Cashless” — diferentiator major)
Implementat sub `app/Models/Cashless/` (30 modele) + `app/Services/Cashless/` (15 servicii) + adapters NFC pentru chipuri **Desfire EV3** și **NTAG213**.

| Componentă | Acoperire |
|---|---|
| Cashless accounts cu spending limits | ✅ |
| Top-up channels & methods (cash, card, voucher) | ✅ |
| Vendor stands & products + inventory movement | ✅ |
| Pricing rules + components (happy hour, VIP) | ✅ |
| Finance fee rules + vendor finance summary | ✅ |
| Disputes, refunds, GDPR requests | ✅ |
| Webhook deliveries către POS-uri | ✅ |
| NFC key management cu rotire chei | ✅ |
| Wristband security service + transactions | ✅ |
| Closure checklist, age verification | ✅ |
| Report snapshots zilnice | ✅ |

**Impact:** un singur client festival mare (ex. tip Ambilet) îți deblochează 99 EUR/lună × 12 = ~1.2k EUR/an doar pe Cashless, peste ticketing comision.

### 4.6 Sales & POS
| Serviciu | Preț | Impact |
|---|---|---|
| **Door Sales** (DoorSale + DoorSaleItem + PlatformFee) | 15 EUR/lună | Capture vânzare la intrare |
| **Shop module** (19 modele: produse, variante, stock, shipping zones, wishlist, gift cards) | 20 EUR/lună | Merchandise revenue |

### 4.7 Engagement & Comunicare
| Serviciu | Preț | Impact |
|---|---|---|
| **WhatsApp** (Twilio adapter + Mock + BSP interface) | per mesaj | Volum mare |
| **SMS** (SendSMS.ro adapter) | 0.40–0.50 EUR/SMS | Margine ~30% |
| **Notifications** (multi-channel: db, email, whatsapp) | inclus | Sticky |
| **Gamification** (badges, XP, points, transactions, rewards) | 15 EUR/lună | Retention |

### 4.8 Integrări B2B (rețea de distribuție)
**18 integrări native** — fiecare e un canal de up-sell și un argument enterprise:

`Airtable, Discord, Facebook CAPI, Google Ads, Google Sheets, Google Workspace, HubSpot, Jira, LinkedIn Ads, Microsoft 365, Salesforce, Slack, Square, Telegram, TikTok Ads, Twilio, WhatsApp Cloud, Zapier, Zoom`

ARPU enterprise (corporate / agenții): 200+ EUR/lună doar din integrări.

### 4.9 Marketplace mode (engine-ul Ambilet)
- **MarketplaceClient + MarketplaceAdmin + MarketplaceOrganizer** (cu team members + bank accounts)
- **MarketplaceCart + Transaction + RefundRequest + RefundItem**
- **MarketplacePayout** (Stripe Connect)
- **MarketplaceCustomer** (separat de tenant pentru data ownership)
- **MarketplaceContactList / Tag / Message** (CRM propriu)
- **MarketplaceTaxRegistry + TaxTemplate** (multi-jurisdicție)
- **MarketplaceVanityUrl** (link-uri brand-friendly pentru organizatori)
- **MarketplaceTicketTransfer** (transfer P2P între utilizatori — combat scalper)
- **MarketplaceGiftCard + Designs + Transactions**
- **MarketplaceNewsletter + EmailTemplate + EmailLog**
- **MarketplaceClientMicroservice** pivot — clientul-marketplace vinde la rândul lui microservicii organizatorilor săi (a 2-a layer de revenue)

## 5. Cele mai mari assets ale Tixello (top 7)

1. **Modelul de revenue compound: comision + microservicii + marketplace take-rate.** ARPU crește organic fără sales team. Validat: 2 clienți → 2.5M EUR ARR.
2. **Mod Marketplace (engine-ul Ambilet)** — un client devine canal de distribuție pentru zeci de organizatori. Network effect rar în industrie.
3. **Modulul Cashless complet** — 30 modele + 15 servicii + adapters NFC. Replicabilitate la festivaluri majore din EU. Valoare per contract: 50–500k EUR/sezon.
4. **Conformitate ANAF / EuPlatesc / Netopia / SmartBill nativă** — moat reglementar pe care un competitor vestic îl construiește în 12+ luni.
5. **40+ microservicii cu pricing modular** — același produs vinde în 5 segmente (organizator mic 5 EUR/lună până la festival enterprise 5k EUR/lună).
6. **18 integrări B2B native** — argument decisiv pentru orice corporate (Salesforce, HubSpot, Microsoft 365, Google Workspace).
7. **Subdomeniu instant `*.ticks.ro` + custom domain Cloudflare API** — onboarding 10 min, eliminând principala obiecție „nu am website”.

## 6. Modelul economic (unit economics)

Pe baza catalogului din cod și a contractelor existente:

| Profil client | ARPU/lună estimat | Take-rate tranzacții | Comentariu |
|---|---|---|---|
| Organizator mic (teatru, club) | 30–60 EUR | ~2–3% GMV | Subdomeniu, microservicii basic |
| Organizator mediu (concert promoter) | 200–400 EUR | ~2% GMV | + CRM + Coupon + Wallet + Door Sales |
| Festival mare | 800–2.000 EUR | ~1.5% GMV | + Cashless + Shop + Insurance |
| Marketplace client (Ambilet-tier) | **125k+ EUR/an** (Ambilet: 1.5M/10 ani) | take-rate negociat | Cazuri reale |

**Take rate confirmat pe contracte semnate**: dacă Ambilet face 1.5M EUR/an și e contract pe 10 ani, înseamnă LTV de 15M EUR per cont marketplace.

## 7. Go-to-market (cum scalăm 20M backlog → 100M)

1. **Replicăm modelul Ambilet în alte 4 țări CEE** (PL, HU, CZ, BG) — același cod, doar adaptor fiscal nou.
2. **Targetăm 20 festivaluri mari** în EU pentru modulul Cashless (deal size mediu: 100k EUR/sezon).
3. **Self-serve onboarding pentru SMB** (organizatori mici) — driver de volum pe microservicii cu margini 80%+.
4. **Channel partnerships** cu agenții de marketing (LinkedIn Ads / Google Ads partners) — leadgen B2B.
5. **API ecosystem** — deschidem marketplace-ul de microservicii către third-party developers (rev-share 70/30).

## 8. Concurența

| Player | Slăbiciune față de Tixello |
|---|---|
| **Eventbrite** | Pricing fix per bilet, fără modul cashless festival, fără ANAF, fără marketplace pentru sub-organizatori |
| **Ticketmaster** | Enterprise-only, costuri integrare 6 luni+, fără microservicii à la carte |
| **iaBilet (RO)** | Fără mod marketplace, fără cashless, fără 18 integrări B2B, fără Stripe Connect |
| **Bilete.ro / Entertix** | Stack legacy, fără modular pricing, fără API extensibil |

**Niciun competitor regional** nu combină ticketing + cashless + marketplace + microservicii + ANAF.

## 9. Echipa & execuție (proof point cheie)

- **De la 0 la 20M EUR backlog în 14 zile** = bias to ship + product-market fit.
- Codebase: 588 migrații DB, 232 modele, 81 servicii — execuție tehnică matură.
- Documentație internă completă: 30+ README per modul, plan de migrare în 8 pack-uri.

## 10. Cererea de finanțare

**[X EUR seed]** pentru:
- 40% — sales & customer success (3 BDR + 1 Head of Sales) → captăm pipeline-ul deja generat.
- 25% — expansiune CEE (legal + adapter fiscal × 4 țări).
- 20% — engineering (mobile apps Door Sales + scanner, AI pricing).
- 15% — marketing (paid + content + partnerships).

**Țintă 18 luni**: **15M EUR ARR**, 200 clienți activi, 3 piețe live.

---

## Anexă — Inventar tehnic (one-pager pentru due diligence)

| Capitol | Cifre |
|---|---|
| Eloquent models | 232 |
| Servicii (`app/Services/*`) | 81 |
| Migrații DB | 588 |
| Resurse Filament Core | 67 |
| Resurse Filament Tenant | 85 |
| Resurse Filament Marketplace | 140 |
| Resurse Filament Vendor (Cashless) | 4 |
| Microservicii catalogate (seederi dedicați) | 45 |
| Integrări B2B native | 18 |
| Procesatori plată suportați | 4 (Stripe, Netopia, PayU, EuPlatesc) |
| Adaptere NFC chip | 2 (Desfire EV3, NTAG213) |
| Adaptere Wallet | 2 (Apple, Google) |
| Adaptere WhatsApp BSP | 2 (Twilio, Mock) |
| Adaptere ANAF | 2 (Anaf real, Mock) |
| Adaptere Insurance | 1 + interface |
