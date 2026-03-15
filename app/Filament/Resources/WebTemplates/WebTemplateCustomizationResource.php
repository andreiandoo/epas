<?php

namespace App\Filament\Resources\WebTemplates;

use App\Enums\WebTemplateCategory;
use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;

class WebTemplateCustomizationResource extends Resource
{
    protected static ?string $model = WebTemplateCustomization::class;

    protected static UnitEnum|string|null $navigationGroup = 'Web Templates';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Personalizări';
    protected static ?string $modelLabel = 'Personalizare';
    protected static ?string $pluralModelLabel = 'Personalizări';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Selectare Template')
                ->schema([
                    Forms\Components\Select::make('web_template_id')
                        ->label('Template')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if (!$state) return;
                            $template = WebTemplate::find($state);
                            if ($template) {
                                $set('_template_category', $template->category->value);
                            }
                        }),

                    Forms\Components\TextInput::make('label')
                        ->label('Denumire Personalizare')
                        ->placeholder('ex: Demo pentru Teatrul Național')
                        ->maxLength(255),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant (opțional)')
                        ->relationship('tenant', 'public_name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Activ',
                            'expired' => 'Expirat',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expiră la')
                        ->nullable(),

                    Forms\Components\TextInput::make('preview_password')
                        ->label('Parolă Preview (opțional)')
                        ->password()
                        ->revealable()
                        ->placeholder('Lasă gol pentru acces liber')
                        ->helperText('Dacă este setat, vizitatorii vor trebui să introducă parola pentru a vedea preview-ul.'),
                ])
                ->columns(2),

            // Dynamic Customization Wizard — auto-generated from template's customizable_fields
            SC\Section::make('Wizard Personalizare')
                ->description('Câmpuri generate automat din definiția template-ului selectat')
                ->schema(function (\Filament\Schemas\Components\Utilities\Get $get): array {
                    $templateId = $get('web_template_id');
                    if (!$templateId) {
                        return [
                            Forms\Components\Placeholder::make('wizard_placeholder')
                                ->content('Selectează un template pentru a vedea câmpurile de personalizare.'),
                        ];
                    }

                    $template = WebTemplate::find($templateId);
                    if (!$template || empty($template->customizable_fields)) {
                        return [
                            Forms\Components\Placeholder::make('no_fields_placeholder')
                                ->content('Acest template nu are câmpuri personalizabile definite.'),
                        ];
                    }

                    $fields = [];
                    $grouped = collect($template->customizable_fields)->groupBy('group');

                    foreach ($grouped as $group => $groupFields) {
                        if ($group) {
                            $fields[] = Forms\Components\Placeholder::make('group_' . \Illuminate\Support\Str::slug($group))
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">' . e(ucfirst($group)) . '</span>'
                                ))
                                ->columnSpanFull();
                        }

                        foreach ($groupFields as $fieldDef) {
                            $key = 'customization_data.' . $fieldDef['key'];
                            $label = $fieldDef['label'] ?? $fieldDef['key'];
                            $default = $fieldDef['default'] ?? null;

                            $field = match ($fieldDef['type'] ?? 'text') {
                                'text' => Forms\Components\TextInput::make($key)
                                    ->label($label)
                                    ->default($default),

                                'textarea' => Forms\Components\Textarea::make($key)
                                    ->label($label)
                                    ->rows(3)
                                    ->default($default),

                                'color' => Forms\Components\ColorPicker::make($key)
                                    ->label($label)
                                    ->default($default),

                                'image' => Forms\Components\FileUpload::make($key)
                                    ->label($label)
                                    ->image()
                                    ->directory('web-templates/customizations'),

                                'url' => Forms\Components\TextInput::make($key)
                                    ->label($label)
                                    ->url()
                                    ->default($default),

                                'select' => Forms\Components\Select::make($key)
                                    ->label($label)
                                    ->options(
                                        is_array($fieldDef['options'] ?? null)
                                            ? array_combine($fieldDef['options'], $fieldDef['options'])
                                            : []
                                    )
                                    ->default($default),

                                'toggle' => Forms\Components\Toggle::make($key)
                                    ->label($label)
                                    ->default((bool) $default),

                                default => Forms\Components\TextInput::make($key)
                                    ->label($label)
                                    ->default($default),
                            };

                            $fields[] = $field;
                        }
                    }

                    return $fields;
                })
                ->columns(2)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => !empty($get('web_template_id'))),

            SC\Section::make('Override-uri Date Demo')
                ->description('Modificări manuale peste datele demo default ale template-ului')
                ->schema([
                    Forms\Components\KeyValue::make('demo_data_overrides')
                        ->label('Override-uri')
                        ->keyLabel('Cheie')
                        ->valueLabel('Valoare')
                        ->addActionLabel('Adaugă override'),
                ])
                ->collapsed(),

            SC\Section::make('Link Preview')
                ->schema([
                    Forms\Components\Placeholder::make('unique_token_display')
                        ->label('Token Unic')
                        ->content(fn (?WebTemplateCustomization $record) => $record?->unique_token ?? 'Se generează automat la salvare'),

                    Forms\Components\Placeholder::make('preview_link')
                        ->label('Link Preview')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->template) {
                                return 'Salvează pentru a genera link-ul de preview';
                            }
                            $url = route('web-template.customized-preview', [
                                'templateSlug' => $record->template->slug,
                                'token' => $record->unique_token,
                            ]);
                            return new \Illuminate\Support\HtmlString(
                                "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 hover:underline\">{$url}</a>"
                            );
                        }),

                    Forms\Components\Placeholder::make('demo_url_display')
                        ->label('URL Demo Public')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->template) {
                                return 'Salvează pentru a genera URL-ul demo';
                            }
                            $url = $record->getPreviewUrl();
                            return new \Illuminate\Support\HtmlString(
                                "<span class=\"text-sm text-gray-500\">{$url}</span>"
                            );
                        }),

                    Forms\Components\Placeholder::make('view_stats')
                        ->label('Statistici')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record) return '-';
                            $lastViewed = $record->last_viewed_at
                                ? $record->last_viewed_at->diffForHumans()
                                : 'niciodată';
                            return "{$record->viewed_count} vizualizări · Ultima: {$lastViewed}";
                        }),

                    Forms\Components\Placeholder::make('utm_stats')
                        ->label('Surse UTM')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || empty($record->utm_data)) return 'Nicio intrare UTM.';
                            $sources = collect($record->utm_data)->groupBy('utm_source')->map->count()->sortDesc()->take(5);
                            $parts = $sources->map(fn ($count, $source) => "{$source}: {$count}")->values()->implode(' · ');
                            return "Top surse: {$parts} (" . count($record->utm_data) . " total)";
                        }),
                ])
                ->visible(fn (?WebTemplateCustomization $record) => $record !== null),

            SC\Section::make('Self-Service Client')
                ->description('Permite clientului să-și editeze singur datele de personalizare printr-un link special')
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generateSelfServiceToken')
                            ->label('Generează Link Self-Service')
                            ->icon('heroicon-o-key')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalDescription('Se va genera un link unic prin care clientul poate edita câmpurile permise.')
                            ->action(function (WebTemplateCustomization $record) {
                                $record->generateSelfServiceToken();
                                \Filament\Notifications\Notification::make()
                                    ->title('Link self-service generat')
                                    ->success()
                                    ->send();
                            })
                            ->visible(fn (?WebTemplateCustomization $record) => $record && empty($record->self_service_token)),
                    ]),

                    Forms\Components\Placeholder::make('self_service_link')
                        ->label('Link Self-Service')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->self_service_token) {
                                return 'Generează un token pentru a crea link-ul self-service.';
                            }
                            $url = $record->getSelfServiceUrl();
                            return new \Illuminate\Support\HtmlString(
                                "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 hover:underline\">{$url}</a>"
                            );
                        }),

                    Forms\Components\CheckboxList::make('self_service_fields')
                        ->label('Câmpuri permise (lasă gol = toate)')
                        ->options(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->template) return [];
                            return collect($record->template->customizable_fields ?? [])
                                ->mapWithKeys(fn ($f) => [$f['key'] => $f['label'] ?? $f['key']])
                                ->toArray();
                        })
                        ->helperText('Selectează ce câmpuri poate edita clientul. Dacă nu selectezi nimic, toate câmpurile sunt disponibile.'),
                ])
                ->visible(fn (?WebTemplateCustomization $record) => $record !== null)
                ->collapsed(),

            SC\Section::make('Feedback Prospecți')
                ->schema([
                    Forms\Components\Placeholder::make('feedback_summary')
                        ->label('Sumar')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record) return '-';
                            $count = $record->feedbacks()->count();
                            if ($count === 0) return 'Niciun feedback primit încă.';
                            $avg = $record->getAverageRating();
                            $stars = str_repeat('★', (int) round($avg)) . str_repeat('☆', 5 - (int) round($avg));
                            return new \Illuminate\Support\HtmlString(
                                "<span class=\"text-lg\">{$stars}</span> <span class=\"font-semibold\">{$avg}/5</span> · {$count} feedback-uri"
                            );
                        }),

                    Forms\Components\Placeholder::make('feedback_list')
                        ->label('Ultimele feedback-uri')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record) return '-';
                            $feedbacks = $record->feedbacks()->latest()->take(10)->get();
                            if ($feedbacks->isEmpty()) return 'Niciun feedback.';

                            $html = '<div class="space-y-3">';
                            foreach ($feedbacks as $fb) {
                                $stars = str_repeat('★', $fb->rating) . str_repeat('☆', 5 - $fb->rating);
                                $name = $fb->name ?: 'Anonim';
                                $company = $fb->company ? " · {$fb->company}" : '';
                                $comment = $fb->comment ? "<p class=\"text-sm text-gray-600 mt-1\">" . e($fb->comment) . "</p>" : '';
                                $date = $fb->created_at->diffForHumans();
                                $html .= "<div class=\"border rounded-lg p-3\"><div class=\"flex items-center justify-between\"><span class=\"text-amber-500\">{$stars}</span><span class=\"text-xs text-gray-400\">{$date}</span></div><div class=\"text-sm font-medium mt-1\">{$name}{$company}</div>{$comment}</div>";
                            }
                            $html .= '</div>';
                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ])
                ->visible(fn (?WebTemplateCustomization $record) => $record !== null)
                ->collapsed(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template.category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn (WebTemplateCategory $state) => $state->label())
                    ->color(fn (WebTemplateCategory $state) => match ($state) {
                        WebTemplateCategory::SimpleOrganizer => 'info',
                        WebTemplateCategory::Marketplace => 'success',
                        WebTemplateCategory::ArtistAgency => 'warning',
                        WebTemplateCategory::Theater => 'danger',
                        WebTemplateCategory::Festival => 'primary',
                        WebTemplateCategory::Stadium => 'gray',
                    }),

                Tables\Columns\TextColumn::make('label')
                    ->label('Denumire')
                    ->searchable()
                    ->placeholder('(fără denumire)'),

                Tables\Columns\TextColumn::make('unique_token')
                    ->label('Token')
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\IconColumn::make('preview_password')
                    ->label('Protejat')
                    ->boolean()
                    ->getStateUsing(fn (WebTemplateCustomization $record) => !empty($record->preview_password))
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('tenant.public_name')
                    ->label('Tenant')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('viewed_count')
                    ->label('Vizualizări')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('web_template_id')
                    ->label('Template')
                    ->relationship('template', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Activ',
                        'expired' => 'Expirat',
                    ]),

                Tables\Filters\TernaryFilter::make('has_password')
                    ->label('Protejat cu parolă')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('preview_password')->where('preview_password', '!=', ''),
                        false: fn ($q) => $q->where(fn ($q) => $q->whereNull('preview_password')->orWhere('preview_password', '')),
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('openPreview')
                        ->label('Deschide Preview')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (WebTemplateCustomization $record) => route('web-template.customized-preview', [
                            'templateSlug' => $record->template->slug,
                            'token' => $record->unique_token,
                        ]))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('copyLink')
                        ->label('Copiază Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->action(function (WebTemplateCustomization $record) {
                            // Handled via JS in frontend
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebTemplateCustomizations::route('/'),
            'create' => Pages\CreateWebTemplateCustomization::route('/create'),
            'edit' => Pages\EditWebTemplateCustomization::route('/{record}/edit'),
        ];
    }
}
