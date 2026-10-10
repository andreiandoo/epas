<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\EventDocumentFiling;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceEmailLog;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceTaxRegistry;
use App\Support\MarketplaceTz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Filing an event's fiscal documents with the city hall.
 *
 * Documents are filed in two groups: the cerere de vizare on its own, and
 * the impozit together with the PV de distrugere. How a group is filed
 * comes from the event's tax registry: by email (sent from here, the send
 * itself records the filing) or through a third-party solution (the
 * operator files there, then confirms here).
 */
class EventDocumentFilingService
{
    public const GROUP_CERERE = 'cerere';

    public const GROUP_IMPOZIT_PV = 'impozit_pv';

    public const GROUPS = [
        self::GROUP_CERERE => 'Cerere vizare',
        self::GROUP_IMPOZIT_PV => 'Impozit + PV distrugere',
    ];

    public const TYPE_LABELS = [
        'cerere_avizare' => 'Cerere vizare bilete',
        'declaratie_impozite' => 'Declarație impozit spectacole',
        'pv_distrugere' => 'PV distrugere bilete',
    ];

    /** Email template slug per group (editable under Email Templates). */
    public const TEMPLATE_SLUGS = [
        self::GROUP_CERERE => 'fiscal_cerere_avizare',
        self::GROUP_IMPOZIT_PV => 'fiscal_impozit_pv',
    ];

    protected static ?bool $tableExists = null;

    /** False until the filings migration has run. */
    public static function available(): bool
    {
        return static::$tableExists ??= Schema::hasTable('event_document_filings');
    }

    /**
     * Document types a group needs for this event. A cancelled event has no
     * show tax to declare, only tickets to destroy.
     *
     * @return string[]
     */
    public function requiredTypes(Event $event, string $group): array
    {
        if ($group === self::GROUP_CERERE) {
            return ['cerere_avizare'];
        }

        return $event->is_cancelled ? ['pv_distrugere'] : ['declaratie_impozite', 'pv_distrugere'];
    }

    /**
     * Everything the "Depunere la primărie" block shows for one group.
     */
    public function status(Event $event, string $group): array
    {
        $types = $this->requiredTypes($event, $group);
        $documents = $this->latestDocuments($event->id, $types);
        $filings = $this->currentFilings([$event->id])[$event->id] ?? [];
        $registry = $event->marketplace_tax_registry_id
            ? MarketplaceTaxRegistry::find($event->marketplace_tax_registry_id)
            : null;

        $rows = [];
        $allGenerated = true;
        $allFiled = true;
        $everFiled = false;
        foreach ($types as $type) {
            $doc = $documents[$type] ?? null;
            $filing = $filings[$type] ?? null;
            $filed = $doc && $filing && $filing['filed_at'] >= $doc['generated_at'];
            $allGenerated = $allGenerated && $doc !== null;
            $allFiled = $allFiled && $filed;
            $everFiled = $everFiled || $filing !== null;
            $rows[$type] = [
                'label' => self::TYPE_LABELS[$type],
                'document' => $doc,
                'filing' => $filing,
                'filed' => $filed,
            ];
        }

        $method = $registry?->submission_method;
        $blocked = match (true) {
            $event->marketplaceOrganizer?->marketplace_manages_documents === false => 'Organizatorul își gestionează singur documentele.',
            ! $allGenerated => 'Generează întâi ' . (count($types) > 1 ? 'toate documentele' : 'documentul') . '.',
            ! $registry => 'Evenimentul nu are un registru fiscal asociat.',
            ! $method => 'Metoda de depunere nu e setată pe registrul fiscal.',
            $method === MarketplaceTaxRegistry::SUBMISSION_EMAIL && ! $registry->submissionEmail() => 'Registrul fiscal nu are o adresă de email.',
            default => null,
        };

        return [
            'group' => $group,
            'label' => self::GROUPS[$group],
            'rows' => $rows,
            'registry' => $registry,
            'method' => $method,
            'recipient' => $registry?->submissionEmail(),
            'filed' => $allGenerated && $allFiled,
            'refile' => $everFiled && ! ($allGenerated && $allFiled),
            'blocked' => $blocked,
        ];
    }

