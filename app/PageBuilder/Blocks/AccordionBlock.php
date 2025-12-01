<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AccordionBlock extends BaseBlock
{
    public static string $type = 'accordion';
    public static string $name = 'Accordion / FAQ';
    public static string $description = 'Expandable FAQ-style content sections';
    public static string $icon = 'heroicon-o-bars-3-bottom-left';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Style')
                ->options([
                    'default' => 'Default',
                    'bordered' => 'Bordered',
                    'separated' => 'Separated Cards',
                    'minimal' => 'Minimal',
                ])
                ->default('default'),

            Toggle::make('allowMultiple')
                ->label('Allow Multiple Open')
                ->default(false)
                ->helperText('Allow multiple items to be open at once'),

            Toggle::make('firstOpen')
                ->label('First Item Open by Default')
                ->default(true),

            Select::make('iconPosition')
                ->label('Icon Position')
                ->options([
                    'left' => 'Left',
                    'right' => 'Right',
                ])
                ->default('right'),

            Select::make('iconStyle')
                ->label('Icon Style')
                ->options([
                    'chevron' => 'Chevron',
                    'plus' => 'Plus/Minus',
                    'arrow' => 'Arrow',
                ])
                ->default('chevron'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Section Subtitle')
                ->maxLength(500),

            Repeater::make('items')
                ->label('Accordion Items')
                ->schema([
                    TextInput::make('question')
                        ->label('Question / Title')
                        ->required()
                        ->maxLength(200),

                    RichEditor::make('answer')
                        ->label('Answer / Content')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'link',
                            'bulletList',
                            'orderedList',
                        ]),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'default',
            'allowMultiple' => false,
            'firstOpen' => true,
            'iconPosition' => 'right',
            'iconStyle' => 'chevron',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Frequently Asked Questions',
                'subtitle' => '',
                'items' => [
                    [
                        'question' => 'How do I purchase tickets?',
                        'answer' => '<p>Simply browse our events, select the one you want to attend, and follow the checkout process.</p>',
                    ],
                    [
                        'question' => 'Can I get a refund?',
                        'answer' => '<p>Refund policies vary by event. Please check the specific event details for refund information.</p>',
                    ],
                    [
                        'question' => 'How will I receive my tickets?',
                        'answer' => '<p>Tickets are delivered electronically to your email immediately after purchase.</p>',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Întrebări Frecvente',
                'subtitle' => '',
                'items' => [
                    [
                        'question' => 'Cum pot cumpăra bilete?',
                        'answer' => '<p>Navighează prin evenimentele noastre, selectează cel dorit și finalizează achiziția.</p>',
                    ],
                    [
                        'question' => 'Pot obține o rambursare?',
                        'answer' => '<p>Politicile de rambursare variază în funcție de eveniment. Verifică detaliile fiecărui eveniment.</p>',
                    ],
                    [
                        'question' => 'Cum voi primi biletele?',
                        'answer' => '<p>Biletele sunt livrate electronic pe email imediat după achiziție.</p>',
                    ],
                ],
            ],
        ];
    }
}
