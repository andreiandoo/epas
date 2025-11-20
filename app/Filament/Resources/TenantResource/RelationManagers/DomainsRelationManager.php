<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\Domain;
use App\Models\DomainVerification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Domain'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(false),
                Forms\Components\Textarea::make('notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('latestVerification.status')
                    ->badge()
                    ->label('Verification')
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latestPackage.version')
                    ->label('Package')
                    ->placeholder('No package'),
                Tables\Columns\TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    // Manual domain verification by admin
                    Tables\Actions\Action::make('manualVerify')
                        ->label('Verify Manually')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Manually Verify Domain')
                        ->modalDescription('This will mark the domain as verified without checking DNS/meta/file. Use only when you have confirmed ownership through other means.')
                        ->action(function (Domain $record) {
                            // Create or update verification
                            $verification = $record->latestVerification;

                            if (!$verification) {
                                $verification = DomainVerification::create([
                                    'domain_id' => $record->id,
                                    'tenant_id' => $record->tenant_id,
                                    'verification_method' => 'dns_txt',
                                    'status' => DomainVerification::STATUS_VERIFIED,
                                    'verified_at' => now(),
                                    'verification_data' => [
                                        'manual_verification' => true,
                                        'verified_by' => auth()->id(),
                                    ],
                                ]);
                            } else {
                                $verification->update([
                                    'status' => DomainVerification::STATUS_VERIFIED,
                                    'verified_at' => now(),
                                    'verification_data' => array_merge(
                                        $verification->verification_data ?? [],
                                        [
                                            'manual_verification' => true,
                                            'verified_by' => auth()->id(),
                                        ]
                                    ),
                                ]);
                            }

                            // Activate the domain
                            $record->update([
                                'is_active' => true,
                                'activated_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Domain verified manually')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Domain $record) => !$record->isVerified()),

                    // Login as super-admin to tenant website
                    Tables\Actions\Action::make('loginAsSuperAdmin')
                        ->label('Login as Admin')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Login to Tenant Website')
                        ->modalDescription('This will generate a secure one-time login link to access the tenant\'s admin panel as a super-admin for debugging purposes.')
                        ->action(function (Domain $record) {
                            // Generate a signed URL with short expiry
                            $token = Str::random(64);
                            $expiresAt = now()->addMinutes(5);

                            // Store the token (you might want to use cache or a dedicated table)
                            cache()->put(
                                "admin_login_token:{$token}",
                                [
                                    'tenant_id' => $record->tenant_id,
                                    'domain_id' => $record->id,
                                    'admin_id' => auth()->id(),
                                    'created_at' => now()->toIso8601String(),
                                ],
                                $expiresAt
                            );

                            // Generate the login URL for the tenant's domain
                            $protocol = config('app.env') === 'production' ? 'https' : 'http';
                            $loginUrl = "{$protocol}://{$record->domain}/admin/super-login?token={$token}";

                            Notification::make()
                                ->title('Admin Login Link Generated')
                                ->body("Link expires in 5 minutes. Opening in new tab...")
                                ->success()
                                ->send();

                            // Return a redirect action
                            return redirect()->away($loginUrl);
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (Domain $record) => $record->is_active),

                    // Generate package for domain
                    Tables\Actions\Action::make('generatePackage')
                        ->label('Generate Package')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Deployment Package')
                        ->modalDescription('This will generate a new deployment package for this domain.')
                        ->action(function (Domain $record) {
                            // Dispatch package generation job
                            // GeneratePackageJob::dispatch($record);

                            Notification::make()
                                ->title('Package generation started')
                                ->body('The package will be available shortly.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Domain $record) => $record->isVerified()),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