    /**
     * Email the group's documents to the city hall and record the filing.
     *
     * @return array{success: bool, message: string}
     */
    public function sendEmail(Event $event, string $group, MarketplaceAdmin $admin): array
    {
        $status = $this->status($event, $group);
        if ($status['blocked']) {
            return ['success' => false, 'message' => $status['blocked']];
        }
        if ($status['method'] !== MarketplaceTaxRegistry::SUBMISSION_EMAIL) {
            return ['success' => false, 'message' => 'La această primărie documentele nu se depun pe email.'];
        }

        $marketplace = $event->marketplaceClient;
        if (! $marketplace?->hasMailConfigured() && ! $marketplace?->hasTransactionalMailConfigured()) {
            return ['success' => false, 'message' => 'Mail-ul nu este configurat. Configurează SMTP/Brevo în Settings > Emails.'];
        }

        $attachments = [];
        foreach ($status['rows'] as $type => $row) {
            $path = $row['document']['file_path'] ? Storage::disk('public')->path($row['document']['file_path']) : null;
            if (! $path || ! file_exists($path)) {
                return ['success' => false, 'message' => 'Fișierul lipsește de pe disc: ' . $row['label'] . '. Regenerează documentul.'];
            }
            $attachments[$type] = [$path, $row['document']['file_name'] ?: ($type . '.pdf')];
        }

        $message = $this->renderEmail($event, $group, $status);
        $fromAddress = $marketplace->getTransactionalEmailFromAddress();
        $fromName = $marketplace->getTransactionalEmailFromName();
        $to = $status['recipient'];
        $organizer = $event->marketplaceOrganizer;

        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to($to)
            ->subject($message['subject'])
            ->html($message['body_html']);
        // The organizer gets a copy of what was filed on their behalf.
        if ($organizer?->email && filter_var($organizer->email, FILTER_VALIDATE_EMAIL) && strcasecmp($organizer->email, $to) !== 0) {
            $email->cc($organizer->email);
        }
        foreach ($attachments as [$path, $name]) {
            $email->attachFromPath($path, $name, 'application/pdf');
        }

        $result = $marketplace->sendTransactionalEmail($email);
        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'message' => 'Trimiterea a eșuat: ' . ($result['error'] ?? 'eroare necunoscută')];
        }

        $log = MarketplaceEmailLog::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_organizer_id' => $organizer?->id,
            'marketplace_event_id' => null,
            'template_slug' => self::TEMPLATE_SLUGS[$group],
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'to_email' => $to,
            'to_name' => $status['registry']->name,
            'subject' => $message['subject'],
            'body_html' => $message['body_html'],
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $result['message_id'] ?? null,
            'metadata' => [
                'transport_used' => $result['transport_used'] ?? null,
                'event_id' => $event->id,
                'filing_group' => $group,
                'attachments' => array_column($attachments, 1),
            ],
        ]);

        $this->record($event, $status, $admin, EventDocumentFiling::METHOD_EMAIL, $to, $log->id);

        return ['success' => true, 'message' => 'Trimis la ' . $to . '.'];
    }

    /**
     * Record that the operator filed the group through the registry's
     * third-party solution.
     *
     * @return array{success: bool, message: string}
     */
    public function confirmThirdParty(Event $event, string $group, MarketplaceAdmin $admin): array
    {
        $status = $this->status($event, $group);
        if ($status['blocked']) {
            return ['success' => false, 'message' => $status['blocked']];
        }
        if ($status['method'] !== MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY) {
            return ['success' => false, 'message' => 'La această primărie documentele se depun pe email.'];
        }

        $this->record($event, $status, $admin, EventDocumentFiling::METHOD_THIRD_PARTY);

        return ['success' => true, 'message' => 'Depunere confirmată.'];
    }

    /**
     * Void the group's current filings (a confirmation made by mistake).
     *
     * @return array{success: bool, message: string}
     */
    public function undo(Event $event, string $group, MarketplaceAdmin $admin): array
    {
        $count = EventDocumentFiling::valid()
            ->where('event_id', $event->id)
            ->whereIn('document_type', $this->requiredTypes($event, $group))
            ->update(['voided_at' => now(), 'voided_by_name' => $admin->name]);

        return $count
            ? ['success' => true, 'message' => 'Depunere anulată.']
            : ['success' => false, 'message' => 'Nu există o depunere de anulat.'];
    }

    /**
     * Latest valid filing per event and document type.
     *
     * @return array<int, array<string, array{filed_at: string, method: string, method_label: string, by: ?string, sent_to: ?string}>>
     */
    public function currentFilings(array $eventIds): array
    {
        if (empty($eventIds) || ! static::available()) {
            return [];
        }

        $out = [];
        $filings = EventDocumentFiling::valid()
            ->whereIn('event_id', $eventIds)
            ->orderBy('filed_at')
            ->orderBy('id')
            ->get();
        foreach ($filings as $filing) {
            // Ordered oldest first, so the last one written wins.
            $out[$filing->event_id][$filing->document_type] = [
                'filed_at' => $filing->filed_at->format('Y-m-d H:i:s'),
                'method' => $filing->method,
                'method_label' => $filing->methodLabel(),
                'by' => $filing->filed_by_name,
                'sent_to' => $filing->sent_to,
            ];
        }

        return $out;
    }

    /**
     * Newest generated document per type. Admin-generated ones live in
     * event_generated_documents, organizer-generated ones only in
     * organizer_documents.
     *
     * @return array<string, array{generated_at: string, file_path: ?string, file_name: ?string}>
     */
    public function latestDocuments(int $eventId, array $types): array
    {
        $rows = DB::table('event_generated_documents as d')
            ->join('marketplace_tax_templates as t', 't.id', '=', 'd.marketplace_tax_template_id')
            ->where('d.event_id', $eventId)
            ->whereIn('t.type', $types)
            ->select('t.type as type', 'd.created_at as generated_at', 'd.file_path', 'd.filename as file_name')
            ->get()
            ->concat(
                DB::table('organizer_documents')
                    ->where('event_id', $eventId)
                    ->whereIn('document_type', $types)
                    ->select('document_type as type', 'created_at as generated_at', 'file_path', 'file_name')
                    ->get()
            );

        $out = [];
        foreach ($rows as $row) {
            $generatedAt = (string) $row->generated_at;
            if (! isset($out[$row->type]) || $generatedAt > $out[$row->type]['generated_at']) {
                $out[$row->type] = [
                    'generated_at' => $generatedAt,
                    'file_path' => $row->file_path,
                    'file_name' => $row->file_name,
                ];
            }
        }

        return $out;
    }

    /**
     * Subject and body of the email to the city hall: the marketplace's own
     * template when one is active, the built-in text otherwise.
     *
     * @return array{subject: string, body_html: string}
     */
    public function renderEmail(Event $event, string $group, array $status): array
    {
        $marketplace = $event->marketplaceClient;
        $organizer = $event->marketplaceOrganizer;
        $venue = $event->venue;
        $text = fn ($v) => is_array($v) ? ($v['ro'] ?? $v['en'] ?? (reset($v) ?: '')) : (string) ($v ?? '');
        $date = ($event->is_postponed && $event->postponed_date) ? $event->postponed_date : $event->start_date;
        $documents = implode(', ', array_column($status['rows'], 'label'));

        $data = [
            'event_name' => $text($event->title),
            'event_date' => $date ? $date->format('d.m.Y') : '',
            'venue_name' => $text($venue?->name),
            'venue_city' => (string) ($venue?->city ?? ''),
            'organizer_name' => (string) ($organizer?->company_name ?: $organizer?->name),
            'organizer_tax_id' => (string) ($organizer?->company_tax_id ?? ''),
            'registry_name' => (string) $status['registry']?->name,
            'documents_list' => $documents,
            'marketplace_name' => (string) ($marketplace?->public_name ?? $marketplace?->name),
        ];

        $template = MarketplaceEmailTemplate::query()
            ->where('marketplace_client_id', $event->marketplace_client_id)
            ->where('slug', self::TEMPLATE_SLUGS[$group])
            ->where('is_active', true)
            ->first();
        if ($template) {
            $rendered = $template->render(array_map('e', $data));

            return ['subject' => html_entity_decode($rendered['subject'], ENT_QUOTES), 'body_html' => $rendered['body_html']];
        }

        $d = array_map('e', $data);
        $location = implode(', ', array_filter([$d['venue_name'], $d['venue_city']]));

        return [
            'subject' => self::GROUPS[$group] . ' - ' . $data['event_name'] . ' ' . $data['event_date'],
            'body_html' => '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">'
                . '<p>Bună ziua,</p>'
                . '<p>Vă transmitem atașat: <strong>' . e($documents) . '</strong>.</p>'
                . '<table style="border-collapse:collapse;margin:16px 0;font-size:13px;">'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Eveniment:</td><td style="padding:4px 0;font-weight:bold;">' . $d['event_name'] . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Data:</td><td style="padding:4px 0;">' . $d['event_date'] . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Locație:</td><td style="padding:4px 0;">' . $location . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Organizator:</td><td style="padding:4px 0;">' . $d['organizer_name'] . ($d['organizer_tax_id'] ? ' (' . $d['organizer_tax_id'] . ')' : '') . '</td></tr>'
                . '</table>'
                . '<p>Vă rugăm să ne confirmați primirea.</p>'
                . '<p>Cu respect,<br><strong>' . $d['marketplace_name'] . '</strong></p>'
                . '</div>',
        ];
    }

    protected function record(Event $event, array $status, MarketplaceAdmin $admin, string $method, ?string $sentTo = null, ?int $emailLogId = null): void
    {
        $now = now();
        foreach ($status['rows'] as $type => $row) {
            EventDocumentFiling::create([
                'marketplace_client_id' => $event->marketplace_client_id,
                'event_id' => $event->id,
                'marketplace_tax_registry_id' => $status['registry']->id,
                'document_type' => $type,
                'document_generated_at' => $row['document']['generated_at'],
                'file_name' => $row['document']['file_name'],
                'method' => $method,
                'sent_to' => $sentTo,
                'third_party_name' => $method === EventDocumentFiling::METHOD_THIRD_PARTY ? $status['registry']->third_party_name : null,
                'email_log_id' => $emailLogId,
                'filed_by_id' => $admin->id,
                'filed_by_name' => $admin->name,
                'filed_at' => $now,
            ]);
        }
    }

    /** "12 oct, 14:05" in the marketplace timezone, from a UTC timestamp string. */
    public static function stamp(?string $utc, ?Event $event = null): string
    {
        return MarketplaceTz::fmt($utc ? \Carbon\Carbon::parse($utc, 'UTC') : null, 'd.m.Y H:i', $event?->marketplaceClient);
    }
}
