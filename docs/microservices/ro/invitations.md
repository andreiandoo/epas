# Invitații (Bilete cu Valoare Zero)

## Prezentare Scurtă

Gestionează oaspeții VIP, acreditările de presă și biletele gratuite cu Invitații. Când biletele trebuie trimise fără plată - fie pentru presă, sponsori sau oaspeți speciali - acest serviciu gestionează întregul flux de la generare la check-in.

Importă liste de destinatari din CSV cu mapare inteligentă a câmpurilor. Trage și plasează foaia ta de calcul, mapează coloanele la câmpuri și generează invitații personalizate în masă. Include nume, emailuri, companii, titluri și chiar locuri pre-atribuite.

Fiecare invitație primește un cod QR unic cu protecție anti-replay. Destinatarii descarcă biletele PDF personalizate sau le primesc prin email. Urmărește cine și-a descărcat invitația și cine are nevoie de un reminder.

Livrarea prin email este integrată cu personalizare. Trimite emailuri de invitație brandate cu numele destinatarului, detaliile evenimentului și linkurile de descărcare. Urmărește statusul livrării - trimis, livrat, respins sau eșuat.

Fluxul de status îți spune totul: creat, renderizat, trimis prin email, descărcat, deschis, check_in efectuat. Știi exact unde se află fiecare invitație. Exportă rapoarte comprehensive pentru planificarea zilei de eveniment.

Anulează invitațiile instant când e nevoie. Invitațiile anulate sunt blocate la check-in, asigurând că doar oaspeții valizi intră. Re-generează invitații pentru destinatarii care nu și le-au folosit încă.

Perfect pentru dineuri de gală, lansări de produse, premiere de film și orice eveniment unde listele de oaspeți contează.

---

## Funcționalități

### Gestionare Loturi
- Creează loturi de invitații pentru evenimente
- Generează N invitații per lot
- Urmărire status: draft → rendering → ready → sending → completed
- Anulează loturi cu anulare automată bilete

### Gestionare Destinatari
- Import CSV cu mapare câmpuri
- Date destinatar: nume, email, telefon, companie, titlu, note
- Atribuire opțională locuri (moduri auto/manual/niciunul)

### Distribuție
- Descărcări PDF individuale cu URL-uri semnate
- Descărcare ZIP în masă pentru loturi întregi
- Livrare email cu coadă și chunking
- Urmărire status livrare: pending, sent, delivered, bounced, failed

### Urmărire
- Flux status: created → rendered → emailed → downloaded → opened → checked_in
- Urmărire descărcare cu IP și user agent
- Urmărire check-in cu poartă și timestamp
- Export CSV cu date comprehensive

### Securitate
- Protecție anti-replay QR cu checksums
- URL-uri descărcare semnate cu expirare
- Rate limiting pe descărcări

---

## Documentație Tehnică

### Endpoint-uri API

```
POST /api/invitations/batches
```
Creează lot invitații.

```
POST /api/invitations/batches/{id}/generate
```
Generează invitații pentru lot.

```
POST /api/invitations/batches/{id}/send
```
Trimite invitații prin email.

```
GET /api/invitations/{code}/download
```
Descarcă PDF invitație.

```
POST /api/invitations/{id}/void
```
Anulează o invitație.
