# Raport de Audit de Securitate - EPAS Platform

**Data:** 2026-01-31
**Versiune:** 1.0
**Auditor:** Security Analysis Tool

---

## 1. Sumar Executiv

Acest raport prezinta rezultatele auditului de securitate efectuat asupra platformei EPAS (Event Platform as a Service). Au fost identificate mai multe vulnerabilitati critice, medii si de importanta scazuta, impreuna cu recomandari specifice de remediere.

### Statistici Generale

| Severitate | Numar |
|------------|-------|
| **Critica** | 3 |
| **Ridicata** | 5 |
| **Medie** | 7 |
| **Scazuta** | 4 |

---

## 2. Vulnerabilitati Critice

### 2.1 CRITICAL-001: Autentificare Tenant Incomplet Implementata

**Locatie:** `app/Http/Middleware/TenantAuthentication.php:38-55`

**Descriere:**
Middleware-ul `TenantAuthentication` nu valideaza efectiv API key-ul impotriva bazei de date. Codul contine un TODO care indica ca validarea nu este implementata:

```php
// TODO: Replace with actual tenant lookup from database
// $tenant = DB::table('tenants')->where('api_key', $apiKey)->first();
```

Tenant ID-ul este acceptat direct din input-ul utilizatorului fara validare, permitand atacatorilor sa acceseze date ale altor tenanți.

**Impact:**
- Acces neautorizat la datele altor tenanti (data breach)
- Escalare privilegii cross-tenant
- CVSS Score: 9.8 (Critical)

**Remediere:**
```php
// PATCH: Validare corecta a API key si tenant
public function handle(Request $request, Closure $next): Response
{
    $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');

    if (!$apiKey) {
        return response()->json(['error' => 'Missing API key'], 401);
    }

    if (str_starts_with($apiKey, 'Bearer ')) {
        $apiKey = substr($apiKey, 7);
    }

    // IMPORTANT: Validare efectiva a API key
    $tenant = \App\Models\Tenant::where('api_key', hash('sha256', $apiKey))
        ->where('is_active', true)
        ->first();

    if (!$tenant) {
        Log::warning('Invalid API key attempt', ['ip' => $request->ip()]);
        return response()->json(['error' => 'Invalid API key'], 401);
    }

    $request->attributes->set('tenant', $tenant);
    $request->attributes->set('tenant_id', $tenant->id);

    return $next($request);
}
```

---

### 2.2 CRITICAL-002: Potential SQL Injection in Search Controller

**Locatie:** `app/Http/Controllers/Admin/GlobalSearchController.php:249-251`

**Descriere:**
Query-ul foloseste parametrul `$query` direct in clauza `orWhere('id', 'LIKE', "%{$query}%")` fara escape corespunzator:

```php
$orders = Order::query()
    ->where('tenant_id', $tenantId)
    ->where(function ($q) use ($lowerQuery, $query) {
        $q->whereRaw("LOWER(customer_email) LIKE ?", [$lowerQuery])
            ->orWhere('id', 'LIKE', "%{$query}%"); // Potential SQL injection
    })
```

**Impact:**
- Exfiltrare date din baza de date
- Modificare/stergere date
- CVSS Score: 8.6 (High)

**Remediere:**
```php
->orWhere('id', 'LIKE', '%' . addcslashes($query, '%_') . '%')
```

---

### 2.3 CRITICAL-003: Session Encryption Disabled

**Locatie:** `.env.example:36`

**Descriere:**
Session encryption este dezactivata by default:
```
SESSION_ENCRYPT=false
```

**Impact:**
- Furt de sesiune
- Session hijacking
- CVSS Score: 7.5 (High)

**Remediere:**
```env
SESSION_ENCRYPT=true
```

---

## 3. Vulnerabilitati de Severitate Ridicata

### 3.1 HIGH-001: CORS Wildcard pentru Seating API

**Locatie:** `.env.example:109`

