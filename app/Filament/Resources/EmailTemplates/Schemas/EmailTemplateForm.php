<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Template Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable name for this template'),

                        Select::make('event_trigger')
                            ->label('Event Trigger')
                            ->options(self::getEventTriggers())
                            ->searchable()
                            ->required()
                            ->helperText('Platform action that triggers this email'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Internal notes about when this template is used'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active templates will be used'),
                    ])->columns(2),

                SC\Section::make('Email Content')
                    ->description('Use {{variable_name}} syntax to insert dynamic content')
                    ->schema([
                        Placeholder::make('variables_helper')
                            ->label('Available Variables')
                            ->content(view('filament.forms.components.variables-selector'))
                            ->columnSpanFull(),

                        TextInput::make('subject')
                            ->label('Email Subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Welcome {{first_name}} to {{public_name}}!')
                            ->helperText('Use {{variable_name}} to insert variables'),

                        Toggle::make('html_mode')
                            ->label('HTML Source Mode')
                            ->default(false)
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Toggle to switch between visual editor and raw HTML'),

                        RichEditor::make('body')
                            ->label('Email Body (Visual)')
                            ->required(fn ($get) => !$get('html_mode'))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                            ])
                            ->columnSpanFull()
                            ->helperText('Use {{variable_name}} placeholders for dynamic content')
                            ->hidden(fn ($get) => $get('html_mode')),

                        Textarea::make('body_html')
                            ->label('Email Body (HTML Source)')
                            ->required(fn ($get) => $get('html_mode'))
                            ->rows(20)
                            ->columnSpanFull()
                            ->helperText('Raw HTML content with {{variable_name}} placeholders. You can paste full HTML templates here.')
                            ->hidden(fn ($get) => !$get('html_mode'))
                            ->afterStateHydrated(function ($state, $set, $record) {
                                if ($record) {
                                    $set('body_html', $record->body);
                                }
                            })
                            ->dehydrateStateUsing(fn ($state, $get) => $get('html_mode') ? $state : null),

                        TagsInput::make('available_variables')
                            ->label('Available Variables')
                            ->suggestions([
                                'first_name',
                                'last_name',
                                'full_name',
                                'email',
                                'company_name',
                                'public_name',
                                'plan',
                                'website_url',
                                'verification_link',
                                'reset_password_link',
                            ])
                            ->columnSpanFull()
                            ->helperText('Variables available for this template (auto-populated based on event)'),
                    ]),
            ]);
    }

    /**
     * Get list of available event triggers
     */
    protected static function getEventTriggers(): array
    {
        return [
            'registration_confirmation' => 'Registration Confirmation - Email verification after signup',
            'welcome_email' => 'Welcome Email - After email verification',
            'password_reset' => 'Password Reset - Reset password link',
            'order_confirmation' => 'Order Confirmation - Sent to customer after placing an order',
            'order_paid' => 'Order Paid - Sent after successful payment',
            'ticket_delivery' => 'Ticket Delivery - Tickets sent after order is completed',
            'invoice_notification' => 'Invoice Notification - New invoice generated',
            'payment_received' => 'Payment Received - Payment confirmation',
            'payment_failed' => 'Payment Failed - Failed payment notification',
            'domain_activated' => 'Domain Activated - Domain is now active',
            'domain_suspended' => 'Domain Suspended - Domain has been suspended',
            'microservice_activated' => 'Microservice Activated - New microservice enabled',
            'trial_ending' => 'Trial Ending - Trial period ending soon',
            'subscription_renewed' => 'Subscription Renewed - Subscription renewal confirmation',
            'subscription_cancelled' => 'Subscription Cancelled - Cancellation confirmation',
            'contract_generated' => 'Contract Generated - Tenant contract PDF sent after onboarding',
        ];
    }
}
