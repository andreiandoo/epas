<?php

namespace App\Filament\Marketplace\Resources\EmailTemplateResource\Pages;

use App\Filament\Marketplace\Resources\EmailTemplateResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditEmailTemplate extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_test')
                ->label('Trimite Test')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    Forms\Components\TextInput::make('test_email')
                        ->label('Email destinatar')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email ?? ''),
                ])
                ->action(function (array $data) {
                    $marketplace = static::getMarketplaceClient();
                    $template = $this->record;

                    // Build sample variables
                    $sampleVars = [
                        'customer_name' => 'Ion Popescu',
                        'customer_email' => $data['test_email'],
                        'marketplace_name' => $marketplace->public_name ?? $marketplace->name ?? 'Marketplace',
                        'order_number' => 'TEST-00001',
                        'event_name' => 'Concert Test - Exemplu',
                        'event_date' => now()->addDays(7)->format('d M Y, H:i'),
                        'event_venue' => 'Sala Palatului, București',
                        'venue_name' => 'Sala Palatului',
                        'venue_address' => 'Str. Ion Câmpineanu 28, București',
                        'tickets_count' => '2',
                        'total_amount' => '150.00 RON',
                        'ticket_type' => 'Bilet Standard',
                        'organizer_name' => 'Organizator Test',
                        'payout_amount' => '1,250.00 RON',
                        'payout_reference' => 'PAY-2026-0001',
                        'period' => now()->subDays(7)->format('d.m.Y') . ' - ' . now()->format('d.m.Y'),
                        'refund_amount' => '75.00 RON',
                        'refund_reference' => 'REF-0001',
                        'rejection_reason' => 'Evenimentul a avut loc conform programului.',
                        'login_url' => 'https://' . ($marketplace->domain ?? 'example.com') . '/login',
                        'reset_url' => 'https://' . ($marketplace->domain ?? 'example.com') . '/reset-password?token=test',
                        'admin_url' => 'https://core.tixello.com/marketplace/events/1/edit',
                        'beneficiary_name' => 'Maria Ionescu',
                        'remaining_stock' => '3',
                        'total_sales' => '5,430.00 RON',
                        'commission' => '162.90 RON',
                        'net_amount' => '5,267.10 RON',
                        'orders_count' => '47',
                        'tickets_count' => '123',
                        'points_amount' => '50',
                        'points_balance' => '150',
                    ];

                    $rendered = $template->render($sampleVars);

                    try {
                        $transport = $marketplace->getSmtpTransport();
                        if (!$transport) {
                            Notification::make()
                                ->title('SMTP nu este configurat')
                                ->body('Configurează SMTP-ul în setările marketplace-ului.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $email = (new \Symfony\Component\Mime\Email())
                            ->from(new \Symfony\Component\Mime\Address(
                                $marketplace->getEmailFromAddress(),
                                $marketplace->getEmailFromName()
                            ))
                            ->to($data['test_email'])
                            ->subject('[TEST] ' . $rendered['subject'])
                            ->html($rendered['body_html']);

                        if ($rendered['body_text']) {
                            $email->text($rendered['body_text']);
                        }

                        $transport->send($email);

                        Notification::make()
                            ->title('Email de test trimis!')
                            ->body('Verifică inbox-ul pentru ' . $data['test_email'])
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Eroare la trimitere')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('preview')
                ->label('Previzualizare')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalContent(fn () => view('filament.marketplace.email-preview', ['template' => $this->record]))
                ->modalHeading('Email Preview')
                ->modalSubmitAction(false),
            Actions\DeleteAction::make(),
        ];
    }
}
