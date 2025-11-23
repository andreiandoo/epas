<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\ContractAmendment;
use App\Models\ContractTemplate;
use App\Services\ContractPdfService;
use App\Mail\ContractMail;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ESignatureService;

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

                Actions\Action::make('preview_contract')
                    ->label('Preview Contract')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Contract Preview')
                    ->modalWidth('7xl')
                    ->modalContent(function () {
                        $template = ContractTemplate::findForTenant($this->record);
                        if (!$template) {
                            return view('filament.modals.contract-preview-error', [
                                'message' => 'No template found for this tenant.',
                            ]);
                        }

                        $processedContent = $template->processContent($this->record);

                        return view('filament.modals.contract-preview', [
                            'tenant' => $this->record,
                            'template' => $template,
                            'content' => $processedContent,
                            'contractUrl' => $this->record->contract_file
                                ? Storage::disk('public')->url($this->record->contract_file)
                                : null,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Actions\Action::make('create_amendment')
                    ->label('Create Amendment')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->visible(fn () => $this->record->contract_file !== null)
                    ->modalHeading('Create Contract Amendment')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('title')
                            ->label('Amendment Title')
                            ->required()
                            ->placeholder('e.g., Commission Rate Change'),

                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->placeholder('Brief description of the amendment'),

                        \Filament\Forms\Components\RichEditor::make('content')
                            ->label('Amendment Content')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'h2', 'h3',
                            ])
                            ->helperText('This content will be added as an amendment to the existing contract'),
                    ])
                    ->action(function (array $data) {
                        $amendment = ContractAmendment::create([
                            'tenant_id' => $this->record->id,
                            'contract_version_id' => $this->record->latestContractVersion?->id,
                            'amendment_number' => ContractAmendment::generateNumber($this->record),
                            'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                            'content' => $data['content'],
                            'status' => 'draft',
                        ]);

                        // Generate amendment PDF
                        $pdf = Pdf::loadView('pdfs.amendment', [
                            'amendment' => $amendment,
                            'tenant' => $this->record,
                        ]);

                        $filename = 'amendment-' . $amendment->amendment_number . '.pdf';
                        $path = 'contracts/amendments/' . $filename;
                        Storage::disk('public')->put($path, $pdf->output());

                        $amendment->update(['file_path' => $path]);

                        Notification::make()
                            ->title('Amendment created successfully')
                            ->success()
                            ->body("Amendment {$amendment->amendment_number} has been created.")
                            ->send();
                    }),

                Actions\Action::make('send_esignature')
                    ->label('Send for E-Signature')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn () => $this->record->contract_file !== null && app(ESignatureService::class)->isConfigured())
                    ->requiresConfirmation()
                    ->modalHeading('Send for E-Signature')
                    ->modalDescription(fn () => "Send the contract to {$this->record->contact_email} for electronic signature?")
                    ->action(function () {
                        $esignService = app(ESignatureService::class);

                        $result = $esignService->sendForSignature($this->record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Contract sent for e-signature')
                                ->success()
                                ->body("The contract has been sent to {$this->record->contact_email} for signing.")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to send for e-signature')
                                ->danger()
                                ->body($result['error'] ?? 'Unknown error')
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