**Descriere:**
```
SEATING_CORS_ORIGINS=*
```

Permite cereri cross-origin de la orice domeniu.

**Impact:**
- Cross-Site Request Forgery (CSRF)
- Data exfiltration

**Remediere:**
Specificati explicit domeniile permise:
```env
SEATING_CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
```

---

### 3.2 HIGH-002: Admin Domain Check in Production

**Locatie:** `app/Http/Middleware/AuthenticateAdmin.php:66-73`

**Descriere:**
Verificarea domeniului email pentru admin este conditionata doar de `config('app.env') === 'local'`, dar daca `MICROSERVICES_ADMIN_DOMAINS` este setat in productie, verificarea nu va functiona corect.

**Remediere:**
Eliminati complet verificarea bazata pe domeniu email si folositi doar roluri explicite.

---

### 3.3 HIGH-003: Shell Command Execution

**Locatie:** `app/Console/Commands/VersionAutoCommand.php:22,26`

**Descriere:**
```php
$output = shell_exec('git diff --cached --name-only 2>/dev/null');
$output = shell_exec('git diff-tree --no-commit-id --name-only -r HEAD 2>/dev/null');
```

Folosirea `shell_exec` prezinta riscuri daca parametrii ar fi introdusi de utilizatori.

**Impact:**
- Command injection (daca parametrii ar fi variabili)

**Remediere:**
Folositi Process component din Symfony sau verificati ca comenzile nu contin input de la utilizator.

---

### 3.4 HIGH-004: File Upload Without Proper Validation

**Locatie:** `app/Http/Controllers/Api/TenantClient/AdminController.php:1132-1135`

**Descriere:**
```php
$file = $request->file('file');
$path = $file->store("tenants/{$tenant->id}/brand", 'public');
```

Nu exista validare explicita pentru tipul de fisier, dimensiune sau continut.

**Remediere:**
```php
$request->validate([
    'file' => [
        'required',
        'file',
        'mimes:jpg,jpeg,png,gif,webp',
        'max:2048', // 2MB max
        'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
    ],
]);

// Verificare suplimentara continut
$mimeType = $file->getMimeType();
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    return response()->json(['error' => 'Invalid file type'], 422);
}
```

---

### 3.5 HIGH-005: CSV Injection

**Locatie:** `app/Http/Controllers/Api/GroupBookingController.php:88`

**Descriere:**
```php
$csvData = array_map('str_getcsv', file($request->file('file')->getPathname()));
```

CSV-ul este parsat fara sanitizare, permitand CSV injection attacks.

**Remediere:**
```php
$csvData = array_map(function($row) {
    return array_map(function($cell) {
        // Remove formula injection characters
        if (preg_match('/^[=+\-@\t\r]/', $cell)) {
            return "'" . $cell;
        }
        return $cell;
    }, str_getcsv($row));
}, file($request->file('file')->getPathname()));
```

---

## 4. Vulnerabilitati de Severitate Medie

### 4.1 MED-001: Verbose Error Messages in Production

**Locatie:** `.env.example:4`

**Descriere:**
```
APP_DEBUG=true
```

Expune stack traces si informatii sensibile.

**Remediere:**
```env
APP_DEBUG=false
```

---

### 4.2 MED-002: Missing Content-Security-Policy Header pentru API

**Locatie:** `app/Http/Middleware/EnhancedSecurityMiddleware.php:224-229`

CSP este adaugat doar pentru raspunsuri HTML.

**Remediere:**
Adaugati CSP header si pentru raspunsuri JSON unde relevant.

---

### 4.3 MED-003: Rate Limiting Insuficient pentru Login

**Locatie:** `routes/api.php`

Nu exista rate limiting specific pentru endpoint-urile de autentificare/login.

**Remediere:**
```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // Max 5 incercari pe minut
```

---

### 4.4 MED-004: HTTP Strict Transport Security (HSTS) Missing

