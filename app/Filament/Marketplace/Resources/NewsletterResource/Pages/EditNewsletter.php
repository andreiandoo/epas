<?php

namespace App\Filament\Marketplace\Resources\NewsletterResource\Pages;

use App\Filament\Marketplace\Resources\NewsletterResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceNewsletterLinkEvent;
use App\Services\NewsletterRenderer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditNewsletter extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = NewsletterResource::class;

    /**
     * Override the default "Edit Marketplace Newsletter" with a more
     * useful "Edit Newsletter - {campaign name}" so the browser tab +
     * page header identify the specific campaign at a glance.
     */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $name = trim((string) ($this->record->name ?? ''));
        return $name !== '' ? "Edit Newsletter - {$name}" : 'Edit Newsletter';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFormSchema(): array
    {
        return parent::getFormSchema();
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        $r = $this->record;
        if (!$r) return null;

        $sent = (int) ($r->sent_count ?? 0);
        // Raw aggregate counters incremented on every pixel/click hit. These
        // OVERCOUNT relative to humans — Gmail/Yahoo image proxies prefetch
        // the pixel on delivery, mail clients refetch on scroll-back, multi-
        // device opens stack. We keep them as "total hits" for transparency
        // but headline the unique numbers below.
        $openHits = (int) ($r->opened_count ?? 0);
        $clickHits = (int) ($r->clicked_count ?? 0);
        $purchases = (int) ($r->purchase_count ?? 0);
        $revenueCents = (int) ($r->purchase_amount_cents ?? 0);

        // Unique opens/clicks = distinct recipients that hit the pixel or a
        // tracked link. Rows with recipient_id IS NULL come from edge cases
        // (preview, forwarded mail decoded against a different APP_KEY) and
        // can't be deduped; they're omitted so this stays a true lower-bound
        // unique count instead of being inflated by an unbounded NULL bucket.
        $uniqueOpens = (int) MarketplaceNewsletterLinkEvent::where('newsletter_id', $r->id)
            ->where('event_type', MarketplaceNewsletterLinkEvent::TYPE_OPEN)
            ->whereNotNull('recipient_id')
            ->distinct('recipient_id')
            ->count('recipient_id');
        $uniqueClicks = (int) MarketplaceNewsletterLinkEvent::where('newsletter_id', $r->id)
            ->where('event_type', MarketplaceNewsletterLinkEvent::TYPE_CLICK)
            ->whereNotNull('recipient_id')
            ->distinct('recipient_id')
            ->count('recipient_id');

        if ($sent + $openHits + $clickHits + $purchases === 0) return null;

        // Rates are computed from UNIQUE actors / sent so a recipient who
        // opens 20 times doesn't push open_rate over 100%. We cap at 100 as
        // a defensive measure (forwarded mail could occasionally push it
        // above when recipient_id is populated by edge cases).
        $openRate = $sent > 0 ? min(100, round(($uniqueOpens / $sent) * 100, 1)) : 0;
        $clickRate = $sent > 0 ? min(100, round(($uniqueClicks / $sent) * 100, 1)) : 0;
        $ctor = $uniqueOpens > 0 ? min(100, round(($uniqueClicks / $uniqueOpens) * 100, 1)) : 0;
        $convRate = $uniqueClicks > 0 ? min(100, round(($purchases / $uniqueClicks) * 100, 1)) : 0;

        // Raw hits multiplier (e.g. "1242 / 487 = 2.6× hits per unique
        // opener") — quick signal for how aggressive the image proxies are.
        $hitsRatio = $uniqueOpens > 0 ? round($openHits / $uniqueOpens, 1) : null;

        $currency = $r->marketplaceClient?->currency ?? 'RON';
        $revenue = number_format($revenueCents / 100, 2, ',', '.') . ' ' . $currency;

        $cell = fn ($label, $value, $sub = null) => '<div style="flex:1;min-width:130px;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">'
            . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:0.6px;color:#6b7280;">' . e($label) . '</div>'
            . '<div style="font-size:20px;font-weight:700;color:#111827;margin-top:2px;">' . e((string) $value) . '</div>'
            . ($sub ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;">' . e($sub) . '</div>' : '')
            . '</div>';

        $openSub = "{$openRate}% open rate"
            . ($openHits !== $uniqueOpens ? " · {$openHits} hit-uri totale" : '')
            . ($hitsRatio && $hitsRatio > 1 ? " ({$hitsRatio}×/persoană)" : '');
        $clickSub = "{$clickRate}% click rate"
            . ($clickHits !== $uniqueClicks ? " · {$clickHits} click-uri totale" : '');

        $html = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0 0;">'
            . $cell('Trimise', $sent)
            . $cell('Deschideri', $uniqueOpens, $openSub)
            . $cell('Click-uri', $uniqueClicks, $clickSub)
            . $cell('CTOR', "{$ctor}%", 'click ÷ open (unic)')
            . $cell('Cumpărări', $purchases, "{$convRate}% conv. rate")
            . $cell('Venit atribuit', $revenue, 'plătit prin newsletter')
            . '</div>';

        return new HtmlString($html);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Previzualizare')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalContent(function () {
                    $newsletter = $this->record;

                    if (empty($newsletter->body_sections)) {
                        return new HtmlString('<div class="p-4 text-center text-gray-500">Adaugă secțiuni în Email Content pentru a vedea previzualizarea.</div>');
                    }

                    $renderer = new NewsletterRenderer();
                    $html = $renderer->renderPreview($newsletter);

                    return new HtmlString(
                        '<div class="p-4">' .
                            '<div class="mb-3 p-3 bg-gray-100 rounded-lg">' .
                                '<p class="text-sm text-gray-600"><strong>Subject:</strong> ' . e($newsletter->subject ?? '') . '</p>' .
                                '<p class="text-sm text-gray-600"><strong>Secțiuni:</strong> ' . count($newsletter->body_sections) . '</p>' .
                            '</div>' .
                            '<div class="border rounded-lg overflow-hidden">' .
                                '<iframe srcdoc="' . e($html) . '" class="w-full border-0" style="min-height: 500px;" sandbox="allow-same-origin"></iframe>' .
                            '</div>' .
                        '</div>'
                    );
                })
                ->modalHeading('Newsletter Preview')
                ->modalSubmitAction(false)
                ->modalWidth('5xl'),

            Actions\Action::make('sendTest')
                ->label('Trimite test')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('test_email')
                        ->label('Email de test')
                        ->email()
                        ->required()
                        ->placeholder('test@example.com')
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data) {
                    try {
                        $renderer = new NewsletterRenderer();
                        $html = $renderer->renderPreview($this->record);
                        $subject = '[TEST] ' . ($this->record->subject ?: 'Newsletter');
                        \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                            static::getMarketplaceClient(),
                            $data['test_email'],
                            'Test',
                            $subject,
                            $html,
                            ['template_slug' => 'newsletter_test']
                        );
                        Notification::make()->title('Test trimis către ' . $data['test_email'])->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Trimiterea a eșuat')->body($e->getMessage())->danger()->send();
                    }
                }),

            // Duplicate — clone settings only (sections, targeting, sender
            // fields). Stats / lifecycle timestamps are zeroed so the copy
            // is a clean draft. Recipients are a separate table and don't
            // get replicated.
            Actions\Action::make('duplicate')
                ->label('Duplică')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Duplică newsletter')
                ->modalDescription('Setările (subiect, secțiuni, target, expeditor) se copiază. Statisticile nu se copiază.')
                ->modalSubmitActionLabel('Duplică')
                ->action(function () {
                    $new = $this->record->replicate();
                    $new->name = 'DUPLICAT - ' . $this->record->name;
                    $new->status = 'draft';
                    $new->scheduled_at = null;
                    $new->started_at = null;
                    $new->completed_at = null;
                    $new->total_recipients = 0;
                    $new->sent_count = 0;
                    $new->failed_count = 0;
                    $new->opened_count = 0;
                    $new->clicked_count = 0;
                    $new->unsubscribed_count = 0;
                    $new->purchase_count = 0;
                    $new->purchase_amount_cents = 0;
                    $new->created_by = auth()->id();
                    $new->save();

                    Notification::make()->title('Duplicat creat')->success()->send();

                    return redirect(NewsletterResource::getUrl('edit', ['record' => $new]));
                }),

            // Real send — same logic as the list-view recordAction so users
            // don't have to bounce back to the index to ship a draft.
            Actions\Action::make('send')
                ->label('Trimite newsletter')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Trimite newsletter')
                ->modalDescription('Sigur vrei să trimiți acum? Acțiunea nu poate fi anulată.')
                ->modalSubmitActionLabel('Trimite')
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function () {
                    $this->record->createRecipients();
                    $this->record->startSending();
                    \App\Jobs\SendNewsletterJob::dispatch($this->record);
                    Notification::make()
                        ->title('Newsletter pornit')
                        ->body('Destinatari: ' . (int) $this->record->total_recipients)
                        ->success()
                        ->send();
                }),

            Actions\Action::make('schedule')
                ->label('Programează')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Trimite la')
                        ->required()
                        ->minDate(now()),
                ])
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function (array $data) {
                    $this->record->createRecipients();
                    $this->record->schedule(new \DateTime($data['scheduled_at']));
                    Notification::make()->title('Programat')->success()->send();
                }),

            Actions\Action::make('cancel')
                ->label('Anulează programare')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'scheduled')
                ->action(function () {
                    $this->record->cancel();
                    Notification::make()->title('Anulat')->success()->send();
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }

    /**
     * Add Save Draft alongside Save / Cancel — explicitly marks the record
     * as draft regardless of the current status field.
     */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Actions\Action::make('saveDraft')
                ->label('Save draft')
                ->color('gray')
                ->visible(fn () => !in_array($this->record->status, ['sending', 'sent']))
                ->action(function () {
                    $data = $this->form->getState();
                    $data['status'] = 'draft';
                    $this->record->update($data);
                    Notification::make()->title('Draft salvat')->success()->send();
                }),
        ];
    }
}
