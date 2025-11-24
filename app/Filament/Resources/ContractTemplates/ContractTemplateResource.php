<?php

namespace App\Filament\Resources\ContractTemplates;

use App\Filament\Resources\ContractTemplates\Pages;
use App\Models\ContractTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Contract Templates';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
                SC\Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', \Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->columns(2),

                SC\Section::make('Business Model Assignment')
                    ->description('Assign this template to specific business models. Leave empty to use as default.')
                    ->schema([
                        Forms\Components\Select::make('work_method')
                            ->label('Work Method')
                            ->options([
                                'exclusive' => 'Exclusive (1%)',
                                'mixed' => 'Mixed (2%)',
                                'reseller' => 'Reseller (3%)',
                            ])
                            ->placeholder('All work methods')
                            ->helperText('Select a specific work method or leave empty for all'),

                        Forms\Components\Select::make('plan')
                            ->label('Commission Plan')
                            ->options([
                                '1percent' => '1% Commission',
                                '2percent' => '2% Commission',
                                '3percent' => '3% Commission',
                            ])
                            ->placeholder('All plans')
                            ->helperText('Select a specific plan or leave empty for all'),

                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'ro' => 'Romanian (Română)',
                                'hu' => 'Hungarian (Magyar)',
                                'de' => 'German (Deutsch)',
                                'fr' => 'French (Français)',
                            ])
                            ->default('en')
                            ->helperText('Template language - matched to tenant locale'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Use this template when no specific match is found')
                            ->inline(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),

                SC\Section::make('Contract Content')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_help')
                            ->label('Available Variables')
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                '<div class="text-sm space-y-1">' .
                                '<p class="font-medium text-gray-700">Click to copy variable to clipboard:</p>' .
                                '<div class="flex flex-wrap gap-1 mt-2">' .
                                collect(ContractTemplate::getDefaultVariables())->map(function ($desc, $var) {
                                    return '<span class="inline-flex items-center px-2 py-1 text-xs font-mono bg-gray-100 rounded cursor-pointer hover:bg-gray-200" onclick="navigator.clipboard.writeText(\'' . $var . '\'); this.classList.add(\'bg-green-100\'); setTimeout(() => this.classList.remove(\'bg-green-100\'), 500);" title="' . htmlspecialchars($desc) . '">' . $var . '</span>';
                                })->join('') .
                                '</div></div>'
                            )),

                        Forms\Components\RichEditor::make('content')
                            ->label('Contract Template Content')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'link',
                                'blockquote',
                                'codeBlock',
                                'redo',
                                'undo',
                            ])
                            ->columnSpanFull()
                            ->helperText('Use the variables above to personalize the contract for each tenant.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('work_method')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'exclusive' => 'success',
                        'mixed' => 'warning',
                        'reseller' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'exclusive' => 'Exclusive (1%)',
                        'mixed' => 'Mixed (2%)',
                        'reseller' => 'Reseller (3%)',
                        default => 'All',
                    }),

                Tables\Columns\TextColumn::make('plan')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => $state ?? 'All'),

                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('tenants_count')
                    ->counts('tenants')
                    ->label('Used By'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('work_method')
                    ->options([
                        'exclusive' => 'Exclusive (1%)',
                        'mixed' => 'Mixed (2%)',
                        'reseller' => 'Reseller (3%)',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'create' => Pages\CreateContractTemplate::route('/create'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }
}