**Locatie:** `.htaccess:8-10`

HTTPS redirect este comentat:
```apache
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Remediere:**
```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Add HSTS header
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

---

### 4.5 MED-005: Insecure Session Cookie

**Locatie:** `config/session.php:172`

```php
'secure' => env('SESSION_SECURE_COOKIE'),
```

Nu are o valoare default sigura.

**Remediere:**
```php
'secure' => env('SESSION_SECURE_COOKIE', true),
```

---

### 4.6 MED-006: API Key in Query String

**Locatie:** `app/Http/Middleware/VerifyApiKey.php:15`

```php
$key = $request->header('X-API-Key') ?? $request->query('api_key');
```

Permite API key in URL, care poate fi logat in access logs.

**Remediere:**
Eliminati suportul pentru API key in query string.

---

### 4.7 MED-007: Missing Request ID for Audit Trail

Nu exista request ID unic pentru corelarea log-urilor.

**Remediere:**
Adaugati middleware pentru request ID:
```php
$requestId = (string) Str::uuid();
$request->headers->set('X-Request-ID', $requestId);
Log::shareContext(['request_id' => $requestId]);
```

---

## 5. Vulnerabilitati de Severitate Scazuta

### 5.1 LOW-001: Missing Security.txt

Nu exista fisier `/.well-known/security.txt`.

### 5.2 LOW-002: Server Information Disclosure

Headerele pot expune versiunea PHP/Apache.

### 5.3 LOW-003: Missing Subresource Integrity

Pentru script-uri si stiluri externe.

### 5.4 LOW-004: Cookie SameSite Not Strict

```php
'same_site' => env('SESSION_SAME_SITE', 'lax'),
```

---

## 6. Protectie DDoS - Analiza si Recomandari

### 6.1 Starea Actuala

Platforma are urmatoarele mecanisme de protectie:

1. **Rate Limiting Basic** - Implementat prin Laravel throttle middleware
2. **EnhancedSecurityMiddleware** - Detectie pattern-uri suspecte
3. **IP Blocklist** - Mecanism de blocare IP bazat pe comportament suspect

### 6.2 Vulnerabilitati la DDoS

1. **Lipsa WAF (Web Application Firewall)**
2. **Nu exista protectie pentru Layer 7 attacks**
3. **Limitare rate per IP poate fi evitata prin IP rotation**
4. **Endpoint-uri publice fara autentificare vulnerabile**

### 6.3 Recomandari Protectie DDoS

#### Nivel 1: Infrastructura (Recomandat)

```markdown
## Cloudflare / AWS Shield / Akamai

1. Activati Cloudflare Pro/Business pentru:
   - DDoS mitigation automatic
   - Rate limiting avansat
   - Bot protection
   - WAF rules

2. Configurare DNS:
   - Proxied (orange cloud) pentru toate domeniile
   - Origin IP hidden
   - SSL/TLS Full (Strict)
```

#### Nivel 2: Aplicatie

```php
// Middleware avansat pentru rate limiting
class DDoSProtectionMiddleware
{
    protected int $maxRequestsPerMinute = 60;
    protected int $maxRequestsPerSecond = 10;
    protected int $suspiciousThreshold = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $key = 'ddos:' . $ip;

        // Check per-second rate
        $perSecond = RateLimiter::attempt(
            $key . ':sec',
            $this->maxRequestsPerSecond,
            fn() => null,
            1
        );

        if (!$perSecond) {
            Log::alert('DDoS: Per-second limit exceeded', ['ip' => $ip]);
            return response('Too Many Requests', 429);
        }

        // Check per-minute rate
        $perMinute = RateLimiter::attempt(
            $key . ':min',
            $this->maxRequestsPerMinute,
            fn() => null,
            60
        );

        if (!$perMinute) {
            // Add to suspicious list
            Cache::increment('suspicious:' . $ip);

            if (Cache::get('suspicious:' . $ip) > $this->suspiciousThreshold) {
                // Block IP temporarily
                Cache::put('blocked:' . $ip, true, 3600);
            }

            return response('Too Many Requests', 429);
        }

        return $next($request);
    }
}
```

