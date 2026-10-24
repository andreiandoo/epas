<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\Onboarding\TenantApprovalService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\HtmlString;

/**
 * Coada înscrierilor din /inregistrare care așteaptă aprobarea.
 */
class TenantApprovals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-plus';
    protected static \UnitEnum|string|null $navigationGroup = 'Tenants';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Tenanți de aprobat';
    protected static ?string $title = 'Tenanți de aprobat';
    protected static ?string $slug = 'tenant-approvals';
    protected string $view = 'filament.pages.tenant-approvals';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['super-admin', 'admin'], true);
    }

    public static function getNavigationBadge(): ?string
    {
        // Rulează pe fiecare pagină din admin: nu are voie să strice meniul înainte de migrație
        try {
            $count = Tenant::where('approval_status', 'pending')->count();
        } catch (\Throwable) {
            return null;
        }

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(Tenant::query()->where('approval_status', 'pending')->with('owner'))
            ->defaultSort('approval_submitted_at')
            ->emptyStateHeading('Nicio înscriere în așteptare')
            ->emptyStateDescription('Înscrierile noi de la /inregistrare apar aici.')
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Organizator')
                    ->description(fn (Tenant $r) => $r->tenant_type?->label())
                    ->searchable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Facturează')
                    ->description(fn (Tenant $r) => trim(strtoupper((string) $r->legal_entity_type) . ($r->cui ? ' · CUI ' . $r->cui : ''))),
                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->description(fn (Tenant $r) => $r->contact_phone)
                    ->copyable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label('Site')
                    ->placeholder('mai târziu'),
                Tables\Columns\TextColumn::make('approval_submitted_at')
                    ->label('Trimisă')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Detalii')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Tenant $r) => $r->public_name)
                    ->modalContent(fn (Tenant $r) => new HtmlString(view('filament.pages.tenant-approval-details', ['tenant' => $r])->render()))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide'),
                Action::make('approve')
                    ->label('Aprobă')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $r) => 'Aprobi ' . $r->public_name . '?')
                    ->modalDescription('Contul devine activ: pornim domeniul ales, creăm site-ul ca ciornă și trimitem contractul și emailul de aprobare.')
                    ->modalSubmitActionLabel('Aprobă')
                    ->action(function (Tenant $r) {
                        app(TenantApprovalService::class)->approve($r, auth()->user());
                        Notification::make()->title($r->public_name . ' a fost aprobat')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->modalHeading(fn (Tenant $r) => 'Respingi ' . $r->public_name . '?')
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivul (îl primește organizatorul pe email)')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000),
                    ])
                    ->modalSubmitActionLabel('Respinge')
                    ->action(function (Tenant $r, array $data) {
                        app(TenantApprovalService::class)->reject($r, auth()->user(), $data['reason']);
                        Notification::make()->title($r->public_name . ' a fost respins')->warning()->send();
                    }),
            ]);
    }
}
