<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\ContractTemplate;
use App\Services\ContractPdfService;
use App\Mail\ContractMail;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('generate_contract')
                    ->label('Generate Contract')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Contract')
                    ->modalDescription('This will generate a new PDF contract for this tenant based on their business model. Any existing contract will be replaced.')
                    ->form([
                        \Filament\Forms\Components\Select::make('template_id')
                            ->label('Contract Template')
                            ->options(ContractTemplate::where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('Auto-select based on work method')
                            ->helperText('Leave empty to automatically select the best matching template'),
                    ])
                    ->action(function (array $data) {
                        $contractService = app(ContractPdfService::class);
                        $template = null;

                        if (!empty($data['template_id'])) {
                            $template = ContractTemplate::find($data['template_id']);
                        }

                        try {
                            $contractService->generate($this->record, $template);

                            Notification::make()
                                ->title('Contract generated successfully')
                                ->success()
                                ->body('The contract PDF has been generated and saved.')
                                ->send();

                            $this->refreshFormData(['contract_file', 'contract_generated_at', 'contract_template_id', 'contract_number']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to generate contract')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Actions\Action::make('send_contract')
                    ->label('Send Contract Email')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->visible(fn () => $this->record->contract_file !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Send Contract Email')
                    ->modalDescription(fn () => "Send the contract to {$this->record->contact_email}?")
                    ->action(function () {
                        try {
                            Mail::to($this->record->contact_email)
                                ->send(new ContractMail($this->record, $this->record->contract_file));

                            $this->record->update(['contract_sent_at' => now()]);

                            Notification::make()
                                ->title('Contract sent successfully')
                                ->success()
                                ->body("Contract has been sent to {$this->record->contact_email}")
                                ->send();

                            $this->refreshFormData(['contract_sent_at']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to send contract')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Actions\Action::make('regenerate_contract')
                    ->label('Regenerate Contract')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => $this->record->contract_file !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Contract')
                    ->modalDescription('This will regenerate the contract with the current tenant data. The old contract will be replaced.')
                    ->action(function () {
                        $contractService = app(ContractPdfService::class);

                        try {
                            $contractService->regenerate($this->record);

                            Notification::make()
                                ->title('Contract regenerated successfully')
                                ->success()
                                ->send();

                            $this->refreshFormData(['contract_file', 'contract_generated_at']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to regenerate contract')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
                ->label('Contract')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->button(),

            Actions\Action::make('reset_owner_password')
                ->label('Reset Owner Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn () => $this->record->owner !== null)
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->revealable(),
                    \Filament\Forms\Components\TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->required()
                        ->same('new_password')
                        ->revealable(),
                ])
                ->action(function (array $data) {
                    $owner = $this->record->owner;
                    if ($owner) {
                        $owner->password = Hash::make($data['new_password']);
                        $owner->save();

                        Notification::make()
                            ->title('Password reset successfully')
                            ->success()
                            ->body("Password for {$owner->name} has been updated.")
                            ->send();
                    }
                }),
        ];
    }
}