#### Nivel 3: Configurare Server

```nginx
# nginx.conf - Rate Limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;
limit_conn_zone $binary_remote_addr zone=conn:10m;

server {
    # Connection limits
    limit_conn conn 20;

    # API rate limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        limit_req_status 429;
    }

    # Login rate limiting
    location ~ ^/(login|register|password) {
        limit_req zone=login burst=5 nodelay;
        limit_req_status 429;
    }

    # Block common attack patterns
    if ($http_user_agent ~* (wget|curl|python|nikto|sqlmap)) {
        return 403;
    }
}
```

#### Nivel 4: Redis pentru Rate Limiting Distribuit

```php
// config/cache.php - Redis rate limiting
'rate_limiting' => [
    'driver' => 'redis',
    'connection' => 'rate_limiter',
],

// Folosire in middleware
use Illuminate\Support\Facades\Redis;

$key = 'rate_limit:' . $request->ip();
$current = Redis::incr($key);

if ($current === 1) {
    Redis::expire($key, 60);
}

if ($current > 100) {
    return response('Rate limit exceeded', 429);
}
```

---

## 7. Securitate Baza de Date si Continuitate

### 7.1 Starea Actuala

- **Driver:** SQLite (development), PostgreSQL (production)
- **Session storage:** Database
- **Queue storage:** Database
- **Cache:** Database

### 7.2 Recomandari Securitate DB

#### 7.2.1 Encryption at Rest

```sql
-- PostgreSQL: Enable Transparent Data Encryption
-- Or use encrypted storage (AWS RDS, Azure SQL)
```

#### 7.2.2 Encryption in Transit

```env
# .env
DB_CONNECTION=pgsql
DB_SSLMODE=require
DB_SSLCERT=/path/to/client-cert.pem
DB_SSLKEY=/path/to/client-key.pem
DB_SSLROOTCERT=/path/to/ca-cert.pem
```

#### 7.2.3 Credentiale Sigure

```env
# Folositi secreturi din environment, nu hardcodate
DB_PASSWORD=${DB_PASSWORD_FROM_VAULT}

# Rotatie automata parole
# Folositi AWS Secrets Manager, HashiCorp Vault, etc.
```

#### 7.2.4 Least Privilege Access

```sql
-- Creati utilizator aplicatie cu permisiuni minime
CREATE USER app_user WITH PASSWORD 'secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user;
REVOKE DROP, TRUNCATE ON ALL TABLES IN SCHEMA public FROM app_user;

-- Utilizator separat pentru migrari
CREATE USER migration_user WITH PASSWORD 'different_password';
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO migration_user;
```

### 7.3 Strategii de Backup

#### 7.3.1 Backup Automat Zilnic

```bash
#!/bin/bash
# backup-db.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/postgresql"
DB_NAME="epas_production"

# Create backup
pg_dump -Fc $DB_NAME > $BACKUP_DIR/backup_$DATE.dump

# Encrypt backup
gpg --encrypt --recipient backup@company.com $BACKUP_DIR/backup_$DATE.dump

# Upload to S3
aws s3 cp $BACKUP_DIR/backup_$DATE.dump.gpg s3://backups-bucket/db/$DATE/

# Clean old local backups (keep 7 days)
find $BACKUP_DIR -mtime +7 -delete

# Verify backup integrity
pg_restore --list $BACKUP_DIR/backup_$DATE.dump > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "Backup verified successfully"
else
    echo "ALERT: Backup verification failed!" | mail -s "Backup Alert" admin@company.com
fi
```

#### 7.3.2 Point-in-Time Recovery (PITR)

```ini
# postgresql.conf
wal_level = replica
archive_mode = on
archive_command = 'aws s3 cp %p s3://wal-archive/%f'
```

