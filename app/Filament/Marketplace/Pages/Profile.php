<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Profile extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profile';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.marketplace.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user) {
            $this->form->fill([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position,
                'avatar' => $user->avatar,
            ]);
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Profile Photo')
                            ->image()
                            ->avatar()
                            ->directory('avatars')
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Contact administrator to change email'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('position')
                            ->label('Position / Title')
                            ->maxLength(255)
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your role in the organization'),
                    ])
                    ->columns(2),

                SC\Section::make('Change Password')
                    ->description('Leave blank to keep your current password')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->currentPassword()
                            ->requiredWith('new_password'),

                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->confirmed(),

                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->requiredWith('new_password'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if ($user) {
            $updateData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'position' => $data['position'],
                'avatar' => $data['avatar'],
            ];

            // Update password if provided
            if (!empty($data['new_password'])) {
                $updateData['password'] = Hash::make($data['new_password']);
            }

            $user->update($updateData);

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
