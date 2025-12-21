<?php

namespace App\Filament\Organizer\Resources;

use App\Models\Marketplace\MarketplaceOrganizerUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class TeamResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizerUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Team Members';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Team Member';
    protected static ?string $pluralModelLabel = 'Team Members';

    public static function canViewAny(): bool
    {
        $user = auth('organizer')->user();
        // Only admins can manage team
        return $user && $user->role === MarketplaceOrganizerUser::ROLE_ADMIN;
    }

    public static function getEloquentQuery(): Builder
    {
        $organizer = auth('organizer')->user()?->organizer;

        return parent::getEloquentQuery()
            ->where('organizer_id', $organizer?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn (string $context) => $context === 'create')
                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8)
                            ->helperText(fn (string $context) => $context === 'edit' ? 'Leave blank to keep current password' : null),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),
                    ])->columns(2),

                Forms\Components\Section::make('Role & Permissions')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(MarketplaceOrganizerUser::getRoles())
                            ->default('editor')
                            ->required()
                            ->helperText('Admin: Full access. Editor: Can manage events. Viewer: Read-only access.'),

                        Forms\Components\TextInput::make('position')
                            ->label('Position/Title')
                            ->maxLength(100)
                            ->placeholder('e.g., Event Manager, Marketing Lead'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive users cannot log in'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentUser = auth('organizer')->user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'editor' => 'info',
                        'viewer' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => MarketplaceOrganizerUser::getRoles()[$state] ?? ucfirst($state)),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(MarketplaceOrganizerUser::getRoles()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (MarketplaceOrganizerUser $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (MarketplaceOrganizerUser $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (MarketplaceOrganizerUser $record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn (MarketplaceOrganizerUser $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation()
                    ->hidden(fn (MarketplaceOrganizerUser $record) => $record->id === $currentUser?->id),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (MarketplaceOrganizerUser $record) => $record->id === $currentUser?->id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => TeamResource\Pages\ListTeam::route('/'),
            'create' => TeamResource\Pages\CreateTeamMember::route('/create'),
            'edit' => TeamResource\Pages\EditTeamMember::route('/{record}/edit'),
        ];
    }
}
