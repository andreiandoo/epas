# Plan de implementare — Chat live (microserviciu Tixello Core)

> Status: **PLAN** (nescris cod). Data: 2026-08-27.
> Slug microserviciu propus: **`live-chat`**.
> Prima activare: **marketplace_client_id = 1 (Ambilet)**.

## 0. Rezumat

Casetă de chat live pe frontend-ul unui marketplace (întâi ambilet.ro), prin care
**clienții** și **organizatorii** comunică cu **operatori AmBilet**. Funcționalitatea
e împachetată ca **microserviciu în Tixello Core**: dacă e activat pentru un
`marketplace_client`, marketplace-ul îl poate folosi; altfel e complet inert
(widget-ul nu se încarcă, rutele răspund „inactiv", panoul operatorului e ascuns).

Fundația tehnică există deja aproape integral — trebuie asamblată, nu construită de la zero.

## 1. Context tehnic existent (verificat în cod)

| Piesă | Stare | Implicație |
|---|---|---|
| **Laravel Reverb** | Instalat (`composer.json`), `BROADCAST_CONNECTION=log` (oprit) | Real-time e config, nu instalare |
| **Redis 7** | Activ ca `CACHE_STORE`, folosit la seat-holds | Presence operatori + queue broadcast |
| **Support tickets polimorfici** | `SupportTicket`+`SupportTicketMessage`, `opener()` MorphTo, `assignee()`=MarketplaceAdmin, departamente, SLA | Pattern ~90% reutilizabil |
| **Poller notificări** | `notification-poller.js` (30s) + `notification-sound.js` | Fallback gata pentru polling |
| **Auth frontend** | `AmbiletAuth.USER_TYPE` = customer/organizer/artist (localStorage, Sanctum) | Știm din prima client vs organizator |
| **Admin operatori** | Panel `/marketplace`, guard `marketplace_admin`, `role`+`permissions` JSON, pivot `support_department_admins` | Operatorii = MarketplaceAdmin cu rol dedicat |
| **Microservicii** | `Microservice` + pivot `marketplace_client_microservices`; `MarketplaceClient::hasMicroservice($slug)` / `getMicroserviceConfig($slug)`; pagina `MicroserviceSettings` (`microservices/{slug}/settings`) | Gating identic cu Facebook CAPI |
| **Injectare globală widget** | `resources/marketplaces/ambilet/includes/scripts.php` | Un `<script>` → widget pe tot site-ul |

## 2. Decizii confirmate cu clientul

1. **Microserviciu Tixello Core** — activabil per marketplace (slug `live-chat`), prima activare Ambilet (id 1).
2. **Transport:** polling întâi (F1 funcțional fără Reverb), Reverb ca upgrade (F3). Confirmat.
3. **Tabele:** chat **dedicat** (nu extindem `support_tickets`).
4. **Anonimi:** permiși, cu **protecție anti-bot** obligatorie (vezi §7).
5. **Organizatori:** **aceeași coadă** ca clienții, dar cu **badge ORGANIZATOR** distinct.
6. **Operatori:** **globali AmBilet** (nu per-organizator în această iterație).

## 3. Împachetare ca microserviciu

**Model & activare** (identic Facebook CAPI):
- Seed rând în `microservices`: slug `live-chat`, nume „Chat live / Live Chat", `icon_image`, `short_description`, `is_active=true`.
- Activare per marketplace = rând în `marketplace_client_microservices` cu `status='active'`.
- **Gate central:** `MarketplaceClient::hasMicroservice('live-chat')`.
  - Frontend: `includes/scripts.php` include `chat-widget.js` **doar** dacă gate-ul e activ (flag expus în `window.AMBILET`).
  - API: fiecare endpoint de chat verifică gate-ul → 403/`inactive` dacă nu.
  - Admin: pagina Filament „Chat live" și resursele apar **doar** dacă marketplace-ul are microserviciul activ.
- **Config per marketplace** în pivot `settings` (JSON), citit cu `getMicroserviceConfig('live-chat')`:
  - program global + timezone, mesaje offline/greeting, culori/branding widget, capacity implicit/operator,
    departamente active, on/off pre-chat pentru anonimi, praguri anti-bot, retenție transcript (zile).
- **Pagină de settings:** refolosim pattern-ul `MicroserviceSettings` (`microservices/live-chat/settings`) pentru
  configurarea de mai sus din panoul marketplace.

> Efect: pe orice alt marketplace fără activare, modulul e 100% inert și non-breaking.

## 4. Concepte de produs

**a) Trei tipuri de inițiatori**
- Client logat → conversație legată de cont; operatorul vede comenzi/bilete.
- Organizator logat (`USER_TYPE=organizer`) → **badge ORGANIZATOR**, aceeași coadă; operatorul vede evenimentele lui.
- Vizitator anonim → pre-chat (nume+email, opțional eveniment) + **anti-bot**.

