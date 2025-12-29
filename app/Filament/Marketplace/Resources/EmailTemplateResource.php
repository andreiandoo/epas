<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EmailTemplateResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceEmailTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplateResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceEmailTemplate::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Email Templates';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Template Details')
                    ->schema([
                        Forms\Components\Select::make('slug')
                            ->label('Template Type')
                            ->options(MarketplaceEmailTemplate::TEMPLATE_SLUGS)
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                $marketplace = static::getMarketplaceClient();
                                return $rule->where('marketplace_client_id', $marketplace?->id);
                            })
                            ->helperText('Each template type can only exist once'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal name for this template'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive templates will use system defaults'),
                    ])->columns(3),

                SC\Section::make('Email Content')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Supports variables like {{customer_name}}, {{order_number}}'),
                        Forms\Components\RichEditor::make('body_html')
                            ->label('HTML Body')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('HTML content of the email. Use {{variable_name}} for dynamic content.'),
                        Forms\Components\Textarea::make('body_text')
                            ->label('Plain Text Body')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Plain text version for email clients that don\'t support HTML'),
                    ]),

                SC\Section::make('Available Variables')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_help')
                            ->content(function ($get) {
                                $slug = $get('slug');
                                $variables = match($slug) {
                                    'ticket_purchase' => '{{customer_name}}, {{order_number}}, {{event_name}}, {{event_date}}, {{tickets_count}}, {{total_amount}}',
                                    'welcome' => '{{customer_name}}, {{customer_email}}, {{marketplace_name}}',
                                    'points_earned' => '{{customer_name}}, {{points_amount}}, {{reason}}, {{total_points}}',
                                    'refund_approved' => '{{customer_name}}, {{order_number}}, {{refund_amount}}, {{refund_reference}}',
                                    'refund_rejected' => '{{customer_name}}, {{order_number}}, {{rejection_reason}}',
                                    'event_reminder' => '{{customer_name}}, {{event_name}}, {{event_date}}, {{event_venue}}, {{tickets_count}}',
                                    'organizer_payout' => '{{organizer_name}}, {{payout_amount}}, {{payout_reference}}, {{period}}',
                                    'organizer_report' => '{{organizer_name}}, {{period}}, {{total_sales}}, {{commission}}, {{net_amount}}',
                                    default => 'Select a template type to see available variables',
                                };
                                return new \Illuminate\Support\HtmlString("<code class='text-sm'>{$variables}</code>");
                            })
                            ->label(''),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MarketplaceEmailTemplate::TEMPLATE_SLUGS[$state] ?? $state),
                Tables\Columns\TextColumn::make('subject')
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
                Tables\Filters\SelectFilter::make('slug')
                    ->label('Type')
                    ->options(MarketplaceEmailTemplate::TEMPLATE_SLUGS),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn ($record) => view('filament.marketplace.email-preview', ['template' => $record]))
                    ->modalHeading('Email Preview')
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
