# Invitations (Zero-Value Tickets)

## Short Presentation

Manage VIP guests, press passes, and complimentary tickets with Invitations. When tickets need to go out without payment - whether for press, sponsors, or special guests - this service handles the entire workflow from generation to check-in.

Import recipient lists from CSV with smart field mapping. Drag and drop your spreadsheet, map columns to fields, and generate personalized invitations in bulk. Include names, emails, companies, titles, and even pre-assigned seats.

Every invitation gets a unique QR code with anti-replay protection. Recipients download their personalized PDF tickets or receive them via email. Track who's downloaded their invitation and who needs a reminder.

Email delivery is built-in with personalization. Send branded invitation emails with the recipient's name, event details, and download links. Track delivery status - sent, delivered, bounced, or failed.

The status flow tells you everything: created, rendered, emailed, downloaded, opened, checked_in. Know exactly where each invitation stands. Export comprehensive reports for event day planning.

Void invitations instantly when needed. Voided invitations are blocked at check-in, ensuring only valid guests enter. Re-generate invitations for recipients who haven't used theirs yet.

Perfect for gala dinners, product launches, film premieres, and any event where guest lists matter.

---

## Features

### Batch Management
- Create invitation batches for events
- Generate N invitations per batch
- Status tracking: draft → rendering → ready → sending → completed
- Cancel batches with automatic ticket voiding

### Recipient Management
- CSV import with field mapping
- Recipient data: name, email, phone, company, title, notes
- Optional seat assignment (auto/manual/none modes)

### Distribution
- Individual PDF downloads with signed URLs
- Bulk ZIP download for entire batches
- Email delivery with queueing and chunking
- Delivery status tracking: pending, sent, delivered, bounced, failed

### Tracking
- Status flow: created → rendered → emailed → downloaded → opened → checked_in
- Download tracking with IP and user agent
- Check-in tracking with gate and timestamp
- CSV export with comprehensive data

### Security
- Anti-replay QR protection with checksums
- Signed download URLs with expiration
- Rate limiting on downloads

---

## Technical Documentation

### API Endpoints

```
POST /api/invitations/batches
```
Create invitation batch.

```
POST /api/invitations/batches/{id}/generate
```
Generate invitations for batch.

```
POST /api/invitations/batches/{id}/send
```
Send invitations via email.

```
GET /api/invitations/{code}/download
```
Download invitation PDF.

```
POST /api/invitations/{id}/void
```
Void an invitation.