**b) Context automat atașat conversației** (diferențiatorul major)
Widget-ul trimite la deschidere: URL+titlu pagină, tip pagină (eveniment/checkout/cont/home),
`event_id`+nume dacă e pe eveniment, conținut/valoare coș, ultima comandă (client logat), UA, oraș (din IP), locale.
Operatorul vede contextul **înainte** să întrebe.

**c) Program de lucru + offline**
- Program per operator + program global AmBilet + zile libere (`chat_holidays`).
- În program & liber → chat live. În program & toți ocupați → coadă cu poziție + ETA.
  În afara programului → „Offline", client lasă mesaj → conversație `offline_message` → email + (opțional) ticket.

**d) Rutare & atribuire**
- Coadă cu atribuire round-robin / least-busy către operatorii online din departamentul potrivit.
- Departamente (reutilizăm `SupportDepartment`): ex. Bilete&comenzi, Plăți, Organizatori, Tehnic.
- Capacity per operator (nr. conversații simultane), configurabil.
- Transfer între operatori, escaladare, note interne (`is_internal`, invizibile clientului).

**e) Productivitate operator**
- Macro-uri / răspunsuri predefinite cu variabile (`{nume}`, `{eveniment}`).
- Typing indicator, read receipts, presence (online/away/offline).
- Atașamente (poze bilet/eroare, PDF) cu validare tip/dimensiune.
- Căutare în conversații + istoricul aceluiași client.

**f) După conversație**
- Rating (1–5 / thumbs) + feedback; transcript pe email; marcaje rezolvat/redeschis/convertit în ticket.

**g) Guvernanță**
- GDPR: consimțământ pre-chat, retenție configurabilă, ștergere la cerere, fără date de card în chat.
- Anti-abuz: rate limiting, blocklist IP/email, filtru spam, throttle deschideri.
- Audit: preluare/transfer/închidere via activitylog (ca la support).

## 5. Model de date (tabele dedicate, stil existent)

Toate cu `marketplace_client_id` (izolare per marketplace).

1. **`chat_conversations`** — `marketplace_client_id`, `department_id?`, `event_id?`,
   `visitor_type` (customer/organizer/artist/guest), `opener` polimorfic (nullable), `guest_name`, `guest_email`,
   `assigned_marketplace_admin_id?`, `status` (queued/active/offline_message/resolved/closed),
   `context` JSON, `rating`, `rating_comment`, `first_response_at`, `resolved_at`, `last_activity_at`.
2. **`chat_messages`** — `conversation_id`, `author` polimorfic (admin/opener/system), `author_type_label`,
   `body`, `is_internal`, `attachments` JSON, `read_at`, `delivered_at`.
