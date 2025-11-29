<?php

namespace App\PageBuilder;

use App\PageBuilder\Blocks\BaseBlock;
use App\PageBuilder\Blocks\CategoryNavBlock;
use App\PageBuilder\Blocks\CtaBannerBlock;
use App\PageBuilder\Blocks\CustomHtmlBlock;
use App\PageBuilder\Blocks\DividerBlock;
use App\PageBuilder\Blocks\EventGridBlock;
use App\PageBuilder\Blocks\FeaturedEventBlock;
use App\PageBuilder\Blocks\HeroBlock;
use App\PageBuilder\Blocks\NewsletterBlock;
use App\PageBuilder\Blocks\PartnersBlock;
use App\PageBuilder\Blocks\SpacerBlock;
use App\PageBuilder\Blocks\TestimonialsBlock;
use App\PageBuilder\Blocks\TextContentBlock;
use App\PageBuilder\Blocks\TextImageBlock;

class BlockRegistry
{
    /**
     * Registered block classes
     * @var array<string, class-string<BaseBlock>>
     */
    protected static array $blocks = [];

    /**
     * Block categories
     */
    public const CATEGORIES = [
        'layout' => [
            'name' => 'Layout',
            'icon' => 'heroicon-o-squares-2x2',
        ],
        'events' => [
            'name' => 'Events',
            'icon' => 'heroicon-o-calendar',
        ],
        'content' => [
            'name' => 'Content',
            'icon' => 'heroicon-o-document-text',
        ],
        'navigation' => [
            'name' => 'Navigation',
            'icon' => 'heroicon-o-bars-3',
        ],
        'marketing' => [
            'name' => 'Marketing',
            'icon' => 'heroicon-o-megaphone',
        ],
        'social-proof' => [
            'name' => 'Social Proof',
            'icon' => 'heroicon-o-star',
        ],
        'advanced' => [
            'name' => 'Advanced',
            'icon' => 'heroicon-o-code-bracket',
        ],
    ];

    /**
     * Register default blocks
     */
    public static function registerDefaults(): void
    {
        static::register(HeroBlock::class);
        static::register(EventGridBlock::class);
        static::register(FeaturedEventBlock::class);
        static::register(CategoryNavBlock::class);
        static::register(TextContentBlock::class);
        static::register(TextImageBlock::class);
        static::register(CtaBannerBlock::class);
        static::register(NewsletterBlock::class);
        static::register(TestimonialsBlock::class);
        static::register(PartnersBlock::class);
        static::register(SpacerBlock::class);
        static::register(DividerBlock::class);
        static::register(CustomHtmlBlock::class);
    }

    /**
     * Register a block class
     * @param class-string<BaseBlock> $class
     */
    public static function register(string $class): void
    {
        if (!is_subclass_of($class, BaseBlock::class)) {
            throw new \InvalidArgumentException("Block class must extend BaseBlock");
        }

        static::$blocks[$class::$type] = $class;
    }

    /**
     * Get block class by type
     * @return class-string<BaseBlock>|null
     */
    public static function get(string $type): ?string
    {
        return static::$blocks[$type] ?? null;
    }

    /**
     * Get all registered blocks
     * @return array<string, class-string<BaseBlock>>
     */
    public static function all(): array
    {
        return static::$blocks;
    }

    /**
     * Get all block definitions for the frontend
     */
    public static function getDefinitions(): array
    {
        $definitions = [];

        foreach (static::$blocks as $type => $class) {
            $definitions[] = $class::getDefinition();
        }

        return $definitions;
    }

    /**
     * Get blocks grouped by category
     */
    public static function getByCategory(): array
    {
        $grouped = [];

        foreach (static::CATEGORIES as $key => $category) {
            $grouped[$key] = [
                'name' => $category['name'],
                'icon' => $category['icon'],
                'blocks' => [],
            ];
        }

        foreach (static::$blocks as $type => $class) {
            $category = $class::$category;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'name' => ucfirst($category),
                    'icon' => 'heroicon-o-cube',
                    'blocks' => [],
                ];
            }
            $grouped[$category]['blocks'][] = $class::getDefinition();
        }

        // Remove empty categories
        return array_filter($grouped, fn($cat) => !empty($cat['blocks']));
    }

    /**
     * Check if a block type exists
     */
    public static function has(string $type): bool
    {
        return isset(static::$blocks[$type]);
    }

    /**
     * Create a new block instance
     */
    public static function create(string $type): ?array
    {
        $class = static::get($type);
        if (!$class) {
            return null;
        }

        return $class::create();
    }

    /**
     * Get settings schema for a block type
     */
    public static function getSettingsSchema(string $type): array
    {
        $class = static::get($type);
        return $class ? $class::getSettingsSchema() : [];
    }

    /**
     * Get content schema for a block type
     */
    public static function getContentSchema(string $type): array
    {
        $class = static::get($type);
        return $class ? $class::getContentSchema() : [];
    }

    /**
     * Validate block data
     */
    public static function validate(array $block): array
    {
        if (!isset($block['type'])) {
            return ['type' => 'Block type is required'];
        }

        $class = static::get($block['type']);
        if (!$class) {
            return ['type' => 'Unknown block type'];
        }

        return $class::validate($block);
    }

    /**
     * Get block picker data for the editor UI
     */
    public static function getPickerData(): array
    {
        return [
            'categories' => static::CATEGORIES,
            'blocks' => static::getByCategory(),
        ];
    }
}
