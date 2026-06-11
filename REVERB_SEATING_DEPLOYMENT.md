# Reverb + Real-Time Seating — Deployment

The mobile app's seating map now loads via WebView pointed at a server-rendered
canvas page (`/seating/embed/{event}`), and seat-status updates broadcast over
Laravel Reverb so site, admin and mobile all stay in sync without polling.

Until you complete the steps below, the broadcaster defaults to `null` and
**all `SeatStatusChanged` events are silently dropped** — the legacy flows
keep working unchanged. The mobile WebView shows the chart correctly but the
"● real-time" indicator stays grey ("real-time off").

---

## 1. Install Reverb on the server

On the deploy host, inside the `epas` repo working tree:

```bash
composer install                     # picks up laravel/reverb from composer.json
php artisan reverb:install            # publishes config, sets env defaults
```

`reverb:install` writes the following to `.env` (review and adjust):

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

# What the browser/WebView client uses to reach Reverb (must be public)
REVERB_HOST_PUBLIC=ws.tixello.com    # or core.tixello.com behind /reverb
REVERB_PORT_PUBLIC=443
```

Then:

```bash
php artisan config:cache
```

## 2. Run Reverb as a daemon (supervisor)

Create `/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/epas/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

## 3. Reverse-proxy the WebSocket (nginx)

Reverb listens on 8080 internally; expose it via TLS on 443. Add to your
core.tixello.com server block (or a separate ws.tixello.com host):

```nginx
location /reverb/ {
    proxy_pass http://127.0.0.1:8080/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400;
}
```

Then set `REVERB_HOST_PUBLIC=core.tixello.com` and the JS client connects to
`wss://core.tixello.com/reverb/`. If you prefer a dedicated subdomain, point
the entire subdomain at the proxy and drop the `/reverb/` prefix.

## 4. Queue worker (already running)

The broadcast uses Laravel's default queue (`sync` is fine for low volume; for
production use `database` or `redis`). The existing queue worker picks up
`ShouldBroadcast` events automatically.

## 5. Smoke test

From `epas/`:

```bash
# Subscribe with a quick CLI listener
php artisan tinker
> event(new App\Events\Seating\SeatStatusChanged(123, [['seat_uid' => 'A-1', 'status' => 'sold']]));
```

Open the embed URL in a desktop browser and watch the bottom-right pill flip
from "real-time off" → "● real-time". Mark a seat sold from the website checkout
or the Filament admin seat editor — the embed page (and the mobile WebView once
the APK is rebuilt) should turn it red instantly.

## Rollback / disable

If Reverb misbehaves, flip a single env var and restart php-fpm:

```
BROADCAST_CONNECTION=null
```

The mobile WebView still works (initial paint is server-rendered) — it just
loses real-time updates and falls back to a static map until the page is
reopened.
