# AmBilet Scan — Redesign Mockups (handoff)

This folder contains a **visual redesign** of the `tixello-app` mobile application
(the "AmBilet Scan" React Native / Expo app) based on the AmBilet.ro marketing
mockups. It is meant to be handed to a Claude session connected to the app so the
redesign can be implemented screen by screen.

- **`index.html`** — self-contained gallery. Every screen of the app is rendered as
  a phone mockup in the new red/white AmBilet identity. Open it in a browser, or
  paste it into the implementing session as the visual reference. Each phone is
  labelled with the exact source file it maps to.
- **`design-system.css`** — the design tokens as CSS variables, with inline
  comments mapping each token to the corresponding token in
  `src/theme/colors.js`. This is the single source of truth for the new palette.
- **`splash-animated.html`** — animated splash concept (loops for preview): the
  scan frame draws in, a glowing beam sweeps down and lights up a QR code, a
  green success ring + check pops, then it morphs into the AmBilet Scan logo.
- **`SplashScreen.animated.js`** — **drop-in React Native implementation** of
  that splash. Same contract as `src/screens/SplashScreen.js`
  (`<SplashScreen onFinish={…} />`, runs once ~2.6s). Uses only
  `react-native` + `react-native-svg` (already installed) — no new deps. To
  use it: replace `src/screens/SplashScreen.js` with this file (or import it in
  its place in `App.js`).

## ⚠️ Golden rule: redesign only, remove nothing

This is a **pure visual re-skin**. Every existing feature, screen, button, state,
permission gate, role, API call, modal, offline path and setting must remain.
The only thing changing is the **look** — the app moves from its current
dark/purple theme to the AmBilet light **red/white** theme shown in the marketing
material. Do not delete features, do not change navigation, do not remove
role-based branching. If a screen has 3 result states today (valid / duplicate /
invalid), it still has 3 after the redesign — just restyled.

## The core change: dark → light + purple → red

The app is currently a **dark theme** built around `background #0A0A0F` and a
purple accent `#8B5CF6`. Splash & Login already hint at the AmBilet brand with a
crimson `#C41E3A`. The redesign **unifies everything** onto the AmBilet system:

- Dark surfaces → **white cards on a light warm-gray background**.
- Purple accent (`purple` family) → **AmBilet red** (`#9A1B22` primary,
  `#C1121F` for links/accents).
- Crimson `#C41E3A` (Splash/Login/OrganizerSwitcher) → same red system.
- Semantic colors (green success, amber warning, red danger, cyan info) are
  **kept**, only retuned for legibility on white.

Because the whole app already funnels its colors through
`src/theme/colors.js`, **the fastest correct implementation is to rewrite that
one file** with the new token values, then fix the handful of screens that
assume a dark background (hardcoded `#0A0A0F`, `#15151F`, `#16161F`, `#1E1E2E`,
`rgba(255,255,255,…)` fills, and the crimson `#C41E3A`).

### Token mapping (`src/theme/colors.js` → new value)

| current token | current value | → new value | role |
|---|---|---|---|
| `background` | `#0A0A0F` | `#F6F2F2` | app background (light) |
| `surface` | `rgba(255,255,255,0.03)` | `#FFFFFF` | cards |
| `surfaceHover` | `rgba(255,255,255,0.06)` | `#FBF8F8` | nested rows |
| `border` | `rgba(255,255,255,0.06)` | `#ECE6E6` | hairline |
| `borderLight` / `borderMedium` | `…0.08 / 0.1` | `#E0D8D8` | stronger border |
| `textPrimary` | `#FFFFFF` | `#1C1B1F` | primary text |
| `textSecondary` | `rgba(255,255,255,0.5)` | `#6B7280` | secondary |
| `textTertiary` | `rgba(255,255,255,0.4)` | `#9CA3AF` | tertiary |
| `textQuaternary` | `rgba(255,255,255,0.3)` | `#C0C4CC` | quaternary |
| `purple` | `#8B5CF6` | `#9A1B22` | **primary / active tab / icons** |
| `purpleSecondary` | `#6366F1` | `#7A141A` | gradient / pressed |
| `purpleLight` | `rgba(139,92,246,0.15)` | `#FBEAEB` | tint fill |
| `purpleBorder` | `rgba(139,92,246,0.3)` | `rgba(154,27,34,0.22)` | tinted border |
| `purpleBg` | `rgba(139,92,246,0.08)` | `#FBEAEB` | icon-chip / selector bg |
| `purpleGlow` | `rgba(139,92,246,0.4)` | `rgba(154,27,34,0.28)` | button shadow |
| `green` | `#10B981` | `#16A34A` | success / Activă / Live |
| `greenBg` / `greenLight` / `greenBorder` | — | `#E7F6EC` / `#E7F6EC` / `rgba(22,163,74,.25)` | success tints |
| `amber` | `#F59E0B` | `#D97706` | warning (VIP gate, pending) |
| `red` | `#EF4444` | `#DC2626` | danger (destructive) |
| `cyan` | `#06B6D4` | `#0E7490` | info (POS gate, card) |
| crimson (hardcoded) | `#C41E3A` | `#9A1B22` / `#C1121F` | unify into red system |
| — | — | `#C1121F` (**new** `redAccent`) | links: `+ Adaugă`, `Editează`, `Anulează vânzarea`, show-password |

