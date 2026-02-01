# RAPORT FINAL DE AUDIT DE SECURITATE - EPAS Platform

**Data:** 2026-02-01
**Versiune:** 3.0 (Audit Exhaustiv Complet)
**Status:** CRITIC - PLATFORMA NU TREBUIE SA FIE IN PRODUCTIE

---

## SUMAR EXECUTIV

Acest raport reprezinta auditul de securitate COMPLET al platformei EPAS. Au fost analizate TOATE componentele: Tenant, Marketplace, Microservicii, Payment Processors, Filament Admin, Seating System, Sessions, Email si fisierele PHP publice.

### STATISTICI FINALE

| Categorie | Critica | Ridicata | Medie | Scazuta | TOTAL |
|-----------|---------|----------|-------|---------|-------|
| Tenant & Marketplace | 7 | 8 | 4 | 2 | 21 |
| Microservicii | 7 | 12 | 5 | 1 | 25 |
| Payment Processors | 5 | 6 | 4 | 0 | 15 |
| Filament Admin | 5 | 10 | 7 | 1 | 23 |
| Seating System | 4 | 6 | 4 | 3 | 17 |
| Public PHP Files | 2 | 4 | 6 | 0 | 12 |
| Sessions & Cookies | 8 | 12 | 6 | 0 | 26 |
| Email & Notifications | 5 | 6 | 6 | 3 | 20 |
| Hardcoded Secrets | 4 | 2 | 3 | 0 | 9 |
| **TOTAL** | **47** | **66** | **45** | **10** | **168** |

---

## TOP 25 VULNERABILITATI CRITICE

### 1. RCE via Webhook Deploy (CVSS 10.0)
**Fisier:** `_webhook-deploy.php:21`
```php
define('DEPLOY_SECRET', 'CHANGE_THIS_TO_RANDOM_SECRET');
```
Atacatorul poate executa cod arbitrar pe server.

### 2. Admin Routes Fara Autentificare (CVSS 9.8)
**Fisier:** `routes/api.php:1250`
Toate rutele admin sunt publice - oricine poate accesa dashboard, orders, customers.

### 3. TenantScope Bypass via Query Param (CVSS 9.5)
**Fisier:** `TenantScope.php:46-48`
Atacatorul poate accesa datele oricarui tenant cu `?_tenant_id=X`.

### 4. Payment Amount Manipulation (CVSS 9.5)
**Fisier:** `CartController.php:327-366`
Utilizatorul poate seta suma de plata arbitrar - cumparare $100 produse pentru $1.

### 5. Double-Spending in Webhooks (CVSS 9.3)
**Fisier:** `MarketplaceClient/PaymentController.php:189-219`
Webhook replay cauzeaza plati multiple, bilete duplicate.

### 6. Session Fixation - Predictable Session ID (CVSS 9.3)
**Fisier:** `CartController.php:594`
Session ID generat din MD5(IP + UserAgent) - predictibil.

### 7. IDOR pe Orders (CVSS 9.1)
**Fisier:** `TenantClient/OrderController.php:267`
Lipsa verificare tenant_id - acces la toate comenzile.

### 8. Webhook Signature Bypass (CVSS 9.0)
**Fisier:** `WhatsAppController.php:256-260`
Verificarea semnaturii webhook este complet comentata.

### 9. XXE Injection in ANAF (CVSS 9.0)
**Fisier:** `AnafAdapter.php:673-680`
XML parsing fara protectie XXE.

### 10. Unsafe PHP Serialization - RCE (CVSS 9.0)
**Fisier:** `DefaultPricingEngine.php:31-92`
`unserialize()` pe date din cache - RCE daca cache-ul e compromis.

### 11. Hardcoded API Key in Source (CVSS 9.0)
**Fisier:** `includes/config.php:15`
API key real hardcodat: `mpc_4qkv4pcuogusFM9234dwihfTrrkBNT2PzpHflnLLmKfSXgkef9BvefCISPFB`

### 12. XML Signature Not Implemented (CVSS 8.8)
**Fisier:** `AnafAdapter.php:665-681`
Facturile ANAF sunt trimise NESEMNATE - oricine le poate modifica.

