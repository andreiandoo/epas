<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\StaffResource\Pages;
use App\Models\VendorEmployee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffResource extends Resource
{
    protected static ?string $model = VendorEmployee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'staff';

    public static function canAccess(): bool
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $employee && in_array($employee->role, ['manager', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $employee = Auth::guard('vendor_employee')->user();

        return parent::getEloquentQuery()
            ->where('vendor_id', $employee->vendor_id);
    }

    public static function form(Form $form): Form
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $form->schema([
            Forms\Components\Section::make('Employee Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('full_name')
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),

                Forms\Components\TextInput::make('phone')
                    ->tel(),

                Forms\Components\Select::make('role')
                    ->options([
                        'manager'    => 'Manager',
                        'supervisor' => 'Supervisor',
                        'member'     => 'Member',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'New Password (leave empty to keep current)'),

                Forms\Components\TextInput::make('pin')
                    ->label('PIN (for POS quick auth)')
                    ->maxLength(6),

                Forms\Components\Select::make('status')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ])->columns(2),

            Forms\Components\Hidden::make('vendor_id')
                ->default(fn () => $employee->vendor_id),

            Forms\Components\Hidden::make('tenant_id')
                ->default(fn () => $employee->tenant_id),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'success' => 'manager',
                        'warning' => 'supervisor',
                        'gray'    => 'member',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->password()
                            ->required()
                            ->minLength(6),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'password' => Hash::make($data['new_password']),
                    ]))
                    ->requiresConfirmation(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit'   => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
