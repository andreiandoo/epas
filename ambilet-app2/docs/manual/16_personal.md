# Capitolul 16 — Personalul (adăugare, roluri, parole)

Adaugi și gestionezi echipa care va lucra la eveniment: casieri,
scanere, manageri. Fiecare are un rol care determină ce poate face.

**Vizibil doar pentru admin** (proprietar sau admin cu permisiuni team).

Timp de citit: **~5 minute**.

---

## 1. Cum ajungi la Personal

**Setări → Comenzi Admin → Asignare Personal** — apasă rândul.

Sau din Panou: **Acțiuni Rapide → Echipă**.

Ambele deschid același **modal** — Echipă & porți.

<!-- SCREENSHOT: modal Asignare Personal cu lista membrilor + form adăugare jos -->
![Asignare Personal modal](./screenshots/16-modal.png)

---

## 2. Ce vezi în modal

**Sus**: lista membrilor curenți.
**Jos**: formular pentru **adăugare membru nou**.

Fiecare membru din listă:
- **Avatar** cu inițiale + culoare per rol
- **Nume** + email
- **Status pill**: 🟢 Activ, 🟡 Pending, ⚠️ Suspendat
- **Rol badge**: Admin (roșu), Manager (verde), Staff (gri), Proprietar (violet)
- **Poartă asignată** (dacă are) — badge cyan
- Buton `×` pentru **eliminare** (nu apare la Proprietar)

---

## 3. Rolurile disponibile

| Rol | Ce poate face | Badge |
|---|---|---|
| **Proprietar** (Owner) | Tot — vede și modifică orice | Violet |
| **Admin** | Vede tot, poate adăuga/edita staff, porți, evenimente | Roșu |
| **Manager** | Vede rapoarte + gestionează personal restrâns | Verde |
| **Staff** | Doar scanare + vânzare, cifre limitate | Gri |

**Permisiuni granulare** (independente de rol):
- `events` — vede lista evenimentelor și le poate schimba
- `orders` — vede vânzările și cifrele lor
- `reports` — accesează tab-ul Rapoarte
- `team` — gestionează personalul
- `checkin` — face check-in la evenimente

Admin ia automat toate cele 5. Restul rolurilor primesc doar ce le
alegi tu.

---

## 4. Adaugă un membru nou

Formularul jos are câmpuri:

- **Prenume + Nume** (obligatoriu)
- **Email** (obligatoriu, trebuie unic per organizator)
- **Parolă** (obligatoriu, minim 6 caractere)
- **Rol**: Admin / Manager / Staff (chips selector)
- **Poarta asignată** (opțional, dacă e Staff / Manager cu check-in)
- **Acces la evenimente** (opțional whitelist — vezi mai jos)

<!-- SCREENSHOT: formular Adaugă Personal Nou cu câmpuri completate -->
![Formular adăugare](./screenshots/16-add-form.png)

Apasă **Adaugă → membrul e creat instant ca `Activ`** + primește pe
email credentialele (dacă bifezi „Trimite email").

---

## 5. Editează un membru existent

**Tap pe cardul membrului** → se extinde cu opțiuni:

### Schimbă poarta asignată

Chip-uri sub „Alocă la poartă". Tap pentru a asigna sau schimba. Tap
pe „Niciuna" pentru a scoate asignarea.

### Schimbă rolul

Sub „Schimbă rol", 3 chips: Admin / Manager / Staff. Tap pe cel dorit
→ rolul se schimbă instant.

Automat, permisiunile se ajustează după rol.

### Resetează parola

Sub „Resetează parola", câmp de parolă nouă + buton `Salvează`:
- Introdu parola nouă (min 6 caractere)
- Apasă `Salvează`
- Membrul se poate loga acum cu noua parolă
- Sync cross-organizator: dacă are cont la mai mulți, se resetează
  peste tot

<!-- SCREENSHOT: card extins cu rol picker + password reset -->
![Card extins](./screenshots/16-expand.png)

### Whitelist evenimente

Dacă e Staff sau Manager (nu Admin), poți limita la anumite evenimente:
- Sub „Acces la evenimente" apare butonul **`Modifică`**
- Se deschide un editor cu checkboxes pentru evenimentele viitoare
- Bifează cele la care poate lucra
- Fără bifă = acces la toate (default)

---

## 6. Activează un membru pending

Dacă adăugarea a mers prin invitație (nu direct add), membrul apare
inițial ca **🟡 Pending**. Trebuie **activat**:

1. Tap pe pill-ul **Activează** (verde) din cardul lui
2. Apare input pentru parolă
3. Introdu parola inițială
4. `Activează Cont` → devine 🟢 Activ

---

## 7. Elimină un membru

Butonul `×` (roșu) din colțul cardului:
1. Confirmare: „Sigur eliminați acest membru?"
2. `Elimină` → membrul e scos din echipă instant
3. Nu se poate loga cu credentialele lui la acest organizator

**Notă**: Proprietarul NU poate fi eliminat.

---

## 8. Numărul de membri (counter în Setări)

În **Setări → Comenzi Admin**, rândul „Asignare Personal" arată un
**counter** cu numărul de membri activi.

<!-- SCREENSHOT: Setări cu rândul Asignare Personal + counter „6" -->
![Counter personal](./screenshots/16-counter.png)

Se actualizează automat la închidere modal.

---

## 9. Case speciale

### Sync cross-organizator

Dacă același email e folosit la **mai mulți organizatori** (persoană
care lucrează pentru mai multe brand-uri):

- Parola e **partajată** — reset într-un organizator o resetează peste
  tot
- Rolurile sunt **separate** — poate fi Admin într-unul, Staff într-altul

Comutarea între organizatori — [cap. 28](./28_comutare_organizatori.md).

### Multi-marketplace

Personalul e per **marketplace client** (ex. AmBilet, Bilete Online,
Tics). Chiar dacă e vorba de același email, contul e izolat între ele.

---

## 10. Limitări

- **Necesită internet** — orice acțiune (add, edit, remove) trebuie
  sync live
- **Max 50 membri** per organizator (arhitectura curentă — dacă ai
  nevoie de mai mulți, contactează suport)
- **Nu poți schimba email-ul** unui membru — trebuie șters și
  readăugat cu noul email

---

## 11. Probleme frecvente

**„Am adăugat un membru, dar nu vede evenimentele mele"**
- Verifică Whitelist evenimente — dacă e limitat la unele evenimente,
  restul nu-i sunt vizibile

**„Am schimbat rolul din Admin în Staff, dar tot vede Rapoarte"**
- Force logout + login pentru a reîmprospăta permisiunile
- Sau așteaptă până se resincronizează (~30s)

**„Reset parolă spune Eroare"**
- Parola sub 6 caractere?
- Membrul e cel Proprietar? Nu poți reseta parola proprietarului din
  aplicație (trebuie prin web-admin)

**„Vreau să adaug 20 de membri deodată"**
- Nu există import CSV în aplicație. Foloseste web-admin sau
  contactează suport AmBilet.

---

## 12. Testează pe viu

1. Deschide **Setări** → **Asignare Personal**
2. Adaugă un membru fictiv (ex. „Test Staff / test-staff@ambilet.local
   / parolatest / Staff")
3. Verifică apare în listă
4. Tap pe cardul lui → extinde
5. Schimbă rolul din Staff → Manager
6. Resetează parola la „nou1234"
7. Elimină membrul (`×`)

---

## Următorul capitol

📖 [Capitolul 17 — Porțile de acces →](./17_porti.md)

📚 [Cuprins →](./00_cuprins.md)
