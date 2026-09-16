<?php

namespace App\Services\Invitations;

use App\Models\Event;
use App\Models\Invite;
use App\Models\MarketplaceOrganizer;
use App\Services\TicketCustomizer\TicketVariableService;

/**
 * Full ticket-template dictionary ({{event.*}}, {{venue.*}}, {{date.*}},
 * {{ticket.*}}, {{buyer.*}}, {{order.*}}, {{organizer.*}}, {{legal.*}},
 * {{barcode}}, {{qrcode}}) for an invitation, from real Event / Venue /
 * Organizer / Invite values.
 *
 * Every key getSampleData() advertises starts EMPTY, so a template variable
 * never shows demo text ("Summer Music Festival", "Live Nation Romania",
 * "15 iulie 2025"). Mirrors Organizer\InvitationsController::buildTemplateData,
 * plus the seat resolved by InviteSeatResolver and the online-event fields.
 */
class InvitationTemplateData
{
    public function __construct(
        private TicketVariableService $variables,
        private InviteSeatResolver $seats,
    ) {
    }

    /**
     * @param array{ticket_label?: ?string, qrcode?: ?string} $options
     */
    public function build(Invite $invite, ?Event $event, array $options = []): array
    {
        $data = $this->blank($this->variables->getSampleData());

        $recipient = is_array($invite->recipient) ? $invite->recipient : [];
        $recipientName = trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''))
            ?: ($recipient['name'] ?? $invite->getRecipientName() ?? 'Invitat');
        $nameParts = explode(' ', $recipientName, 2);
        $code = (string) $invite->invite_code;
        $ticketLabel = trim((string) ($options['ticket_label'] ?? ''));

        $seat = $this->seats->resolve($invite, $event?->id);
        $seatFields = $this->seats->templateFields($seat, $invite->seat_ref);

        $data['ticket'] = array_merge($data['ticket'], $seatFields, [
            'type' => $ticketLabel !== '' ? mb_strtoupper($ticketLabel, 'UTF-8') : 'INVITAȚIE',
            'price' => 'GRATUIT',
            'price_detail' => $ticketLabel !== '' ? $ticketLabel : 'Invitație',
            'number' => $code,
            'code_short' => $code,
            'code_long' => $code,
            'serial' => $code,
            'is_insured' => 'false',
            'verify_url' => url('/verify/' . $code),
        ]);

        $data['buyer'] = array_merge($data['buyer'], [
            'name' => $recipientName,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'email' => $recipient['email'] ?? $invite->getRecipientEmail() ?? '',
        ]);

        $data['barcode'] = $code;
        $data['qrcode'] = $options['qrcode'] ?? ($invite->qr_data ?: url('/verify/' . $code));

        if (!$event) {
            return $data;
        }

        $event->loadMissing(['venue', 'marketplaceOrganizer']);
        $venue = $event->venue;
        $organizer = $event->marketplaceOrganizer;
        $isOnline = (bool) ($event->is_online ?? false);

        $data['event'] = array_merge($data['event'], [
            'name' => $this->translated($event->title),
            'description' => $this->translated($event->description),
            'image' => $this->variables->resolveEventImageUrl($event),
            'is_online' => $isOnline ? 'true' : 'false',
            'online_provider_label' => $isOnline ? ($event->online_provider_label ?? 'Online') : '',
            'online_lobby_note' => $isOnline
                ? 'Link-ul de acces devine activ cu ' . (int) ($event->online_lobby_opens_minutes_before ?? 15) . ' min. înainte de start.'
                : '',
        ]);

        $data['venue'] = array_merge($data['venue'], [
            'name' => $venue ? $this->translated($venue->name) : '',
            'address' => (string) ($venue?->address ?? ''),
            'city' => (string) ($venue?->city ?? ''),
        ]);

        $data['date'] = array_merge($data['date'], $this->variables->buildDateBlock($event, null, null));

        $data['organizer'] = array_merge($data['organizer'], $this->organizer($organizer));

        $data['legal'] = array_merge($data['legal'], [
            'terms' => $this->organizerValue($organizer, 'ticket_terms') ?: $this->translated($event->ticket_terms ?? null),
        ]);

        return $data;
    }

    /**
     * Same keys as the sample dictionary, every leaf emptied.
     */
    private function blank(array $sample): array
    {
        foreach ($sample as $key => $value) {
            $sample[$key] = is_array($value) ? $this->blank($value) : '';
        }

        return $sample;
    }

    /**
     * Organizer identity through getIssuerData(): a persoana fizica organizer
     * shows its individual data and never its CNP (same as the organizer flow).
     */
    private function organizer(?MarketplaceOrganizer $organizer): array
    {
        if (!$organizer) {
            return [];
        }

        $issuer = $organizer->getIssuerData();
        $isPf = ($organizer->person_type ?? null) === 'pf';
        $phone = $this->organizerValue($organizer, 'phone');
        $email = $this->organizerValue($organizer, 'email', 'billing_email');
        $taxId = $isPf ? '' : ($issuer['tax_id'] ?? $this->organizerValue($organizer, 'company_tax_id', 'tax_id', 'cui'));
        $address = $issuer['address'] ?? $this->organizerValue($organizer, 'company_address', 'address');
        $city = $issuer['city'] ?? $this->organizerValue($organizer, 'company_city', 'city');
        $contact = implode(' · ', array_values(array_filter([$phone, $email])));

        if ($isPf) {
            $identity = $contact;
        } else {
            $addressCity = implode(', ', array_values(array_filter([$address, $city])));
            $identity = implode(' / ', array_values(array_filter([
                $taxId !== '' ? 'CUI: ' . $taxId : '',
                $addressCity !== '' ? 'sediu: ' . $addressCity : '',
            ])));
        }

        return [
            'name' => (string) ($organizer->name ?? ($issuer['name'] ?? '')),
            'company_name' => (string) ($issuer['name'] ?? $this->organizerValue($organizer, 'company_name')),
            'tax_id' => (string) $taxId,
            'tax_line' => (!$isPf && $taxId !== '') ? 'CUI: ' . $taxId : '',
            'identity' => $identity,
            'contact' => $contact,
            'company_address' => (string) $address,
            'city' => (string) $city,
            'website' => $this->organizerValue($organizer, 'website'),
            'phone' => $phone,
            'email' => $email,
            'ticket_terms' => $this->organizerValue($organizer, 'ticket_terms'),
        ];
    }

    private function organizerValue(?MarketplaceOrganizer $organizer, string ...$columns): string
    {
        if (!$organizer) {
            return '';
        }
        foreach ($columns as $column) {
            $value = $organizer->{$column} ?? null;
            if (!empty($value)) {
                return is_string($value) ? $value : (string) $value;
            }
        }

        return '';
    }

    /**
     * Translatable column (JSON ro/en or plain text) → ro, then en, then first.
     */
    private function translated(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        if (is_array($value)) {
            $value = $value['ro'] ?? $value['en'] ?? (reset($value) ?: '');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
