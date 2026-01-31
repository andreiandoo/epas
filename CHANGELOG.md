# CHANGELOG

Acest document urmărește toate modificările și dezvoltările din branch-ul `core-main`, de la începutul proiectului (28 Octombrie 2025) până în prezent.

---

## [2026-01-31] - Căutare Globală și Optimizări

### Funcționalități Noi
- Pagină de rezultate căutare pentru frontend marketplace
- Căutare case-insensitive și diacritic-insensitive

### Îmbunătățiri
- Optimizări de performanță pentru homepage
- Adăugare rută .htaccess pentru pagina de căutare (/cauta)

### Remedieri
- Fix rezultate căutare care nu se afișau pe marketplace frontend
- Fix eroare coloană API căutare
- Adăugare editare sursă HTML la template-uri fiscale

---

## [2026-01-30] - Knowledge Base & Media Library

### Funcționalități Noi
- **Knowledge Base (KB) complet** - categorii, articole, pagini cu URL-uri românești
- **Media Library** cu compresie automată imagini, unelte CLI, grid view, tracking utilizare
- API helper pentru integrare Knowledge Base
- Sistem notificări pentru portal organizator și admin marketplace
- Pagină EventTaxReport și redenumire Tax Templates în Doc Templates

### Îmbunătățiri
- Seeder AmBilet Knowledge Base cu categorii și articole
- Email-uri înregistrare organizator, comision fix, îmbunătățiri UI
- Redesign pagini participanți și promo organizator
- Generare automată contract organizator la verificare

### Remedieri
- Compatibilitate Filament 4 pentru diverse componente
- Fix claim fișiere media orfane pentru marketplace

---

## [2026-01-29] - Sistem Documente Portal Organizator

### Funcționalități Noi
- **Sistem generare documente** (Cerere avizare, Declarație impozite)
- Pagină comenzi servicii organizator (/organizator/servicii/comenzi)
- Căutare instant pentru evenimente
- Cod control pentru participanți

### Îmbunătățiri
- Pagină servicii cu prețuri reale, template-uri email, previzualizări plasament

---

## [2026-01-28] - Analytics & Seating Designer Îmbunătățiri

### Funcționalități Noi
- API goals/milestones, îmbunătățiri pagină analytics, raport eveniment
- Secțiuni lipsă în seating designer marketplace
- Variabile noi template fiscal pentru organizator și eveniment

### Remedieri
- Fix modal Edit Section în seating designer
- Fix taburi status eveniment și eroare SVG seating designer

---

## [2026-01-26-27] - Servicii Extra & Email Marketing

### Funcționalități Noi
- **Backend complet Servicii Extra** (Service Orders)
- Management conturi bancare organizator
- Cheie API organizator și admin prețuri servicii
- Enhanced email marketing cu audience filters

### Îmbunătățiri
- Hero marketplace: redesign ca 3D coverflow carousel

---

## [2026-01-25] - Promoții Organizator

### Funcționalități Noi
- **Funcționalitate completă Promoții Organizator**
- Pagină Servicii Extra pentru promoții organizator
- Gateway plată marketplace (Netopia)

---

## [2026-01-23-24] - Formular Eveniment Organizator

### Funcționalități Noi
- Redesign creare eveniment organizator ca formular accordion multi-step
- Multiselect gen/artist, editor TinyMCE/Jodit, îmbunătățiri căutare venue
- Management sold și plăți organizator marketplace

---

## [2026-01-20-22] - Seating Designer Complet

### Funcționalități Noi
- **Integrare sistem hold locuri cu coș**
- Handle vizual curbă pentru secțiuni
- Setări rând, CTRL+drag rânduri, funcționalitate curbă secțiune
- Meniu context click-dreapta
- Redesign stilizare locuri cu efect 3D și culori noi
- Modal selecție locuri îmbunătățit

---

## [2026-01-17-19] - Analytics Dashboard Organizator

### Funcționalități Noi
- **Dashboard analytics complet pentru organizatori**
- Tracking milestones și goals
- UTM parameter tracking
- Redis (Upstash) integration pentru real-time visitor tracking
- Multi-provider GeoIP service cu fallback chain
- Globe modal cu vizitatori live

### Îmbunătățiri
- Seating map import și editor avansat
- Export SVG, keyboard shortcuts, snap-to-grid

---

## [2026-01-12-16] - Evenimente și Checkout Îmbunătățiri