### 13. Super Admin Login Bypass (CVSS 8.7)
**Fisier:** `AuthController.php:334-373`
Endpoint public fara rate limiting - brute-forceable.

### 14. Price Manipulation in Seating (CVSS 8.5)
**Fisier:** `SeatingController.php:246-306`
Suma de plata acceptata de la client fara validare.

### 15. Cross-Tenant Seat Access (CVSS 8.5)
**Fisier:** `EventSeatingLayout.php:12-82`
Modelele seating nu au TenantScope - acces cross-tenant.

### 16. Session Hijacking via Header (CVSS 8.5)
**Fisier:** `CartController.php:574-576`
Session ID acceptat din header fara validare - hijacking.

### 17. CORS Wildcard + Credentials (CVSS 8.5)
**Fisier:** `config/cors.php:5,10`
`allowed_origins: *` cu `credentials: true` = CSRF complet.

### 18. All Policies Return True (CVSS 8.3)
**Fisiere:** `VenuePolicy.php`, `ArtistPolicy.php`
Toate metodele de autorizare returneaza `true` - oricine poate face orice.

### 19. Mass Assignment ($guarded = []) (CVSS 8.3)
**Fisier:** `Artist.php:33`
`$guarded = []` permite mass assignment pe TOATE campurile.

### 20. User Policy Temporary Bypass (CVSS 8.3)
**Fisier:** `UserPolicy.php:18`
`return true;` hardcodat pentru "testing".

### 21. Reset Owner Password No Auth (CVSS 8.3)
**Fisier:** `EditTenant.php:296-328`
Oricine poate reseta parola owner-ului tenant.

### 22. SSL Disabled in Payment Processors (CVSS 8.0)
**Fisiere:** `EuplatescProcessor.php:162`, `PayUProcessor.php:191`
SSL verification dezactivat - MITM attacks.

### 23. Open Redirect in Login (CVSS 7.8)
**Fisier:** `login.php:118`
Redirect URL acceptat fara validare.

### 24. HTML Injection in Emails (CVSS 7.8)
**Fisiere:** `contract.blade.php:51`, `InvoiceMail.php:99`
`{!! !!}` fara escape - XSS in emails.

### 25. Session Encryption Disabled (CVSS 7.5)
**Fisier:** `config/session.php:50`
`SESSION_ENCRYPT=false` by default.

---

## VULNERABILITATI PE COMPONENTE

### A. TENANT & MARKETPLACE (21 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| TM-01 | Admin routes fara auth | CRITICA | routes/api.php:1250 |
| TM-02 | IDOR Orders | CRITICA | OrderController.php:267 |
| TM-03 | Super admin login bypass | CRITICA | AuthController.php:334 |
| TM-04 | TenantScope query param bypass | CRITICA | TenantScope.php:46 |
| TM-05 | Tenant resolution bypass | CRITICA | AuthController.php:30-52 |
| TM-06 | Missing tenant isolation marketplace | CRITICA | EventsController.php:619 |
| TM-07 | API keys plaintext | CRITICA | MarketplaceClientAuth.php:38 |
| TM-08 | withoutGlobalScopes misuse | RIDICATA | AffiliateController.php |
| TM-09 | Email verification weak | RIDICATA | AuthController.php:390 |
| TM-10 | Order authorization missing | RIDICATA | OrdersController.php:212 |
| TM-11 | Cart validation missing | RIDICATA | OrderController.php:109 |
| TM-12 | SQL injection shop search | RIDICATA | ShopProductController.php:113 |
| TM-13 | Rate limiting missing sensitive ops | RIDICATA | Multiple |
| TM-14 | Tenant resolution arbitrary | RIDICATA | AuthController.php:48 |
| TM-15 | Mass assignment potential | MEDIE | AdminController.php:180 |
| TM-16 | Permission checks missing | MEDIE | AdminController.php:456 |
| TM-17 | Error info disclosure | MEDIE | AuthController.php:181 |
| TM-18 | Null handling issues | MEDIE | AccountController.php:52 |
| TM-19 | CORS wildcard seating | SCAZUTA | .env.example:109 |
| TM-20 | Domain check production | SCAZUTA | AuthenticateAdmin.php:66 |
| TM-21 | Shell exec in version cmd | SCAZUTA | VersionAutoCommand.php:22 |