> Add two new tokens: `red` (primary, `#9A1B22`) and `redAccent`
> (`#C1121F`, for text links). Keep the existing `red` used for destructive
> actions but rename/retune to `danger` (`#DC2626`) so brand-red and
> destructive-red don't collide visually.

### Role & gate color conventions (preserve — just restyle the pills)

Roles: **Admin** = red-tint pill (`#FCE8E9`/`#B0212B`), **Manager** =
green-tint (`#E7F6EC`/`#15803D`), **Staff** = neutral (`#EEF1F4`/`#5B6472`),
**Owner/Proprietar** = keep a distinct tint. Avatars keep their multi-color
initials (red / blue / amber / purple backgrounds).

Gate types: **Intrare** = green, **VIP** = amber, **POS** = cyan,
**Ieșire** = danger-red. `Activă` status = green dot + green label.

## Screen-by-screen map

Each mockup in `index.html` is captioned with its source file. Summary:

| # | Mockup | Source file(s) | Notes / features to keep |
|---|---|---|---|
| 1 | Splash | `screens/SplashScreen.js` | animated logo + glow + loading bar; rebrand to "AmBilet Scan" |
| 2 | Login | `screens/LoginScreen.js` | email/pass, show/hide, error box; red primary button |
| 3 | Panou · Admin | `screens/DashboardScreen.js → AdminDashboard` | Intrați + capacity bar, 4 tappable stat tiles (open breakdown modals), Online-vs-ușă, quick actions, recent activity, Închide tura |
| 4 | Panou · Scanner | `screens/DashboardScreen.js → ScannerDashboard` | Încasări card (numerar/card), personal stats, big Scanare/Vânzare buttons |
| 5 | Scanare | `screens/CheckInScreen.js` | camera QR, **3 result states** (ACCES APROBAT/DEJA SCANAT/BILET INVALID), live stats, Cod Manual, reports-only + paused overlays |
| 6 | Vânzare (POS) | `screens/SalesScreen.js` + `SeatingMapScreen.js` | cart + commission, seat map (WebView), payment (Numerar/Card POS/Card NFC), success QR + email capture, today's sales |
| 7 | Echipă & porți | `modals/StaffAssignmentModal.js` + `GateManagerModal.js` | **hero screen — mirrors marketing #1.** members with roles + gates with Activă status + shift summary |
| 8 | Editare membru | `modals/StaffAssignmentModal.js` | expand member: activate, assign gate (chips), event whitelist, remove; "Adaugă Personal Nou" form (Admin/Manager/Staff) |
| 9 | Administrare porți | `modals/GateManagerModal.js` | venue card, add-gate form (type chips), gate list with Activă/Inactivă toggle, Asignează-mă, delete |
| 10 | Rapoarte | `screens/ReportsScreen.js` | check-in rate + sparkline, gate performance bars, revenue, hourly chart, past-event selector, Exportă Raport |
| 11 | Setări | `screens/SettingsScreen.js` | Cont / Scanner toggles / Vânzare POS (NFC) / Mod Offline / Hardware / Comenzi Admin / logout — all role-gated |
| 12 | Selector eveniment | `modals/EventsModal.js` + `EventSelector.js` | bottom sheet grouped LIVE/Azi/Viitor, status badges |
| 13 | Notificări + Urgențe | `modals/NotificationsPanel.js` + `EmergencyModal.js` | notification types (alert/success/info, read/unread) + 8 severity-coded emergency options |
| 14 | Listă invitați | `modals/GuestListModal.js` | VIP/Artist/Press/Guest, search, count, inline check-in |
| 15 | Evenimente locație | `screens/VenueEventsScreen.js` | venue-owner list, Viitor/Trecut/Toate tabs, sold/check-in, Anulat/Reprogramat badges |
| 16 | Detaliu eveniment locație | `screens/VenueEventDetailScreen.js` | stats + Export CSV, Scanare/Vânzare jump, attendee list, status pills, export download/email |
| 17 | Detaliu bilet | `screens/VenueTicketDetailScreen.js` | ticket/client/order/event info + notes CRUD, "Grupează biletele" |

