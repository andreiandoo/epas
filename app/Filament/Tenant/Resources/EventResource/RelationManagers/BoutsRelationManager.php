<?php

namespace App\Filament\Tenant\Resources\EventResource\RelationManagers;

use App\Enums\TenantType;
use App\Models\EventBout;
use App\Models\TenantArtist;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila „Fight card” a evenimentului (doar organizatorii de competiții): meciurile
 * galei, ordinea pe afiș, categoria, centura și rezultatul după gală. Site-ul
 * tenantului le citește din /tenant-client/events/{slug}/fight-card.
 */
class BoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'bouts';

    protected static ?string $title = 'Fight card';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $tenant = auth()->user()?->tenant;

        return $tenant?->tenant_type === TenantType::Competition || $ownerRecord->bouts()->exists();
    }

    private function fighterOptions(): array
    {
        $tenantId = auth()->user()?->tenant?->id;

        return TenantArtist::where('tenant_id', $tenantId)->where('role', 'fighter')->orderBy('name')
            ->get()->mapWithKeys(fn ($f) => [$f->id => $f->name . (! empty($f->meta['nickname']) ? ' „' . $f->meta['nickname'] . '”' : '')])
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        $fighters = $this->fighterOptions();

        return $schema->schema([
            SC\Section::make('Meciul')->schema([
                Forms\Components\Select::make('card_section')->label('Secțiunea')->options(EventBout::SECTIONS)->default('main_card')->required(),
                Forms\Components\TextInput::make('sort_order')->label('Ordinea în secțiune')->numeric()->default(0)->helperText('0 = primul'),
                Forms\Components\Select::make('fighter_a_id')->label('Colțul roșu')->options($fighters)->searchable(),
                Forms\Components\TextInput::make('fighter_a_name')->label('… sau nume (TBA)')->maxLength(120),
                Forms\Components\Select::make('fighter_b_id')->label('Colțul albastru')->options($fighters)->searchable(),
                Forms\Components\TextInput::make('fighter_b_name')->label('… sau nume (TBA)')->maxLength(120),
                Forms\Components\TextInput::make('weight_class')->label('Categoria')->placeholder('Ușoară (70 kg)'),
                Forms\Components\Select::make('discipline')->label('Disciplina')->options(EventBout::DISCIPLINES)->default('mma'),
                Forms\Components\TextInput::make('rounds')->label('Runde')->numeric()->default(3),
                Forms\Components\TextInput::make('round_minutes')->label('Minute / rundă')->numeric()->default(5),
                Forms\Components\Toggle::make('is_title_fight')->label('Meci pentru centură')->live(),
                Forms\Components\TextInput::make('title_name')->label('Centura')->visible(fn ($get) => (bool) $get('is_title_fight')),
            ])->columns(2),
            SC\Section::make('Rezultat')->description('Se completează după gală; apare în arhivă și în istoricul luptătorilor.')->schema([
                Forms\Components\Select::make('status')->label('Stare')->options([
                    'scheduled' => 'Programat', 'confirmed' => 'Confirmat', 'completed' => 'Disputat', 'cancelled' => 'Anulat',
                ])->default('scheduled'),
                Forms\Components\Select::make('winner')->label('Câștigător')->options(['a' => 'Colțul roșu', 'b' => 'Colțul albastru', 'draw' => 'Egal', 'nc' => 'Fără decizie']),
                Forms\Components\TextInput::make('method')->label('Metoda')->placeholder('KO/TKO, Submission, Decizie unanimă'),
                Forms\Components\TextInput::make('result_round')->label('Runda')->numeric(),
                Forms\Components\TextInput::make('result_time')->label('Timp (mm:ss)')->maxLength(8),
            ])->columns(3)->collapsible(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('card_section')->label('Secțiune')->badge()
                    ->formatStateUsing(fn ($state) => EventBout::SECTIONS[$state] ?? $state),
                Tables\Columns\TextColumn::make('sort_order')->label('#'),
                Tables\Columns\TextColumn::make('fighter_a')->label('Roșu')
                    ->state(fn (EventBout $r) => $r->fighterA?->name ?? $r->fighter_a_name ?? 'TBA'),
                Tables\Columns\TextColumn::make('fighter_b')->label('Albastru')
                    ->state(fn (EventBout $r) => $r->fighterB?->name ?? $r->fighter_b_name ?? 'TBA'),
                Tables\Columns\TextColumn::make('weight_class')->label('Categoria'),
                Tables\Columns\IconColumn::make('is_title_fight')->label('Centură')->boolean(),
                Tables\Columns\TextColumn::make('status')->label('Stare')->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Adaugă meci')
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['tenant_id' => auth()->user()?->tenant?->id]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