### Funcționalități Noi
- Parent-child event system pentru multi-day și recurring events
- Export functionality, goals tracking, scheduled reports
- Commission details și payment info
- Ticket Insurance settings

### Îmbunătățiri
- Mobile drawers, custom related events, featured images
- Cart drawer commission, checkout form improvements

---

## [2026-01-09-11] - Sistem Plăți și Gamification

### Funcționalități Noi
- **Payment methods microservices architecture**
- Separate Test/Live credentials pentru payment methods
- MarketplaceCustomerResource pentru utilizatori
- Follow functionality pentru artists și venues
- Dynamic rule-based contact lists
- Tax Registry și Tax Templates pentru Marketplace

### Îmbunătățiri
- XP action triggers pentru gamification system
- Referral system complet cu tracking și notifications

---

## [2026-01-03-08] - Marketplace Frontend Complet

### Funcționalități Noi
- Regions, cities, event categories și blog support
- Romanian regions and cities seeder
- Partner Venues și Venue Categories
- Artist Partners și comprehensive events listing
- Customer API pentru marketplace user dashboard
- PWA manifest

### Îmbunătățiri
- Cart, checkout, și category page improvements

---

## [2026-01-01-02] - Marketplace Panel Refactor

### Funcționalități Noi
- Marketplace Filament Panel pentru marketplace clients (Ambilet)
- Gamification extension: rewards, badges, XP & levels system
- Customer auth API integration pentru marketplace frontend

### Remedieri
- Rename Public namespace la PublicApi (PHP reserved word)
- Compatibilitate Filament 4

---

## [2025-12-26-30] - Marketplace Client Architecture

### Funcționalități Noi
- **Marketplace client architecture pentru custom white-label websites**
- Payout system pentru marketplace organizers
- Customer notifications, password reset, event cancellation
- Ticket transfer, reminders, promo codes
- Gift card system complet pentru marketplace
- Refund notifications și payment processor integration

---

## [2025-12-22-25] - Tax Module

### Funcționalități Noi
- **Tax Module cu General și Local taxes**
- Tax analytics, audit logging, webhooks, caching
- Romanian taxes seeder cu event type mappings
- VAT support pentru VAT payer tenants
- Organizer types și subdomain support

---

## [2025-12-19-21] - Shop & Gamification Modules

### Funcționalități Noi
- **Shop navigation** pentru tenant websites
- **Gamification module** cu points system
- Upsells și bundles pentru tenant website
- Optional subdomain onboarding pentru tenants

### Îmbunătățiri
- Unified cart pentru tickets și shop products
- Shop products în checkout cu shipping și commission

---

## [2025-12-13-18] - Website Templates

### Funcționalități Noi
- **Sleek template** pentru tenant websites cu modern app-like design
- **Theater template** pentru performing arts venues
- **Pub template** pentru bars și casual venues
- Tailwind CSS compilation pentru admin și tenant panels

### Îmbunătățiri
- Artist letter field pentru alphabetical filtering
- Shop și gamification modules în PackageGeneratorService

---

## [2025-12-07-12] - Integration Microservices

### Funcționalități Noi
- Google Ads, TikTok Ads, LinkedIn Ads connector microservices
- Platform-wide dual-tracking system
- Advanced platform marketing features: audiences, customers, attribution, RFM
- GDPR-compliant cookie consent system
- WhatsApp Cloud webhook verification

### Îmbunătățiri
- Mobile UX și responsive design fixes
- Apple Pay domain verification

---

## [2025-12-01-06] - Visual Website Editor

### Funcționalități Noi
- **Visual website editor** ca purchasable microservice
- 22 new page builder blocks pentru complete website construction
- Comprehensive SaaS metrics în Revenue Analytics
- Tabs în tenant edit page și microservices management

---

## [2025-11-28-30] - Tenant Client Enhancements

### Funcționalități Noi
- Ticket detail modal cu QR code display
- Countdown timer pe single event page
- Beneficiary information în tickets
- Public API endpoints cu pagination

### Îmbunătățiri
- Comprehensive event API response
- Bulk delete pentru venues, orders, tickets, customers

---

## [2025-11-22-27] - Customer Authentication System

### Funcționalități Noi
- **Complete customer authentication system**
- Dynamic mail configuration per tenant
- Customer account backend (orders, tickets, profile, email verification)
- Watchlist feature pentru customer favorite events
- Demographic fields pentru customers