Not drawn separately (shared chrome, shown inside the screens above):
`components/Header.js` (appbar: logo + Live/Offline pill + bell),
`components/ShiftBar.js` (shift timer + turnover + pause/emergency),
`components/EventSelector.js` (event bar), `modals/OrganizerSwitcherModal.js`
(multi-organizer switch — restyle the hardcoded `#C41E3A` to the red system),
`modals/StaffModal.js` (read-only team performance view),
`modals/ManualEntryModal.js`, `screens/TicketListScreen.js`. Apply the same
tokens to these.

## Implementation guidance

1. **Rewrite `src/theme/colors.js`** using the mapping table. Add `redAccent`
   and split brand-red vs `danger`. This alone re-skins ~80% of the app.
2. **Sweep hardcoded dark values.** Search for `#0A0A0F`, `#15151F`,
   `#16161F`, `#1E1E2E`, `#0a0a14`, `#0f0f1f`, `#C41E3A`, `#A51C30`, and
   `rgba(255,255,255,…)` fills. Replace sheet/modal backgrounds with
   `#FFFFFF` (or `#F6F2F2`), overlays with `rgba(20,10,10,0.35)`, and
   `rgba(255,255,255,0.0x)` track/hover fills with the light equivalents.
   `StatusBar` `barStyle` should become `dark-content` on light headers.
2b. **Icons:** the RN app draws icons as inline `react-native-svg` (not the
   Laravel `<x-svg-icon>` component from the root CLAUDE.md — that guidance is
   for the web app, not this Expo app). Keep the SVG paths; only their `stroke`/
   `fill` colors change via tokens.
3. **Cards:** white background, `borderColor` the new `border`, add a soft
   shadow (`shadowColor:'#140A0A', shadowOpacity:0.05, shadowRadius:12,
   elevation:1`). Radius stays 14–20.
4. **Buttons:** primary = solid `red` with a soft red shadow; secondary/ghost =
   white with border; destructive text = `redAccent`. Bottom tab bar: white,
   active tint `red`, inactive `#9CA3AF`, top border `border`.
5. **Header/logo:** replace `assets/logo-header.png` with an AmBilet Scan
   lockup that reads on white (see the `.brand-logo` treatment in the mockups).
   The old logo art was built for a dark bar.
6. Work screen by screen against the matching phone in `index.html`. The
   captions and this table tell you exactly which file each maps to.

## Gotchas already surfaced during analysis

- **Two brandings coexist today** (crimson "AmBilet" on Splash/Login, purple
  "Tixello Staff" elsewhere, version string "Tixello Staff v…"). The redesign
  unifies to **AmBilet Scan** — update the version label too.
- `OrganizerSwitcherModal.js` uses a hardcoded crimson `#C41E3A` outside the
  token system — restyle it explicitly.
- Dead styles exist (SalesScreen `modeToggleRow`/`seatingMapButton`,
  GuestList `fallbackNotice`) — safe to leave; not rendered.
- `ManualEntryModal.js` is in **English** and appears superseded by the inline
  Romanian manual-entry modal inside `CheckInScreen.js`. Don't delete it as
  part of the re-skin; just restyle. Flag for the team if consolidation is
  wanted (out of scope for a visual redesign).
- Role label inconsistency: admin renders as `Admin` in StaffAssignmentModal
  but `Administrator` in OrganizerSwitcherModal — preserve as-is unless told.
