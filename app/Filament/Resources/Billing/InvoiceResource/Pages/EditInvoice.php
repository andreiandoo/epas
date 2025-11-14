<?php
namespace App\Filament\Resources\Billing\InvoiceResource\Pages;

use App\Filament\Resources\Billing\InvoiceResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected string $view = 'filament.resources.billing.invoice.pages.edit-invoice';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('invoices.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('send_email')
                ->label('Send Email')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Send Invoice Email')
                ->modalDescription('Send this invoice to the tenant via email with PDF attachment.')
                ->action(function () {
                    try {
                        $tenant = $this->record->tenant;

                        if (!$tenant || !$tenant->email) {
                            $this->notify('danger', 'Cannot send email: Tenant email not found.');
                            return;
                        }

                        // Send email using InvoiceMail
                        \Illuminate\Support\Facades\Mail::to($tenant->email)
                            ->send(new \App\Mail\InvoiceMail($this->record, 'invoice_created'));

                        // Log email
                        \App\Models\EmailLog::create([
                            'recipient_email' => $tenant->email,
                            'recipient_name' => $tenant->name,
                            'subject' => "Invoice {$this->record->number}",
                            'body' => "Manual invoice email sent",
                            'sent_at' => now(),
                            'status' => 'sent',
                            'event_trigger' => 'manual_send',
                        ]);

                        $this->notify('success', "Invoice email sent successfully to {$tenant->email}!");
                    } catch (\Exception $e) {
                        \Log::error("Failed to send invoice email manually: " . $e->getMessage());
                        $this->notify('danger', 'Failed to send email: ' . $e->getMessage());
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    public function getSettings()
    {
        return Setting::current();
    }
}
