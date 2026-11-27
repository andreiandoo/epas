<?php

namespace App\Filament\Marketplace\Resources\TicketResource\Pages;

use App\Filament\Marketplace\Resources\TicketResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Mail\TicketEmail;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address as SymfonyAddress;

class ViewTicket extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    use HasMarketplaceContext;

    protected static string $resource = TicketResource::class;

    // Marketplace-only layout (admin/tenant keep filament.tickets.pages.view-ticket).
    protected string $view = 'filament.marketplace.tickets.view-ticket';

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'ep-tk-page'];
    }

    public function getBreadcrumbs(): array
    {
        return [
            \App\Filament\Marketplace\Resources\TicketResource::getUrl('index') => __('Bilete'),
            (string) ($this->record->code ?? $this->record->id),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Bilet') . ' ' . ($this->record->code ?? $this->record->id);
    }

    /** Ticket code + state pills (check-in wins over plain "Valid", as before). */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $t = $this->record;
        [$label, $tone] = match (true) {
            $t->checked_in_at && ! in_array($t->status, ['cancelled', 'void', 'refunded'], true) => [__('Check-in efectuat'), 'info'],
            $t->status === 'valid' => [__('Valid'), 'ok'],
            $t->status === 'used' => [__('Utilizat'), 'info'],
            in_array($t->status, ['cancelled', 'void'], true) => [__('Anulat'), 'bad'],
            $t->status === 'refunded' => [__('Rambursat'), 'bad'],
            default => [ucfirst((string) $t->status), 'mute'],
        };
        $hasInsurance = (bool) ($t->meta['has_insurance'] ?? false);
        $insuranceAmount = $t->meta['insurance_amount'] ?? null;
        $isRefundable = (bool) ($t->marketplaceTicketType?->is_refundable ?? $t->ticketType?->is_refundable ?? false);
        $second = $hasInsurance
            ? [__('Asigurat') . ($insuranceAmount ? ' ' . number_format((float) $insuranceAmount, 2, ',', '.') . ' ' . ($t->order?->currency ?? 'RON') : ''), 'ok']
            : ($isRefundable ? [__('Returnabil'), 'warn'] : [__('Nereturnabil'), 'mute']);

        return new \Illuminate\Support\HtmlString(
            '<span class="ep-tk-code">' . e($t->code) . '</span>'
            . ' <span class="ep-tk-pill ep-tk-pill--' . $tone . '"><i></i>' . e($label) . '</span>'
            . ' <span class="ep-tk-pill ep-tk-pill--' . $second[1] . '">' . e($second[0]) . '</span>'
        );
    }

    /** One line: type | seat | price | event | date. */
    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $t = $this->record;
        $event = $t->ticketType?->event;
        $parts = [];
        $parts[] = e($t->marketplaceTicketType?->name ?? $t->ticketType?->name ?? '');
        $seat = static::seatHuman($t);
        if ($seat !== '') {
            $parts[] = e($seat);
        }
        $currency = $t->order?->currency ?? $t->ticketType?->currency ?? 'RON';
        $price = $t->getEffectivePrice() ?: (($t->ticketType?->price_cents ?? 0) / 100);
        $parts[] = e(number_format((float) $price, 2, ',', '.') . ' ' . $currency);
        if ($event) {
            $title = $event->getTranslation('title', app()->getLocale()) ?: $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en');
            $url = \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $event]);
            $parts[] = '<a href="' . e($url) . '">' . e((string) $title) . '</a>';
            $when = trim(($event->event_date?->format('d.m.Y') ?? '') . ($event->start_time ? ', ' . substr((string) $event->start_time, 0, 5) : ''));
            if ($when !== '') {
                $parts[] = e($when);
            }
        }

        return new \Illuminate\Support\HtmlString('<span class="ep-tk-meta">' . implode(' <span class="ep-tk-sep">|</span> ', array_filter($parts)) . '</span>');
    }

    /** "PARTER DR, rând 14, loc 6" (or the raw seat label when nothing else is known). */
    public static function seatHuman(\App\Models\Ticket $t): string
    {
        $d = $t->getSeatDetails() ?? [];
        $bits = array_filter([
            $d['section_name'] ?? null,
            ! empty($d['row_label']) ? __('rând') . ' ' . $d['row_label'] : null,
            ! empty($d['seat_number']) ? __('loc') . ' ' . $d['seat_number'] : null,
        ]);

        return $bits ? implode(', ', $bits) : (string) ($t->seat_label ?? '');
    }

    protected function hasInfolist(): bool
    {
        // We use a custom blade view instead of infolist
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label(__('Descarcă PDF'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $ticket = $this->record;
                    $event = $ticket->resolveEvent();

                    // Resolve the best template: event → marketplace client default → none
                    $template = $this->resolveTicketTemplate($ticket, $event);

                    Log::channel('marketplace')->info('Ticket download', [
                        'ticket_id' => $ticket->id,
                        'event_id' => $event?->id,
                        'ticket_type_id' => $ticket->ticket_type_id,
                        'event_id_column' => $ticket->event_id,
                        'template_id' => $template?->id,
                        'template_name' => $template?->name,
                        'template_status' => $template?->status,
                        'has_template_data' => !empty($template?->template_data),
                        'layers_count' => count($template?->template_data['layers'] ?? []),
                        'using_custom' => $template && !empty($template->template_data) && $template->status === 'active',
                    ]);

                    if ($template && !empty($template->template_data) && $template->status === 'active') {
                        return $this->downloadCustomTemplate($ticket, $template);
                    }

                    // Fallback to generic PDF template
                    return $this->downloadGenericTemplate($ticket, $event);
                }),

            Actions\Action::make('email')
                ->label(__('Trimite pe email'))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\Radio::make('recipient_type')
                        ->label(__('Send to'))
                        ->options([
                            'customer' => __('Customer Email'),
                            'custom' => __('Custom Email'),
                        ])
                        ->default('customer')
                        ->live()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('custom_email')
                        ->label(__('Email Address'))
                        ->email()
                        ->required()
                        ->visible(fn ($get) => $get('recipient_type') === 'custom'),
                ])
                ->action(function (array $data) {
                    $ticket = $this->record;

                    $email = $data['recipient_type'] === 'customer'
                        ? $ticket->order?->customer_email
                        : $data['custom_email'];

                    if (!$email) {
                        Notification::make()
                            ->title(__('Adresă email lipsă'))
                            ->body(__('Nu există o adresă de email asociată acestui bilet.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    // Get marketplace client for mail transport.
                    // Retrimiterea biletelor este tranzacțională → providerul tranzacțional
                    // cu fallback runtime la primary dacă SMTP-ul tranzacțional eșuează.
                    $marketplaceClient = static::getMarketplaceClient();

                    if (!$marketplaceClient?->hasMailConfigured() && !$marketplaceClient?->hasTransactionalMailConfigured()) {
                        Notification::make()
                            ->title(__('Email neconfigurat'))
                            ->body(__('Configurați SMTP-ul în setările marketplace-ului pentru a trimite emailuri.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    $fromAddress = $marketplaceClient->getTransactionalEmailFromAddress();
                    $fromName = $marketplaceClient->getTransactionalEmailFromName();

                    Log::channel('marketplace')->info('Ticket email: starting send', [
                        'ticket_id' => $ticket->id,
                        'ticket_code' => $ticket->code,
                        'to' => $email,
                    ]);

                    try {
                        // Build ticket email content
                        $ticketMail = new TicketEmail($ticket, static::getMarketplaceClientId());

                        // Render email HTML body
                        $emailBody = view('emails.ticket', [
                            'ticket' => $ticket,
                            'eventTitle' => $ticketMail->eventTitle,
                            'ticketTypeName' => $ticketMail->ticketTypeName,
                            'venueName' => $ticketMail->venueName,
                            'event' => $ticketMail->resolvedEvent,
                        ])->render();

                        // Generate PDF attachment
                        $pdfData = $ticketMail->generatePdfData();

                        $symfonyEmail = (new SymfonyEmail())
                            ->from(new SymfonyAddress($fromAddress, $fromName))
                            ->to($email)
                            ->subject("Biletul tău pentru {$ticketMail->eventTitle}")
                            ->html($emailBody)
                            ->attach($pdfData, "ticket-{$ticket->code}.pdf", 'application/pdf');

                        // sendTransactionalEmail() încearcă tranzacțional → primary
                        $result = $marketplaceClient->sendTransactionalEmail($symfonyEmail);

                        if (!$result['success']) {
                            Log::channel('marketplace')->error('Ticket email: both transports failed', [
                                'ticket_id' => $ticket->id,
                                'to' => $email,
                                'error' => $result['error'],
                            ]);
                            Notification::make()
                                ->title(__('Eroare trimitere email'))
                                ->body('Nu s-a putut trimite emailul: ' . ($result['error'] ?? 'unknown'))
                                ->danger()
                                ->send();
                            return;
                        }

                        Log::channel('marketplace')->info('Ticket email: sent successfully', [
                            'ticket_id' => $ticket->id,
                            'to' => $email,
                            'from' => $fromAddress,
                            'transport_used' => $result['transport_used'],
                        ]);

                        $suffix = $result['transport_used'] === 'primary_fallback' ? ' (via Brevo fallback)' : '';
                        Notification::make()
                            ->title(__('Bilet trimis!'))
                            ->body("Biletul a fost trimis la {$email}{$suffix}")
                            ->success()
                            ->send();

                        activity('tenant')
                            ->performedOn($ticket)
                            ->withProperties([
                                'sent_to' => $email,
                                'ticket_code' => $ticket->code,
                            ])
                            ->log('Ticket emailed');

                    } catch (\Throwable $e) {
                        Log::channel('marketplace')->error('Ticket email: failed', [
                            'ticket_id' => $ticket->id,
                            'to' => $email,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title(__('Eroare trimitere email'))
                            ->body('Nu s-a putut trimite emailul: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Rarely used: status change and undoing a check-in share one menu.
            Actions\ActionGroup::make([
            Actions\Action::make('change_status')
                ->label(__('Schimbă status'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label(__('Status nou'))
                        ->options([
                            'valid' => __('Valid'),
                            'used' => __('Folosit'),
                            'cancelled' => __('Anulat'),
                            'pending' => __('În așteptare'),
                        ])
                        ->default(fn () => $this->record->status)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ticket = $this->record;
                    $oldStatus = $ticket->status;
                    $newStatus = $data['status'];
                    $hadCheckIn = $ticket->checked_in_at !== null || $ticket->scanned_at !== null;

                    // A ticket scanned at the gate keeps status='valid' and only
                    // gets checked_in_at, so "already valid + checked in" is a
                    // real change to make, not a no-op. Bailing out here is what
                    // made it impossible to undo an accidental check-in.
                    if ($oldStatus === $newStatus && ! ($newStatus === 'valid' && $hadCheckIn)) {
                        Notification::make()->title(__('Statusul nu s-a schimbat'))->info()->send();
                        return;
                    }

                    $ticket->update([
                        'status' => $newStatus,
                    ] + ($newStatus === 'valid' ? self::clearedCheckInAttributes() : []));

                    $clearedCheckIn = $newStatus === 'valid' && $hadCheckIn;

                    activity('tenant')
                        ->performedOn($ticket)
                        ->withProperties([
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'ticket_code' => $ticket->code ?? $ticket->barcode,
                            'check_in_cleared' => $clearedCheckIn,
                        ])
                        ->log("Ticket status changed: {$oldStatus} → {$newStatus}");

                    Notification::make()
                        ->title(__('Status actualizat'))
                        ->body($clearedCheckIn
                            ? "Biletul este {$newStatus} și check-in-ul a fost anulat."
                            : "Biletul a fost schimbat din {$oldStatus} în {$newStatus}")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading(__('Schimbă statusul biletului'))
                ->modalDescription(__('Ești sigur că vrei să schimbi statusul acestui bilet?')),

            // Check-in lives on its own axis (checked_in_at / scanned_at), not
            // on `status` — a scanned ticket stays status='valid'. So undoing an
            // accidental gate scan needs its own action rather than a fake
            // "Nevalidat" entry in the status dropdown, which would allow
            // impossible combinations like "not checked in + cancelled".
            Actions\Action::make('undo_checkin')
                ->label(__('Anulează check-in'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->checked_in_at !== null || $this->record->scanned_at !== null)
                ->requiresConfirmation()
                ->modalHeading(__('Anulează check-in-ul biletului'))
                ->modalDescription(__('Biletul redevine nevalidat și poate fi scanat din nou la intrare. Folosește asta când un organizator a validat un bilet din greșeală.'))
                ->action(function () {
                    $ticket = $this->record;
                    $checkedInAt = $ticket->checked_in_at;
                    $oldStatus = $ticket->status;

                    // The operator panel flips status to 'used' when it scans,
                    // so undoing that scan has to walk the status back too.
                    $attributes = self::clearedCheckInAttributes();
                    if ($oldStatus === 'used') {
                        $attributes['status'] = 'valid';
                    }

                    $ticket->update($attributes);

                    activity('tenant')
                        ->performedOn($ticket)
                        ->withProperties([
                            'ticket_code' => $ticket->code ?? $ticket->barcode,
                            'checked_in_at' => $checkedInAt?->toDateTimeString(),
                            'old_status' => $oldStatus,
                            'new_status' => $ticket->status,
                        ])
                        ->log('Ticket check-in undone');

                    Notification::make()
                        ->title(__('Check-in anulat'))
                        ->body(__('Biletul este din nou nevalidat și poate fi scanat la intrare.'))
                        ->success()
                        ->send();
                }),
            ])
                ->label(__('Mai multe'))
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(\Filament\Support\Enums\IconPosition::After)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Every column that marks a ticket as having passed the gate.
     *
     * Two independent flows write check-in state: the organizer scan apps set
     * checked_in_at/by/via, while App\Filament\Operator\Pages\CheckIn sets
     * scanned_at/scanned_by_user_id. Clearing only one set leaves the ticket
     * looking un-scanned in one panel and scanned in the other.
     */
    protected static function clearedCheckInAttributes(): array
    {
        return [
            'checked_in_at' => null,
            'checked_in_by' => null,
            'checked_in_via' => null,
            'scanned_at' => null,
            'scanned_by_user_id' => null,
        ];
    }

    /**
     * Resolve the best ticket template for a ticket.
     * Priority: event's assigned template → marketplace client's default template
     * Only returns templates with visible layers.
     */
    protected function resolveTicketTemplate(Ticket $ticket, ?Event $event): ?TicketTemplate
    {
        // 1. Try the event's directly assigned template
        $template = $event?->ticketTemplate;
        if ($template && $this->isTemplateUsable($template)) {
            return $template;
        }

        // 2. Fall back to marketplace client's default active template
        $clientId = $ticket->marketplace_client_id
            ?? $event?->marketplace_client_id
            ?? $ticket->order?->marketplace_client_id
            ?? static::getMarketplaceClientId();

        if ($clientId) {
            $defaultTemplate = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

            if ($defaultTemplate && $this->isTemplateUsable($defaultTemplate)) {
                return $defaultTemplate;
            }

            // 3. Try any active template with layers for this marketplace client
            $anyTemplate = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->first(fn ($t) => $this->isTemplateUsable($t));

            if ($anyTemplate) {
                return $anyTemplate;
            }
        }

        Log::channel('marketplace')->warning('Template resolution: no usable template found', [
            'ticket_id' => $ticket->id,
            'event_template_id' => $event?->ticket_template_id,
            'resolved_client_id' => $clientId ?? null,
        ]);

        return null;
    }

    /**
     * Check if a template is usable (active, has template_data with visible layers)
     */
    protected function isTemplateUsable(?TicketTemplate $template): bool
    {
        if (!$template || $template->status !== 'active' || empty($template->template_data)) {
            return false;
        }

        $layers = $template->template_data['layers'] ?? [];
        if (empty($layers)) {
            return false;
        }

        // Check for at least one visible layer
        $visibleLayers = array_filter($layers, fn($l) => !isset($l['visible']) || $l['visible'] !== false);
        return !empty($visibleLayers);
    }

    /**
     * Download ticket using a custom TicketTemplate (HTML-based PDF)
     */
    protected function downloadCustomTemplate(Ticket $ticket, TicketTemplate $template)
    {
        try {
            $variableService = app(TicketVariableService::class);
            $generator = app(TicketPreviewGenerator::class);

            $locale = $variableService->resolveOrderLocale($ticket);
            $data = $variableService->resolveTicketData($ticket, $locale);
            $content = $generator->renderToHtml($template->template_data, $data, $locale);

            // If rendered content is empty/whitespace, fall back to generic
            if (empty(trim($content))) {
                Log::channel('marketplace')->warning('Ticket custom PDF: renderToHtml produced empty content, falling back to generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                ]);
                $event = $ticket->resolveEvent();
                return $this->downloadGenericTemplate($ticket, $event);
            }

            $size = $template->getSize();
            $widthPt = round($size['width'] * 2.8346, 2);
            $heightPt = round($size['height'] * 2.8346, 2);
            $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

            // Seating-map second page — fully gated. When the gate fails
            // (default everywhere) $seatingPageHtml stays empty and the
            // HTML below is byte-identical to the original ticket PDF.
            $seatingPageHtml = \App\Support\SeatingPdfInjector::renderPageFor($ticket, $widthPt, $heightPt);

            // Multi-page opt-in (template_data.page_2.enabled). Cand e activ,
            // randam pagina 2 (verso) folosind template_data.page_2 ca template
            // separat. Pagina 2 imparte @page size cu pagina 1 (DomPDF nu suporta
            // dimensiuni diferite per pagina) — organizatorul isi seteaza dimensiuni
            // egale pentru cele 2 pagini in editor.
            $page2Html = '';
            $page2Data = $template->template_data['page_2'] ?? null;
            if (is_array($page2Data) && ($page2Data['enabled'] ?? false) && !empty($page2Data['layers'] ?? [])) {
                try {
                    $page2Content = $generator->renderToHtml($page2Data, $data, $locale);
                    if (!empty(trim($page2Content))) {
                        $page2Bg = $page2Data['meta']['background']['color'] ?? $bgColor;
                        $page2Html = '<div class="ep-ticket-page" style="page-break-before: always; background-color: ' . $page2Bg . ';">' . $page2Content . '</div>';
                    }
                } catch (\Throwable $e) {
                    Log::channel('marketplace')->warning('Ticket PDF page_2 render failed', [
                        'ticket_id' => $ticket->id,
                        'template_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Combina cele 2 posibile pagini suplimentare intr-un singur string.
            // Ordinea: seating page (daca exista) -> page_2 verso (daca exista).
            $extraPagesHtml = $seatingPageHtml . $page2Html;

            if ($extraPagesHtml === '') {
                // Original single-page HTML — preserved byte-for-byte so
                // every live ticket renders identically.
                $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; size: {$widthPt}pt {$heightPt}pt; }
        * { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; width: {$widthPt}pt; height: {$heightPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }
    </style>
</head>
<body>
{$content}
</body>
</html>
HTML;
            } else {
                // Multi-page variant: ticket content lives inside a fixed-
                // size div (preserving its in-template positioning) so the
                // body can flow to pagini suplimentare. body's fixed height +
                // overflow:hidden would suppress page-break-before, so they
                // move onto .ep-ticket-page instead.
                $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; size: {$widthPt}pt {$heightPt}pt; }
        * { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; }
        .ep-ticket-page { width: {$widthPt}pt; height: {$heightPt}pt; overflow: hidden; position: relative; }
    </style>
</head>
<body>
<div class="ep-ticket-page">{$content}</div>
{$extraPagesHtml}
</body>
</html>
HTML;
            }

            Log::channel('marketplace')->info('Ticket PDF HTML generated', [
                'ticket_id' => $ticket->id,
                'template_id' => $template->id,
                'paper_pt' => [$widthPt, $heightPt],
                'html_length' => strlen($html),
                'content_length' => strlen($content),
            ]);

            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt])
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true);

            $pdfOutput = $pdf->output();

            if (empty($pdfOutput)) {
                Log::channel('marketplace')->error('Ticket custom PDF: DomPDF produced empty output, falling back to generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                ]);
                $event = $ticket->resolveEvent();
                return $this->downloadGenericTemplate($ticket, $event);
            }

            $template->markAsUsed();

            return response()->streamDownload(
                fn () => print($pdfOutput),
                "ticket-{$ticket->code}.pdf"
            );
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('Ticket custom PDF: exception, falling back to generic', [
                'ticket_id' => $ticket->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $event = $ticket->resolveEvent();
            return $this->downloadGenericTemplate($ticket, $event);
        }
    }

    /**
     * Download ticket using the generic PDF template (fallback)
     */
    protected function downloadGenericTemplate(Ticket $ticket, ?Event $event)
    {
        $venue = $event?->venue;
        $marketplace = $ticket->order?->tenant ?? $ticket->order?->marketplaceClient;

        $eventTitle = is_array($event?->title)
            ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
            : ($event?->title ?? 'Event');

        Log::channel('marketplace')->debug('Ticket generic PDF: generating', [
            'ticket_id' => $ticket->id,
            'event_id' => $event?->id,
            'event_title' => $eventTitle,
            'has_venue' => !is_null($venue),
            'has_marketplace' => !is_null($marketplace),
        ]);

        $venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;

        // Generate QR code as base64 data URI
        $qrCodeDataUri = $this->generateQrCodeDataUri($ticket->getVerifyUrl());

        $pdf = Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'event' => $event,
            'eventTitle' => $eventTitle,
            'venue' => $venue,
            'venueName' => $venueName,
            'beneficiary' => $ticket->meta['beneficiary'] ?? null,
            'tenant' => $marketplace,
            'ticketTerms' => $marketplace?->ticket_terms ?? null,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "ticket-{$ticket->code}.pdf"
        );
    }

    /**
     * Generate a QR code as a base64 data URI
     */
    protected function generateQrCodeDataUri(string $data): string
    {
        try {
            // Fetch QR code from API and convert to base64
            $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                'size' => '180x180',
                'data' => $data,
                'color' => '181622',
                'margin' => '0',
                'format' => 'png',
            ]);

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);

            if ($imageData !== false) {
                return 'data:image/png;base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            // Fallback to a simple placeholder if QR generation fails
        }

        // Return a simple SVG placeholder if external API fails
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">
            <rect width="180" height="180" fill="#f3f4f6"/>
            <text x="90" y="90" text-anchor="middle" font-family="monospace" font-size="12" fill="#6b7280">QR: ' . htmlspecialchars(substr($data, 0, 10)) . '</text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
