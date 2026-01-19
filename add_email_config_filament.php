<?php

$filePath = __DIR__ . '/app/Filament/Resources/Tenants/TenantResource.php';
$content = file_get_contents($filePath);

$emailConfigSection = "            SC\Section::make('Email Configuration')
                ->description('Configure custom SMTP settings for this tenant. Leave empty to use core mail (Brevo).')
                ->schema([
                    Forms\Components\Select::make('settings.mail.driver')
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
                        ->reactive()
                        ->helperText('Select your email service provider'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('settings.mail.host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.gmail.com')
                            ->helperText('Your mail server hostname'),

                        Forms\Components\TextInput::make('settings.mail.port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->default(587)
                            ->helperText('Usually 587 for TLS, 465 for SSL'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('settings.mail.username')
                            ->label('Username / Email')
                            ->email()
                            ->helperText('Your SMTP username or email address'),

                        Forms\Components\TextInput::make('settings.mail.password')
                            ->label('Password / App Password')
                            ->password()
                            ->dehydrateStateUsing(fn (\$state) => filled(\$state) ? encrypt(\$state) : null)
                            ->dehydrated(fn (\$state) => filled(\$state))
                            ->helperText('For Gmail/Outlook, use App Password'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('settings.mail.encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS (Recommended)',
                                'ssl' => 'SSL',
                                null => 'None',
                            ])
                            ->default('tls')
                            ->helperText('Security protocol for mail connection'),

                        Forms\Components\TextInput::make('settings.mail.from_address')
                            ->label('From Email Address')
                            ->email()
                            ->placeholder('noreply@yourdomain.com')
                            ->helperText('Email address shown as sender'),
                    ]),

                    Forms\Components\TextInput::make('settings.mail.from_name')
                        ->label('From Name')
                        ->placeholder('Your Company Name')
                        ->helperText('Display name shown as sender')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),

";

// Find the "Additional Settings" section and insert email config before it
$searchString = "            SC\\Section::make('Additional Settings')";
$replaceWith = $emailConfigSection . $searchString;

$newContent = str_replace($searchString, $replaceWith, $content);

if ($newContent === $content) {
    echo "ERROR: Could not find the insertion point!\n";
    exit(1);
}

file_put_contents($filePath, $newContent);
echo "✅ Successfully added Email Configuration section to TenantResource.php\n";