### B. MICROSERVICII (25 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| MS-01 | Webhook signature bypass | CRITICA | WhatsAppController.php:256 |
| MS-02 | Missing tenant isolation webhook | CRITICA | WhatsAppCloudWebhookController.php:110 |
| MS-03 | No tenant verification API | CRITICA | WhatsAppController.php:247 |
| MS-04 | XXE injection | CRITICA | AnafAdapter.php:673 |
| MS-05 | SSRF risk | CRITICA | AnafAdapter.php:200 |
| MS-06 | Unsigned XML signatures | CRITICA | AnafAdapter.php:665 |
| MS-07 | Unencrypted logs sensitive data | CRITICA | AnafAdapter.php:127 |
| MS-08 | Weak phone validation | RIDICATA | WhatsAppService.php:111 |
| MS-09 | IDOR queue access | RIDICATA | EFacturaController.php:134 |
| MS-10 | Weak Twilio signature | RIDICATA | TwilioAdapter.php:362 |
| MS-11 | No rate limiting API | RIDICATA | All API Controllers |
| MS-12 | Weak credential encryption | RIDICATA | AccountingService.php:60 |
| MS-13 | Unsafe base64 decoding | RIDICATA | AnafAdapter.php:364 |
| MS-14 | No CSRF webhooks | RIDICATA | WhatsAppCloudWebhookController.php:27 |
| MS-15 | PII in error responses | RIDICATA | WhatsAppController.php:108 |
| MS-16 | Template variable injection | RIDICATA | WhatsAppService.php:504 |
| MS-17 | Admin bypass risk | RIDICATA | microservices.php:225 |
| MS-18 | Unverified event queueing | RIDICATA | WhatsAppCloudWebhookController.php:108 |
| MS-19 | Missing filename sanitization | RIDICATA | EFacturaController.php:225 |
| MS-20 | No transaction rollback | MEDIE | AccountingService.php:127 |
| MS-21 | Unvalidated API responses | MEDIE | SmartBillAdapter.php:315 |
| MS-22 | Race condition queueing | MEDIE | EFacturaService.php:90 |
| MS-23 | Mock adapter in production | MEDIE | MockBspAdapter.php:203 |
| MS-24 | Missing audit logging | MEDIE | All Services |
| MS-25 | No output encoding | SCAZUTA | EFacturaController.php:212 |

### C. PAYMENT PROCESSORS (15 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| PP-01 | Double-spending webhook | CRITICA | PaymentController.php:189 |
| PP-02 | Payment amount manipulation | CRITICA | CartController.php:328 |
| PP-03 | Insurance premium user-controlled | CRITICA | CheckoutController.php:250 |
| PP-04 | Webhook signature bypass missing keys | CRITICA | All PaymentProcessors |
| PP-05 | Unimplemented payment processing | CRITICA | TenantPaymentWebhookController.php:74 |
| PP-06 | SSL verification disabled | RIDICATA | EuplatescProcessor.php:162 |
| PP-07 | Callback routes unauthenticated | RIDICATA | routes/api.php |
| PP-08 | No HTTPS enforcement redirects | RIDICATA | PaymentProcessors |
| PP-09 | Race condition inventory | RIDICATA | ShopCheckoutService.php:283 |
| PP-10 | Config not revalidated | RIDICATA | PaymentController.php:46 |
| PP-11 | Metadata accepts sensitive | MEDIE | PaymentProcessors:54 |
| PP-12 | Netopia signature broken | MEDIE | NetopiaProcessor.php:155 |
| PP-13 | Gift card calculation complex | MEDIE | ShopCheckoutService.php:267 |
| PP-14 | Payment method not validated | MEDIE | ShopCheckoutService.php:214 |
| PP-15 | No rate limit payment intent | MEDIE | CartController.php:323 |

