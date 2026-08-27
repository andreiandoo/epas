# Chat live — Runbook de deploy & testare

Microserviciu `live-chat`. Transport = **polling** (Reverb doar scaffold, inert).
Tot e non-breaking și gated: inactiv pe orice marketplace fără activare.

## 1. Deploy backend (core.tixello.com)

Respectă secvența standard core (vezi `project_core_tixello_deploy`):
```bash
php artisan down
git pull origin core
composer install --no-dev --optimize-autoloader
php artisan migrate --force            # creează cele 7 tabele chat_*
php artisan db:seed --class="Database\\Seeders\\LiveChatMicroserviceSeeder"
php artisan config:cache
php artisan route:cache
sudo service php8.4-fpm restart
php artisan up
```

## 2. Activează microserviciul pentru Ambilet (marketplace_client_id = 1)

Tinker (oneliner):
```bash
php artisan tinker --execute='$m=\App\Models\Microservice::where("slug","live-chat")->first(); \App\Models\MarketplaceClient::find(1)->microservices()->syncWithoutDetaching([$m->id => ["status"=>"active","is_active"=>true,"activated_at"=>now()]]); echo "ok";'
```
Verifică:
```bash
php artisan tinker --execute='dump(\App\Models\MarketplaceClient::find(1)->hasMicroservice("live-chat"));'
```

## 3. Adaugă un operator + program (din /marketplace)

- Loghează-te în panoul marketplace ca admin al Ambilet.
- Apar sub grupul **Communications**: „Chat live" (consola), „Program operatori chat", „Zile libere chat", „Răspunsuri predefinite", „Blocklist chat".
- Adaugă un **Program operatori chat** pentru contul tău (zi + interval care acoperă acum).
- Deschide **Chat live** și apasă **Online**.

## 4. Deploy widget (ambilet.ro)

Widget-ul + wiring-ul proxy sunt în `resources/marketplaces/ambilet/`:
```
deploy-ambilet.bat
```
(Include `assets/js/components/chat-widget.js`, `api/proxy.php`, `includes/scripts.php`.)

## 5. Teste automate (pe mediu cu vendor/)

```bash
php artisan test --filter=Chat
```
Acoperă: open→offline/queued/active, capacitate operator, first_response, resolve+eliberare slot, note interne excluse din public, anti-bot (honeypot/time-trap/rate-limit/blocklist), `chat:close-inactive`, `chat:purge-transcripts`. Rulează pe conexiune SQLite izolată (nu atinge prod).

## 6. QA manual (checklist)

**Widget public (incognito, delogat):**
- [ ] Bula de chat apare pe ambilet.ro (dispare dacă microserviciul e dezactivat).
- [ ] Pre-chat cere nume + email; fără ele nu pornește.
- [ ] Cu operator Online → mesajul intră, operatorul îl vede în consolă și răspunde; răspunsul apare în widget în ≤ intervalul de polling.
- [ ] Fără operator / în afara programului → „Suntem offline", mesajul devine `offline_message`.
- [ ] Refresh pagină → conversația persistă (localStorage ref+token).
- [ ] La rezolvare → apar stelele de rating; ratingul se salvează.
- [ ] Email transcript ajunge la adresa guestului (dacă marketplace mail e configurat).

**Client logat (customer):**
- [ ] Nu i se cere nume/email (identificat automat).
- [ ] Operatorul vede numele real + contextul (pagină/eveniment).

**Organizator logat:**
- [ ] Badge **ORGANIZATOR** în widget și în consolă; aceeași coadă.

**Anti-bot:**
- [ ] Submit instant (sub 2s) sau honeypot completat → respins (429).
- [ ] Multe conversații de pe același IP → limitate.
- [ ] Intrare în „Blocklist chat" (IP/email) → deschiderea e blocată.

**Consolă operator:**
- [ ] Coadă / Conversațiile mele / Alți operatori se populează.
- [ ] Preia, răspunde, notă internă (invizibilă clientului), transfer, rezolvă.
- [ ] Răspuns predefinit se inserează (cu {name}/{event}).
- [ ] Statusul online/away/offline + heartbeat; badge-ul cu numărul din coadă.
- [ ] Strip statistici (în așteptare / active / ale mele / rating mediu).

**Programat:**
- [ ] `php artisan chat:close-inactive` închide conversații inactive.
- [ ] `php artisan chat:cleanup-presence` trece operatorii inactivi pe offline.
- [ ] `php artisan chat:purge-transcripts --dry-run` raportează corect.

## 7. Dezactivare rapidă (kill switch)

```bash
php artisan tinker --execute='$m=\App\Models\Microservice::where("slug","live-chat")->first(); \App\Models\MarketplaceClient::find(1)->microservices()->updateExistingPivot($m->id,["status"=>"inactive"]); echo "off";'
```
Widget-ul dispare, API-ul răspunde 404, resursele din admin se ascund. Zero impact pe restul site-ului.

## Deferred / next
- **Atașamente fișiere**: proxy-ul forwardează JSON; suportul multipart e de adăugat (upload endpoint + storage + afișare).
- **Reverb (F3 real-time)**: setează `CHAT_TRANSPORT=reverb` + `BROADCAST_CONNECTION=reverb` + REVERB_*, pornește daemonul Reverb, adaugă clientul Echo în `scripts.php`. Până atunci polling-ul e activ.
- **F6**: WhatsApp/Telegram, chatbot pre-operator, operatori per-organizator.
