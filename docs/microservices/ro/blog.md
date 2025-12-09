# Blog & Articole

## Prezentare Scurtă

Spune-ți povestea cu Blog & Articole. Evenimentele grozave merită conținut grozav. Acest sistem comprehensiv de gestionare a conținutului transformă platforma ta într-o centrală de publicare, ajutându-te să te conectezi cu audiența înainte, în timpul și după evenimente.

Creează articole captivante despre evenimente viitoare, interviuri cu artiști, imagini din culise și rezumate ale evenimentelor. Editorul de text îmbogățit face scrierea o plăcere, cu încorporare ușoară de media pentru fotografii, videoclipuri și conținut social care aduce poveștile tale la viață.

Organizează conținutul în felul tău cu categorii, taguri și serii. Construiește anticiparea cu o serie de articole care duc la evenimentul tău principal. Grupează acoperirea festivalului într-o serie dedicată pe care fanii o pot urmări de la prima zi până la ultimul bis.

Setul de instrumente SEO asigură că conținutul tău este descoperit. Titluri meta personalizate, descrieri și cuvinte cheie ajută motoarele de căutare să înțeleagă și să clasifice conținutul tău. Imaginile featured atrag priviri în partajările sociale.

Suportul multi-autor lasă întreaga ta echipă să contribuie. Urmărește cine a scris ce, construiește profiluri de autori și prezintă-ți creatorii de conținut. Istoricul reviziilor înseamnă că nu pierzi niciodată un draft, iar fluxul de publicare ține totul organizat de la prima idee până la articolul publicat.

Menține cititorii implicați cu un sistem de comentarii complet cu instrumente de moderare. Urmărește vizualizările articolelor și construiește lista de abonați la newsletter direct din blogul tău. Transformă cititorii în cumpărători de bilete cu call-to-action-uri plasate strategic.

Evenimentele tale au povești. E timpul să le spui.

---

## Descriere Detaliată

Blog & Articole este un sistem complet de gestionare a conținutului conceput pentru organizatorii de evenimente care vor să construiască conexiuni mai profunde cu audiența lor prin marketing de conținut. Oferă toate instrumentele necesare pentru a crea, organiza și distribui conținut captivant.

### Creare Conținut

Editorul de text îmbogățit suportă:
- Text formatat cu titluri, liste și citate
- Încorporare imagini cu legende și galerii
- Încorporare video de la YouTube, Vimeo și altele
- Încorporări social media
- Blocuri HTML personalizate pentru layout-uri avansate

### Organizare Conținut

Instrumente multiple de organizare țin conținutul tău structurat:
- **Categorii**: Subiecte largi precum "Știri", "Recenzii", "Interviuri"
- **Taguri**: Cuvinte cheie specifice pentru organizare granulară
- **Serii**: Grupează articolele înrudite în colecții secvențiale
- **Autori**: Suport multi-autor cu profiluri

### Flux de Publicare

Articolele trec printr-un flux clar:
1. **Draft**: Lucru în progres, nevizibil publicului
2. **Programat**: Gata de publicare la o dată/oră viitoare
3. **Publicat**: Live și vizibil cititorilor
4. **Privat/Protejat cu Parolă**: Acces restricționat

### Optimizare SEO

Instrumentele SEO integrate includ:
- Titluri și descrieri meta personalizate
- Targetare cuvinte cheie
- Slug-uri automate cu personalizare
- Taguri Open Graph pentru partajare socială
- Calcul timp de citire

### Funcții de Engagement

- Sistem de comentarii cu coadă de moderare
- Urmărire vizualizări și analize
- Formulare de abonare newsletter
- Sugestii articole conexe
- Butoane de partajare socială

---

## Funcționalități

### Gestionare Conținut
- Editor text îmbogățit cu încorporare media
- Stări draft, programat și publicat
- Istoric revizii conținut
- Articole și imagini featured
- Calcul automat timp de citire
- Vizibilitate public, privat și protejat cu parolă

### Organizare
- Gestionare categorii și taguri
- Suport autori multipli cu profiluri
- Organizare articole în serii
- Personalizare slug
- Sugestii articole conexe

### SEO & Descoperire
- Metadate SEO (titlu, descriere, cuvinte cheie)
- Integrare partajare socială
- Timp automat de citire
- Generare sitemap
- URL-uri prietenoase cu căutarea

### Engagement
- Sistem comentarii cu moderare
- Urmărire vizualizări și analize
- Abonări newsletter
- Articole conexe
- Butoane partajare socială

---

## Cazuri de Utilizare

### Promovare Evenimente
Construiește entuziasmul pentru evenimentele viitoare cu articole de prezentare, spotlight-uri pe artiști și conținut de preview care conduce vânzările de bilete.

### Prezentări Artiști și Performeri
Intervievează artiști și performeri, partajând poveștile lor și creând conexiuni mai profunde cu audiența ta.

