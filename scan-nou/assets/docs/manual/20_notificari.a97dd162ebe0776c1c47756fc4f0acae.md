# Capitolul 20 — Panoul de notificări

Panou dropdown pentru **notificări** — alerte, check-in-uri importante, sync-uri, rapoarte urgențe primite. Plus, jos, butoane de apel-rapid pentru urgențe.

Timp de citit: **~3 minute**.

---

## 1. Cum ajungi la panou

**Iconă clopoțel** în header, dreapta sus:

- Fără cerc roșu = fără notificări noi
- **Cu cerc roșu + număr** = ai notificări necitite (ex. „5")

<!-- SCREENSHOT: header cu clopoțel + badge roșu 3 -->
![Clopoțel cu badge](./screenshots/20-bell.png)

**Tap** → panoul se deschide ca dropdown dreapta.

---

## 2. Ce vezi

Sus:
- Titlu **„Notificări"** + badge roșu cu număr necitite
- Link **„Marchează toate citite"** dreapta (dacă ai necitite)

**Sub**: listă notificări în ordine cronologică descendentă (cele mai recente sus).

**Jos, două secțiuni**:
- **„Sună la Contact Urgență"** — 3 butoane pentru apel telefonic ([cap. 19](./19_contact_urgente.md))
- **„Alertă în aplicație (către admini)"** — trimite raport in-app cu opțiune de foto + notă vocală ([cap. 18](./18_raportare_urgente.md))

<!-- SCREENSHOT: panou notificări complet cu listă + butoane apel jos -->
![Panou notificări](./screenshots/20-panel.png)

---

## 3. Tipuri de notificări

| Iconă | Tip | Când apare |
|---|---|---|
| ⚠️ (roșu) | Alert / Urgență | Raport urgență primit, capacitate 90%+, ceva critic |
| ✅ (verde) | Succes | Sale nou, check-in reușit, sync completă |
| ℹ️ (cyan) | Info | Actualizări, mesaje sistem |

Fiecare notificare arată:
- Iconă cu fundal color
- **Mesaj** (max 2 rânduri)
- **Timp** (ex. „acum 2 min")
- **Bulinuță roșie** dreapta dacă e necitită

---

## 4. Notificări generate automat

Aplicația trimite notificări local pentru:

- **Vânzări noi** — succes cu suma încasată
- **Scanare validă** — persoana cu tipul biletului
- **Duplicate** — atenționare
- **Raport urgență trimis** — auto-confirmare
- **Sync completă** — după ce s-au sincronizat vânzările offline

---

## 5. Notificări de la server

Când e wire-uit push (viitor), vor apărea:

- **Alerte de la alți administratori** — un coleg a raportat o urgență
- **Modificări eveniment** — admin a schimbat capacitatea
- **Refunduri procesate** — clientul a cerut și primit refund

Deocamdată acestea apar doar când aplicația e deschisă pe fundal.

---

## 6. Marchează ca citit

**Tap pe o notificare** → devine „citită" (fără bulinuță roșie).

**Tap pe „Marchează toate citite"** → toate devin citite deodată.

Contorul din header scade după fiecare marchează.

---

## 7. Notificarea despre urgențe primite

Dacă un coleg de echipă raportează urgență ([cap. 18](./18_raportare_urgente.md)), tu (admin/proprietar) primești în panou:

```
[⚠️] Urgență: Problemă Tehnică
     Raportat de Ion Popescu (Manager) — poarta VIP1.
     [thumbnail 56×56] [▶ Redă · 12s]
     acum 30 sec
```

Cu iconă roșie alert. Include:
- Cine a raportat + rolul
- De la ce poartă
- **Thumbnail foto** (dacă atașat) — 56×56 px, click pentru mărire
- **Buton Redă** pentru nota vocală (dacă atașată) — cu durata în secunde

---

## 8. Sunet + vibrație la primire

Când o notificare nouă apare **live** (în timp ce e app-ul deschis), aplicația:

- **Sună** (respectă toggle-ul „Efecte Sonore" din Setări → Scanner)
- **Vibrează** — pattern lung pentru alerte, scurt pentru info

Astfel n-o ratezi chiar dacă nu te uiți la ecran.

---

## 9. Butoanele de apel-rapid (jos)

Sub notificări, ai mereu:
- **🔴 Urgență Medicală**
- **🟡 Problemă Tehnică**
- **🔷 Alertă Pază**

Fiecare arată numărul setat sau „Nesetat". Detalii în
[cap. 19](./19_contact_urgente.md).

---

## 10. Panoul rămâne poziționat sub tab bar

Panoul se deschide de sub header în partea dreaptă, dar **nu suprapune** tab bar-ul de jos. Vezi mereu unde ești în app.

Închiderea:
- **Tap în afara panoului** (zona întunecată)
- **Butonul back Android**

---

## 11. Limitări

- Notificările locale se **pierd** la închiderea completă a app-ului (nu sunt persistate) — versiune curentă. Push-uri persistate = lucrare viitoare
- Max ~50 notificări afișate; cele foarte vechi sunt curățate automat
- Nu poți șterge o notificare individual (deocamdată — folosește „Marchează toate citite")

---

## 12. Probleme frecvente

**„Badge-ul roșu arată număr, dar în panou nu apare nimic"**
- Bug rar de cache. Închide și redeschide panoul.

**„Am primit notificare urgență, dar fără poză"**
- Renderer de media pentru notificări = lucrare viitoare (în roadmap). Deocamdată doar textul.

**„Nu aud sunetul de notificare"**
- Verifică toggle-ul „Efecte Sonore" din Setări scanner
- Verifică volumul telefonului (nu media, ci notificări/apeluri)

---

## 13. Testează pe viu

1. Fă o vânzare test ([cap. 8](./08_bilete_test.md))
2. După confirmare, tap pe clopoțel
3. Vezi apărea o notificare verde de succes cu numele + suma
4. Tap pe „Marchează toate citite" — badge dispare
5. Trimite un raport urgență ([cap. 18](./18_raportare_urgente.md))
6. Verifică că apare tot în panou (dacă ești admin/proprietar)

---

## Următorul capitol

📖 [Capitolul 21 — Tura →](./21_tura.md)

📚 [Cuprins →](./00_cuprins.md)
