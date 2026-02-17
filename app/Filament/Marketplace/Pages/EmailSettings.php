<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

class EmailSettings extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Email Settings';

    // Hidden: email settings are now managed in Settings > Emails tab
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected string $view = 'filament.marketplace.pages.email-settings';

    public ?array $smtpData = [];
    public ?array $emailData = [];

    public function mount(): void
    {
        $marketplace = static::getMarketplaceClient();

        $this->smtpData = $marketplace->smtp_settings ?? [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
        ];

        $this->emailData = $marketplace->email_settings ?? [
            'from_name' => $marketplace->name,
            'from_email' => $marketplace->contact_email,
            'reply_to' => '',
        ];
    }

    protected function getForms(): array
    {
        return [
            'smtpForm',
            'emailForm',
        ];
    }

    public function smtpForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('SMTP Configuration')
                    ->description('Configure your SMTP server for sending newsletters and transactional emails')
                    ->schema([
                        Forms\Components\TextInput::make('host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.example.com')
                            ->required(),
                        Forms\Components\TextInput::make('port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->default(587)
                            ->required(),
                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(),
                        Forms\Components\Select::make('encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                'none' => 'None',
                            ])
                            ->default('tls')
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('smtpData');
    }

    public function emailForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Default Email Settings')
                    ->description('Default sender information for all outgoing emails')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('From Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('from_email')
                            ->label('From Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply-To Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('If different from the From email'),
                    ])->columns(3),
            ])
            ->statePath('emailData');
    }

    public function saveSmtp(): void
    {
        $data = $this->smtpForm->getState();
        $marketplace = static::getMarketplaceClient();

        $marketplace->update([
            'smtp_settings' => $data,
        ]);

        Notification::make()
            ->title('SMTP Settings Saved')
            ->success()
            ->send();
    }

    public function saveEmail(): void
    {
        $data = $this->emailForm->getState();
        $marketplace = static::getMarketplaceClient();

        $marketplace->update([
            'email_settings' => $data,
        ]);

        Notification::make()
            ->title('Email Settings Saved')
            ->success()
            ->send();
    }

    public function testSmtp(): void
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace->hasSmtpConfigured()) {
            Notification::make()
                ->title('SMTP Not Configured')
                ->body('Please save your SMTP settings first.')
                ->danger()
                ->send();
            return;
        }

        try {
            $transport = $marketplace->getSmtpTransport();

            if (!$transport) {
                throw new \Exception('Failed to create SMTP transport');
            }

            $testEmail = (new Email())
                ->from($marketplace->getEmailFromAddress())
                ->to(auth()->user()->email)
                ->subject('Test Email from ' . $marketplace->name)
                ->html('<p>This is a test email to verify your SMTP configuration is working correctly.</p>');

            $transport->send($testEmail);

            Notification::make()
                ->title('Test Email Sent')
                ->body('A test email has been sent to ' . auth()->user()->email)
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('SMTP Test Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_smtp')
                ->label('Test SMTP')
                ->icon('heroicon-o-paper-airplane')
                ->action('testSmtp')
                ->requiresConfirmation()
                ->modalDescription('Send a test email to your account email address?'),
        ];
    }
}
