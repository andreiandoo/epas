<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Component;

abstract class BaseBlock
{
    /**
     * Block type identifier
     */
    public static string $type = '';

    /**
     * Display name
     */
    public static string $name = '';

    /**
     * Block description
     */
    public static string $description = '';

    /**
     * Heroicon name
     */
    public static string $icon = 'heroicon-o-cube';

    /**
     * Block category for grouping in picker
     */
    public static string $category = 'content';

    /**
     * Get the settings schema for Filament forms
     * @return array<Component>
     */
    abstract public static function getSettingsSchema(): array;

    /**
     * Get the content schema for Filament forms (translatable fields)
     * @return array<Component>
     */
    abstract public static function getContentSchema(): array;

    /**
     * Get default settings
     */
    public static function getDefaultSettings(): array
    {
        return [];
    }

    /**
     * Get default content
     */
    public static function getDefaultContent(): array
    {
        return [
            'en' => [],
            'ro' => [],
        ];
    }

    /**
     * Get block definition for frontend
     */
    public static function getDefinition(): array
    {
        return [
            'type' => static::$type,
            'name' => static::$name,
            'description' => static::$description,
            'icon' => static::$icon,
            'category' => static::$category,
            'defaultSettings' => static::getDefaultSettings(),
            'defaultContent' => static::getDefaultContent(),
        ];
    }

    /**
     * Create a new block instance with defaults
     */
    public static function create(): array
    {
        return [
            'id' => static::$type . '_' . uniqid(),
            'type' => static::$type,
            'settings' => static::getDefaultSettings(),
            'content' => static::getDefaultContent(),
        ];
    }

    /**
     * Validate block data
     */
    public static function validate(array $block): array
    {
        $errors = [];

        if (!isset($block['type']) || $block['type'] !== static::$type) {
            $errors['type'] = 'Invalid block type';
        }

        return $errors;
    }
}
