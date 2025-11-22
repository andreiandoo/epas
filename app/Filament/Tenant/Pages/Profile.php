<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;

class Profile extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profile';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.tenant.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if ($tenant) {
            $this->form->fill([
                'name' => $tenant->name,
                'public_name' => $tenant->public_name,
                'company_name' => $tenant->company_name,
                'cui' => $tenant->cui,
                'reg_com' => $tenant->reg_com,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'country' => $tenant->country,
                'postal_code' => $tenant->postal_code,
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
                'website' => $tenant->website,
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,
            ]);
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Internal Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('public_name')
                            ->label('Public Display Name')
                            ->maxLength(255)
                            ->helperText('Name shown to customers'),

                        Forms\Components\TextInput::make('company_name')
                            ->label('Legal Company Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cui')
                            ->label('CUI / VAT Number')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('reg_com')
                            ->label('Trade Register')
                            ->maxLength(50),
                    ])
                    ->columns(2),

                SC\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('State / County')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('country')
                            ->maxLength(2)
                            ->helperText('2-letter code (e.g., RO)'),

                        Forms\Components\TextInput::make('postal_code')
                            ->maxLength(20),
                    ])
                    ->columns(2),

                SC\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                SC\Section::make('Bank Details')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('bank_account')
                            ->label('IBAN')
                            ->maxLength(50),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        if ($tenant) {
            $tenant->update($data);

            Notification::make()
                ->success()
                ->title('Profile updated')
                ->body('Your profile information has been saved.')
                ->send();
        }
    }

    public function getTitle(): string
    {
        return 'Profile';
    }
}
