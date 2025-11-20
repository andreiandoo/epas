<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;

class TranslatableField
{
    protected static array $locales = ['en', 'ro'];

    protected static array $localeLabels = [
        'en' => 'English',
        'ro' => 'Romanian',
    ];

    protected static array $localeIcons = [
        'en' => 'heroicon-o-globe-alt',
        'ro' => 'heroicon-o-flag',
    ];

    /**
     * Create a translatable text input field
     */
    public static function make(string $name, string $label): Tabs
    {
        return self::createTabs($name, $label, 'text');
    }

    /**
     * Create a translatable textarea field
     */
    public static function textarea(string $name, string $label, int $rows = 3): Tabs
    {
        return self::createTabs($name, $label, 'textarea', ['rows' => $rows]);
    }

    /**
     * Create a translatable rich editor field
     */
    public static function richEditor(string $name, string $label): Tabs
    {
        return self::createTabs($name, $label, 'richEditor');
    }

    protected static function createTabs(string $name, string $label, string $type, array $options = []): Tabs
    {
        $tabs = [];

        foreach (self::$locales as $locale) {
            $fieldName = "{$name}.{$locale}";

            $input = match ($type) {
                'textarea' => Textarea::make($fieldName)
                    ->hiddenLabel()
                    ->rows($options['rows'] ?? 3),

                'richEditor' => RichEditor::make($fieldName)
                    ->hiddenLabel(),

                default => TextInput::make($fieldName)
                    ->hiddenLabel(),
            };

            $tabs[] = Tab::make(self::$localeLabels[$locale])
                ->icon(self::$localeIcons[$locale])
                ->schema([$input]);
        }

        return Tabs::make($name)
            ->label($label)
            ->tabs($tabs)
            ->contained(false);
    }
}
