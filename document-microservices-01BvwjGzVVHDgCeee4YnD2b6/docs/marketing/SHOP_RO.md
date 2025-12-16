# 🛒 Microserviciu Magazin

## Prezentare Generală

Transformă platforma ta de evenimente într-o soluție completă de e-commerce. Microserviciul Magazin îți permite să vinzi produse fizice, produse digitale și carduri cadou alături de biletele tale pentru evenimente. Creează oportunități de upsell, combină produsele cu biletele și crește venitul per client.

**Preț:** €29/lună per tenant

---

## Funcționalități Principale

### Gestionarea Produselor
- **Produse Fizice**: Vinde merchandise, îmbrăcăminte, accesorii cu urmărire completă a stocului
- **Produse Digitale**: Livrează conținut descărcabil cu link-uri securizate și cu expirare
- **Variante de Produse**: Creează combinații de mărime, culoare și atribute personalizate
- **Categorii și Organizare**: Structură ierarhică de categorii pentru navigare ușoară
- **Suport Galerie**: Multiple imagini pentru produse cu selecție de imagine principală

### Inventar și Control Stoc
- **Inventar în Timp Real**: Actualizări automate ale stocului la achiziție și rambursare
- **Alerte Stoc Redus**: Primești notificări când produsele sunt aproape epuizate
- **Rezervare Stoc**: Rezervare de 15 minute în timpul checkout-ului pentru prevenirea supravânzării
- **Opțiune Stoc Nelimitat**: Perfect pentru produse digitale sau articole la comandă

### Prețuri Flexibile
- **Prețuri Promoționale**: Programează prețuri promoționale
- **Urmărirea Costurilor**: Monitorizează marjele de profit cu urmărirea cost-per-articol
- **Multi-valută**: Suport pentru RON, EUR, USD, GBP

### Livrare și Îndeplinire Comenzi
- **Zone de Livrare**: Definește regiuni cu tarife de livrare personalizate
- **Metode Multiple**: Tarif fix, bazat pe greutate, bazat pe preț sau livrare gratuită
- **Estimări Livrare**: Afișează timpul estimat de livrare către clienți
- **Procesare Comenzi**: Urmărește statusul comenzii de la procesare la livrat

### Integrare cu Evenimente
- **Produse Upsell**: Sugerează produse relevante în timpul checkout-ului biletelor
- **Produse Bundle**: Include merchandise cu anumite tipuri de bilete
- **Checkout Combinat**: O singură plată pentru bilete și produse

### Carduri Cadou
- **Carduri Cadou Digitale**: Vinde și răscumpără carduri cadou în magazinul tău
- **Valori Personalizate**: Permite clienților să aleagă suma cardului cadou
- **Urmărirea Soldului**: Gestionarea soldului cardurilor cadou în timp real

### Experiența Clientului
- **Recenzii Produse**: Colectează și afișează recenzii ale clienților
- **Wishlist**: Permite clienților să salveze produsele pentru mai târziu
- **Notificări Disponibilitate**: Alertează clienții când produsele epuizate revin în stoc

---

## Gestionarea Comenzilor

### Fluxul Comenzii
1. **În Așteptare** - Comandă plasată, se așteaptă plata
2. **Procesare** - Plată primită, se pregătește comanda
3. **Expediat** - Comanda expediată cu număr de urmărire
4. **Livrat** - Comanda primită de client
5. **Finalizat** - Tranzacție finalizată
6. **Anulat/Rambursat** - Comanda anulată sau rambursată

### Funcționalități
- Generare automată a numărului de comandă (ex. SH-2024-00001)
- Istoric detaliat al comenzii cu marcaje temporale
- Note interne pentru comunicarea personalului
- Export comenzi în masă pentru îndeplinire

---

## Panou de Administrare

Accesează gestionarea Magazinului prin panoul de control al tenant-ului:

