# parc — Nordvale (demo leisure tenant, parc.tixello.ro)

A complete `kind: leisure` storefront: catalogue, rental pools with live
availability, day + time-slot booking, cart, checkout, account area.

Design language is lifted from `verticals/demo-leisure` (the Nordvale HTML set):
pine green on cream, acid-lime accent, Fraunces + DM Sans. All of it lives in
`theme.css` as token overrides — no structural CSS.

## Go-live, in order

**1. Seed the tenant** (on the server, where the app can reach the database):

```bash
php artisan db:seed --class=NordvaleParcSeeder
```

It prints the `tenant_id` it created, and sets up: the tenant with every leisure
feature on, the `parc.tixello.ro` Domain, a tax registry, venue, one season
event, 9 ticket types (access / rentals with duration variants / a slotted
guided tour / two POS-only bar items), 120 days of capacity, 3 resource types
with 18 units, and 5 operator accounts. It is idempotent — safe to re-run.

**2. Paste the id** into `site.config.php → tenant_id`. It must be a literal.
`tools/build.php` refuses to build while it is 0, on purpose: a site built with
tenant_id 0 renders perfectly and shows nothing at all.

**3. Deploy:**

```bash
KIT_DEPLOY_TOKEN=… bash tools/deploy.sh parc
```

Pushes the build to the `parc` branch and pings
`https://parc.tixello.ro/_webhook-deploy.php`. The subdomain, its document root
and that webhook file have to exist on the host first.

**4. Check.** `/` · `/activitati` · `/inchirieri` · `/activitate/{slug}` ·
`/cos` · `/finalizare` · `/cont`. The booking widget on an activity page should
load a month of availability and, for slotted types, the intervals inside a day.

## Accounts the seeder creates

| Email | Parolă | Unde |
|---|---|---|
| `owner@nordvale.demo` | `nordvale123` | panoul de tenant |
| `manager@nordvale.demo` | `operator123` | `/operator` — tot |
| `casier@nordvale.demo` | `operator123` | `/operator` — POS |
| `receptie@nordvale.demo` | `operator123` | `/operator` — check-in |
| `rentals@nordvale.demo` | `operator123` | `/operator` — închirieri |
| `inventar@nordvale.demo` | `operator123` | `/operator` — inventar |

Demo credentials. Rotate or disable them before this tenant is anything but a
demo.

## Known gaps (not this template's fault)

- **`/operator` POS does not complete a sale.** `Pos::checkout()` is a stub — it
  clears the cart and shows a notification. Check-in and rentals on that panel
  are real.
- **Checkout pays through the demo processor.** `/tenant-client/demo-checkout`
  writes real orders and tickets and holds leisure capacity under a row lock,
  but the payment step is simulated. Wire a real PSP before taking money.
- **No accommodation.** Cabins and camping nights need date-range inventory,
  which the leisure model does not have; `ticket_type_capacities` is per day or
  per interval.
