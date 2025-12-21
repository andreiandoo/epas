<?php

namespace App\Filament\Resources\Marketplace\MarketplaceResource\RelationManagers;

use App\Models\Marketplace\MarketplaceOrganizer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizersRelationManager extends RelationManager
{
    protected static string $relationship = 'organizers';

    protected static ?string $title = 'Organizers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Organizer Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_name')
                            ->label('Contact Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Contact Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending_approval' => 'Pending Approval',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'closed' => 'Closed',
                            ])
                            ->default('pending_approval')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Company Details')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cui')
                            ->label('Tax ID (CUI)')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('country')
                            ->label('Country')
                            ->default('RO')
                            ->maxLength(10),
                    ])->columns(2),

                Forms\Components\Section::make('Commission Override')
                    ->description('Leave empty to use marketplace defaults')
                    ->schema([
                        Forms\Components\Select::make('commission_type')
                            ->label('Commission Type')
                            ->options([
                                '' => '-- Use Marketplace Default --',
                                'percent' => 'Percentage Only',
                                'fixed' => 'Fixed Amount Only',
                                'both' => 'Percentage + Fixed',
                            ])
                            ->live(),

                        Forms\Components\TextInput::make('commission_percent')
                            ->label('Commission %')
                            ->numeric()
                            ->suffix('%')
                            ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), ['percent', 'both'])),

                        Forms\Components\TextInput::make('commission_fixed')
                            ->label('Fixed Amount')
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), ['fixed', 'both'])),
                    ])->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_approval' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('total_events')
                    ->label('Events')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pending_payout')
                    ->label('Pending')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Organizer'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (MarketplaceOrganizer $record) => $record->status === 'pending_approval')
                    ->action(function (MarketplaceOrganizer $record) {
                        $record->approve();
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->visible(fn (MarketplaceOrganizer $record) => $record->status === 'active')
                    ->action(function (MarketplaceOrganizer $record) {
                        $record->suspend();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
