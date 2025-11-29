import { TixelloConfig } from '../core/ConfigManager';

export interface Block {
    id: string;
    type: string;
    settings: Record<string, any>;
    content: Record<string, Record<string, any>>; // { en: {...}, ro: {...} }
}

export interface PageLayout {
    blocks: Block[];
}

type BlockRenderer = (block: Block, config: TixelloConfig) => string;

export class PageBuilderModule {
    private static renderers: Map<string, BlockRenderer> = new Map();
    private static config: TixelloConfig;
    private static currentLanguage: string = 'en';

    /**
     * Initialize the PageBuilder module
     */
    static init(config: TixelloConfig): void {
        this.config = config;
        this.currentLanguage = config.site?.language || 'en';
        this.registerDefaultRenderers();
        console.log('[PageBuilder] Initialized');
    }

    /**
     * Set the current language for content rendering
     */
    static setLanguage(lang: string): void {
        this.currentLanguage = lang;
    }

    /**
     * Register a block renderer
     */
    static registerRenderer(type: string, renderer: BlockRenderer): void {
        this.renderers.set(type, renderer);
    }

    /**
     * Render a single block
     */
    static renderBlock(block: Block): string {
        const renderer = this.renderers.get(block.type);
        if (!renderer) {
            console.warn(`[PageBuilder] No renderer for block type: ${block.type}`);
            return `<!-- Unknown block type: ${block.type} -->`;
        }

        try {
            return renderer(block, this.config);
        } catch (error) {
            console.error(`[PageBuilder] Error rendering block ${block.type}:`, error);
            return `<!-- Error rendering block: ${block.type} -->`;
        }
    }

    /**
     * Render all blocks in a layout
     */
    static renderLayout(layout: PageLayout): string {
        if (!layout?.blocks || !Array.isArray(layout.blocks)) {
            return '';
        }

        return layout.blocks.map(block => this.renderBlock(block)).join('\n');
    }

    /**
     * Get localized content from a block
     */
    static getContent(block: Block, key: string, fallback: string = ''): string {
        const langContent = block.content?.[this.currentLanguage];
        if (langContent && langContent[key] !== undefined) {
            return langContent[key];
        }
        // Fallback to English
        const enContent = block.content?.['en'];
        if (enContent && enContent[key] !== undefined) {
            return enContent[key];
        }
        return fallback;
    }

