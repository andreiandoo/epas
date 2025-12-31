<?php

namespace App\Filament\Tenant\Resources\Tracking;

use App\Filament\Tenant\Resources\Tracking\PersonTagResource\Pages;
use App\Models\Tracking\PersonTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonTagResource extends Resource
{
    protected static ?string $model = PersonTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tracking';

    protected static ?string $navigationLabel = 'Person Tags';

    protected static ?int $navigationSort = 50;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', filament()->getTenant()->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tag Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) =>
                                $set('slug', \Illuminate\Support\Str::slug($state))
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn($rule) =>
                                $rule->where('tenant_id', filament()->getTenant()->id)
                            ),

                        Forms\Components\Select::make('category')
                            ->options(PersonTag::CATEGORIES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, callable $set) =>
                                $set('color', PersonTag::CATEGORY_COLORS[$state] ?? '#6B7280')
                            ),

                        Forms\Components\ColorPicker::make('color')
                            ->default('#6B7280'),

                        Forms\Components\TextInput::make('icon')
                            ->placeholder('heroicon-o-tag')
                            ->helperText('Heroicon name (e.g., heroicon-o-star)'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority tags appear first'),

                        Forms\Components\Toggle::make('is_system')
                            ->label('System Tag')
                            ->helperText('System tags cannot be deleted')
                            ->disabled(fn($record) => $record?->is_system),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Auto-Tagging Rule')
                    ->schema([
                        Forms\Components\Toggle::make('is_auto')
                            ->label('Enable Auto-Tagging')
                            ->helperText('Automatically apply this tag based on conditions')
                            ->live(),

                        Forms\Components\Placeholder::make('rule_info')
                            ->content('Configure auto-tagging rules after creating the tag.')
                            ->visible(fn($get) => $get('is_auto') && !$get('id')),
                    ])
                    ->visible(fn($record) => $record === null || !$record->is_system),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(PersonTag $record) => $record->description),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn($state) => PersonTag::CATEGORIES[$state] ?? $state)
                    ->color(fn(PersonTag $record) => match ($record->category) {
                        'behavior' => 'info',
                        'demographic' => 'purple',
                        'preference' => 'pink',
                        'lifecycle' => 'success',
                        'engagement' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\ColorColumn::make('color'),

                Tables\Columns\TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Persons')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_auto')
                    ->label('Auto')
                    ->boolean()
                    ->trueIcon('heroicon-o-bolt')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(PersonTag::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('System Tags'),

                Tables\Filters\TernaryFilter::make('is_auto')
                    ->label('Auto-Tagging'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn(PersonTag $record) => $record->is_system),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(PersonTag $record) => $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filter out system tags
                            return $records->filter(fn($record) => !$record->is_system);
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_system_tags')
                    ->label('Initialize System Tags')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This will create all predefined system tags for your tenant.')
                    ->action(function () {
                        PersonTag::createSystemTags(filament()->getTenant()->id);
                    }),
            ]);
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
            'index' => Pages\ListPersonTags::route('/'),
            'create' => Pages\CreatePersonTag::route('/create'),
            'view' => Pages\ViewPersonTag::route('/{record}'),
            'edit' => Pages\EditPersonTag::route('/{record}/edit'),
        ];
    }
}
