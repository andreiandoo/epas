# Tixello Mobile

Aplicație mobilă nativă (**Expo / React Native + TypeScript**) pentru organizatori și staff Tixello: panou, scanare/validare bilete, vânzare la ușă (POS), selectare loc pe hartă, rapoarte, notificări.

Modelată după `ambilet-app2` (Tixello Scan) și designul din `plans/mds/TIXELLO_MOBILE_APP_DESIGN.html`. **Aditiv** — nu modifică nimic din backendul existent; consumă API-ul de la `core.tixello.com`.

## Rulare

```bash
cd tixello-mobile
npm install
npm start          # Expo dev server (scanează QR cu Expo Go)
npm run android    # sau rulează direct pe Android
npm run ios        # sau pe iOS (macOS)
npm run typecheck  # verificare TypeScript
```

Build APK/AAB: `eas build -p android` (necesită cont Expo/EAS).

## Structură

```
src/
  api/          client HTTP (Sanctum + rutare pe context), endpoint-uri tipate, DTO-uri
  components/   AppHeader (avatar+switch+clopoțel), UI kit (Card, KPI, buton, progress)
  navigation/   root stack (auth vs tabs) + taburi per tip de context
  screens/      Login, Switcher, Home, Scan, Sales, SeatMap, Reports, Notifications, Settings
  store/        AppContext (sesiune, context activ, accent), sesiune securizată
  theme/        tokenuri de culoare + accent per-context (organizator/teatru/venue/artist/agenție)
```

## Context & accent

Un singur cont poate accesa mai multe entități (`available_organizers[]` + `switch-organizer`).
La login, `organizer_type` mapează la un **context** care schimbă accentul UI și rutarea API:

| Context | Accent | Bază API |
|---|---|---|
| Organizator / team | teal `#00c896` | `/marketplace-client/organizer/*` |
| Teatru / artist / agenție / festival | violet/turcoaz/mov/pink | idem (organizator) |
| Venue-owner | cyan `#00e5ff` | `/marketplace-client/venue-owner/*` |
| Tenant | teal | `/tenant-client/*` — **necesită API nou** (vezi mai jos) |

## Stare & pași următori

- **Latura organizator/marketplace**: endpoint-urile există; ecranele se conectează la ele.
- **Latura tenant (white-label)**: backendul **NU** are încă un API de gestiune cu login de staff — vezi analiza din
  `plans/mds/TIXELLO_MOBILE_APP_PLAN.md`. Trebuie construit un strat de API tenant-scoped (auth Sanctum,
  controllere filtrate pe `tenant_id`) peste modelele partajate `Order`/`Ticket`/`Event`.
- Ecranele conțin câteva date de tip placeholder (catalog POS, hartă locuri, feed notificări), marcate în cod,
  până la conectarea completă la API și `expo-constants` extra (`apiBaseUrl`).
- Cheia de marketplace (`mpc_`) nu se împachetează în binar — se livrează prin bootstrap; token-ul Sanctum autorizează operatorul.
