<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Domain;
use App\Models\TenantPage;
use App\PageBuilder\BlockRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PageBuilder extends Page
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Page Builder';
    protected static \UnitEnum|string|null $navigationGroup = 'Website';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.tenant.pages.page-builder';

    public const MICROSERVICE_SLUG = 'website-visual-editor';

    public ?int $currentPageId = null;
    public ?string $currentPageSlug = null;
    public array $blocks = [];
    public array $availableBlocks = [];
    public string $previewUrl = '';
    public array $pages = [];

    public ?array $blockSettingsData = [];
    public ?string $editingBlockId = null;
    public ?string $editingBlockType = null;
    public string $contentLanguage = 'en';

    /**
     * Check if the current user can access this page
     */
    public static function canAccess(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->hasMicroservice(self::MICROSERVICE_SLUG);
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return;
        }

        // Check microservice access
        if (!$tenant->hasMicroservice(self::MICROSERVICE_SLUG)) {
            Notification::make()
                ->warning()
                ->title('Feature not available')
                ->body('Please purchase the Website Visual Editor to access this feature.')
                ->persistent()
                ->send();

            $this->redirect(route('filament.tenant.pages.dashboard'));
            return;
        }

        // Register blocks
        BlockRegistry::registerDefaults();
        $this->availableBlocks = BlockRegistry::getPickerData();

        // Load pages
        $this->loadPages();

        // Select home page by default
        $homePage = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', 'home')
            ->first();

        if ($homePage) {
            $this->selectPage($homePage->id);
        }

        // Get preview URL
        $domain = Domain::where('tenant_id', $tenant->id)
            ->where('is_verified', true)
            ->first();

        if ($domain) {
            $this->previewUrl = 'https://' . $domain->domain;
        }
    }

    protected function loadPages(): void
    {
        $tenant = auth()->user()->tenant;

        $this->pages = TenantPage::where('tenant_id', $tenant->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('menu_order')
            ->get()
            ->map(fn (TenantPage $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title['en'] ?? $page->title[$tenant->locale] ?? $page->slug,
                'isSystem' => $page->is_system,
                'isPublished' => $page->is_published,
            ])
            ->toArray();
    }

    public function selectPage(int $pageId): void
    {
        $tenant = auth()->user()->tenant;

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('id', $pageId)
            ->first();

        if ($page) {
            $this->currentPageId = $page->id;
            $this->currentPageSlug = $page->slug;
            $this->blocks = $page->getBlocks();
        }
    }

    public function addBlock(string $type): void
    {
        $block = BlockRegistry::create($type);

        if ($block) {
            $this->blocks[] = $block;
            $this->saveBlocks();

            Notification::make()
                ->success()
                ->title('Block added')
                ->send();
        }
    }

    public function removeBlock(string $blockId): void
    {
        $this->blocks = array_values(
            array_filter($this->blocks, fn($block) => ($block['id'] ?? '') !== $blockId)
        );

        $this->saveBlocks();

        Notification::make()
            ->success()
            ->title('Block removed')
            ->send();
    }

    public function moveBlock(string $blockId, string $direction): void
    {
        $index = null;

        foreach ($this->blocks as $i => $block) {
            if (($block['id'] ?? '') === $blockId) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($newIndex < 0 || $newIndex >= count($this->blocks)) {
            return;
        }

        // Swap
        $temp = $this->blocks[$index];
        $this->blocks[$index] = $this->blocks[$newIndex];
        $this->blocks[$newIndex] = $temp;

        $this->saveBlocks();
    }

    public function reorderBlocks(array $orderedIds): void
    {
        $blocksById = [];
        foreach ($this->blocks as $block) {
            if (isset($block['id'])) {
                $blocksById[$block['id']] = $block;
            }
        }

        $reordered = [];
        foreach ($orderedIds as $id) {
            if (isset($blocksById[$id])) {
                $reordered[] = $blocksById[$id];
            }
        }

        $this->blocks = $reordered;
        $this->saveBlocks();
    }

    public function editBlock(string $blockId): void
    {
        $block = null;
        foreach ($this->blocks as $b) {
            if (($b['id'] ?? '') === $blockId) {
                $block = $b;
                break;
            }
        }

        if (!$block) {
            return;
        }

        $this->editingBlockId = $blockId;
        $this->editingBlockType = $block['type'];

        // Merge settings and content for the form
        $formData = array_merge(
            $block['settings'] ?? [],
            ['content' => $block['content'][$this->contentLanguage] ?? []]
        );

        $this->blockSettingsData = $formData;

        $this->dispatch('open-modal', id: 'block-settings-modal');
    }

    public function getBlockSettingsForm(): array
    {
        if (!$this->editingBlockType) {
            return [];
        }

        $settingsSchema = BlockRegistry::getSettingsSchema($this->editingBlockType);
        $contentSchema = BlockRegistry::getContentSchema($this->editingBlockType);

        // Wrap content fields
        $wrappedContentSchema = [];
        foreach ($contentSchema as $field) {
            $wrappedContentSchema[] = $field->statePath('content.' . $field->getName());
        }

        return array_merge($settingsSchema, $wrappedContentSchema);
    }

    public function saveBlockSettings(): void
    {
        if (!$this->editingBlockId) {
            return;
        }

        $formData = $this->blockSettingsData;
        $content = $formData['content'] ?? [];
        unset($formData['content']);

        foreach ($this->blocks as $index => $block) {
            if (($block['id'] ?? '') === $this->editingBlockId) {
                $this->blocks[$index]['settings'] = array_merge(
                    $this->blocks[$index]['settings'] ?? [],
                    $formData
                );
                $this->blocks[$index]['content'][$this->contentLanguage] = $content;
                break;
            }
        }

        $this->saveBlocks();

        $this->editingBlockId = null;
        $this->editingBlockType = null;
        $this->blockSettingsData = [];

        $this->dispatch('close-modal', id: 'block-settings-modal');

        Notification::make()
            ->success()
            ->title('Block updated')
            ->send();
    }

    public function duplicateBlock(string $blockId): void
    {
        $sourceBlock = null;
        $sourceIndex = null;

        foreach ($this->blocks as $index => $block) {
            if (($block['id'] ?? '') === $blockId) {
                $sourceBlock = $block;
                $sourceIndex = $index;
                break;
            }
        }

        if (!$sourceBlock) {
            return;
        }

        $newBlock = $sourceBlock;
        $newBlock['id'] = $sourceBlock['type'] . '_' . uniqid();

        // Insert after the source block
        array_splice($this->blocks, $sourceIndex + 1, 0, [$newBlock]);

        $this->saveBlocks();

        Notification::make()
            ->success()
            ->title('Block duplicated')
            ->send();
    }

    protected function saveBlocks(): void
    {
        if (!$this->currentPageId) {
            return;
        }

        $page = TenantPage::find($this->currentPageId);

        if ($page) {
            $page->updateBlocks($this->blocks);
            $this->syncPreview();
        }
    }

    public function syncPreview(): void
    {
        $this->dispatch('layout-changed', layout: ['blocks' => $this->blocks]);
    }

    public function createPage(): void
    {
        $this->dispatch('open-modal', id: 'create-page-modal');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Page Builder';
    }

    public function getBlockName(string $type): string
    {
        $class = BlockRegistry::get($type);
        return $class ? $class::$name : $type;
    }

    public function getBlockIcon(string $type): string
    {
        $class = BlockRegistry::get($type);
        return $class ? $class::$icon : 'heroicon-o-cube';
    }

    public function getBlockPreviewText(array $block): string
    {
        $content = $block['content'][$this->contentLanguage] ?? $block['content']['en'] ?? [];
        return $content['title'] ?? $content['text'] ?? '';
    }
}