- **Produse** → Creează și gestionează catalogul de produse
- **Categorii** → Organizează produsele în categorii
- **Atribute** → Definește atributele produselor (mărime, culoare, etc.)
- **Comenzi** → Vizualizează și procesează comenzile clienților
- **Carduri Cadou** → Gestionează inventarul de carduri cadou
- **Livrare** → Configurează zonele și metodele de livrare
- **Setări** → Configurare generală magazin

---

## Puncte de Integrare

### Procesarea Plăților
Funcționează perfect cu procesatorii de plăți existenți (Stripe, PayPal, etc.)

### Sistemul de Cupoane
Integrare completă cu sistemul de cupoane al platformei:
- Reduceri specifice pe produs
- Promoții la nivel de categorie
- Reduceri la bundle
- Produs gratuit la achiziție

### Analiză
Urmărește performanța magazinului:
- Vânzări pe produs și categorie
- Tendințe de venituri
- Rotația inventarului
- Tipare de achiziție ale clienților

---

## Endpoint-uri API

### Produse
- `GET /api/shop/products` - Listare produse cu filtrare și paginare
- `GET /api/shop/products/{slug}` - Detalii produs cu variante
- `GET /api/shop/categories` - Listare toate categoriile
- `GET /api/shop/categories/{slug}` - Categorie cu produse

### Coș
- `GET /api/shop/cart` - Obține coșul curent
- `POST /api/shop/cart/items` - Adaugă produs în coș
- `PUT /api/shop/cart/items/{id}` - Actualizează cantitatea
- `DELETE /api/shop/cart/items/{id}` - Șterge articol
- `POST /api/shop/cart/coupon` - Aplică cod cupon

### Checkout
- `POST /api/shop/checkout/init` - Inițializează sesiunea de checkout
- `POST /api/shop/checkout/submit` - Finalizează achiziția
- `GET /api/shop/checkout/shipping-methods` - Opțiuni de livrare disponibile

### Comenzi
- `GET /api/shop/orders` - Istoric comenzi client
- `GET /api/shop/orders/{number}` - Detalii comandă

### Descărcări
- `GET /api/shop/downloads/{token}` - Descarcă produs digital

---

## Opțiuni de Configurare

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Nume Magazin | Nume afișat pentru magazin | Nume tenant |
| Monedă | Moneda implicită pentru produse | RON |
| Rată TVA | Procent TVA/impozit vânzări | 19% |
| TVA Inclus | Prețurile includ TVA | Da |
| Prefix Comandă | Prefix pentru numere comenzi | SH |
| Prag Stoc Redus | Alertă când stocul scade sub | 5 |
| Activează Recenzii | Permite recenzii produse | Nu |
| Activează Wishlist | Permite funcția wishlist | Nu |
| Mod Checkout | Combinat cu bilete sau separat | Combinat |

---

## Cazuri de Utilizare

### Merchandise Festival
Vinde merchandise oficial al evenimentului, tricouri ale trupelor și suveniruri alături de biletele de festival.

### Materiale Conferință
Oferă materiale de curs digitale, înregistrări și caiete de lucru fizice pentru participanții la conferință.

### Pachete VIP
Creează bundle-uri premium care includ bilete, merchandise și conținut digital exclusiv.

### Merchandise Locație
Teatrele și locațiile pot vinde programe de spectacol, postere și suveniruri.

---

## Cum Să Începi

1. **Activează Microserviciul**: Activează Magazinul în setările tenant-ului
2. **Configurează Setările**: Configurează numele magazinului, moneda și setările de taxare
3. **Adaugă Produse**: Creează primele produse cu imagini și prețuri
4. **Configurează Livrarea**: Configurează zonele și metodele de livrare
5. **Testează Checkout-ul**: Plasează o comandă de test pentru a verifica fluxul
6. **Lansează**: Începe să vinzi!

---

## Suport

Pentru asistență cu microserviciul Magazin, contactează administratorul platformei sau consultă documentația tehnică.
