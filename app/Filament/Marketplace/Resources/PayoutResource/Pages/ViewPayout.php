<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Models\Invoice;
use App\Models\OrganizerDocument;
use App\Services\EFactura\EFacturaService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        $decont = $this->record->decontDocument;
        $invoice = $this->record->invoice;

        return [
            // ========== STATUS ACTIONS ==========
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeApproved())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->approve($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('process')
                ->label('Mark Processing')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeProcessed())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->markAsProcessing($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('complete')
                ->label('Complete Payout')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeCompleted())
                ->form([
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference')
                        ->required()
                        ->helperText('Bank transfer reference or transaction ID'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    $this->refreshFormData(['status', 'payment_reference', 'payment_notes', 'completed_at']);
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canBeRejected())
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->reject($admin->id, $data['reason']);

                    // Delete associated decont document if exists
                    $decont = $this->record->decontDocument;
                    if ($decont) {
                        if ($decont->file_path) {
                            Storage::disk('public')->delete($decont->file_path);
                        }
                        $decont->delete();
                    }

                    // Delete associated invoice if exists
                    $invoice = $this->record->invoice;
                    if ($invoice) {
                        $invoice->delete();
                    }

                    Notification::make()->title('Decont respins')->body('Documentele asociate au fost șterse.')->success()->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('add_note')
                ->label('Admin Note')
                ->icon('heroicon-o-pencil-square')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->default(fn () => $this->record->admin_notes)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update(['admin_notes' => $data['admin_notes']]);
                    $this->refreshFormData(['admin_notes']);
                }),

            // ========== GENERATE DECONT (when none exists) ==========
            Actions\Action::make('generate_decont')
                ->label('Generează Decont')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Se va genera documentul de decont pentru acest payout.')
                ->visible(fn () => $this->record->decontDocument === null && in_array($this->record->status, ['approved', 'processing', 'completed']) && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $observer = new \App\Observers\MarketplacePayoutObserver();
                    $method = new \ReflectionMethod($observer, 'generateDecont');
                    $method->setAccessible(true);
                    $method->invoke($observer, $this->record);

                    Notification::make()->title('Decont generat')->success()->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            // ========== DECONT ACTIONS (when decont exists) ==========
            Actions\ActionGroup::make([
                Actions\Action::make('view_decont')
                    ->label('Vezi decont')
                    ->icon('heroicon-o-eye')
                    ->url(fn () => OrganizerDocumentResource::getUrl('view', ['record' => $this->record->decontDocument]))
                    ->openUrlInNewTab(),

                Actions\Action::make('download_decont')
                    ->label('Descarcă decont')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => $this->record->decontDocument?->file_path !== null)
                    ->url(fn () => $this->record->decontDocument?->download_url, shouldOpenInNewTab: true),

                Actions\Action::make('send_decont')
                    ->label('Trimite decont')
                    ->icon('heroicon-o-envelope')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Adresa email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                    ])
                    ->action(function (array $data) {
                        $this->sendDocumentByEmail($this->record->decontDocument, $data['email'], 'Decont');
                    }),

                Actions\Action::make('regenerate_decont')
                    ->label('Regenerează decont')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Decontul existent va fi șters și regenerat.')
                    ->action(function () {
                        $existingDecont = $this->record->decontDocument;
                        if ($existingDecont) {
                            if ($existingDecont->file_path) {
                                Storage::disk('public')->delete($existingDecont->file_path);
                            }
                            $existingDecont->delete();
                        }
                        $observer = new \App\Observers\MarketplacePayoutObserver();
                        $method = new \ReflectionMethod($observer, 'generateDecont');
                        $method->setAccessible(true);
                        $method->invoke($observer, $this->record);

                        Notification::make()->title('Decont regenerat')->success()->send();
                        $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                    }),
            ])
                ->label('Decont')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->button()
                ->visible(fn () => $this->record->decontDocument !== null && !in_array($this->record->status, ['rejected', 'cancelled'])),

            // ========== GENERATE INVOICE (when decont exists but no invoice) ==========
            Actions\Action::make('generate_invoice')
                ->label('Generează factură')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Se va genera o factură asociată acestui decont.')
                ->visible(fn () => $this->record->decontDocument !== null && $this->record->invoice === null && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $payout = $this->record;
                    $organizer = $payout->organizer;
                    $marketplace = $payout->marketplaceClient;

                    $lastInvoice = Invoice::where('marketplace_client_id', $marketplace->id)
                        ->orderByDesc('id')
                        ->first();
                    $nextNumber = $lastInvoice ? ((int) preg_replace('/\D/', '', $lastInvoice->number) + 1) : 1;
                    $invoiceNumber = 'F-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

                    $invoice = Invoice::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $organizer->id,
                        'marketplace_payout_id' => $payout->id,
                        'number' => $invoiceNumber,
                        'type' => 'fiscal',
                        'description' => 'Factura pentru decont ' . $payout->reference,
                        'issue_date' => now(),
                        'period_start' => $payout->period_start,
                        'period_end' => $payout->period_end,
                        'due_date' => now()->addDays(30),
                        'subtotal' => $payout->commission_amount ?? 0,
                        'vat_rate' => 19,
                        'vat_amount' => round(($payout->commission_amount ?? 0) * 0.19, 2),
                        'amount' => round(($payout->commission_amount ?? 0) * 1.19, 2),
                        'currency' => $payout->currency ?? 'RON',
                        'status' => 'outstanding',
                        'meta' => [
                            'payout_reference' => $payout->reference,
                            'issuer' => [
                                'name' => $marketplace->company_name ?? $marketplace->name,
                                'cui' => $marketplace->cui ?? '',
                                'address' => $marketplace->address ?? '',
                            ],
                            'client' => [
                                'name' => $organizer->company_name ?? $organizer->name,
                                'cui' => $organizer->cui ?? '',
                                'address' => $organizer->address ?? '',
                            ],
                            'items' => [[
                                'description' => 'Comision servicii ticketing - ' . $payout->reference,
                                'quantity' => 1,
                                'unit_price' => $payout->commission_amount ?? 0,
                                'amount' => $payout->commission_amount ?? 0,
                            ]],
                        ],
                    ]);

                    Notification::make()->title('Factura generata: ' . $invoiceNumber)->success()->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('view_invoice')
                    ->label('Vezi factura')
                    ->icon('heroicon-o-eye')
                    ->visible(fn () => $this->record->invoice !== null)
                    ->url(fn () => OrganizerInvoiceResource::getUrl('edit', ['record' => $this->record->invoice]))
                    ->openUrlInNewTab(),

                Actions\Action::make('download_invoice')
                    ->label('Descarca factura')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => $this->record->invoice !== null)
                    ->url(fn () => $this->record->invoice?->proforma_pdf_url ?? $this->record->invoice?->fiscal_pdf_url, shouldOpenInNewTab: true),

                Actions\Action::make('register_invoice')
                    ->label('Inregistreaza eFactura')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Factura va fi trimisa catre sistemul eFactura ANAF.')
                    ->visible(fn () => $this->record->invoice !== null)
                    ->action(function () {
                        try {
                            $efacturaService = app(EFacturaService::class);
                            $efacturaService->queueMarketplaceInvoice($this->record->invoice);
                            Notification::make()->title('Factura trimisa in eFactura')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Eroare eFactura')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Actions\Action::make('send_invoice')
                    ->label('Trimite factura')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn () => $this->record->invoice !== null)
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Adresa email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                    ])
                    ->action(function (array $data) {
                        $this->sendInvoiceByEmail($this->record->invoice, $data['email']);
                    }),
            ])
                ->label('Factura')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('success')
                ->button()
                ->visible(fn () => $this->record->invoice !== null),
        ];
    }

    /**
     * Send a document (decont) PDF by email.
     */
    protected function sendDocumentByEmail(OrganizerDocument $document, string $email, string $docType): void
    {
        try {
            $filePath = Storage::disk('public')->path($document->file_path);

            if (!file_exists($filePath)) {
                Notification::make()->title('Fisierul nu a fost gasit')->danger()->send();
                return;
            }

            $subject = "{$docType} {$document->title} — {$this->record->reference}";

            Mail::raw("Buna ziua,\n\nAtasat gasiti {$docType} pentru decontul {$this->record->reference}.\n\nCu respect,\n" . ($this->record->marketplaceClient?->name ?? 'Tixello'), function ($message) use ($email, $subject, $filePath, $document) {
                $message->to($email)
                    ->subject($subject)
                    ->attach($filePath, [
                        'as' => $document->file_name,
                        'mime' => 'application/pdf',
                    ]);
            });

            Notification::make()->title("{$docType} trimis la {$email}")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Eroare la trimitere')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Send an invoice by email (HTML content or PDF attachment).
     */
    protected function sendInvoiceByEmail(Invoice $invoice, string $email): void
    {
        try {
            $subject = "Factura #{$invoice->number} — {$this->record->reference}";
            $body = "Buna ziua,\n\nAtasat gasiti factura #{$invoice->number} pentru decontul {$this->record->reference}.\n\nSuma: {$invoice->amount} {$invoice->currency}\nScadenta: " . ($invoice->due_date?->format('d.m.Y') ?? '-') . "\n\nCu respect,\n" . ($this->record->marketplaceClient?->name ?? 'Tixello');

            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            Notification::make()->title("Factura trimisa la {$email}")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Eroare la trimitere')->body($e->getMessage())->danger()->send();
        }
    }
}
