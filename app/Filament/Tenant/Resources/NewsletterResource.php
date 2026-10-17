<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\NewsletterResource\Pages;
use App\Jobs\SendTenantNewsletterJob;
use App\Models\TenantNewsletter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Campaniile de newsletter ale tenantului: subiect, text, un buton opțional, limba
 * abonaților. „Trimite un test” la o adresă, apoi „Trimite acum” (în coadă, cu
 * dezabonare personală în fiecare email). O campanie trimisă nu se mai poate edita.
 */
class NewsletterResource extends Resource
{
    protected static ?string $model = TenantNewsletter::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static \UnitEnum|string|null $navigationGroup = 'Website';
    protected static ?int $navigationSort = 39;
    protected static ?string $slug = 'newsletter';
    protected static ?string $modelLabel = 'campanie';
    protected static ?string $pluralModelLabel = 'Newsletter';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Website');
    }

    public static function getNavigationLabel(): string
    {
        return 'Newsletter';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()->tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Mesajul')->schema([
                Forms\Components\TextInput::make('subject')->label('Subiect')->required()->maxLength(190),
                Forms\Components\TextInput::make('preheader')->label('Text de previzualizare')->maxLength(190)
                    ->helperText('Rândul scurt pe care îl vede abonatul lângă subiect, în lista de emailuri.'),
                Forms\Components\RichEditor::make('body')->label('Textul')->required()
                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote', 'undo', 'redo']),
                SC\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('button_label')->label('Buton — text')->maxLength(80)->placeholder('Cumpără bilete'),
                    Forms\Components\TextInput::make('button_url')->label('Buton — link')->url()->maxLength(500)->placeholder('https://…'),
                ]),
            ]),
            SC\Section::make('Cui îi trimitem')->schema([
                Forms\Components\Select::make('locale')->label('Abonații')->options(TenantNewsletter::LOCALES)->default('all')->required()
                    ->helperText('Abonații își aleg limba când se abonează pe site (română sau engleză).'),
            ]),
        ])->disabled(fn ($record) => $record && $record->status !== 'draft');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject')->label('Subiect')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('locale')->label('Abonații')->formatStateUsing(fn ($state) => TenantNewsletter::LOCALES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->label('Stare')->badge()
                    ->formatStateUsing(fn ($state) => TenantNewsletter::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['draft' => 'gray', 'sending' => 'warning', 'sent' => 'success', 'failed' => 'danger'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('sent_count')->label('Trimise')->formatStateUsing(fn ($state, $record) => $record->status === 'draft' ? '—' : $state . ' / ' . $record->recipients_count),
                Tables\Columns\TextColumn::make('sent_at')->label('Trimis la')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])
            ->actions([
                EditAction::make()->label(fn ($record) => $record->status === 'draft' ? 'Editează' : 'Vezi'),
            ]);
    }

    /** „Trimite un test” și „Trimite acum”, pe pagina campaniei. */
    public static function sendActions(): array
    {
        return [
            Action::make('test')->label('Trimite un test')->icon('heroicon-o-beaker')->color('gray')
                ->schema([Forms\Components\TextInput::make('email')->label('Adresa de test')->email()->required()->default(fn () => auth()->user()->email)])
                ->action(function (array $data, $record) {
                    SendTenantNewsletterJob::dispatchSync($record->id, $data['email']);
                    Notification::make()->success()->title('Testul a fost trimis la ' . $data['email'])->send();
                }),
            Action::make('send')->label('Trimite acum')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible(fn ($record) => $record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Trimiți campania?')
                ->modalDescription(fn ($record) => 'Pleacă la ' . $record->audience()->count() . ' abonați. După trimitere nu se mai poate modifica.')
                ->action(function ($record) {
                    $n = $record->audience()->count();
                    if ($n === 0) {
                        Notification::make()->warning()->title('Nu ai abonați pentru limba aleasă.')->send();
                        return;
                    }
                    $record->forceFill(['status' => 'sending', 'recipients_count' => $n])->save();
                    SendTenantNewsletterJob::dispatch($record->id);
                    Notification::make()->success()->title('Campania se trimite la ' . $n . ' abonați.')->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
        ];
    }
}