### Rezumate Evenimente
Publică galerii foto, highlight-uri video și rezumate scrise care extind experiența evenimentului și mențin participanții implicați.

### Din Culise
Partajează crearea evenimentelor tale - pregătirea locației, prezentări ale echipei și poveștile care fac evenimentele tale speciale.

### Știri din Industrie
Poziționează-te ca autoritate în industrie acoperind știri relevante, tendințe și perspective în spațiul tău de evenimente.

### Povești ale Clienților
Prezintă testimoniale, experiențe ale participanților și povești din comunitate care construiesc dovadă socială și încredere.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Blog & Articole oferă un CMS complet pentru crearea, organizarea și publicarea conținutului. Suportă fluxuri de lucru multi-autor, optimizare SEO și urmărire engagement.

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `blog_categories` | Categorii conținut |
| `blog_tags` | Taguri conținut |
| `blog_authors` | Profiluri autori |
| `blog_series` | Serii/colecții articole |
| `blog_articles` | Conținut articole |
| `blog_article_tag` | Relații articol-tag |
| `blog_article_revisions` | Istoric versiuni |
| `blog_article_views` | Analize vizualizări |
| `blog_comments` | Comentarii cititori |
| `blog_subscriptions` | Abonați newsletter |

### Endpoint-uri API

#### Listează Articole
```
GET /api/blog/articles
```
Listează articolele publicate cu paginare și filtre.

#### Obține Articol
```
GET /api/blog/articles/{slug}
```
Obține articolul după slug.

#### Creează Articol
```
POST /api/blog/articles
```
Creează articol nou (admin).

#### Actualizează Articol
```
PUT /api/blog/articles/{id}
```
Actualizează conținutul articolului.

#### Publică Articol
```
POST /api/blog/articles/{id}/publish
```
Mută articolul în starea publicat.

#### Listează Categorii
```
GET /api/blog/categories
```
Listează toate categoriile.

#### Listează Taguri
```
GET /api/blog/tags
```
Listează toate tagurile.

#### Listează Autori
```
GET /api/blog/authors
```
Listează profilurile autorilor.

#### Listează Serii
```
GET /api/blog/series
```
Listează seriile de articole.

#### Obține Comentarii
```
GET /api/blog/articles/{id}/comments
```
Obține comentariile articolului.

#### Postează Comentariu
```
POST /api/blog/articles/{id}/comments
```
Trimite comentariu cititor.

#### Abonează-te
```
POST /api/blog/subscribe
```
Abonează-te la newsletter.

#### Obține Statistici
```
GET /api/blog/stats
```
Prezentare generală analize blog.

### Structură Articol

```json
{
  "id": 1,
  "title": "Lineup-ul Festivalului de Vară 2025 Anunțat",
  "slug": "lineup-festival-vara-2025",
  "excerpt": "Pregătiți-vă pentru cel mai mare festival al anului...",
  "content": "<p>Suntem încântați să anunțăm...</p>",
  "featured_image": "/images/festival-lineup.jpg",
  "author": {
    "id": 1,
    "name": "Ion Editor",
    "avatar": "/avatars/ion.jpg",
    "bio": "Jurnalist muzical și entuziast de festivaluri"
  },
  "category": {
    "id": 1,
    "name": "Știri",
    "slug": "stiri"
  },
  "tags": ["festival", "lineup", "vara"],
  "series": {
    "id": 1,
    "name": "Numărătoare Inversă Festival",
    "position": 3
  },
  "seo": {
    "meta_title": "Lineup Festival Vară 2025 | Blog Evenimente",
    "meta_description": "Vezi lineup-ul complet...",
    "keywords": ["festival vara", "festival muzica", "2025"]
  },
  "status": "published",
  "visibility": "public",
  "reading_time": 5,
  "views": 1250,
  "comments_count": 23,
  "published_at": "2025-01-15T10:00:00Z",
  "created_at": "2025-01-14T15:30:00Z",
  "updated_at": "2025-01-15T09:45:00Z"
}
```

### Configurare

```php
'blog' => [
    'max_articles' => 'unlimited',
    'max_categories' => 100,
    'max_authors' => 50,
    'comments' => [
        'enabled' => true,
        'moderation' => true,
        'require_approval' => true,
    ],
    'seo' => [
        'auto_generate' => true,
        'max_title_length' => 60,
        'max_description_length' => 160,
    ],
    'reading_time' => [
        'words_per_minute' => 200,
    ],
]
```

### Exemplu de Integrare

```php
use App\Services\Blog\BlogService;

$blog = app(BlogService::class);

// Creează articol
$article = $blog->createArticle([
    'title' => 'Anunț Eveniment Nou',
    'content' => '<p>Știri interesante...</p>',
    'category_id' => 1,
    'author_id' => 1,
    'status' => 'draft',
]);

// Publică articol
$blog->publish($article->id);

// Obține articole populare
$popular = $blog->getPopular(limit: 5);

// Urmărește vizualizare
$blog->trackView($article->id, $request);
```
