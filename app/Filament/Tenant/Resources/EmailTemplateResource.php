<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EmailTemplateResource\Pages;
use App\Models\Marketplace\MarketplaceEmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = MarketplaceEmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Email Templates';
    protected static ?string $modelLabel = 'Email Template';
    protected static ?string $pluralModelLabel = 'Email Templates';

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();
        return $tenant && $tenant->isMarketplace();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = filament()->getTenant();

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable name for this template'),

                        Forms\Components\Select::make('event_trigger')
                            ->label('Event Trigger')
                            ->options(MarketplaceEmailTemplate::EVENT_TRIGGERS)
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $variables = MarketplaceEmailTemplate::TRIGGER_VARIABLES[$state] ?? [];
                                $set('available_variables', $variables);
                            })
                            ->helperText('Platform action that triggers this email'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Internal notes about when this template is used'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active templates will be used'),
                    ])->columns(2),

                Forms\Components\Section::make('Email Content')
                    ->description('Use {{variable_name}} syntax to insert dynamic content')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_info')
                            ->label('Available Variables')
                            ->content(function (Forms\Get $get) {
                                $trigger = $get('event_trigger');
                                $variables = MarketplaceEmailTemplate::TRIGGER_VARIABLES[$trigger] ?? [];

                                if (empty($variables)) {
                                    return 'Select an event trigger to see available variables.';
                                }

                                $tags = array_map(fn($v) => "{{$v}}", $variables);
                                return implode(', ', $tags);
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('subject')
                            ->label('Email Subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Welcome {{organizer_name}} to {{marketplace_name}}!')
                            ->helperText('Use {{variable_name}} to insert variables'),

                        Forms\Components\RichEditor::make('body')
                            ->label('Email Body')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                            ])
                            ->columnSpanFull()
                            ->helperText('Use {{variable_name}} placeholders for dynamic content'),

                        Forms\Components\TagsInput::make('available_variables')
                            ->label('Variables in Use')
                            ->disabled()
                            ->columnSpanFull()
                            ->helperText('These variables are available for this template type'),
                    ]),

                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn () => filament()->getTenant()?->id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_trigger')
                    ->label('Event Trigger')
                    ->formatStateUsing(fn ($state) => MarketplaceEmailTemplate::EVENT_TRIGGERS[$state] ?? $state)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_trigger')
                    ->label('Event Trigger')
                    ->options(MarketplaceEmailTemplate::EVENT_TRIGGERS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Preview: ' . $record->name)
                    ->modalContent(fn ($record) => view('filament.tenant.components.email-preview', [
                        'subject' => $record->subject,
                        'body' => $record->body,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
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
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
