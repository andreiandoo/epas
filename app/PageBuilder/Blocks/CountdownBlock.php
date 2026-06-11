<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CountdownBlock extends BaseBlock
{
    public static string $type = 'countdown';
    public static string $name = 'Countdown Timer';
    public static string $description = 'Countdown timer to a specific date or event';
    public static string $icon = 'heroicon-o-clock';
    public static string $category = 'events';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('targetType')
                ->label('Countdown To')
                ->options([
                    'custom' => 'Custom Date',
                    'event' => 'Event Start',
                ])
                ->default('custom')
                ->live(),

            DateTimePicker::make('targetDate')
                ->label('Target Date & Time')
                ->required()
                ->visible(fn ($get) => $get('targetType') === 'custom'),

            Select::make('eventId')
                ->label('Select Event')
                ->options([]) // Populated dynamically
                ->searchable()
                ->visible(fn ($get) => $get('targetType') === 'event'),

            Select::make('style')
                ->label('Display Style')
                ->options([
                    'flip' => 'Flip Cards',
                    'simple' => 'Simple Numbers',
                    'circles' => 'Circular Progress',
                    'minimal' => 'Minimal Text',
                ])
                ->default('flip'),

            Select::make('size')
                ->label('Size')
                ->options([
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),

            Toggle::make('showDays')
                ->label('Show Days')
                ->default(true),

            Toggle::make('showHours')
                ->label('Show Hours')
                ->default(true),

            Toggle::make('showMinutes')
                ->label('Show Minutes')
                ->default(true),

            Toggle::make('showSeconds')
                ->label('Show Seconds')
                ->default(true),

            Toggle::make('showLabels')
                ->label('Show Labels')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Subtitle (shown when countdown ends)')
                ->maxLength(200),

            TextInput::make('expiredText')
                ->label('Expired Text')
                ->maxLength(200),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'targetType' => 'custom',
            'targetDate' => null,
            'eventId' => null,
            'style' => 'flip',
            'size' => 'md',
            'showDays' => true,
            'showHours' => true,
            'showMinutes' => true,
            'showSeconds' => true,
            'showLabels' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Event Starts In',
                'subtitle' => '',
                'expiredText' => 'Event has started!',
            ],
            'ro' => [
                'title' => 'Evenimentul începe în',
                'subtitle' => '',
                'expiredText' => 'Evenimentul a început!',
            ],
        ];
    }
}
