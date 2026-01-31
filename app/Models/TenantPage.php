<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPage extends Model
{
    use Translatable;

    public const TYPE_CONTENT = 'content';
    public const TYPE_BUILDER = 'builder';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'title',
        'slug',
        'page_type',
        'content',
        'layout',
        'menu_location',
        'menu_order',
        'is_published',
        'is_system',
        'meta',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'layout' => 'array',
        'meta' => 'array',
        'is_published' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected array $translatable = ['title', 'content'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TenantPage::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TenantPage::class, 'parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInMenu($query, string $location)
    {
        return $query->where('menu_location', $location)
            ->where('is_published', true)
            ->orderBy('menu_order');
    }

    public function scopeHeaderMenu($query)
    {
        return $query->inMenu('header');
    }

    public function scopeFooterMenu($query)
    {
        return $query->inMenu('footer');
    }

    public function scopeBuilder($query)
    {
        return $query->where('page_type', self::TYPE_BUILDER);
    }

    public function scopeContent($query)
    {
        return $query->where('page_type', self::TYPE_CONTENT);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function isBuilder(): bool
    {
        return $this->page_type === self::TYPE_BUILDER;
    }

    public function isContent(): bool
    {
        return $this->page_type === self::TYPE_CONTENT;
    }

    public function isSystem(): bool
    {
        return $this->is_system === true;
    }

    /**
     * Get blocks from layout
     */
    public function getBlocks(): array
    {
        return $this->layout['blocks'] ?? [];
    }

    /**
     * Update blocks in layout
     */
    public function updateBlocks(array $blocks): void
    {
        $this->update([
            'layout' => ['blocks' => $blocks]
        ]);
    }

    /**
     * Add a block to the layout
     */
    public function addBlock(array $block, ?int $position = null): void
    {
        $blocks = $this->getBlocks();

        if ($position !== null && $position >= 0 && $position <= count($blocks)) {
            array_splice($blocks, $position, 0, [$block]);
        } else {
            $blocks[] = $block;
        }

        $this->updateBlocks($blocks);
    }

    /**
     * Remove a block from the layout
     */
    public function removeBlock(string $blockId): void
    {
        $blocks = array_filter($this->getBlocks(), fn($block) => ($block['id'] ?? '') !== $blockId);
        $this->updateBlocks(array_values($blocks));
    }

    /**
     * Update a specific block in the layout
     */
    public function updateBlock(string $blockId, array $data): void
    {
        $blocks = $this->getBlocks();

        foreach ($blocks as $index => $block) {
            if (($block['id'] ?? '') === $blockId) {
                $blocks[$index] = array_merge($block, $data);
                break;
            }
        }

        $this->updateBlocks($blocks);
    }

    /**
     * Reorder blocks
     */
    public function reorderBlocks(array $blockIds): void
    {
        $blocks = $this->getBlocks();
        $blocksById = [];

        foreach ($blocks as $block) {
            if (isset($block['id'])) {
                $blocksById[$block['id']] = $block;
            }
        }

        $reordered = [];
        foreach ($blockIds as $id) {
            if (isset($blocksById[$id])) {
                $reordered[] = $blocksById[$id];
            }
        }

        $this->updateBlocks($reordered);
    }

    /**
     * Get block by ID
     */
    public function getBlock(string $blockId): ?array
    {
        foreach ($this->getBlocks() as $block) {
            if (($block['id'] ?? '') === $blockId) {
                return $block;
            }
        }
        return null;
    }

    /**
     * Create default system pages for a tenant
     */
    public static function createDefaultPages(Tenant $tenant): void
    {
        // Home page
        self::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'home'],
            [
                'title' => ['en' => 'Home', 'ro' => 'Acasă'],
                'page_type' => self::TYPE_BUILDER,
                'is_system' => true,
                'is_published' => true,
                'menu_location' => 'none',
                'layout' => [
                    'blocks' => [
                        [
                            'id' => 'hero_' . uniqid(),
                            'type' => 'hero',
                            'settings' => [
                                'backgroundType' => 'gradient',
                                'height' => 'large',
                                'alignment' => 'center',
                                'showSearch' => true,
                            ],
                            'content' => [
                                'en' => [
                                    'title' => 'Welcome to Our Events',
                                    'subtitle' => 'Discover amazing experiences and book your tickets today',
                                ],
                                'ro' => [
                                    'title' => 'Bine ați venit la Evenimentele Noastre',
                                    'subtitle' => 'Descoperă experiențe unice și rezervă biletele acum',
                                ],
                            ],
                        ],
                        [
                            'id' => 'events_' . uniqid(),
                            'type' => 'event-grid',
                            'settings' => [
                                'columns' => 3,
                                'source' => 'upcoming',
                                'limit' => 6,
                                'showPagination' => false,
                            ],
                            'content' => [
                                'en' => [
                                    'title' => 'Upcoming Events',
                                    'subtitle' => '',
                                ],
                                'ro' => [
                                    'title' => 'Evenimente Viitoare',
                                    'subtitle' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        // Events page
        self::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'events'],
            [
                'title' => ['en' => 'Events', 'ro' => 'Evenimente'],
                'page_type' => self::TYPE_BUILDER,
                'is_system' => true,
                'is_published' => true,
                'menu_location' => 'header',
                'menu_order' => 1,
                'layout' => [
                    'blocks' => [
                        [
                            'id' => 'hero_' . uniqid(),
                            'type' => 'hero',
                            'settings' => [
                                'backgroundType' => 'gradient',
                                'height' => 'small',
                                'alignment' => 'center',
                                'showSearch' => true,
                            ],
                            'content' => [
                                'en' => [
                                    'title' => 'All Events',
                                    'subtitle' => 'Browse all our upcoming events',
                                ],
                                'ro' => [
                                    'title' => 'Toate Evenimentele',
                                    'subtitle' => 'Răsfoiește toate evenimentele noastre',
                                ],
                            ],
                        ],
                        [
                            'id' => 'events_' . uniqid(),
                            'type' => 'event-grid',
                            'settings' => [
                                'columns' => 3,
                                'source' => 'all',
                                'limit' => 12,
                                'showPagination' => true,
                            ],
                            'content' => [
                                'en' => ['title' => '', 'subtitle' => ''],
                                'ro' => ['title' => '', 'subtitle' => ''],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