#### 7.3.3 Replicare pentru High Availability

```yaml
# docker-compose.yml pentru PostgreSQL HA
version: '3.8'
services:
  postgres-primary:
    image: postgres:16
    environment:
      POSTGRES_REPLICATION_MODE: master
      POSTGRES_REPLICATION_USER: replicator
      POSTGRES_REPLICATION_PASSWORD: ${REPL_PASSWORD}
    volumes:
      - postgres_primary_data:/var/lib/postgresql/data

  postgres-replica:
    image: postgres:16
    depends_on:
      - postgres-primary
    environment:
      POSTGRES_REPLICATION_MODE: slave
      POSTGRES_MASTER_HOST: postgres-primary
      POSTGRES_REPLICATION_USER: replicator
      POSTGRES_REPLICATION_PASSWORD: ${REPL_PASSWORD}
    volumes:
      - postgres_replica_data:/var/lib/postgresql/data
```

### 7.4 Disaster Recovery Plan

```markdown
## Recovery Time Objective (RTO): 4 ore
## Recovery Point Objective (RPO): 1 ora

### Procedura de Recovery:

1. **Identificare incident** (0-15 min)
   - Monitorizare alerte (Datadog, PagerDuty)
   - Evaluare impact

2. **Activare DR** (15-30 min)
   - Notificare echipa
   - Activare replica read pentru citiri

3. **Restore din backup** (30 min - 2 ore)
   - Download ultimul backup valid
   - Restore in instanta noua
   - Apply WAL logs pentru PITR

4. **Verificare integritate** (30 min)
   - Rulare teste integritate date
   - Verificare tranzactii recente

5. **Switchover trafic** (15-30 min)
   - Update DNS
   - Restart servicii aplicatie
   - Monitorizare errori

6. **Post-mortem** (24-48 ore)
   - Analiza cauza root
   - Update proceduri
   - Implementare preventie
```

---

## 8. Plan de Implementare Patch-uri

### Prioritate Imediata (24-48 ore)

1. **CRITICAL-001:** Fix TenantAuthentication middleware
2. **CRITICAL-002:** Sanitize SQL queries in search
3. **CRITICAL-003:** Enable session encryption

### Prioritate Ridicata (1 saptamana)

1. HIGH-001: Configure specific CORS origins
2. HIGH-002: Remove domain-based admin check
3. HIGH-004: Add file upload validation
4. HIGH-005: Implement CSV sanitization

### Prioritate Medie (2 saptamani)

1. MED-001: Disable debug mode
2. MED-003: Add login rate limiting
3. MED-004: Enable HSTS
4. MED-005: Secure session cookies

### Prioritate Scazuta (1 luna)

1. Implementare security.txt
2. Configurare server headers
3. Adaugare SRI pentru assets externe

---

## 9. Checklist Securitate

- [ ] Validare API key in TenantAuthentication
- [ ] Sanitizare SQL queries
- [ ] Session encryption activata
- [ ] CORS restrictionat la domenii specifice
- [ ] File upload validation
- [ ] Rate limiting pentru login
- [ ] HTTPS fortat + HSTS
- [ ] Cookie secure flag
- [ ] DDoS protection (Cloudflare/WAF)
- [ ] Database backups automatizate
- [ ] Database replication
- [ ] Monitoring si alerting

---

## 10. Concluzii

Platforma EPAS necesita remedieri urgente pentru vulnerabilitatile critice identificate, in special:

1. **Autentificarea tenant** - incomplet implementata
2. **SQL injection** - potential in search
3. **Session security** - encryption dezactivata

Dupa implementarea patch-urilor de securitate, se recomanda:
- Penetration testing periodic
- Security audit trimestrial
- Bug bounty program
- Security training pentru dezvoltatori

---

*Raport generat automat. Pentru intrebari, contactati echipa de securitate.*