### D. FILAMENT ADMIN (23 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| FA-01 | Mass assignment $guarded=[] | CRITICA | Artist.php:33 |
| FA-02 | VenuePolicy all true | CRITICA | VenuePolicy.php:11 |
| FA-03 | ArtistPolicy all true | CRITICA | ArtistPolicy.php:11 |
| FA-04 | UserPolicy temporary bypass | CRITICA | UserPolicy.php:18 |
| FA-05 | Reset owner password no auth | CRITICA | EditTenant.php:296 |
| FA-06 | Settings management no auth | RIDICATA | ManageConnections.php |
| FA-07 | Tenant context not enforced | RIDICATA | TenantResource.php:25 |
| FA-08 | Customer bulk delete no auth | RIDICATA | CustomerResource.php:278 |
| FA-09 | Media bulk actions no auth | RIDICATA | MediaLibraryResource.php:571 |
| FA-10 | Image download SSRF | RIDICATA | ImportArtists.php:355 |
| FA-11 | Microservice config no auth | RIDICATA | TenantResource.php:674 |
| FA-12 | User role assignment no auth | RIDICATA | UserResource.php:50 |
| FA-13 | Export without auth check | RIDICATA | Multiple |
| FA-14 | Weak marketplace isolation | RIDICATA | Marketplace Resources |
| FA-15 | Arbitrary JSON settings | MEDIE | TenantResource.php:843 |
| FA-16 | CSV upload weak validation | MEDIE | ImportArtists.php:50 |
| FA-17 | SMTP credentials in JSON | MEDIE | TenantResource.php:768 |
| FA-18 | Email campaign viewAny bypass | MEDIE | EmailCampaignPolicy.php:12 |
| FA-19 | Contract variables XSS | MEDIE | TenantResource.php:570 |
| FA-20 | No policy settings save | MEDIE | ManageConnections.php:26 |
| FA-21 | Bulk compress no auth | MEDIE | MediaLibraryResource.php:571 |
| FA-22 | Resource IDOR potential | SCAZUTA | Various Resources |
| FA-23 | Missing tenant scope resources | SCAZUTA | TenantResource.php |

### E. SEATING SYSTEM (17 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| SS-01 | Cross-tenant seat access | CRITICA | EventSeatingLayout.php:12 |
| SS-02 | Price manipulation amount_cents | CRITICA | SeatingController.php:246 |
| SS-03 | Unsafe PHP serialization RCE | CRITICA | DefaultPricingEngine.php:31 |
| SS-04 | Session ID spoofing | CRITICA | SeatingSessionMiddleware.php:53 |
| SS-05 | Race condition double-booking | RIDICATA | SeatHoldService.php:41 |
| SS-06 | Hold timeout bypass | RIDICATA | SeatHoldService.php:282 |
| SS-07 | Idempotency key cache poison | RIDICATA | SeatingController.php:266 |
| SS-08 | Unauthorized seat release | RIDICATA | SeatHoldService.php:135 |
| SS-09 | Redis key injection | RIDICATA | SeatHoldService.php:372 |
| SS-10 | Version constraint bypass | RIDICATA | SeatHoldService.php:207 |
| SS-11 | No validation seat_uid format | MEDIE | SeatingController.php:160 |
| SS-12 | Cache flush entire | MEDIE | DefaultPricingEngine.php:193 |
| SS-13 | Marketplace client not enforced | MEDIE | SeatingController.php:37 |
| SS-14 | Rate limit session attacks | MEDIE | config/seating.php:55 |
| SS-15 | Insufficient logging | SCAZUTA | SeatHoldService.php |
| SS-16 | Session UID in headers | SCAZUTA | SeatingSessionMiddleware.php:44 |
| SS-17 | CORS no validation seating | SCAZUTA | config/seating.php:279 |

### F. PUBLIC PHP FILES (12 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| PF-01 | RCE webhook deploy | CRITICA | _webhook-deploy.php:21 |
| PF-02 | Hardcoded API credentials | CRITICA | includes/config.php:15 |
| PF-03 | CORS wildcard all | RIDICATA | api/proxy.php:31 |
| PF-04 | Open redirect | RIDICATA | login.php:118 |
| PF-05 | Missing CSRF all forms | RIDICATA | login.php, register.php |
| PF-06 | Email enumeration | RIDICATA | forgot-password.php:182 |
| PF-07 | Weak rate limiting session | MEDIE | api/proxy.php:44 |
| PF-08 | Demo data in production | MEDIE | api/search.php:38 |
| PF-09 | Test scripts exposed | MEDIE | test_customer_tokens.php |
| PF-10 | Client-side only auth | MEDIE | login.php, register.php |
| PF-11 | Missing input validation proxy | MEDIE | api/proxy.php:68 |
| PF-12 | Missing security headers | MEDIE | All PHP files |