    /**
     * Register all default block renderers
     */
    private static registerDefaultRenderers(): void {
        // Hero Block
        this.registerRenderer('hero', (block) => {
            const title = this.getContent(block, 'title', 'Welcome');
            const subtitle = this.getContent(block, 'subtitle', '');
            const buttonText = this.getContent(block, 'buttonText', 'Get Started');
            const buttonLink = block.settings.buttonLink || '/events';
            const backgroundImage = block.settings.backgroundImage || '';
            const overlayOpacity = block.settings.overlayOpacity ?? 50;
            const height = block.settings.height || 'large';
            const alignment = block.settings.alignment || 'center';

            const heightClass = {
                small: 'min-h-[300px]',
                medium: 'min-h-[450px]',
                large: 'min-h-[600px]',
                full: 'min-h-screen'
            }[height] || 'min-h-[450px]';

            const alignClass = {
                left: 'text-left items-start',
                center: 'text-center items-center',
                right: 'text-right items-end'
            }[alignment] || 'text-center items-center';

            const bgStyle = backgroundImage
                ? `background-image: url('${backgroundImage}'); background-size: cover; background-position: center;`
                : 'background: linear-gradient(135deg, var(--tixello-primary), var(--tixello-secondary));';

            return `
                <section class="relative ${heightClass} flex items-center justify-center" style="${bgStyle}">
                    <div class="absolute inset-0 bg-black" style="opacity: ${overlayOpacity / 100}"></div>
                    <div class="relative z-10 max-w-4xl mx-auto px-4 flex flex-col ${alignClass}">
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">${this.escapeHtml(title)}</h1>
                        ${subtitle ? `<p class="text-xl md:text-2xl text-white/90 mb-8 max-w-2xl">${this.escapeHtml(subtitle)}</p>` : ''}
                        ${buttonText ? `
                            <a href="${buttonLink}" class="inline-flex items-center px-8 py-4 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">
                                ${this.escapeHtml(buttonText)}
                                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        ` : ''}
                    </div>
                </section>
            `;
        });

        // Event Grid Block
        this.registerRenderer('event-grid', (block) => {
            const title = this.getContent(block, 'title', 'Upcoming Events');
            const subtitle = this.getContent(block, 'subtitle', '');
            const columns = block.settings.columns || 3;
            const limit = block.settings.limit || 6;
            const showFilters = block.settings.showFilters ?? true;
            const source = block.settings.source || 'upcoming';

            return `
                <section class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="text-center mb-12">
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>
                            ${subtitle ? `<p class="text-lg text-gray-600 max-w-2xl mx-auto">${this.escapeHtml(subtitle)}</p>` : ''}
                        </div>
                        ${showFilters ? `
                            <div class="flex flex-wrap gap-4 justify-center mb-8">
                                <button class="px-4 py-2 bg-primary text-white rounded-full text-sm font-medium">All Events</button>
                                <button class="px-4 py-2 bg-white text-gray-700 rounded-full text-sm font-medium hover:bg-gray-100">Concerts</button>
                                <button class="px-4 py-2 bg-white text-gray-700 rounded-full text-sm font-medium hover:bg-gray-100">Theater</button>
                                <button class="px-4 py-2 bg-white text-gray-700 rounded-full text-sm font-medium hover:bg-gray-100">Sports</button>
                            </div>
                        ` : ''}
                        <div id="event-grid-${block.id}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${columns} gap-6" data-source="${source}" data-limit="${limit}">
                            <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                            <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                            <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                        </div>
                    </div>
                </section>
            `;
        });

        // Featured Event Block
        this.registerRenderer('featured-event', (block) => {
            const title = this.getContent(block, 'title', 'Featured Event');
            const eventId = block.settings.eventId;
            const layout = block.settings.layout || 'horizontal';

            const layoutClass = layout === 'vertical' ? 'flex-col' : 'flex-col lg:flex-row';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">${this.escapeHtml(title)}</h2>
                        <div id="featured-event-${block.id}" class="flex ${layoutClass} gap-8 bg-white rounded-2xl shadow-lg overflow-hidden" data-event-id="${eventId || ''}">
                            <div class="animate-pulse bg-gray-200 h-64 lg:h-auto lg:w-1/2"></div>
                            <div class="p-8 flex-1 space-y-4">
                                <div class="animate-pulse bg-gray-200 h-8 w-3/4 rounded"></div>
                                <div class="animate-pulse bg-gray-200 h-4 w-1/2 rounded"></div>
                                <div class="animate-pulse bg-gray-200 h-20 rounded"></div>
                            </div>
                        </div>
                    </div>
                </section>
            `;
        });

        // Category Navigation Block
        this.registerRenderer('category-nav', (block) => {
            const title = this.getContent(block, 'title', 'Browse by Category');
            const style = block.settings.style || 'cards';
            const showCount = block.settings.showCount ?? true;

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">${this.escapeHtml(title)}</h2>
                        <div id="category-nav-${block.id}" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4" data-style="${style}" data-show-count="${showCount}">
                            ${[1,2,3,4,5,6].map(() => `
                                <div class="animate-pulse bg-gray-200 rounded-lg h-32"></div>
                            `).join('')}
                        </div>
                    </div>
                </section>
            `;
        });

        // Text Content Block
        this.registerRenderer('text-content', (block) => {
            const title = this.getContent(block, 'title', '');
            const content = this.getContent(block, 'content', '');
            const alignment = block.settings.alignment || 'left';
            const maxWidth = block.settings.maxWidth || 'full';

            const alignClass = {
                left: 'text-left',
                center: 'text-center mx-auto',
                right: 'text-right ml-auto'
            }[alignment] || 'text-left';

            const widthClass = {
                narrow: 'max-w-2xl',
                medium: 'max-w-4xl',
                wide: 'max-w-6xl',
                full: 'max-w-7xl'
            }[maxWidth] || 'max-w-7xl';

            return `
                <section class="py-16">
                    <div class="${widthClass} ${alignClass} px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-6">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="prose prose-lg max-w-none">
                            ${content}
                        </div>
                    </div>
                </section>
            `;
        });

        // Text + Image Block
        this.registerRenderer('text-image', (block) => {
            const title = this.getContent(block, 'title', '');
            const content = this.getContent(block, 'content', '');
            const imageUrl = block.settings.imageUrl || '';
            const imagePosition = block.settings.imagePosition || 'right';
            const imageAlt = this.getContent(block, 'imageAlt', '');

            const orderClass = imagePosition === 'left' ? 'lg:flex-row-reverse' : 'lg:flex-row';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex flex-col ${orderClass} gap-12 items-center">
                            <div class="flex-1">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-6">${this.escapeHtml(title)}</h2>` : ''}
                                <div class="prose prose-lg">
                                    ${content}
                                </div>
                            </div>
                            <div class="flex-1">
                                ${imageUrl ? `
                                    <img src="${imageUrl}" alt="${this.escapeHtml(imageAlt)}" class="rounded-2xl shadow-lg w-full">
                                ` : `
                                    <div class="bg-gray-200 rounded-2xl aspect-video flex items-center justify-center">
                                        <span class="text-gray-400">Image placeholder</span>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                </section>
            `;
        });

        // CTA Banner Block
        this.registerRenderer('cta-banner', (block) => {
            const title = this.getContent(block, 'title', 'Ready to get started?');
            const subtitle = this.getContent(block, 'subtitle', '');
            const buttonText = this.getContent(block, 'buttonText', 'Get Started');
            const buttonLink = block.settings.buttonLink || '/events';
            const backgroundColor = block.settings.backgroundColor || 'primary';
            const style = block.settings.style || 'simple';

            const bgClass = backgroundColor === 'primary'
                ? 'bg-primary'
                : backgroundColor === 'secondary'
                    ? 'bg-secondary'
                    : 'bg-gradient-to-r from-primary to-secondary';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="${bgClass} rounded-2xl p-12 text-center text-white">
                            <h2 class="text-3xl md:text-4xl font-bold mb-4">${this.escapeHtml(title)}</h2>
                            ${subtitle ? `<p class="text-xl text-white/90 mb-8 max-w-2xl mx-auto">${this.escapeHtml(subtitle)}</p>` : ''}
                            <a href="${buttonLink}" class="inline-flex items-center px-8 py-4 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">
                                ${this.escapeHtml(buttonText)}
                            </a>
                        </div>
                    </div>
                </section>
            `;
        });

        // Newsletter Block
        this.registerRenderer('newsletter', (block) => {
            const title = this.getContent(block, 'title', 'Stay Updated');
            const subtitle = this.getContent(block, 'subtitle', 'Subscribe to our newsletter');
            const buttonText = this.getContent(block, 'buttonText', 'Subscribe');
            const placeholderText = this.getContent(block, 'placeholderText', 'Enter your email');
            const style = block.settings.style || 'inline';

            return `
                <section class="py-16 bg-gray-50">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>
                        <p class="text-lg text-gray-600 mb-8">${this.escapeHtml(subtitle)}</p>
                        <form class="flex flex-col sm:flex-row gap-4 justify-center" id="newsletter-form-${block.id}">
                            <input type="email" placeholder="${this.escapeHtml(placeholderText)}"
                                class="flex-1 max-w-md px-6 py-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent" required>
                            <button type="submit" class="px-8 py-4 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                                ${this.escapeHtml(buttonText)}
                            </button>
                        </form>
                    </div>
                </section>
            `;
        });

        // Testimonials Block
        this.registerRenderer('testimonials', (block) => {
            const title = this.getContent(block, 'title', 'What People Say');
            const testimonials = block.settings.testimonials || [];
            const style = block.settings.style || 'cards';
            const columns = block.settings.columns || 3;

            const testimonialsHtml = testimonials.length > 0
                ? testimonials.map((t: any) => `
                    <div class="bg-white p-6 rounded-xl shadow-md">
                        <div class="flex items-center mb-4">
                            ${t.avatar ? `<img src="${t.avatar}" alt="${this.escapeHtml(t.name)}" class="w-12 h-12 rounded-full mr-4">` : `
                                <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold mr-4">
                                    ${t.name?.charAt(0) || '?'}
                                </div>
                            `}
                            <div>
                                <div class="font-semibold text-gray-900">${this.escapeHtml(t.name || 'Anonymous')}</div>
                                ${t.role ? `<div class="text-sm text-gray-500">${this.escapeHtml(t.role)}</div>` : ''}
                            </div>
                        </div>
                        <p class="text-gray-600 italic">"${this.escapeHtml(t.quote || '')}"</p>
                    </div>
                `).join('')
                : `<p class="text-gray-500 col-span-full text-center">No testimonials configured</p>`;

            return `
                <section class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-12 text-center">${this.escapeHtml(title)}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${columns} gap-6">
                            ${testimonialsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Partners Block
        this.registerRenderer('partners', (block) => {
            const title = this.getContent(block, 'title', 'Our Partners');
            const partners = block.settings.partners || [];
            const style = block.settings.style || 'grid';
            const grayscale = block.settings.grayscale ?? true;

            const partnersHtml = partners.length > 0
                ? partners.map((p: any) => `
                    <a href="${p.url || '#'}" target="_blank" rel="noopener" class="flex items-center justify-center p-4 ${grayscale ? 'grayscale hover:grayscale-0' : ''} transition">
                        ${p.logo ? `<img src="${p.logo}" alt="${this.escapeHtml(p.name || '')}" class="max-h-12">` : `
                            <span class="text-gray-500">${this.escapeHtml(p.name || 'Partner')}</span>
                        `}
                    </a>
                `).join('')
                : `<p class="text-gray-500 col-span-full text-center">No partners configured</p>`;

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8 items-center">
                            ${partnersHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Spacer Block
        this.registerRenderer('spacer', (block) => {
            const height = block.settings.height || 'medium';
            const showDivider = block.settings.showDivider ?? false;

            const heightPx = {
                small: '32px',
                medium: '64px',
                large: '96px',
                xlarge: '128px'
            }[height] || '64px';

            return `
                <div class="relative" style="height: ${heightPx}">
                    ${showDivider ? `
                        <div class="absolute inset-x-0 top-1/2 transform -translate-y-1/2">
                            <div class="max-w-7xl mx-auto px-4">
                                <div class="border-t border-gray-200"></div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        });

        // Divider Block
        this.registerRenderer('divider', (block) => {
            const style = block.settings.style || 'solid';
            const width = block.settings.width || 'full';
            const color = block.settings.color || 'gray-200';

            const widthClass = {
                small: 'max-w-xs',
                medium: 'max-w-md',
                large: 'max-w-2xl',
                full: 'max-w-7xl'
            }[width] || 'max-w-7xl';

            const styleClass = {
                solid: 'border-solid',
                dashed: 'border-dashed',
                dotted: 'border-dotted'
            }[style] || 'border-solid';

            return `
                <div class="py-8">
                    <div class="${widthClass} mx-auto px-4">
                        <div class="border-t ${styleClass} border-${color}"></div>
                    </div>
                </div>
            `;
        });

        // Custom HTML Block
        this.registerRenderer('custom-html', (block) => {
            const html = this.getContent(block, 'html', '');
            const containerClass = block.settings.containerClass || '';
            const fullWidth = block.settings.fullWidth ?? false;

            if (!html) {
                return '<!-- Empty custom HTML block -->';
            }

            return fullWidth
                ? `<div class="${containerClass}">${html}</div>`
                : `<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 ${containerClass}">${html}</div>`;
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    private static escapeHtml(text: string): string {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Update a page's layout and re-render
     */
    static updateLayout(layout: PageLayout, containerId: string = 'page-content'): void {
        const container = document.getElementById(containerId);
        if (!container) {
            console.warn(`[PageBuilder] Container not found: ${containerId}`);
            return;
        }

        container.innerHTML = this.renderLayout(layout);
        this.initializeBlocks(layout);
    }

    /**
     * Initialize interactive blocks after rendering
     */
    static initializeBlocks(layout: PageLayout): void {
        if (!layout?.blocks) return;

        layout.blocks.forEach(block => {
            switch (block.type) {
                case 'event-grid':
                    this.initEventGrid(block);
                    break;
                case 'featured-event':
                    this.initFeaturedEvent(block);
                    break;
                case 'category-nav':
                    this.initCategoryNav(block);
                    break;
                case 'newsletter':
                    this.initNewsletter(block);
                    break;
            }
        });
    }

    /**
     * Initialize event grid with API data
     */
    private static async initEventGrid(block: Block): Promise<void> {
        const container = document.getElementById(`event-grid-${block.id}`);
        if (!container) return;

        const source = container.dataset.source || 'upcoming';
        const limit = parseInt(container.dataset.limit || '6', 10);

        try {
            const response = await fetch(`${this.config.apiEndpoint}/events?filter=${source}&limit=${limit}`);
            if (!response.ok) return;

            const data = await response.json();
            const events = data.data || [];

            if (events.length === 0) {
                container.innerHTML = '<p class="col-span-full text-center text-gray-500">No events found</p>';
                return;
            }

            container.innerHTML = events.map((event: any) => `
                <a href="/event/${event.slug}" class="group bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
                    <div class="aspect-video bg-gray-200 overflow-hidden">
                        ${event.image ? `<img src="${event.image}" alt="${this.escapeHtml(event.title)}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">` : ''}
                    </div>
                    <div class="p-4">
                        <div class="text-sm text-primary font-medium mb-1">${event.date_formatted || ''}</div>
                        <h3 class="font-semibold text-gray-900 group-hover:text-primary transition">${this.escapeHtml(event.title)}</h3>
                        <p class="text-sm text-gray-500 mt-1">${event.venue || ''}</p>
                    </div>
                </a>
            `).join('');
        } catch (error) {
            console.error('[PageBuilder] Failed to load events:', error);
        }
    }

    /**
     * Initialize featured event with API data
     */
    private static async initFeaturedEvent(block: Block): Promise<void> {
        const container = document.getElementById(`featured-event-${block.id}`);
        if (!container) return;

        const eventId = container.dataset.eventId;
        if (!eventId) return;

        try {
            const response = await fetch(`${this.config.apiEndpoint}/events/${eventId}`);
            if (!response.ok) return;

            const data = await response.json();
            const event = data.data;
            if (!event) return;

            container.innerHTML = `
                <div class="lg:w-1/2 aspect-video lg:aspect-auto">
                    ${event.image ? `<img src="${event.image}" alt="${this.escapeHtml(event.title)}" class="w-full h-full object-cover">` : `
                        <div class="w-full h-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center">
                            <span class="text-white text-4xl font-bold">${event.title?.charAt(0) || 'E'}</span>
                        </div>
                    `}
                </div>
                <div class="p-8 flex-1">
                    <div class="text-primary font-medium mb-2">${event.date_formatted || ''}</div>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(event.title)}</h3>
                    <p class="text-gray-600 mb-6">${event.description || ''}</p>
                    <div class="flex items-center gap-4">
                        <a href="/event/${event.slug}" class="btn-primary px-6 py-3 rounded-lg font-semibold">
                            Get Tickets
                        </a>
                        <span class="text-lg font-semibold text-gray-900">
                            ${event.price_from ? `From ${event.currency || '€'}${event.price_from}` : 'Free'}
                        </span>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('[PageBuilder] Failed to load featured event:', error);
        }
    }

    /**
     * Initialize category navigation with API data
     */
    private static async initCategoryNav(block: Block): Promise<void> {
        const container = document.getElementById(`category-nav-${block.id}`);
        if (!container) return;

        const showCount = container.dataset.showCount === 'true';

        try {
            const response = await fetch(`${this.config.apiEndpoint}/categories`);
            if (!response.ok) return;

            const data = await response.json();
            const categories = data.data || [];

            if (categories.length === 0) {
                container.innerHTML = '<p class="col-span-full text-center text-gray-500">No categories found</p>';
                return;
            }

            container.innerHTML = categories.map((cat: any) => `
                <a href="/events?category=${cat.slug}" class="group flex flex-col items-center p-4 bg-white rounded-xl shadow-md hover:shadow-lg transition">
                    <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-3 group-hover:bg-primary/20 transition">
                        <span class="text-2xl">${cat.icon || '🎫'}</span>
                    </div>
                    <span class="font-medium text-gray-900">${this.escapeHtml(cat.name)}</span>
                    ${showCount && cat.events_count !== undefined ? `
                        <span class="text-sm text-gray-500">${cat.events_count} events</span>
                    ` : ''}
                </a>
            `).join('');
        } catch (error) {
            console.error('[PageBuilder] Failed to load categories:', error);
        }
    }

    /**
     * Initialize newsletter form
     */
    private static initNewsletter(block: Block): void {
        const form = document.getElementById(`newsletter-form-${block.id}`) as HTMLFormElement;
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const emailInput = form.querySelector('input[type="email"]') as HTMLInputElement;
            const email = emailInput?.value;

            if (!email) return;

            try {
                const response = await fetch(`${this.config.apiEndpoint}/newsletter/subscribe`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                if (response.ok) {
                    form.innerHTML = '<p class="text-green-600 font-medium">Thank you for subscribing!</p>';
                } else {
                    throw new Error('Subscription failed');
                }
            } catch {
                form.innerHTML = '<p class="text-red-600">Something went wrong. Please try again.</p>';
            }
        });
    }
}
