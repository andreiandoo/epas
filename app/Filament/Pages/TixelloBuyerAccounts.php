<?php

namespace App\Filament\Pages;

use App\Models\TixelloBuyerAccount;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\HtmlString;

/**
 * Conturile de cumpărător Tixello și registrul acordurilor (Tixello operator separat).
 * Doar citire: acordurile le dă și le retrage numai cumpărătorul.
 */
class TixelloBuyerAccounts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-identification';
    protected static \UnitEnum|string|null $navigationGroup = 'Tenants';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Conturi cumpărători Tixello';
    protected static ?string $title = 'Conturi cumpărători Tixello';
    protected static ?string $slug = 'tixello-buyer-accounts';
    protected string $view = 'filament.pages.tenant-approvals';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['super-admin', 'admin'], true);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(TixelloBuyerAccount::query()->withCount('consents'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Niciun cont încă')
            ->emptyStateDescription('Apar aici cumpărătorii care bifează contul sau recomandările Tixello la plată, ori își fac cont la /contul-meu.')
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('name')->label('Nume')->state(fn (TixelloBuyerAccount $r) => $r->name ?: '—'),
                Tables\Columns\IconColumn::make('account_enabled')->label('Cont')->boolean(),
                Tables\Columns\IconColumn::make('marketing_opt_in')->label('Recomandări')->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')->label('Ultima intrare')->since()->placeholder('niciodată'),
                Tables\Columns\TextColumn::make('created_at')->label('Creat')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('marketing_opt_in')->label('Acceptă recomandări'),
                Tables\Filters\TernaryFilter::make('account_enabled')->label('Are cont'),
            ])
            ->recordActions([
                Action::make('consents')
                    ->label(fn (TixelloBuyerAccount $r) => 'Acorduri (' . $r->consents_count . ')')
                    ->icon('heroicon-o-document-check')
                    ->color('gray')
                    ->modalHeading(fn (TixelloBuyerAccount $r) => 'Registrul acordurilor: ' . $r->email)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide')
                    ->modalContent(fn (TixelloBuyerAccount $r) => new HtmlString(view('filament.pages.tixello-buyer-consents', [
                        'consents' => $r->consents()->latest('id')->get(),
                    ])->render())),
            ]);
    }
}