### G. SESSIONS & COOKIES (26 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| SC-01 | Predictable session ID | CRITICA | CartController.php:594 |
| SC-02 | Client session ID accept | CRITICA | CartController.php:575 |
| SC-03 | Unencrypted session storage | CRITICA | config/session.php:50 |
| SC-04 | Missing HttpOnly flag | CRITICA | config/session.php:172 |
| SC-05 | SameSite Lax not Strict | CRITICA | config/session.php:202 |
| SC-06 | Cross-tenant session | CRITICA | AuthController.php:169 |
| SC-07 | CORS wildcard credentials | CRITICA | config/cors.php:5 |
| SC-08 | Insecure tenant resolution | CRITICA | AuthController.php:30 |
| SC-09 | Token expiration not enforced | RIDICATA | AuthController.php:272 |
| SC-10 | Weak token hashing SHA256 | RIDICATA | AuthController.php:128 |
| SC-11 | No concurrent session limit | RIDICATA | Database migrations |
| SC-12 | Plaintext API keys | RIDICATA | MarketplaceClientAuth.php:38 |
| SC-13 | Missing session regeneration | RIDICATA | AuthController.php:123 |
| SC-14 | Debug logging secrets | RIDICATA | DebugCookieSession.php:32 |
| SC-15 | No CSRF protection | RIDICATA | All API endpoints |
| SC-16 | Incomplete logout | RIDICATA | AuthController.php:240 |
| SC-17 | Plaintext reset tokens | RIDICATA | AuthController.php:390 |
| SC-18 | Excessive token lifetime | RIDICATA | AuthController.php:131 |
| SC-19 | Wildcard token scopes | RIDICATA | CustomerToken.php:45 |
| SC-20 | No idle timeout | RIDICATA | config/session.php:35 |
| SC-21 | Session ID not validated | MEDIE | CartController.php:594 |
| SC-22 | Config-dependent security | MEDIE | config/session.php |
| SC-23 | Ineffective concurrency track | MEDIE | DDoSProtectionMiddleware.php:313 |
| SC-24 | Non-HMAC signatures | MEDIE | VerifyTenantClientRequest.php:127 |
| SC-25 | Weak nonce validation | MEDIE | VerifyTenantClientRequest.php:30 |
| SC-26 | Insecure key generation | MEDIE | MarketplaceClient.php:79 |

### H. EMAIL & NOTIFICATIONS (20 vulnerabilitati)

| ID | Vulnerabilitate | Severitate | Fisier |
|----|-----------------|------------|--------|
| EN-01 | HTML injection templates | CRITICA | contract.blade.php:51 |
| EN-02 | SSL disabled QR download | CRITICA | TicketEmail.php:103 |
| EN-03 | Bank details exposed | CRITICA | InvoiceMail.php:54 |
| EN-04 | Stripe session ID exposed | CRITICA | MicroservicePurchaseConfirmation.php:96 |
| EN-05 | Raw HTML email content | CRITICA | InvoiceMail.php:99 |
| EN-06 | Header injection risk | RIDICATA | MarketplaceEmailService.php:273 |
| EN-07 | Tracking pixel no consent | RIDICATA | invitation.blade.php:178 |
| EN-08 | No rate limiting email | RIDICATA | MarketplaceEmailService.php:219 |
| EN-09 | Unsanitized template vars | RIDICATA | MarketplaceEmailTemplate.php:84 |
| EN-10 | Credential exposure logs | RIDICATA | MarketplaceEmailService.php:287 |
| EN-11 | Admin URL exposure | MEDIE | MicroservicePurchaseConfirmation.php:98 |
| EN-12 | No unsubscribe mechanism | MEDIE | Shop Notifications |
| EN-13 | Email enumeration | MEDIE | MarketplacePasswordResetNotification.php:39 |
| EN-14 | Insecure attachment storage | MEDIE | InvoiceMail.php:104 |
| EN-15 | Payment link plaintext | MEDIE | GroupPaymentReminderNotification.php:33 |
| EN-16 | Open mail relay potential | MEDIE | MarketplaceClient.php:577 |
| EN-17 | Missing unsubscribe token | SCAZUTA | ShopAbandonedCartNotification.php |
| EN-18 | Plaintext QR in emails | SCAZUTA | TicketEmail.php:58 |
| EN-19 | Missing security headers email | SCAZUTA | All email templates |
| EN-20 | Health check details exposed | SCAZUTA | health.blade.php:115 |

