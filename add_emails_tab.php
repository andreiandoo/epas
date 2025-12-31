<?php

$filePath = __DIR__ . '/app/Filament/Tenant/Pages/Settings.php';
$content = file_get_contents($filePath);

$emailsTab = "
                        SC\\Tabs\\Tab::make('Emails')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                SC\\Section::make('Email Configuration')
                                    ->description('Configure custom SMTP settings for sending emails. Leave empty to use core mail (Brevo).')
                                    ->schema([
                                        Forms\\Components\\Select::make('mail_driver')
                                            ->label('Mail Provider')
                                            ->options([
                                                'smtp' => 'SMTP (Generic)',
                                                'gmail' => 'Gmail',
                                                'outlook' => 'Microsoft 365 / Outlook',
                                                'mailgun' => 'Mailgun',
                                                'ses' => 'Amazon SES',
                                                'postmark' => 'Postmark',
                                                'sendgrid' => 'SendGrid',
                                            ])
                                            ->default('smtp')
                                            ->placeholder('Select mail provider')
                                            ->helperText('Select your email service provider'),

                                        Forms\\Components\\TextInput::make('mail_host')
                                            ->label('SMTP Host')
                                            ->placeholder('smtp.gmail.com')
                                            ->maxLength(255)
                                            ->helperText('Your mail server hostname'),

                                        Forms\\Components\\TextInput::make('mail_port')
                                            ->label('SMTP Port')
                                            ->numeric()
                                            ->default(587)
                                            ->placeholder('587')
                                            ->helperText('Usually 587 for TLS, 465 for SSL'),

                                        Forms\\Components\\TextInput::make('mail_username')
                                            ->label('Username / Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('your-email@example.com')
                                            ->helperText('Your SMTP username or email address'),

                                        Forms\\Components\\TextInput::make('mail_password')
                                            ->label('Password / App Password')
                                            ->password()
                                            ->maxLength(255)
                                            ->placeholder('••••••••')
                                            ->helperText('For Gmail/Outlook, use App Password. Leave empty to keep existing password.')
                                            ->dehydrated(fn (\$state) => filled(\$state)),

                                        Forms\\Components\\Select::make('mail_encryption')
                                            ->label('Encryption')
                                            ->options([
                                                'tls' => 'TLS (Recommended)',
                                                'ssl' => 'SSL',
                                                null => 'None',
                                            ])
                                            ->default('tls')
                                            ->placeholder('Select encryption')
                                            ->helperText('Security protocol for mail connection'),

                                        Forms\\Components\\TextInput::make('mail_from_address')
                                            ->label('From Email Address')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('noreply@yourdomain.com')
                                            ->helperText('Email address shown as sender'),

                                        Forms\\Components\\TextInput::make('mail_from_name')
                                            ->label('From Name')
                                            ->maxLength(255)
                                            ->placeholder('Your Company Name')
                                            ->helperText('Display name shown as sender'),
                                    ])->columns(2),
                            ]),
";

// Insert before "Payment Processor" tab
$searchString = "                        SC\\Tabs\\Tab::make('Payment Processor')";
$replaceWith = $emailsTab . $searchString;

$newContent = str_replace($searchString, $replaceWith, $content);

if ($newContent === $content) {
    echo "ERROR: Could not find the insertion point!\n";
    exit(1);
}

file_put_contents($filePath, $newContent);
echo "✅ Successfully added Emails tab to Settings.php\n";
