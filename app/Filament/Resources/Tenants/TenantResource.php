<?php

namespace App\Filament\Resources\Tenants;

use App\Models\Tenant;
use App\Models\ContractTemplate;
use App\Services\LocationService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use BackedEnum;
use Filament\Forms;
use Filament\Tables;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Core';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name (Legal/Company Name)')
                        ->required()
                        ->helperText('Legal company name as registered'),

                    Forms\Components\TextInput::make('public_name')
                        ->label('Public Name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if (! $state) return;
                            $set('slug', \Illuminate\Support\Str::slug($state));
                        })
                        ->helperText('Display name (e.g., Teatrul Odeon while company is Wits Fits SRL)'),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'pending' => 'Pending',
                            'suspended' => 'Suspended',
                            'cancelled' => 'Cancelled',
                            'terminated' => 'Terminated',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\Select::make('type')
                        ->label('Client Type')
                        ->options([
                            'single' => 'Single',
                            'small' => 'Small',
                            'medium' => 'Medium',
                            'large' => 'Large',
                            'premium' => 'Premium',
                        ])
                        ->nullable()
                        ->helperText('Client tier for billing/features'),

                    Forms\Components\Select::make('plan')
                        ->label('Plan')
                        ->options([
                            '1percent' => '1% - Exclusive',
                            '2percent' => '2% - Mixed',
                            '3percent' => '3% - Reseller',
                        ])
                        ->nullable()
                        ->helperText('Commission plan based on work method'),

                    Forms\Components\Select::make('locale')
                        ->label('Language / Locale')
                        ->options([
                            'ro' => 'Romanian (Română)',
                            'en' => 'English',
                            'hu' => 'Hungarian (Magyar)',
                            'de' => 'German (Deutsch)',
                            'fr' => 'French (Français)',
                        ])
                        ->default('ro')
                        ->required()
                        ->helperText('Default language for this tenant'),
                ])->columns(2),

            SC\Section::make('Company Details')
                ->description('Legal entity information for invoicing and contracts')
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Company Name')
                        ->maxLength(255)
                        ->helperText('Legal company name as registered'),

                    Forms\Components\TextInput::make('cui')
                        ->label('CUI / Tax ID')
                        ->maxLength(50)
                        ->helperText('Fiscal identification number (CUI in Romania)'),

                    Forms\Components\TextInput::make('reg_com')
                        ->label('Trade Register')
                        ->maxLength(255)
                        ->helperText('Company registration number (Registrul Comerțului)'),

                    Forms\Components\TextInput::make('contract_number')
                        ->label('Contract Number')
                        ->maxLength(255)
                        ->helperText('Tenant contract number'),

                    Forms\Components\FileUpload::make('contract_file')
                        ->label('Contract File')
                        ->disk('public')
                        ->directory('contracts')
                        ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240)
                        ->helperText('Upload tenant contract (PDF, DOC, DOCX - max 10MB)'),

                    Forms\Components\Toggle::make('vat_payer')
                        ->label('VAT Payer')
                        ->default(true)
                        ->helperText('Is this company registered for VAT?'),

                    Forms\Components\TextInput::make('bank_account')
                        ->label('Bank Account (IBAN)')
                        ->maxLength(255)
                        ->helperText('For receiving payments'),

                    Forms\Components\TextInput::make('bank_name')
                        ->label('Bank Name')
                        ->maxLength(255)
                        ->helperText('Name of the banking institution'),
                ])->columns(2),

            SC\Section::make('Address')
                ->description('Company address for legal and billing purposes')
                ->schema([
                    Forms\Components\Textarea::make('address')
                        ->label('Street Address')
                        ->rows(2)
                        ->columnSpanFull()
                        ->helperText('Full street address'),

                    Forms\Components\Select::make('country')
                        ->label('Country')
                        ->options(function () {
                            $locationService = app(LocationService::class);
                            return $locationService->getCountries();
                        })
                        ->searchable()
                        ->default('Romania')
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            // Clear dependent fields when country changes
                            $set('state', null);
                            $set('city', null);
                        })
                        ->helperText('Select country'),

                    Forms\Components\Select::make('state')
                        ->label('State / County')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $country = $get('country');
                            if (!$country) {
                                return [];
                            }

                            $locationService = app(LocationService::class);
                            $countryCode = $locationService->getCountryCode($country);

                            if (!$countryCode) {
                                return [];
                            }

                            return $locationService->getStates($countryCode);
                        })
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            // Clear city when state changes
                            $set('city', null);
                        })
                        ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => !$get('country'))
                        ->helperText('Select state/county (Județ in Romania)'),

                    Forms\Components\Select::make('city')
                        ->label('City')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $country = $get('country');
                            $state = $get('state');

                            if (!$country || !$state) {
                                return [];
                            }

                            $locationService = app(LocationService::class);
                            $countryCode = $locationService->getCountryCode($country);

                            if (!$countryCode) {
                                return [];
                            }

                            return $locationService->getCities($countryCode, $state);
                        })
                        ->searchable()
                        ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => !$get('country') || !$get('state'))
                        ->helperText('Select city'),
                ])->columns(3),

            SC\Section::make('Billing')
                ->description('Automated billing configuration')
                ->schema([
                    Forms\Components\DateTimePicker::make('billing_starts_at')
                        ->label('Billing Start Date')
                        ->native(false)
                        ->default(now())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                            $cycleDays = $get('billing_cycle_days') ?? 30;
                            if ($state) {
                                $set('due_at', \Carbon\Carbon::parse($state)->addDays($cycleDays));
                            }
                        })
                        ->helperText('When billing cycle begins (defaults to registration date)'),

                    Forms\Components\TextInput::make('billing_cycle_days')
                        ->label('Billing Cycle (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                            $billingStart = $get('billing_starts_at');
                            if ($billingStart && $state) {
                                $set('due_at', \Carbon\Carbon::parse($billingStart)->addDays($state));
                            }
                        })
                        ->helperText('Number of days between billing cycles'),

                    Forms\Components\DateTimePicker::make('due_at')
                        ->label('Next Billing Date')
                        ->native(false)
                        ->nullable()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Automatically calculated: Billing Start Date + Billing Cycle Days'),
                ])->columns(3),

            SC\Section::make('Commission Settings')
                ->description('Default commission settings for this tenant\'s events')
                ->schema([
                    Forms\Components\Select::make('work_method')
                        ->label('Work Method')
                        ->options([
                            'exclusive' => 'Exclusive (1%)',
                            'mixed' => 'Mixed (2%)',
                            'reseller' => 'Reseller (3%)',
                        ])
                        ->reactive()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            $rateMap = [
                                'exclusive' => 1,
                                'mixed' => 2,
                                'reseller' => 3,
                            ];
                            if (isset($rateMap[$state])) {
                                $set('commission_rate', $rateMap[$state]);
                                $planMap = [
                                    'exclusive' => '1percent',
                                    'mixed' => '2percent',
                                    'reseller' => '3percent',
                                ];
                                $set('plan', $planMap[$state]);
                            }
                        })
                        ->hint('Sales method - automatically sets commission rate and plan')
                        ->hintIcon('heroicon-o-information-circle'),

                    Forms\Components\TextInput::make('commission_rate')
                        ->label('Commission Rate (%)')
                        ->numeric()
                        ->default(1)
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(1)
                        ->suffix('%')
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->hint('Automatically set based on Work Method')
                        ->hintIcon('heroicon-o-information-circle'),

                    Forms\Components\Select::make('commission_mode')
                        ->label('Commission Mode')
                        ->options([
                            'included' => 'Included in ticket price',
                            'added_on_top' => 'Added on top of ticket price',
                        ])
                        ->default('included')
                        ->required()
                        ->hint('How the Tixello commission is applied')
                        ->hintIcon('heroicon-o-information-circle'),

                    Forms\Components\Select::make('estimated_monthly_tickets')
                        ->label('Estimated Monthly Tickets')
                        ->options([
                            '0' => '0 - 100 tickets/month',
                            '100' => '100 - 500 tickets/month',
                            '500' => '500 - 1,000 tickets/month',
                            '1000' => '1,000 - 5,000 tickets/month',
                            '5000' => '5,000 - 10,000 tickets/month',
                            '10000' => 'Over 10,000 tickets/month',
                        ])
                        ->helperText('Expected monthly ticket volume'),
                ])->columns(2),

            SC\Section::make('Tenant Representative')
                ->description('The user who controls this tenant account')
                ->schema([
                    Forms\Components\Select::make('owner_id')
                        ->label('Owner User Account')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique('users', 'email')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                            Forms\Components\Hidden::make('role')
                                ->default('tenant'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return \App\Models\User::create($data)->id;
                        })
                        ->helperText('Select existing user or create new one')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('contact_first_name')
                        ->label('First Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_last_name')
                        ->label('Last Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('contact_position')
                        ->label('Position in Company')
                        ->maxLength(255)
                        ->helperText('e.g., Manager, Director, Administrator'),
                ])->columns(2),

            SC\Section::make('Domains')
                ->description('Websites associated with this tenant - manage domain status and access')
                ->schema([
                    // Show repeater for creating new domains (when no record exists)
                    Forms\Components\Repeater::make('new_domains')
                        ->label('Domains')
                        ->schema([
                            Forms\Components\TextInput::make('domain')
                                ->label('Domain Name')
                                ->required()
                                ->placeholder('events.example.com')
                                ->helperText('Full domain without http/https'),
                            Forms\Components\Toggle::make('is_primary')
                                ->label('Primary Domain')
                                ->default(false),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Add Domain')
                        ->reorderable(false)
                        ->visible(fn ($record) => $record === null)
                        ->helperText('Add domains for this tenant. You can manage them after saving.'),

                    // Show existing domains manager for editing
                    Forms\Components\Placeholder::make('domains_manager')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) {
                                return '';
                            }
                            return new \Illuminate\Support\HtmlString(
                                view('filament.resources.tenants.domains-manager', [
                                    'domains' => $record->domains,
                                    'tenantId' => $record->id,
                                ])->render()
                            );
                        })
                        ->visible(fn ($record) => $record !== null),
                ])->collapsible()
                ->columnSpanFull(),

            SC\Section::make('Ticket Terms & Conditions')
                ->description('Terms and conditions that apply to all events for this tenant')
                ->schema([
                    Forms\Components\RichEditor::make('ticket_terms')
                        ->label('Ticket Terms')
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
                            'undo',
                            'redo',
                        ])
                        ->helperText('These terms will be auto-populated in all events for this tenant')
                        ->columnSpanFull(),
                ])->collapsible(),

            SC\Section::make('Deployment Packages')
                ->description('Download deployment packages for each domain. These packages contain the tenant website code.')
                ->schema([
                    Forms\Components\Placeholder::make('packages_manager')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) {
                                return 'Save the tenant first to manage deployment packages.';
                            }
                            return new \Illuminate\Support\HtmlString(
                                view('filament.resources.tenants.packages-manager', [
                                    'tenant' => $record,
                                ])->render()
                            );
                        }),
                ])->collapsible()
                ->visible(fn ($record) => $record !== null)
                ->columnSpanFull(),

            SC\Section::make('Contract')
                ->description('Automatically generated contract for this tenant')
                ->schema([
                    Forms\Components\Placeholder::make('contract_info')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) {
                                return 'Save the tenant first to generate a contract.';
                            }

                            $html = '<div class="space-y-4">';

                            // Contract status
                            if ($record->contract_file) {
                                // Status badge
                                $statusColors = [
                                    'draft' => 'gray',
                                    'generated' => 'blue',
                                    'sent' => 'yellow',
                                    'viewed' => 'purple',
                                    'signed' => 'green',
                                ];
                                $statusColor = $statusColors[$record->contract_status] ?? 'gray';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-' . $statusColor . '-100 text-' . $statusColor . '-800">' . ucfirst($record->contract_status) . '</span>';
                                if ($record->contract_signed_at) {
                                    $html .= '<span class="text-green-600 text-sm">✓ Signed</span>';
                                }
                                $html .= '</div>';

                                $html .= '<div class="grid grid-cols-2 gap-4 text-sm mt-3">';
                                $html .= '<div><strong>Contract Number:</strong> ' . ($record->contract_number ?? 'N/A') . '</div>';
                                $html .= '<div><strong>Template:</strong> ' . ($record->contractTemplate?->name ?? 'Default') . '</div>';
                                $html .= '<div><strong>Generated:</strong> ' . ($record->contract_generated_at ? $record->contract_generated_at->format('d.m.Y H:i') : 'N/A') . '</div>';
                                $html .= '<div><strong>Sent:</strong> ' . ($record->contract_sent_at ? $record->contract_sent_at->format('d.m.Y H:i') : 'Not sent') . '</div>';
                                if ($record->contract_viewed_at) {
                                    $html .= '<div><strong>Viewed:</strong> ' . $record->contract_viewed_at->format('d.m.Y H:i') . '</div>';
                                }
                                if ($record->contract_signed_at) {
                                    $html .= '<div><strong>Signed:</strong> ' . $record->contract_signed_at->format('d.m.Y H:i') . '</div>';
                                }
                                $html .= '<div><strong>Renewal Date:</strong> ' . ($record->contract_renewal_date ? $record->contract_renewal_date->format('d.m.Y') : 'Not set') . '</div>';
                                $html .= '<div><strong>Auto Renew:</strong> ' . ($record->contract_auto_renew ? 'Yes' : 'No') . '</div>';
                                $html .= '<div><strong>Versions:</strong> ' . $record->contractVersions()->count() . '</div>';
                                $html .= '</div>';

                                $html .= '<div class="flex gap-2 mt-4">';
                                $html .= '<a href="' . route('admin.tenant.contract.download', $record) . '" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700" target="_blank">';
                                $html .= '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>';
                                $html .= 'Download</a>';
                                $html .= '<a href="' . route('admin.tenant.contract.preview', $record) . '" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" target="_blank">';
                                $html .= '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>';
                                $html .= 'Preview</a>';
                                $html .= '</div>';
                            } else {
                                $html .= '<div class="flex items-center gap-2 text-yellow-600">';
                                $html .= '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
                                $html .= '<span class="font-medium">No contract generated yet</span>';
                                $html .= '</div>';
                                $html .= '<p class="text-sm text-gray-500">Use the actions in the header to generate a contract for this tenant.</p>';
                            }

                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ])
                ->collapsible()
                ->visible(fn ($record) => $record !== null)
                ->columnSpanFull(),

            SC\Section::make('Additional Settings')
                ->schema([
                    Forms\Components\KeyValue::make('settings')
                        ->label('Custom Settings (JSON)')
                        ->nullable(),
                ])->columns(1)->collapsible(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->withCount([
                        'events as events_total',
                        'events as events_active' => function ($q) {
                            $q->where('status', '=', 'published')
                              ->whereDate('event_date', '>=', now());
                        },
                        'events as events_completed' => function ($q) {
                            $q->whereDate('event_date', '<', now());
                        },
                        'orders as orders_total',
                    ])
                    ->withSum('orders as income_cents', 'total_cents');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (\App\Models\Tenant $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger' => fn ($state) => in_array($state, ['suspended', 'cancelled', 'terminated']),
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('events_total')
                    ->label('Events (Total)')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('events_active')
                    ->label('Active')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('events_completed')
                    ->label('Completed')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('orders_total')
                    ->label('Orders')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('income_cents')
                    ->label('Income')
                    ->money('RON', divideBy: 100)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payments')
                    ->label('Payments')
                    ->default('N/A')
                    ->alignCenter()
                    ->toggleable()
                    ->tooltip('Payment tracking coming soon'),

                Tables\Columns\TextColumn::make('due_at')
                    ->label('Due At')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'terminated' => 'Terminated',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'single' => 'Single',
                        'small' => 'Small',
                        'medium' => 'Medium',
                        'large' => 'Large',
                        'premium' => 'Premium',
                    ]),
            ])
            ->actions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
            'payment-config' => Pages\ManagePaymentConfig::route('/{record}/payment-config'),
        ];
    }
}