### I. HARDCODED SECRETS (9 vulnerabilitati)

| ID | Secret | Severitate | Fisier | Linie |
|----|--------|------------|--------|-------|
| HS-01 | Marketplace API Key | CRITICA | includes/config.php | 15 |
| HS-02 | Deploy webhook secret | CRITICA | _webhook-deploy.php | 21 |
| HS-03 | Database password | CRITICA | docker-compose.yml | 35 |
| HS-04 | Duplicate API key | CRITICA | ambilet/includes/config.php | 15 |
| HS-05 | Example API key HTML | RIDICATA | organizer-api-docs.html | 173 |
| HS-06 | Duplicate example key | RIDICATA | ambilet/organizer-api-docs.html | 173 |
| HS-07 | Brevo API key placeholder | MEDIE | includes/config.php | 33 |
| HS-08 | GitHub account info | MEDIE | _webhook-deploy.php | 24-26 |
| HS-09 | Duplicate Brevo key | MEDIE | ambilet/includes/config.php | 34 |

---

## ACTIUNI IMEDIATE NECESARE

### In Urmatoarele 24 ORE:

1. **Dezactivati deploy webhook** sau schimbati secretul
2. **Adaugati middleware auth la admin routes** (routes/api.php:1250)
3. **Rotati API key-ul hardcodat** din includes/config.php
4. **Activati SESSION_ENCRYPT=true**
5. **Fixati TenantScope** - eliminati acceptarea _tenant_id din query
6. **Adaugati tenant_id check in OrderController**
7. **Dezactivati super-login endpoint**
8. **Fixati CORS** - eliminati wildcard cu credentials

### In Urmatoarea SAPTAMANA:

1. Implementati webhook signature verification
2. Hash-uiti toate API keys in database
3. Adaugati rate limiting pe login/register
4. Fixati toate politicile Filament (return true)
5. Adaugati validare pret in payment flow
6. Implementati idempotency corecta in webhooks
7. Inlocuiti unserialize() cu JSON

### In Urmatoarele 2 SAPTAMANI:

1. Audit complet al tuturor modelelor pentru TenantScope
2. Implementare CSRF tokens
3. Regenerare sesiuni la login
4. Audit email templates pentru HTML injection
5. Securizare seating system
6. Implementare logging si audit trail

---

## CONCLUZII

### Platforma EPAS are **168 vulnerabilitati de securitate** identificate:
- **47 CRITICE** - necesita remediare imediata
- **66 RIDICATE** - necesita remediare in max 1 saptamana
- **45 MEDII** - necesita remediare in max 2 saptamani
- **10 SCAZUTE** - necesita planificare

### Cele mai afectate componente:
1. **Sessions & Cookies** - 26 vulnerabilitati
2. **Microservicii** - 25 vulnerabilitati
3. **Filament Admin** - 23 vulnerabilitati
4. **Tenant & Marketplace** - 21 vulnerabilitati

### RECOMANDARE FINALA:
**Platforma NU ar trebui sa fie in productie** pana cand cel putin vulnerabilitatile CRITICE (47) nu sunt remediate. Expunerea curenta permite:
- Remote Code Execution
- Data breach complet
- Frauda financiara
- Compromiterea tuturor utilizatorilor

---

*Raport generat: 2026-02-01*
*Auditor: Security Analysis Tool*
*Versiune: 3.0 Final*