3. **`chat_operator_statuses`** — `marketplace_admin_id`, `presence`, `active_chats_count`, `last_seen_at`
   (live în **Redis**; DB = „last known").
4. **`chat_operator_schedules`** — `marketplace_admin_id`, `day_of_week`, `start_time`, `end_time`, `timezone`.
5. **`chat_holidays`** — zile libere globale.
6. **`chat_canned_responses`** — `marketplace_client_id`, `department_id?`, `shortcut`, `title`, `body`.
7. **`chat_blocklist`** — `ip`/`email`, motiv, `expires_at`.

**Reutilizăm:** `SupportDepartment`, `support_department_admins`, notificările existente (fallback), activitylog.
**Conversie offline→ticket:** conversația nerezolvată devine `SupportTicket` cu `chat_conversation_id` de legătură.

## 6. Transport real-time

**Principal — Reverb** (F3): `BROADCAST_CONNECTION=reverb`, config `REVERB_*`, server Reverb ca daemon Ploi
(pe lângă queue worker). Canale: `chat.conversation.{id}` (privat), `presence-chat.operators`,
`chat.operator.{adminId}` (conversație nouă). Autorizare în `routes/channels.php` (pattern existent `event.{id}.seats`).
Client: bundle Echo + `pusher-js` adăugat în `scripts.php` (azi nu e încărcat).

**Fallback — polling** (F1): refolosim patternul `notification-poller.js` (3–5s când chat-ul e deschis).
Dacă Reverb e oprit/pică, chat-ul degradează grațios la polling. **Livrăm fallback-ul întâi**, Reverb ca upgrade.

> Non-breaking: Reverb = serviciu separat; neponit → site-ul merge identic pe polling.

## 7. Anti-bot (obligatoriu pentru anonimi)

- **Honeypot** + **time-trap** (respinge submit-uri instant) în pre-chat.
- **Rate limiting**: deschideri conversație / IP / sesiune; mesaje/minut.
- **Throttling progresiv** + `chat_blocklist` (IP/email) cu expirare.
- **Proof-of-work / captcha invizibil** (ex. Turnstile) doar la semnale de abuz (fără fricțiune pt. userii buni).
- **Filtru conținut** (linkuri/spam) pe primul mesaj anonim; conversațiile suspecte intră în coadă „de verificat", nu direct la operator.
- Anonimii **nu** primesc acces la date de cont; contextul lor e limitat la pagină/eveniment.

## 8. Componente de construit

**Frontend public** (`resources/marketplaces/ambilet/`)
- `assets/js/components/chat-widget.js` — bulă + fereastră, montat global din `scripts.php` **după** `auth.js`/`api.js`,
  **doar** dacă gate-ul microserviciului e activ. Citește `USER_TYPE` (badge organizator), colectează context,
  gestionează pre-chat/coadă/typing/atașamente/rating; transport polling→Reverb.
- CSS widget + sunet (refolosim `notification-sound.js`).
- Endpoints prin `api/proxy.php` → Core: start, send, poll/subscribe, upload, rating.
- **Deploy (memorie):** livrare din `resources/marketplaces/ambilet/` via `deploy-ambilet.bat`; verifică copii duble
  (`epas/organizer/...`) și sincronizează; rutele `/chat/...` au nevoie de RewriteRule în `.htaccess`.

**Backend (Core API)**
- Controllere API widget (public, Sanctum opțional, rate limiting, gate microserviciu).
- Servicii: `ChatRoutingService` (coadă/atribuire/capacity), `ChatPresenceService` (Redis),
  `ChatScheduleService` (program/offline), `ChatConversationService`.
- Broadcast events: `MessageSent`, `ConversationAssigned`, `TypingStarted`, `OperatorPresenceChanged`.
- Job conversie offline→ticket + email transcript (prin sistemul transactional existent).
- Comenzi programate: expirare conversații inactive, cleanup presence, sync program.

**Admin operator (Filament `/marketplace`)**
- **Filament Page „Chat live"** (stil `WhatsAppNotificationsPage`): 3 coloane — coadă/active | fir mesaje | context client
  (comenzi, eveniment, coș). Toggle online/away, contor conversații, macro-uri, transfer, note interne, închidere+rezolvare.
  Vizibilă doar cu microserviciul activ.
- **Resources CRUD:** operatori & program, macro-uri, blocklist, departamente (`SupportDepartmentResource` există), rapoarte.
- **Permisiuni:** rol nou `chat_operator` pe `MarketplaceAdmin` (folosim `role`/`permissions`/`hasPermission()`); super-admin vede tot.
- **Widget dashboard:** conversații în așteptare, timp mediu răspuns, operatori online.
- **Settings microserviciu:** pagina `microservices/live-chat/settings` (program global, mesaje, branding, praguri anti-bot, retenție).

## 9. Metrici & SLA

Timp până la primul răspuns, durată medie, conversații/operator, rată rezolvare, satisfacție (rating),
volum pe departament/eveniment, ore de vârf. Reutilizăm helperii SLA din `SupportTicket`.

## 10. Faze de livrare (fiecare = deploy independent, non-breaking)

- **F0 — Fundație:** seed microserviciu `live-chat` + gating; migrații tabele chat; modele; servicii schelet;
  rol `chat_operator`; config Reverb rămâne pe `log`/polling. *Zero impact pe live.*
- **F1 — MVP polling:** widget (anonim + logat) cu gate, pre-chat + anti-bot de bază, send/receive pe polling,
  panou operator Filament de bază, atribuire simplă, badge organizator. **Chat end-to-end fără Reverb.**
- **F2 — Program & offline:** program operatori + global, coadă cu poziție, mesaj offline → email/ticket, presence online/away.
- **F3 — Real-time (Reverb):** activare Reverb, typing/read-receipts/presence live, sunet; polling rămâne fallback.
- **F4 — Productivitate & context bogat:** macro-uri, atașamente, transfer/escaladare, note interne, context complet operator.
- **F5 — Guvernanță & metrici:** rating + transcript email, GDPR/retenție, anti-abuz avansat, dashboard SLA, rapoarte, pagina de settings microserviciu.
- **F6 (opțional):** integrare WhatsApp/Telegram (modele de integrare există), chatbot/FAQ pre-operator, proactive messages (checkout abandonat), operatori per-organizator.

## 11. Riscuri & mitigări

- **Producție live (memorie: strict non-breaking):** tot gated de microserviciu; neactivat = inert.
- **Reverb pe Ploi:** daemon separat + config:cache/route:cache respectate; fallback polling dacă daemon pică.
- **Copii duble ambilet + .htaccess:** checklist deploy dedicat.
- **Abuz anonimi:** anti-bot din F1, nu amânat.
- **Sarcină operatori:** capacity + coadă + program, ca să nu se supraîncarce.

## 12. Puncte deschise pentru iterația următoare

- Departamentele exacte și regulile de rutare (mai ales pt. organizatori în coada comună).
- Politica de retenție transcript (zile) și fluxul GDPR de ștergere.
- Branding widget (culori/logo) — din settings microserviciu.
