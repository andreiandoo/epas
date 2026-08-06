# Capitolul 6 — Vânzarea fără internet

> *„Semnalul cade tocmai când vine coada mare. Ce fac?"*

Din **AmBilet** poți încasa bilete fără internet. Vânzările se salvează pe telefon și se trimit la server automat când revine semnalul. Casierul nu se blochează niciodată — coada continuă să curgă.

Timp estimat pentru citirea acestui capitol: **~4 minute**. Timp până funcționezi cu vânzarea offline: **0 secunde**. Merge din prima.

---

## 1. De unde știi că ești offline

Sus pe ecran, lângă clopoțelul de notificări, e o pastilă mică ce arată starea conexiunii:

- 🟢 **Online** — ai semnal, totul merge live
- 🔴 **Offline** — ai pierdut semnalul, aplicația a intrat automat în modul offline

**Nu trebuie să apeși nimic** — aplicația detectează singură când cade semnalul (Wi-Fi sau date mobile) și trece automat pe offline. Toate funcțiile importante — vânzare, check-in, raportare urgențe — continua să meargă.

---

## 2. Vinzi normal, cu 2 excepții

Fluxul e identic cu vânzarea online. Deschizi tabul **Vânzare**, adaugi biletele în coș, apeși **Numerar** sau **Card POS**, confirmi încasarea. Aplicația salvează vânzarea local și îți afișează ecranul de plată reușită.

### Ce se schimbă vizibil

**a) Plata cu Card prin NFC (Stripe Tap) nu funcționează.**
Are nevoie de conexiune la Stripe pentru autorizarea cardului. Dacă apeși butonul offline, primești un mesaj clar și îți sugerează Numerar sau Card POS.

**b) Ecranul de succes NU generează QR pentru trimis pe email.**
La vânzarea online, apărea un cod QR pe care clientul îl scana ca să primească biletele pe email. Offline nu putem face asta — biletele ajung pe email după ce se sincronizează cu serverul (când revine semnalul).

Restul e identic: coșul, numărătoarea, statisticile turei, totul.

<!-- SCREENSHOT: ecranul Vânzare cu 3 bilete în coș + butonul Numerar activ, în modul offline -->
![Vânzare offline — coș cu 3 bilete](./screenshots/06-cart-offline.png)

---

## 3. Cum arată succesul offline

După ce confirmi încasarea (fie cash, fie card POS), vezi același ecran verde „**Plată Reușită!**" cu suma încasată. Aplicația a salvat vânzarea pe telefon și a adăugat-o la totalurile turei.

<!-- SCREENSHOT: ecran verde Plată Reușită! cu sumă + notă discretă că e offline -->
![Succes vânzare offline](./screenshots/06-success-offline.png)

Apeși **Finalizează** și te întorci în ecranul de vânzare, gata pentru următorul client.

---

## 4. „X în așteptare" — cum urmărești ce nu s-a sincronizat

De îndată ce ai o vânzare (sau scanare) offline, sus pe ecran apare o **pastilă galbenă** cu numărul de operațiuni nesincronizate:

<!-- SCREENSHOT: header cu pastila galbenă "3 în așteptare" evidențiată -->
![Badge 3 în așteptare](./screenshots/06-pending-badge.png)

Această pastilă îți spune că **X vânzări/scanări sunt salvate pe telefon și așteaptă internet**. Nu ai pierdut nimic — pur și simplu n-au ajuns încă la server.

**Un tap pe pastilă** forțează sincronizarea manuală (util dacă crezi că ai semnal dar aplicația n-a observat încă).

---

## 5. Sincronizare automată când revii online

În momentul în care aplicația detectează internet din nou:

1. Pastila de status se face **verde: Online**
2. Aplicația trimite automat, în background, toate vânzările din coadă
3. Pe rând, pastila galbenă scade — „3 în așteptare" → „2" → „1" → dispare
4. Biletele clienților ajung acum pe email (dacă au dat email la vânzare)

Toate astea în câteva secunde, fără să apeși nimic. Poți continua să vinzi în paralel — sincronizarea rulează pe fundal.

<!-- SCREENSHOT: header cu pastila verde Online + pastila galbenă în scădere -->
![Sincronizare în progres](./screenshots/06-syncing.png)

---

## 6. Protecție împotriva dublei încasări

Un lucru important pe care echipa AmBilet l-a construit special pentru tine: chiar dacă aplicația retrimite aceeași vânzare de mai multe ori (din cauza unui semnal instabil), serverul recunoaște vânzarea și **NU creează bilete duplicate**.

Fiecare vânzare are un cod unic generat pe telefon. Serverul îl verifică — dacă îl vede a doua oară, întoarce comanda existentă în loc să creeze una nouă. **Clientul NU va fi taxat de două ori.**

---

## 7. Limitări pe care e bine să le știi

| Ce nu merge offline | De ce | Ce faci în schimb |
|---|---|---|
| Plata cu **Card NFC (Stripe Tap)** | Are nevoie de Stripe live | Folosește **Numerar** sau **Card POS** |
| **QR de trimitere email** la succes | Endpoint-ul cere order id de la server | Biletele merg pe email după sincronizare |
| **Ecranul Bilete Eveniment** (lista completă) | Se încarcă live de la server | Datele existente în cache rămân disponibile |
| **Rapoartele detaliate** | Depind de agregări server | Panoul arată totalurile locale |

Restul merge normal: vânzare, check-in, cash count, raportare urgențe.

---

## 8. Probleme frecvente

**„Pastila galbenă nu dispare deși am internet"**
- Verifică că ai internet real (poți deschide un browser?)
- Apasă pastila galbenă → declanșează sincronizare manuală
- Dacă persistă >2 minute, închide și redeschide aplicația

**„Am făcut o vânzare offline, dar nu apare pe raportul admin de pe web"**
- Așteaptă până se sincronizează (verifică pastila galbenă)
- Odată dispărută, refresh pe pagina admin → apare imediat

**„Cum știu că e safe să închid app-ul cu vânzări în așteptare?"**
- E safe. Vânzările sunt salvate persistent pe telefon.
- Când redeschizi, aplicația continuă sincronizarea de unde a rămas.
- **NU șterge aplicația** cu vânzări în așteptare (asta șterge și coada)

**„Casierul de la altă casă spune că vede online, eu spun offline"**
- Fiecare telefon are propriul semnal. Verifică Wi-Fi/date pe device-ul tău.
- Comută pe „date mobile" dacă Wi-Fi-ul e slab (chiar dacă arată conectat)

---

## 9. Testează pe viu

Dacă vrei să încerci acum:

1. [**Deschide ecranul Vânzare →**](app://navigate/Sales)
2. Pornește **modul avion** pe telefon (Setări Android → Rețea → Mod avion)
3. Verifică că pastila de status devine roșie **Offline** în header
4. Fă o vânzare de test (un bilet mic, cash)
5. Verifică că apare pastila galbenă **1 în așteptare**
6. Oprește modul avion
7. Așteaptă 5-10 secunde
8. Pastila galbenă dispare — vânzarea a ajuns la server

Poți verifica și în admin-ul web că vânzarea de test a intrat corect.

---

## Următorul capitol

📖 [Capitolul 7 — Bilete Eveniment →](./07_bilete_eveniment.md)

📚 [Cuprins →](./00_cuprins.md)
