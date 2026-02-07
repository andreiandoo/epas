# Load Test Suite – bilete.online (Tixello Marketplace)

Simulare completă de trafic mare pentru marketplace-ul bilete.online, bazat pe [k6](https://k6.io).

## Structură

```
load-tests/
├── config.js                    # Configurare globală, SLO-uri, profile de trafic
├── run.sh                       # Script runner principal
├── analyze.js                   # Analizator rezultate (Node.js)
├── lib/
│   └── helpers.js               # Funcții helper (metrici, checks, headere)
├── scenarios/
│   ├── 00-full-mix.js           # Mix complet de trafic (toate tipurile de useri)
│   ├── 01-pages.js              # Test încărcare pagini HTML
│   ├── 02-api-proxy.js          # Stress test pe /api/proxy.php
│   ├── 03-seating.js            # Test sistem de locuri (Redis-backed)
│   ├── 04-user-journey.js       # Journey complet: browse → select → checkout
│   ├── 05-search-stress.js      # Search autocomplete la fiecare tastă
│   └── 06-onsale-simulation.js  # Simulare moment punere în vânzare
└── reports/                     # Rezultate generate automat
```

## Instalare k6

```bash
# macOS
brew install k6

# Linux (Debian/Ubuntu)
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | \
  sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Docker
docker pull grafana/k6
```

## Profile de trafic

| Profil    | Descriere                                | VUs max | Durată    |
|-----------|------------------------------------------|---------|-----------|
| `smoke`   | Sanity check – 1 user                   | 1       | 30s       |
| `normal`  | Zi obișnuită                             | 100     | ~9 min    |
| `peak`    | Vârf de trafic (on-sale)                 | 500     | ~8 min    |
| `spike`   | Spike viral / flash sale                 | 1000    | ~2.5 min  |
| `stress`  | Găsire punct de rupere                   | 2000    | ~13 min   |
| `soak`    | Stabilitate pe termen lung               | 100     | ~34 min   |

## Utilizare

### Quick start

```bash
cd load-tests

# Smoke test (verificare rapidă)
./run.sh pages smoke

# Trafic normal – mix complet
./run.sh full-mix normal

# Simulare on-sale Coldplay (spike extrem)
./run.sh onsale spike

# Toate scenariile, secvențial
./run.sh all normal
```

### Scenarii individuale cu k6 direct

```bash
# Pages – profil peak
k6 run --env BASE_URL=https://bilete.online --env PROFILE=peak scenarios/01-pages.js

# API stress cu output JSON
k6 run --env PROFILE=stress --out json=reports/api-stress.json scenarios/02-api-proxy.js

# Seating cu event ID specific
k6 run --env SEATING_EVENT_ID=42 --env PROFILE=spike scenarios/03-seating.js

# Full mix cu target personalizat
k6 run --env BASE_URL=https://staging.bilete.online --env PROFILE=normal scenarios/00-full-mix.js
```

### Analiză rezultate

```bash
node analyze.js reports/api-proxy_peak_20260207_120000.json
node analyze.js reports/   # analizează toate fișierele din director
```

## Scenarii detaliate

### 01 – Page Load Performance
Testează toate paginile critice: homepage, listing evenimente, detaliu eveniment, artiști, locații, regiuni, pagini statice.

### 02 – API Proxy Stress
Stresează `/api/proxy.php` cu toate acțiunile: config, events, featured, categories, search, venues, artists, theme.

### 03 – Seating System
Testează sistemul de locuri Redis-backed: load layout → get availability → hold seats → release/confirm. Simulează race conditions.

### 04 – User Journey
Flow complet end-to-end: Homepage → Browse → Search → Event Detail → Select Tickets → Cart → Checkout. Cu think times realiste.

### 05 – Search Stress
Simulează search-as-you-type: fiecare tastă generează un request autocomplete. Include burst searches concurente.

### 06 – On-Sale Simulation
Cel mai extrem scenariu: simulează momentul punerii în vânzare a unui eveniment popular. Include F5 storm, race conditions pe locuri, checkout concurrent.

## SLO-uri (Service Level Objectives)

| Metric              | Target p95 | Target p99 |
|---------------------|------------|------------|
| Page load           | < 2s       | < 4s       |
| API response        | < 500ms    | < 1.5s     |
| Seating API         | < 300ms    | < 800ms    |
| TTFB                | < 1.5s     | -          |
| Error rate          | < 1%       | -          |
| Full user journey   | < 25s      | -          |

## Variabile de mediu

| Variabilă          | Default                      | Descriere                          |
|--------------------|------------------------------|------------------------------------|
| `BASE_URL`         | `https://bilete.online`      | URL-ul marketplace-ului            |
| `CORE_API`         | `https://core.tixello.com`   | URL-ul API-ului core Tixello       |
| `API_KEY`          | -                            | API key pentru endpointuri v1      |
| `SEATING_EVENT_ID` | `1`                          | ID-ul evenimentului cu seating     |
| `PROFILE`          | `normal`                     | Profilul de trafic                 |
| `K6_OUT`           | -                            | Output suplimentar (cloud, csv)    |

## Interpretare rezultate

Analizorul (`analyze.js`) generează:
- **Traffic Summary** – total requests, error rate, peak VUs
- **Page/API/Seating Times** – min, avg, med, p90, p95, p99, max
- **SLO Compliance** – pass/fail pentru fiecare target
- **Recommendations** – sugestii automate bazate pe rezultate