### Îmbunătățiri
- Cart service și localStorage functionality
- Checkout și order flow complet

---

## [2025-11-19-21] - Tenant Dashboard & Microservices

### Funcționalități Noi
- **Tenant dashboard panel cu full account management**
- Microservices store cu cart și Stripe checkout
- Door Sales / Box Office microservice
- CRM & Marketing Automation microservice
- Analytics Dashboard microservice
- Mobile Wallet microservice pentru Apple Wallet și Google Pay
- Waitlist & Resale microservice
- Group Booking microservice

### Îmbunătățiri
- Coupon discounts și global search functionality
- Venue infolist și email templates seeder

---

## [2025-11-16-18] - Microservices Infrastructure

### Funcționalități Noi
- **Comprehensive affiliate tracking microservice**
- Complete Stripe payment integration
- Tenant payment processor integration system
- Tracking & Pixels manager microservice
- Ticket Customizer Component microservice
- Invitations microservice
- Ticket Insurance microservice
- Accounting Connectors microservice
- eFactura (RO) microservice
- WhatsApp Notifications microservice
- Tenant Notification System

### Îmbunătățiri
- Production adapters și monitoring infrastructure
- API infrastructure și status monitoring

---

## [2025-11-14-15] - Foundation & Migrations

### Funcționalități Noi
- Enhanced invoice system cu VAT support
- Move Laravel application la repository root pentru Ploi deployment

### Remedieri
- Comprehensive migration order fixes
- Filament 4 compatibility fixes

---

## [2025-10-28-29] - Initial Commit

### Funcționalități Noi
- **Initial commit** - project setup
- Core API route `/v1/public/events`
- Development environment setup

---

# Rezumat Funcționalități Principale

## 🏪 Marketplace Platform
- White-label marketplace pentru multiple clients
- Organizer portal complet
- Customer authentication și accounts
- Gift cards și promo codes
- Refund management

## 🎫 Portal Organizator
- Dashboard cu statistici și analytics
- Management evenimente (creare, editare, publicare)
- Sistem documente (Cerere avizare, Declarație impozite)
- Management echipă și conturi bancare
- Servicii extra și promoții
- Sistem notificări

## 🗺️ Seating Designer
- Editor vizual pentru layout-uri locuri
- Sistem hold locuri integrat cu coș
- Efect 3D pentru locuri
- Setări rând și secțiune avansate
- Import/export layout-uri SVG

## 📚 Knowledge Base
- Categorii și articole
- URL-uri românești
- Tracking vizualizări
- Articole relacionate

## 💳 Plăți & Facturare
- Integrare Netopia, Stripe, PayU, Euplatesc
- Management sold organizator
- Sistem plăți servicii extra
- Tax module complet

## 🔍 Căutare Globală
- Căutare case-insensitive
- Suport diacritice
- Pagină rezultate dedicată

## 📧 Comunicare
- Template-uri email
- Sistem notificări
- WhatsApp integration
- Invitații echipă

## 🖼️ Media Library
- Compresie automată imagini
- Grid view
- Tracking utilizare
- Scanare fișiere orfane

## 🎮 Gamification
- Rewards și badges
- XP & levels system
- Referral tracking
- Points pentru acțiuni

## 🛒 Shop Module
- Product management
- Variants și inventory
- Shipping zones
- Upsells și bundles

## 📊 Analytics
- Dashboard organizator
- Real-time visitor tracking
- Milestones și goals
- UTM tracking
- GeoIP localization

## 🔧 Microservices (20+)
- Affiliate Tracking
- Ticket Customizer
- Invitations
- Ticket Insurance
- Accounting Connectors
- eFactura (RO)
- WhatsApp Notifications
- Mobile Wallet
- Waitlist & Resale
- Group Booking
- Analytics Dashboard
- CRM & Marketing
- Door Sales / Box Office
- Visual Website Editor
- Blog
- Coupon Codes
- Google/TikTok/LinkedIn Ads
- și altele...

---

## Statistici

| Perioadă | Commit-uri |
|----------|------------|
| Octombrie 2025 | 2 |
| Noiembrie 2025 | ~350 |
| Decembrie 2025 | ~450 |
| Ianuarie 2026 | ~960 |
| **Total** | **~1766** |

---

*Generat automat din istoricul commit-urilor branch-ului `core-main`*
*Prima versiune: 2025-10-28*
*Ultima actualizare: 2026-01-31*
