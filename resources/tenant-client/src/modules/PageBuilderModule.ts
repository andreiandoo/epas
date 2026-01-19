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
     * Get localized array content from a block
     */
    static getContentArray<T = Record<string, unknown>>(block: Block, key: string, fallback: T[] = []): T[] {
        const langContent = block.content?.[this.currentLanguage];
        if (langContent && Array.isArray(langContent[key])) {
            return langContent[key] as T[];
        }
        // Fallback to English
        const enContent = block.content?.['en'];
        if (enContent && Array.isArray(enContent[key])) {
            return enContent[key] as T[];
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

        // Countdown Block
        this.registerRenderer('countdown', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const expiredMessage = this.getContent(block, 'expiredMessage', 'Event has started!');
            const style = block.settings.style || 'flip';
            const targetDate = block.settings.targetDate || '';
            const showLabels = block.settings.showLabels ?? true;
            const alignment = block.settings.alignment || 'center';

            const alignClass = {
                left: 'text-left',
                center: 'text-center',
                right: 'text-right'
            }[alignment] || 'text-center';

            const styleClass = {
                flip: 'countdown-flip',
                simple: 'countdown-simple',
                circles: 'countdown-circles'
            }[style] || 'countdown-simple';

            return `
                <section class="py-16">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 ${alignClass}">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        ${subtitle ? `<p class="text-lg text-gray-600 mb-8">${this.escapeHtml(subtitle)}</p>` : ''}
                        <div id="countdown-${block.id}" class="countdown ${styleClass} flex justify-center gap-4"
                             data-target="${targetDate}" data-expired-message="${this.escapeHtml(expiredMessage)}">
                            <div class="countdown-item">
                                <span class="countdown-value text-4xl md:text-6xl font-bold text-primary" data-days>00</span>
                                ${showLabels ? '<span class="countdown-label text-sm text-gray-500">Days</span>' : ''}
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value text-4xl md:text-6xl font-bold text-primary" data-hours>00</span>
                                ${showLabels ? '<span class="countdown-label text-sm text-gray-500">Hours</span>' : ''}
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value text-4xl md:text-6xl font-bold text-primary" data-minutes>00</span>
                                ${showLabels ? '<span class="countdown-label text-sm text-gray-500">Minutes</span>' : ''}
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value text-4xl md:text-6xl font-bold text-primary" data-seconds>00</span>
                                ${showLabels ? '<span class="countdown-label text-sm text-gray-500">Seconds</span>' : ''}
                            </div>
                        </div>
                    </div>
                </section>
            `;
        });

        // Event List Block
        this.registerRenderer('event-list', (block) => {
            const title = this.getContent(block, 'title', 'Upcoming Events');
            const emptyMessage = this.getContent(block, 'emptyMessage', 'No events found');
            const style = block.settings.style || 'default';
            const limit = block.settings.limit || 5;
            const source = block.settings.source || 'upcoming';
            const showDate = block.settings.showDate ?? true;
            const showVenue = block.settings.showVenue ?? true;
            const showPrice = block.settings.showPrice ?? true;

            return `
                <section class="py-16">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-8">${this.escapeHtml(title)}</h2>` : ''}
                        <div id="event-list-${block.id}" class="event-list event-list-${style} space-y-4"
                             data-source="${source}" data-limit="${limit}"
                             data-show-date="${showDate}" data-show-venue="${showVenue}" data-show-price="${showPrice}"
                             data-empty-message="${this.escapeHtml(emptyMessage)}">
                            ${[1,2,3].map(() => `
                                <div class="animate-pulse flex gap-4 p-4 bg-white rounded-lg shadow">
                                    <div class="w-24 h-24 bg-gray-200 rounded"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </section>
            `;
        });

        // Image Block
        this.registerRenderer('image', (block) => {
            const caption = this.getContent(block, 'caption', '');
            const altText = this.getContent(block, 'altText', '');
            const imageUrl = block.settings.imageUrl || '';
            const size = block.settings.size || 'medium';
            const alignment = block.settings.alignment || 'center';
            const borderRadius = block.settings.borderRadius || 'md';
            const shadow = block.settings.shadow ?? true;
            const lightbox = block.settings.lightbox ?? true;
            const linkUrl = block.settings.linkUrl || '';

            const sizeClass = {
                small: 'max-w-md',
                medium: 'max-w-2xl',
                large: 'max-w-4xl',
                full: 'max-w-7xl'
            }[size] || 'max-w-2xl';

            const alignClass = {
                left: '',
                center: 'mx-auto',
                right: 'ml-auto'
            }[alignment] || 'mx-auto';

            const radiusClass = {
                none: 'rounded-none',
                sm: 'rounded',
                md: 'rounded-lg',
                lg: 'rounded-2xl'
            }[borderRadius] || 'rounded-lg';

            const imageHtml = imageUrl
                ? `<img src="${imageUrl}" alt="${this.escapeHtml(altText)}" class="w-full ${radiusClass} ${shadow ? 'shadow-lg' : ''}">`
                : `<div class="aspect-video bg-gray-200 ${radiusClass} flex items-center justify-center">
                       <span class="text-gray-400">No image</span>
                   </div>`;

            const wrappedImage = linkUrl
                ? `<a href="${linkUrl}" ${lightbox ? 'data-lightbox="true"' : ''}>${imageHtml}</a>`
                : lightbox
                    ? `<a href="${imageUrl}" data-lightbox="true">${imageHtml}</a>`
                    : imageHtml;

            return `
                <section class="py-8">
                    <figure class="${sizeClass} ${alignClass} px-4 sm:px-6 lg:px-8">
                        ${wrappedImage}
                        ${caption ? `<figcaption class="mt-4 text-center text-sm text-gray-600">${this.escapeHtml(caption)}</figcaption>` : ''}
                    </figure>
                </section>
            `;
        });

        // Video Block
        this.registerRenderer('video', (block) => {
            const title = this.getContent(block, 'title', '');
            const caption = this.getContent(block, 'caption', '');
            const videoUrl = block.settings.videoUrl || '';
            const provider = block.settings.provider || 'youtube';
            const aspectRatio = block.settings.aspectRatio || '16:9';
            const autoplay = block.settings.autoplay ?? false;
            const loop = block.settings.loop ?? false;
            const muted = block.settings.muted ?? false;
            const controls = block.settings.controls ?? true;

            const aspectClass = {
                '16:9': 'aspect-video',
                '4:3': 'aspect-[4/3]',
                '1:1': 'aspect-square',
                '21:9': 'aspect-[21/9]'
            }[aspectRatio] || 'aspect-video';

            let embedHtml = '';
            if (videoUrl) {
                if (provider === 'youtube') {
                    const videoId = this.extractYouTubeId(videoUrl);
                    const params = new URLSearchParams();
                    if (autoplay) params.set('autoplay', '1');
                    if (loop) params.set('loop', '1');
                    if (muted) params.set('mute', '1');
                    if (!controls) params.set('controls', '0');
                    embedHtml = `<iframe src="https://www.youtube.com/embed/${videoId}?${params.toString()}"
                                         class="w-full h-full rounded-lg" frameborder="0"
                                         allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                         allowfullscreen></iframe>`;
                } else if (provider === 'vimeo') {
                    const videoId = this.extractVimeoId(videoUrl);
                    const params = new URLSearchParams();
                    if (autoplay) params.set('autoplay', '1');
                    if (loop) params.set('loop', '1');
                    if (muted) params.set('muted', '1');
                    embedHtml = `<iframe src="https://player.vimeo.com/video/${videoId}?${params.toString()}"
                                         class="w-full h-full rounded-lg" frameborder="0"
                                         allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
                } else {
                    embedHtml = `<video src="${videoUrl}" class="w-full h-full rounded-lg"
                                        ${controls ? 'controls' : ''} ${autoplay ? 'autoplay' : ''}
                                        ${loop ? 'loop' : ''} ${muted ? 'muted' : ''}></video>`;
                }
            } else {
                embedHtml = `<div class="w-full h-full bg-gray-200 rounded-lg flex items-center justify-center">
                                 <span class="text-gray-400">No video configured</span>
                             </div>`;
            }

            return `
                <section class="py-8">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="${aspectClass}">
                            ${embedHtml}
                        </div>
                        ${caption ? `<p class="mt-4 text-center text-sm text-gray-600">${this.escapeHtml(caption)}</p>` : ''}
                    </div>
                </section>
            `;
        });

        // Button Block
        this.registerRenderer('button', (block) => {
            const text = this.getContent(block, 'text', 'Click Here');
            const url = block.settings.url || '#';
            const style = block.settings.style || 'primary';
            const size = block.settings.size || 'md';
            const alignment = block.settings.alignment || 'center';
            const fullWidth = block.settings.fullWidth ?? false;
            const openInNewTab = block.settings.openInNewTab ?? false;
            const icon = block.settings.icon || '';
            const iconPosition = block.settings.iconPosition || 'left';

            const styleClass = {
                primary: 'bg-primary text-white hover:bg-primary-dark',
                secondary: 'bg-secondary text-white hover:bg-secondary-dark',
                outline: 'border-2 border-primary text-primary hover:bg-primary hover:text-white',
                ghost: 'text-primary hover:bg-primary/10'
            }[style] || 'bg-primary text-white hover:bg-primary-dark';

            const sizeClass = {
                sm: 'px-4 py-2 text-sm',
                md: 'px-6 py-3 text-base',
                lg: 'px-8 py-4 text-lg'
            }[size] || 'px-6 py-3 text-base';

            const alignClass = {
                left: 'text-left',
                center: 'text-center',
                right: 'text-right'
            }[alignment] || 'text-center';

            const iconHtml = icon ? `<span class="button-icon">${icon}</span>` : '';
            const buttonContent = iconPosition === 'right'
                ? `${this.escapeHtml(text)} ${iconHtml}`
                : `${iconHtml} ${this.escapeHtml(text)}`;

            return `
                <section class="py-4">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 ${alignClass}">
                        <a href="${url}" ${openInNewTab ? 'target="_blank" rel="noopener"' : ''}
                           class="inline-flex items-center gap-2 ${styleClass} ${sizeClass} ${fullWidth ? 'w-full justify-center' : ''} font-semibold rounded-lg transition">
                            ${buttonContent}
                        </a>
                    </div>
                </section>
            `;
        });

        // Stats Counter Block
        this.registerRenderer('stats-counter', (block) => {
            const title = this.getContent(block, 'title', '');
            const stats = this.getContentArray(block, 'stats');
            const columns = block.settings.columns || 4;
            const style = block.settings.style || 'simple';
            const animate = block.settings.animate ?? true;

            const statsHtml = Array.isArray(stats) && stats.length > 0
                ? stats.map((stat: any) => `
                    <div class="stat-item text-center p-6 ${style === 'cards' ? 'bg-white rounded-xl shadow-md' : ''}">
                        ${stat.icon ? `<div class="text-4xl mb-2">${stat.icon}</div>` : ''}
                        <div class="stat-value text-4xl md:text-5xl font-bold text-primary mb-2"
                             ${animate ? `data-count-to="${stat.value}"` : ''}>
                            ${stat.prefix || ''}${animate ? '0' : stat.value}${stat.suffix || ''}
                        </div>
                        <div class="stat-label text-gray-600">${this.escapeHtml(stat.label || '')}</div>
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No statistics configured</p>';

            return `
                <section class="py-16 ${style === 'bordered' ? 'border-y border-gray-200' : ''}">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-12 text-center">${this.escapeHtml(title)}</h2>` : ''}
                        <div id="stats-${block.id}" class="grid grid-cols-2 md:grid-cols-${columns} gap-8" data-animate="${animate}">
                            ${statsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Accordion Block
        this.registerRenderer('accordion', (block) => {
            const title = this.getContent(block, 'title', '');
            const items = this.getContentArray(block, 'items');
            const style = block.settings.style || 'simple';
            const allowMultiple = block.settings.allowMultiple ?? false;
            const defaultOpen = block.settings.defaultOpen ?? 0;

            const itemsHtml = Array.isArray(items) && items.length > 0
                ? items.map((item: any, index: number) => `
                    <div class="accordion-item ${style === 'bordered' ? 'border border-gray-200 rounded-lg mb-2' : 'border-b border-gray-200'}"
                         x-data="{ open: ${index === defaultOpen ? 'true' : 'false'} }">
                        <button @click="open = !open"
                                class="accordion-header w-full flex items-center justify-between p-4 text-left font-medium text-gray-900 hover:bg-gray-50 transition">
                            <span>${this.escapeHtml(item.question || item.title || '')}</span>
                            <svg class="w-5 h-5 transform transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="accordion-content">
                            <div class="p-4 text-gray-600 prose prose-sm max-w-none">
                                ${item.answer || item.content || ''}
                            </div>
                        </div>
                    </div>
                `).join('')
                : '<p class="text-center text-gray-500">No items configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="accordion" x-data="{ allowMultiple: ${allowMultiple} }">
                            ${itemsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Alert Banner Block
        this.registerRenderer('alert-banner', (block) => {
            const message = this.getContent(block, 'message', '');
            const linkText = this.getContent(block, 'linkText', '');
            const linkUrl = block.settings.linkUrl || '';
            const type = block.settings.type || 'info';
            const dismissible = block.settings.dismissible ?? true;
            const position = block.settings.position || 'inline';
            const icon = block.settings.icon ?? true;

            const typeStyles: Record<string, { bg: string; text: string; icon: string }> = {
                info: { bg: 'bg-blue-50', text: 'text-blue-800', icon: 'ℹ️' },
                success: { bg: 'bg-green-50', text: 'text-green-800', icon: '✓' },
                warning: { bg: 'bg-yellow-50', text: 'text-yellow-800', icon: '⚠️' },
                error: { bg: 'bg-red-50', text: 'text-red-800', icon: '✕' },
                promo: { bg: 'bg-primary/10', text: 'text-primary', icon: '🎉' }
            };

            const styles = typeStyles[type] || typeStyles.info;
            const positionClass = position === 'sticky' ? 'sticky top-0 z-50' : '';

            return `
                <div class="alert-banner ${styles.bg} ${positionClass}" x-data="{ show: true }" x-show="show">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                        <div class="flex items-center justify-center gap-4">
                            ${icon ? `<span class="flex-shrink-0">${styles.icon}</span>` : ''}
                            <p class="${styles.text} text-sm font-medium">
                                ${this.escapeHtml(message)}
                                ${linkText && linkUrl ? `<a href="${linkUrl}" class="underline ml-2">${this.escapeHtml(linkText)}</a>` : ''}
                            </p>
                            ${dismissible ? `
                                <button @click="show = false" class="flex-shrink-0 ${styles.text} hover:opacity-70">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });

        // Slider Block
        this.registerRenderer('slider', (block) => {
            const slides = this.getContentArray(block, 'slides');
            const height = block.settings.height || 'medium';
            const autoplay = block.settings.autoplay ?? true;
            const autoplaySpeed = block.settings.autoplaySpeed || 5000;
            const showArrows = block.settings.showArrows ?? true;
            const showDots = block.settings.showDots ?? true;
            const effect = block.settings.effect || 'slide';

            const heightClass = {
                small: 'h-[300px]',
                medium: 'h-[400px]',
                large: 'h-[500px]',
                full: 'h-screen'
            }[height] || 'h-[400px]';

            const slidesHtml = Array.isArray(slides) && slides.length > 0
                ? slides.map((slide: any, index: number) => `
                    <div class="slide ${index === 0 ? 'active' : ''} absolute inset-0 transition-opacity duration-500" data-index="${index}">
                        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${slide.image || ''}')">
                            <div class="absolute inset-0 bg-black" style="opacity: ${slide.overlayOpacity || 0.5}"></div>
                        </div>
                        <div class="relative h-full flex items-center justify-${slide.contentPosition || 'center'}">
                            <div class="text-center text-white px-4 max-w-4xl mx-auto">
                                ${slide.title ? `<h2 class="text-4xl md:text-5xl font-bold mb-4">${this.escapeHtml(slide.title)}</h2>` : ''}
                                ${slide.subtitle ? `<p class="text-xl md:text-2xl mb-8 text-white/90">${this.escapeHtml(slide.subtitle)}</p>` : ''}
                                ${slide.buttonText ? `
                                    <a href="${slide.buttonUrl || '#'}" class="inline-block px-8 py-4 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">
                                        ${this.escapeHtml(slide.buttonText)}
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('')
                : '<div class="absolute inset-0 bg-gray-200 flex items-center justify-center"><span class="text-gray-500">No slides configured</span></div>';

            return `
                <section class="slider relative ${heightClass} overflow-hidden"
                         id="slider-${block.id}"
                         data-autoplay="${autoplay}"
                         data-speed="${autoplaySpeed}"
                         data-effect="${effect}">
                    <div class="slides-container relative h-full">
                        ${slidesHtml}
                    </div>
                    ${showArrows && Array.isArray(slides) && slides.length > 1 ? `
                        <button class="slider-prev absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/80 rounded-full flex items-center justify-center hover:bg-white transition z-10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button class="slider-next absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/80 rounded-full flex items-center justify-center hover:bg-white transition z-10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    ` : ''}
                    ${showDots && Array.isArray(slides) && slides.length > 1 ? `
                        <div class="slider-dots absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                            ${slides.map((_: any, i: number) => `
                                <button class="w-3 h-3 rounded-full ${i === 0 ? 'bg-white' : 'bg-white/50'} hover:bg-white transition" data-slide="${i}"></button>
                            `).join('')}
                        </div>
                    ` : ''}
                </section>
            `;
        });

        // Image Gallery Block
        this.registerRenderer('image-gallery', (block) => {
            const title = this.getContent(block, 'title', '');
            const images = this.getContentArray(block, 'images');
            const columns = block.settings.columns || 4;
            const style = block.settings.style || 'grid';
            const gap = block.settings.gap || 'md';
            const borderRadius = block.settings.borderRadius || 'md';
            const lightbox = block.settings.lightbox ?? true;
            const showCaptions = block.settings.showCaptions ?? true;

            const gapClass = {
                none: 'gap-0',
                sm: 'gap-2',
                md: 'gap-4',
                lg: 'gap-6'
            }[gap] || 'gap-4';

            const radiusClass = {
                none: 'rounded-none',
                sm: 'rounded',
                md: 'rounded-lg',
                lg: 'rounded-xl'
            }[borderRadius] || 'rounded-lg';

            const imagesHtml = Array.isArray(images) && images.length > 0
                ? images.map((img: any, index: number) => `
                    <a href="${img.src || ''}" ${lightbox ? 'data-lightbox="gallery"' : ''}
                       class="gallery-item group relative overflow-hidden ${radiusClass} ${style === 'featured' && index === 0 ? 'col-span-2 row-span-2' : ''}">
                        <img src="${img.src || ''}" alt="${this.escapeHtml(img.alt || '')}"
                             class="w-full h-full object-cover aspect-square group-hover:scale-105 transition duration-300">
                        ${showCaptions && img.caption ? `
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 opacity-0 group-hover:opacity-100 transition">
                                <p class="text-white text-sm">${this.escapeHtml(img.caption)}</p>
                            </div>
                        ` : ''}
                    </a>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No images configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="grid grid-cols-2 md:grid-cols-${columns} ${gapClass}" id="gallery-${block.id}">
                            ${imagesHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Social Links Block
        this.registerRenderer('social-links', (block) => {
            const title = this.getContent(block, 'title', '');
            const style = block.settings.style || 'rounded';
            const size = block.settings.size || 'md';
            const alignment = block.settings.alignment || 'center';
            const colorScheme = block.settings.colorScheme || 'brand';
            const showLabels = block.settings.showLabels ?? false;
            const openInNewTab = block.settings.openInNewTab ?? true;

            const socialNetworks = [
                { key: 'facebook', name: 'Facebook', icon: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z', color: '#1877F2' },
                { key: 'instagram', name: 'Instagram', icon: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z', color: '#E4405F' },
                { key: 'twitter', name: 'X', icon: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z', color: '#000000' },
                { key: 'youtube', name: 'YouTube', icon: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z', color: '#FF0000' },
                { key: 'tiktok', name: 'TikTok', icon: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z', color: '#000000' },
                { key: 'linkedin', name: 'LinkedIn', icon: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z', color: '#0A66C2' },
                { key: 'spotify', name: 'Spotify', icon: 'M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z', color: '#1DB954' }
            ];

            const sizeClasses = {
                sm: 'w-8 h-8',
                md: 'w-10 h-10',
                lg: 'w-12 h-12'
            };

            const alignClass = {
                left: 'justify-start',
                center: 'justify-center',
                right: 'justify-end'
            }[alignment] || 'justify-center';

            const linksHtml = socialNetworks
                .filter(sn => {
                    const url = this.getContent(block, sn.key, '');
                    return url && url.trim() !== '';
                })
                .map(sn => {
                    const url = this.getContent(block, sn.key, '');
                    const bgColor = colorScheme === 'brand' ? sn.color :
                                   colorScheme === 'primary' ? 'var(--tixello-primary)' :
                                   colorScheme === 'dark' ? '#1f2937' : '#f3f4f6';
                    const textColor = colorScheme === 'light' ? '#1f2937' : '#ffffff';

                    const styleAttr = style === 'rounded' || style === 'square'
                        ? `background-color: ${bgColor}; color: ${textColor};`
                        : `color: ${bgColor};`;

                    const shapeClass = style === 'rounded' ? 'rounded-full' :
                                       style === 'square' ? 'rounded-lg' : '';

                    return `
                        <a href="${url}" ${openInNewTab ? 'target="_blank" rel="noopener"' : ''}
                           class="social-link ${sizeClasses[size] || sizeClasses.md} ${shapeClass} flex items-center justify-center hover:opacity-80 transition"
                           style="${styleAttr}" title="${sn.name}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="${sn.icon}"/>
                            </svg>
                            ${showLabels ? `<span class="ml-2">${sn.name}</span>` : ''}
                        </a>
                    `;
                }).join('');

            return `
                <section class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="flex flex-wrap gap-4 ${alignClass}">
                            ${linksHtml || '<p class="text-gray-500">No social links configured</p>'}
                        </div>
                    </div>
                </section>
            `;
        });

        // Map Block
        this.registerRenderer('map', (block) => {
            const title = this.getContent(block, 'title', '');
            const address = this.getContent(block, 'address', '');
            const markers = this.getContentArray(block, 'markers');
            const provider = block.settings.provider || 'openstreetmap';
            const latitude = block.settings.latitude || 44.4268;
            const longitude = block.settings.longitude || 26.1025;
            const zoom = block.settings.zoom || 14;
            const height = block.settings.height || 'md';
            const showMarker = block.settings.showMarker ?? true;
            const borderRadius = block.settings.borderRadius || 'md';

            const heightPx = {
                sm: '250px',
                md: '400px',
                lg: '500px',
                xl: '600px'
            }[height] || '400px';

            const radiusClass = {
                none: 'rounded-none',
                sm: 'rounded',
                md: 'rounded-lg',
                lg: 'rounded-xl'
            }[borderRadius] || 'rounded-lg';

            let mapHtml = '';
            if (provider === 'openstreetmap') {
                mapHtml = `
                    <iframe
                        src="https://www.openstreetmap.org/export/embed.html?bbox=${longitude - 0.01},${latitude - 0.01},${longitude + 0.01},${latitude + 0.01}&layer=mapnik${showMarker ? `&marker=${latitude},${longitude}` : ''}"
                        class="w-full ${radiusClass}" style="height: ${heightPx}; border: 0;"
                        allowfullscreen loading="lazy">
                    </iframe>
                `;
            } else {
                mapHtml = `
                    <div id="map-${block.id}" class="w-full ${radiusClass}" style="height: ${heightPx};"
                         data-provider="${provider}" data-lat="${latitude}" data-lng="${longitude}"
                         data-zoom="${zoom}" data-show-marker="${showMarker}"
                         data-markers='${JSON.stringify(markers)}'>
                        <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-500">Map loading...</span>
                        </div>
                    </div>
                `;
            }

            return `
                <section class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        ${address ? `<p class="text-gray-600 mb-4">${this.escapeHtml(address)}</p>` : ''}
                        ${mapHtml}
                    </div>
                </section>
            `;
        });

        // Tabs Block
        this.registerRenderer('tabs', (block) => {
            const tabs = this.getContentArray(block, 'tabs');
            const style = block.settings.style || 'default';
            const alignment = block.settings.alignment || 'left';
            const vertical = block.settings.vertical ?? false;

            const alignClass = {
                left: 'justify-start',
                center: 'justify-center',
                right: 'justify-end',
                full: ''
            }[alignment] || 'justify-start';

            const tabsHtml = Array.isArray(tabs) && tabs.length > 0
                ? tabs.map((tab: any, index: number) => `
                    <button @click="activeTab = ${index}"
                            :class="{ 'border-primary text-primary': activeTab === ${index}, 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== ${index} }"
                            class="px-4 py-2 border-b-2 font-medium text-sm transition ${alignment === 'full' ? 'flex-1' : ''}">
                        ${this.escapeHtml(tab.title || '')}
                    </button>
                `).join('')
                : '';

            const contentHtml = Array.isArray(tabs) && tabs.length > 0
                ? tabs.map((tab: any, index: number) => `
                    <div x-show="activeTab === ${index}" x-transition class="prose prose-lg max-w-none">
                        ${tab.content || ''}
                    </div>
                `).join('')
                : '<p class="text-gray-500">No tabs configured</p>';

            return `
                <section class="py-8">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ activeTab: 0 }">
                        <div class="flex ${alignClass} ${vertical ? 'flex-col sm:flex-row gap-4' : 'border-b border-gray-200 gap-4'}">
                            ${tabsHtml}
                        </div>
                        <div class="mt-6">
                            ${contentHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Card Block
        this.registerRenderer('card', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const cards = this.getContentArray(block, 'cards');
            const columns = block.settings.columns || 3;
            const style = block.settings.style || 'shadow';
            const showImage = block.settings.showImage ?? true;

            const styleClass = {
                default: 'bg-white',
                bordered: 'bg-white border border-gray-200',
                shadow: 'bg-white shadow-md',
                minimal: 'bg-transparent'
            }[style] || 'bg-white shadow-md';

            const cardsHtml = Array.isArray(cards) && cards.length > 0
                ? cards.map((card: any) => `
                    <div class="${styleClass} rounded-xl overflow-hidden group">
                        ${showImage && card.image ? `
                            <div class="aspect-video overflow-hidden">
                                <img src="${card.image}" alt="${this.escapeHtml(card.title || '')}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            </div>
                        ` : ''}
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">${this.escapeHtml(card.title || '')}</h3>
                            <p class="text-gray-600 mb-4">${this.escapeHtml(card.description || '')}</p>
                            ${card.link ? `
                                <a href="${card.link}" class="text-primary font-medium hover:underline">
                                    ${this.escapeHtml(card.linkText || 'Learn More')} →
                                </a>
                            ` : ''}
                        </div>
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No cards configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600 max-w-2xl mx-auto">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${columns} gap-6">
                            ${cardsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Heading Block
        this.registerRenderer('heading', (block) => {
            const heading = this.getContent(block, 'heading', '');
            const subheading = this.getContent(block, 'subheading', '');
            const level = block.settings.level || 'h2';
            const alignment = block.settings.alignment || 'left';
            const size = block.settings.size || 'md';
            const showDivider = block.settings.showDivider ?? false;

            const alignClass = {
                left: 'text-left',
                center: 'text-center',
                right: 'text-right'
            }[alignment] || 'text-left';

            const sizeClass = {
                sm: 'text-xl md:text-2xl',
                md: 'text-2xl md:text-3xl',
                lg: 'text-3xl md:text-4xl',
                xl: 'text-4xl md:text-5xl'
            }[size] || 'text-2xl md:text-3xl';

            return `
                <section class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 ${alignClass}">
                        <${level} class="${sizeClass} font-bold text-gray-900">${this.escapeHtml(heading)}</${level}>
                        ${subheading ? `<p class="mt-2 text-lg text-gray-600">${this.escapeHtml(subheading)}</p>` : ''}
                        ${showDivider ? `<div class="mt-4 w-20 h-1 bg-primary ${alignment === 'center' ? 'mx-auto' : alignment === 'right' ? 'ml-auto' : ''}"></div>` : ''}
                    </div>
                </section>
            `;
        });

        // Quote Block
        this.registerRenderer('quote', (block) => {
            const quote = this.getContent(block, 'quote', '');
            const author = this.getContent(block, 'author', '');
            const authorTitle = this.getContent(block, 'authorTitle', '');
            const style = block.settings.style || 'bordered';
            const showQuoteMarks = block.settings.showQuoteMarks ?? true;

            const styleClass = {
                default: '',
                bordered: 'border-l-4 border-primary pl-6',
                centered: 'text-center',
                card: 'bg-gray-50 p-8 rounded-xl',
                large: 'text-xl md:text-2xl'
            }[style] || 'border-l-4 border-primary pl-6';

            return `
                <section class="py-8">
                    <blockquote class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 ${styleClass}">
                        ${showQuoteMarks ? '<span class="text-4xl text-primary leading-none">"</span>' : ''}
                        <p class="text-lg md:text-xl text-gray-700 italic mb-4">${this.escapeHtml(quote)}</p>
                        ${author ? `
                            <footer class="flex items-center gap-3 ${style === 'centered' ? 'justify-center' : ''}">
                                <cite class="not-italic">
                                    <span class="font-semibold text-gray-900">${this.escapeHtml(author)}</span>
                                    ${authorTitle ? `<span class="text-gray-500"> — ${this.escapeHtml(authorTitle)}</span>` : ''}
                                </cite>
                            </footer>
                        ` : ''}
                    </blockquote>
                </section>
            `;
        });

        // Table Block
        this.registerRenderer('table', (block) => {
            const title = this.getContent(block, 'title', '');
            const caption = this.getContent(block, 'caption', '');
            const headers = this.getContentArray(block, 'headers');
            const rows = this.getContentArray(block, 'rows');
            const style = block.settings.style || 'striped';
            const showHeader = block.settings.showHeader ?? true;

            const styleClass = {
                default: '',
                striped: '[&>tbody>tr:nth-child(odd)]:bg-gray-50',
                bordered: 'border border-gray-200 [&_td]:border [&_th]:border',
                minimal: ''
            }[style] || '';

            const headersHtml = showHeader && Array.isArray(headers) && headers.length > 0
                ? `<thead class="bg-gray-100"><tr>${headers.map((h: any) => `<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">${this.escapeHtml(typeof h === 'string' ? h : h.header || '')}</th>`).join('')}</tr></thead>`
                : '';

            const rowsHtml = Array.isArray(rows) && rows.length > 0
                ? rows.map((row: any) => `
                    <tr class="hover:bg-gray-50 transition">
                        ${Array.isArray(row.cells) ? row.cells.map((cell: any) => `
                            <td class="px-4 py-3 text-sm text-gray-600">${this.escapeHtml(typeof cell === 'string' ? cell : cell.cell || '')}</td>
                        `).join('') : ''}
                    </tr>
                `).join('')
                : '<tr><td class="px-4 py-3 text-center text-gray-500" colspan="100">No data</td></tr>';

            return `
                <section class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="overflow-x-auto">
                            <table class="w-full ${styleClass}">
                                ${headersHtml}
                                <tbody class="divide-y divide-gray-200">
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                        ${caption ? `<p class="mt-2 text-sm text-gray-500">${this.escapeHtml(caption)}</p>` : ''}
                    </div>
                </section>
            `;
        });

        // List Block
        this.registerRenderer('list', (block) => {
            const title = this.getContent(block, 'title', '');
            const items = this.getContentArray(block, 'items');
            const listType = block.settings.listType || 'bullet';
            const columns = block.settings.columns || 1;

            const markerHtml = {
                bullet: '•',
                numbered: '',
                check: '✓',
                icon: '',
                none: ''
            };

            const itemsHtml = Array.isArray(items) && items.length > 0
                ? items.map((item: any, index: number) => `
                    <li class="flex gap-3">
                        ${listType !== 'none' && listType !== 'numbered' ? `<span class="text-primary flex-shrink-0">${markerHtml[listType as keyof typeof markerHtml] || '•'}</span>` : ''}
                        ${listType === 'numbered' ? `<span class="text-primary font-medium flex-shrink-0">${index + 1}.</span>` : ''}
                        <div>
                            <span class="text-gray-900">${this.escapeHtml(item.text || '')}</span>
                            ${item.subtext ? `<span class="block text-sm text-gray-500">${this.escapeHtml(item.subtext)}</span>` : ''}
                        </div>
                    </li>
                `).join('')
                : '<li class="text-gray-500">No items configured</li>';

            return `
                <section class="py-8">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        <ul class="grid grid-cols-1 ${columns > 1 ? `md:grid-cols-${columns}` : ''} gap-3">
                            ${itemsHtml}
                        </ul>
                    </div>
                </section>
            `;
        });

        // Icon Box Block
        this.registerRenderer('icon-box', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const boxes = this.getContentArray(block, 'boxes');
            const columns = block.settings.columns || 3;
            const style = block.settings.style || 'default';
            const iconPosition = block.settings.iconPosition || 'top';

            const styleClass = {
                default: '',
                bordered: 'border border-gray-200 rounded-xl',
                filled: 'bg-gray-50 rounded-xl',
                minimal: ''
            }[style] || '';

            const boxesHtml = Array.isArray(boxes) && boxes.length > 0
                ? boxes.map((box: any) => `
                    <div class="p-6 ${styleClass} ${iconPosition === 'top' ? 'text-center' : 'flex gap-4'}">
                        <div class="${iconPosition === 'top' ? 'w-16 h-16 mx-auto mb-4' : 'w-12 h-12 flex-shrink-0'} bg-primary/10 rounded-full flex items-center justify-center text-primary text-2xl">
                            ${box.icon ? `<span>${box.icon.replace('heroicon-o-', '')}</span>` : '★'}
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">${this.escapeHtml(box.title || '')}</h3>
                            <p class="text-gray-600">${this.escapeHtml(box.description || '')}</p>
                        </div>
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No items configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${columns} gap-6">
                            ${boxesHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Logo Block
        this.registerRenderer('logo', (block) => {
            const logo = this.getContent(block, 'logo', '');
            const altText = this.getContent(block, 'altText', 'Logo');
            const link = this.getContent(block, 'link', '');
            const size = block.settings.size || 'md';
            const alignment = block.settings.alignment || 'left';
            const linkToHome = block.settings.linkToHome ?? true;

            const sizeClass = {
                xs: 'h-6',
                sm: 'h-8',
                md: 'h-12',
                lg: 'h-16',
                xl: 'h-24'
            }[size] || 'h-12';

            const alignClass = {
                left: 'justify-start',
                center: 'justify-center',
                right: 'justify-end'
            }[alignment] || 'justify-start';

            const href = link || (linkToHome ? '/' : '');
            const logoHtml = logo
                ? `<img src="${logo}" alt="${this.escapeHtml(altText)}" class="${sizeClass} w-auto">`
                : `<span class="text-2xl font-bold text-primary">Logo</span>`;

            return `
                <div class="flex ${alignClass} py-4">
                    ${href ? `<a href="${href}">${logoHtml}</a>` : logoHtml}
                </div>
            `;
        });

        // Pricing Block
        this.registerRenderer('pricing', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const plans = this.getContentArray(block, 'plans');
            const columns = block.settings.columns || 3;

            const plansHtml = Array.isArray(plans) && plans.length > 0
                ? plans.map((plan: any) => `
                    <div class="relative bg-white rounded-2xl shadow-lg overflow-hidden ${plan.featured ? 'ring-2 ring-primary' : ''}">
                        ${plan.featured && plan.badge ? `
                            <div class="absolute top-0 right-0 bg-primary text-white px-4 py-1 text-sm font-medium rounded-bl-lg">
                                ${this.escapeHtml(plan.badge)}
                            </div>
                        ` : ''}
                        <div class="p-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">${this.escapeHtml(plan.name || '')}</h3>
                            <p class="text-gray-600 mb-4">${this.escapeHtml(plan.description || '')}</p>
                            <div class="mb-6">
                                <span class="text-4xl font-bold text-gray-900">${this.escapeHtml(plan.price || '')}</span>
                                <span class="text-gray-500">${this.escapeHtml(plan.period || '')}</span>
                            </div>
                            <ul class="space-y-3 mb-8">
                                ${Array.isArray(plan.features) ? plan.features.map((f: any) => `
                                    <li class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span class="text-gray-600">${this.escapeHtml(typeof f === 'string' ? f : f.feature || '')}</span>
                                    </li>
                                `).join('') : ''}
                            </ul>
                            <a href="${plan.buttonLink || '#'}" class="block w-full text-center px-6 py-3 ${plan.featured ? 'bg-primary text-white' : 'bg-gray-100 text-gray-900 hover:bg-gray-200'} font-semibold rounded-lg transition">
                                ${this.escapeHtml(plan.buttonText || 'Get Started')}
                            </a>
                        </div>
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No pricing plans configured</p>';

            return `
                <section class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${columns} gap-8">
                            ${plansHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Team Block
        this.registerRenderer('team', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const members = this.getContentArray(block, 'members');
            const columns = block.settings.columns || 4;
            const showBio = block.settings.showBio ?? true;
            const showSocial = block.settings.showSocial ?? true;

            const membersHtml = Array.isArray(members) && members.length > 0
                ? members.map((member: any) => `
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden bg-gray-200">
                            ${member.photo ? `<img src="${member.photo}" alt="${this.escapeHtml(member.name || '')}" class="w-full h-full object-cover">` : `
                                <div class="w-full h-full flex items-center justify-center text-4xl font-bold text-gray-400">
                                    ${(member.name || 'T').charAt(0)}
                                </div>
                            `}
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">${this.escapeHtml(member.name || '')}</h3>
                        <p class="text-primary text-sm mb-2">${this.escapeHtml(member.role || '')}</p>
                        ${showBio && member.bio ? `<p class="text-gray-600 text-sm">${this.escapeHtml(member.bio)}</p>` : ''}
                        ${showSocial && (member.linkedin || member.twitter) ? `
                            <div class="flex justify-center gap-2 mt-3">
                                ${member.linkedin ? `<a href="${member.linkedin}" target="_blank" class="text-gray-400 hover:text-primary">LinkedIn</a>` : ''}
                                ${member.twitter ? `<a href="${member.twitter}" target="_blank" class="text-gray-400 hover:text-primary">Twitter</a>` : ''}
                            </div>
                        ` : ''}
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No team members configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-2 md:grid-cols-${columns} gap-8">
                            ${membersHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Contact Info Block
        this.registerRenderer('contact-info', (block) => {
            const title = this.getContent(block, 'title', '');
            const description = this.getContent(block, 'description', '');
            const contacts = this.getContentArray(block, 'contacts');
            const layout = block.settings.layout || 'vertical';
            const showIcons = block.settings.showIcons ?? true;

            const icons: Record<string, string> = {
                phone: '📞',
                email: '✉️',
                address: '📍',
                hours: '🕐',
                website: '🌐',
                custom: '•'
            };

            const contactsHtml = Array.isArray(contacts) && contacts.length > 0
                ? contacts.map((contact: any) => {
                    const icon = showIcons ? icons[contact.type as keyof typeof icons] || icons.custom : '';
                    let href = contact.link || '';
                    if (!href && contact.type === 'phone') href = `tel:${contact.value}`;
                    if (!href && contact.type === 'email') href = `mailto:${contact.value}`;

                    return `
                        <div class="flex items-start gap-3">
                            ${icon ? `<span class="text-xl">${icon}</span>` : ''}
                            <div>
                                ${contact.label ? `<span class="text-sm text-gray-500 block">${this.escapeHtml(contact.label)}</span>` : ''}
                                ${href ? `<a href="${href}" class="text-gray-900 hover:text-primary">${this.escapeHtml(contact.value || '')}</a>` : `<span class="text-gray-900">${this.escapeHtml(contact.value || '')}</span>`}
                            </div>
                        </div>
                    `;
                }).join('')
                : '<p class="text-gray-500">No contact information configured</p>';

            const layoutClass = layout === 'horizontal' ? 'flex flex-wrap gap-8' : layout === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : 'space-y-4';

            return `
                <section class="py-8">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        ${description ? `<p class="text-gray-600 mb-6">${this.escapeHtml(description)}</p>` : ''}
                        <div class="${layoutClass}">
                            ${contactsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Form Block
        this.registerRenderer('form', (block) => {
            const title = this.getContent(block, 'title', '');
            const description = this.getContent(block, 'description', '');
            const fields = this.getContentArray(block, 'fields');
            const submitText = this.getContent(block, 'submitText', 'Submit');
            const successMessage = this.getContent(block, 'successMessage', 'Thank you!');
            const layout = block.settings.layout || 'vertical';

            const fieldsHtml = Array.isArray(fields) && fields.length > 0
                ? fields.map((field: any) => {
                    const inputClass = 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent';
                    const widthClass = field.width === 'half' ? 'md:col-span-1' : 'md:col-span-2';
                    let inputHtml = '';

                    switch (field.type) {
                        case 'textarea':
                            inputHtml = `<textarea name="${field.name}" placeholder="${this.escapeHtml(field.placeholder || '')}" ${field.required ? 'required' : ''} rows="4" class="${inputClass}"></textarea>`;
                            break;
                        case 'select':
                            const options = (field.options || '').split(',').map((o: string) => `<option value="${o.trim()}">${o.trim()}</option>`).join('');
                            inputHtml = `<select name="${field.name}" ${field.required ? 'required' : ''} class="${inputClass}"><option value="">Select...</option>${options}</select>`;
                            break;
                        case 'checkbox':
                            inputHtml = `<label class="flex items-center gap-2"><input type="checkbox" name="${field.name}" ${field.required ? 'required' : ''} class="w-4 h-4 text-primary rounded"> ${this.escapeHtml(field.label || '')}</label>`;
                            break;
                        default:
                            inputHtml = `<input type="${field.type || 'text'}" name="${field.name}" placeholder="${this.escapeHtml(field.placeholder || '')}" ${field.required ? 'required' : ''} class="${inputClass}">`;
                    }

                    return `
                        <div class="${widthClass}">
                            ${field.type !== 'checkbox' ? `<label class="block text-sm font-medium text-gray-700 mb-2">${this.escapeHtml(field.label || '')}${field.required ? ' *' : ''}</label>` : ''}
                            ${inputHtml}
                        </div>
                    `;
                }).join('')
                : '';

            return `
                <section class="py-8">
                    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        ${description ? `<p class="text-gray-600 mb-6">${this.escapeHtml(description)}</p>` : ''}
                        <form id="form-${block.id}" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-success="${this.escapeHtml(successMessage)}">
                            ${fieldsHtml}
                            <div class="md:col-span-2">
                                <button type="submit" class="px-8 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                                    ${this.escapeHtml(submitText)}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            `;
        });

        // Audio Block
        this.registerRenderer('audio', (block) => {
            const title = this.getContent(block, 'title', '');
            const artist = this.getContent(block, 'artist', '');
            const audioUrl = this.getContent(block, 'audioUrl', '') || block.settings.audioFile || '';
            const coverImage = this.getContent(block, 'coverImage', '');
            const autoplay = block.settings.autoplay ?? false;
            const loop = block.settings.loop ?? false;
            const showDownload = block.settings.showDownload ?? false;

            return `
                <section class="py-8">
                    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="bg-white rounded-xl shadow-md p-6 flex gap-4 items-center">
                            ${coverImage ? `<img src="${coverImage}" alt="${this.escapeHtml(title)}" class="w-20 h-20 rounded-lg object-cover">` : `
                                <div class="w-20 h-20 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                                </div>
                            `}
                            <div class="flex-1">
                                ${title ? `<h3 class="font-semibold text-gray-900">${this.escapeHtml(title)}</h3>` : ''}
                                ${artist ? `<p class="text-sm text-gray-500">${this.escapeHtml(artist)}</p>` : ''}
                                ${audioUrl ? `
                                    <audio controls ${autoplay ? 'autoplay' : ''} ${loop ? 'loop' : ''} class="w-full mt-2">
                                        <source src="${audioUrl}" type="audio/mpeg">
                                    </audio>
                                ` : '<p class="text-gray-400 text-sm mt-2">No audio file</p>'}
                            </div>
                            ${showDownload && audioUrl ? `<a href="${audioUrl}" download class="text-primary hover:underline">Download</a>` : ''}
                        </div>
                    </div>
                </section>
            `;
        });

        // Embed Block
        this.registerRenderer('embed', (block) => {
            const title = this.getContent(block, 'title', '');
            const url = this.getContent(block, 'url', '');
            const caption = this.getContent(block, 'caption', '');
            const type = block.settings.type || 'url';
            const aspectRatio = block.settings.aspectRatio || '16:9';
            const maxWidth = block.settings.maxWidth || 800;

            const aspectClass = {
                '16:9': 'aspect-video',
                '4:3': 'aspect-[4/3]',
                '1:1': 'aspect-square',
                '9:16': 'aspect-[9/16]',
                'auto': ''
            }[aspectRatio] || 'aspect-video';

            let embedHtml = '';
            if (url) {
                if (type === 'youtube') {
                    const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1] || '';
                    embedHtml = `<iframe src="https://www.youtube.com/embed/${videoId}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>`;
                } else if (type === 'vimeo') {
                    const videoId = url.match(/(?:vimeo\.com\/)(\d+)/)?.[1] || '';
                    embedHtml = `<iframe src="https://player.vimeo.com/video/${videoId}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>`;
                } else {
                    embedHtml = `<iframe src="${url}" class="w-full h-full" frameborder="0"></iframe>`;
                }
            } else {
                embedHtml = `<div class="w-full h-full bg-gray-200 flex items-center justify-center"><span class="text-gray-400">No embed URL</span></div>`;
            }

            return `
                <section class="py-8">
                    <div class="mx-auto px-4 sm:px-6 lg:px-8" style="max-width: ${maxWidth}px">
                        ${title ? `<h2 class="text-xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="${aspectClass} rounded-lg overflow-hidden">
                            ${embedHtml}
                        </div>
                        ${caption ? `<p class="mt-2 text-sm text-gray-500 text-center">${this.escapeHtml(caption)}</p>` : ''}
                    </div>
                </section>
            `;
        });

        // File Download Block
        this.registerRenderer('file-download', (block) => {
            const title = this.getContent(block, 'title', '');
            const files = this.getContentArray(block, 'files');
            const style = block.settings.style || 'list';
            const showFileSize = block.settings.showFileSize ?? true;
            const showFileType = block.settings.showFileType ?? true;

            const fileIcons: Record<string, string> = {
                pdf: '📄',
                doc: '📝',
                xls: '📊',
                zip: '📦',
                image: '🖼️',
                other: '📁'
            };

            const filesHtml = Array.isArray(files) && files.length > 0
                ? files.map((file: any) => `
                    <a href="${file.file || file.externalUrl || '#'}" target="_blank" class="flex items-center gap-4 p-4 bg-white rounded-lg shadow hover:shadow-md transition group">
                        ${showFileType ? `<span class="text-2xl">${fileIcons[file.fileType as keyof typeof fileIcons] || fileIcons.other}</span>` : ''}
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-gray-900 group-hover:text-primary truncate block">${this.escapeHtml(file.name || 'File')}</span>
                            ${file.description ? `<span class="text-sm text-gray-500 truncate block">${this.escapeHtml(file.description)}</span>` : ''}
                        </div>
                        ${showFileSize && file.fileSize ? `<span class="text-sm text-gray-400">${this.escapeHtml(file.fileSize)}</span>` : ''}
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </a>
                `).join('')
                : '<p class="text-gray-500 text-center">No files configured</p>';

            return `
                <section class="py-8">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title ? `<h2 class="text-2xl font-bold text-gray-900 mb-6">${this.escapeHtml(title)}</h2>` : ''}
                        <div class="space-y-3">
                            ${filesHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Breadcrumb Block
        this.registerRenderer('breadcrumb', (block) => {
            const items = this.getContentArray(block, 'items');
            const separator = block.settings.separator || 'chevron';
            const showHomeIcon = block.settings.showHomeIcon ?? true;

            const separators: Record<string, string> = {
                chevron: '<svg class="w-4 h-4 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>',
                slash: '<span class="mx-2 text-gray-400">/</span>',
                arrow: '<span class="mx-2 text-gray-400">→</span>',
                dot: '<span class="mx-2 text-gray-400">•</span>'
            };

            const sep = separators[separator] || separators.chevron;

            const itemsHtml = Array.isArray(items) && items.length > 0
                ? items.map((item: any, index: number) => {
                    const isLast = index === items.length - 1 || item.isCurrentPage;
                    const content = index === 0 && showHomeIcon
                        ? `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>`
                        : this.escapeHtml(item.label || '');

                    return `
                        ${index > 0 ? sep : ''}
                        ${isLast || !item.url
                            ? `<span class="text-gray-500">${content}</span>`
                            : `<a href="${item.url}" class="text-primary hover:underline">${content}</a>`
                        }
                    `;
                }).join('')
                : '';

            return `
                <nav class="py-4">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <ol class="flex items-center text-sm">
                            ${itemsHtml}
                        </ol>
                    </div>
                </nav>
            `;
        });

        // Timeline Block
        this.registerRenderer('timeline', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const items = this.getContentArray(block, 'items');
            const style = block.settings.style || 'vertical';

            const statusColors: Record<string, string> = {
                completed: 'bg-green-500',
                current: 'bg-primary',
                upcoming: 'bg-gray-300'
            };

            const itemsHtml = Array.isArray(items) && items.length > 0
                ? items.map((item: any, index: number) => `
                    <div class="relative pl-8 pb-8 ${index === items.length - 1 ? '' : 'border-l-2 border-gray-200 ml-3'}">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full ${statusColors[item.status as keyof typeof statusColors] || statusColors.completed} flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-6">
                            <span class="text-sm font-medium text-primary">${this.escapeHtml(item.date || '')}</span>
                            <h3 class="text-lg font-semibold text-gray-900 mt-1">${this.escapeHtml(item.title || '')}</h3>
                            ${item.description ? `<div class="mt-2 text-gray-600 prose prose-sm">${item.description}</div>` : ''}
                        </div>
                    </div>
                `).join('')
                : '<p class="text-gray-500 text-center">No timeline items configured</p>';

            return `
                <section class="py-16">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="relative">
                            ${itemsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Reviews Block
        this.registerRenderer('reviews', (block) => {
            const title = this.getContent(block, 'title', '');
            const subtitle = this.getContent(block, 'subtitle', '');
            const reviews = this.getContentArray(block, 'reviews');
            const layout = block.settings.layout || 'grid';
            const columns = block.settings.columns || 2;
            const showRating = block.settings.showRating ?? true;
            const showVerifiedBadge = block.settings.showVerifiedBadge ?? true;

            const stars = (rating: number) => '★'.repeat(rating) + '☆'.repeat(5 - rating);

            const reviewsHtml = Array.isArray(reviews) && reviews.length > 0
                ? reviews.map((review: any) => `
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex items-center gap-4 mb-4">
                            ${review.avatar ? `<img src="${review.avatar}" class="w-12 h-12 rounded-full">` : `
                                <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                    ${(review.author || 'A').charAt(0)}
                                </div>
                            `}
                            <div>
                                <div class="font-semibold text-gray-900">${this.escapeHtml(review.author || '')}</div>
                                ${showVerifiedBadge && review.verified ? `<span class="text-xs text-green-600">✓ Verified</span>` : ''}
                            </div>
                        </div>
                        ${showRating ? `<div class="text-yellow-400 mb-2">${stars(review.rating || 5)}</div>` : ''}
                        ${review.title ? `<h4 class="font-medium text-gray-900 mb-2">${this.escapeHtml(review.title)}</h4>` : ''}
                        <p class="text-gray-600">${this.escapeHtml(review.content || '')}</p>
                        ${review.date ? `<p class="text-sm text-gray-400 mt-3">${this.escapeHtml(review.date)}</p>` : ''}
                    </div>
                `).join('')
                : '<p class="col-span-full text-center text-gray-500">No reviews configured</p>';

            return `
                <section class="py-16 bg-gray-50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        ${title || subtitle ? `
                            <div class="text-center mb-12">
                                ${title ? `<h2 class="text-3xl font-bold text-gray-900 mb-4">${this.escapeHtml(title)}</h2>` : ''}
                                ${subtitle ? `<p class="text-lg text-gray-600">${this.escapeHtml(subtitle)}</p>` : ''}
                            </div>
                        ` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-${columns} gap-6">
                            ${reviewsHtml}
                        </div>
                    </div>
                </section>
            `;
        });

        // Header Block
        this.registerRenderer('header', (block) => {
            const logo = this.getContent(block, 'logo', '');
            const logoAlt = this.getContent(block, 'logoAlt', 'Logo');
            const navigation = this.getContentArray(block, 'navigation');
            const ctaText = this.getContent(block, 'ctaText', '');
            const ctaUrl = this.getContent(block, 'ctaUrl', '/');
            const sticky = block.settings.sticky ?? true;
            const showCta = block.settings.showCta ?? true;
            const backgroundColor = block.settings.backgroundColor || 'white';

            const bgClass = {
                white: 'bg-white',
                dark: 'bg-gray-900 text-white',
                transparent: 'bg-transparent',
                primary: 'bg-primary text-white'
            }[backgroundColor] || 'bg-white';

            const navHtml = Array.isArray(navigation) && navigation.length > 0
                ? navigation.map((item: any) => `
                    <a href="${item.url || '#'}" ${item.isExternal ? 'target="_blank"' : ''} class="hover:text-primary transition">
                        ${this.escapeHtml(item.label || '')}
                    </a>
                `).join('')
                : '';

            return `
                <header class="${bgClass} ${sticky ? 'sticky top-0 z-50' : ''} shadow-sm" x-data="{ mobileOpen: false }">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center justify-between h-16">
                            <a href="/" class="flex-shrink-0">
                                ${logo ? `<img src="${logo}" alt="${this.escapeHtml(logoAlt)}" class="h-8 w-auto">` : `<span class="text-xl font-bold">Logo</span>`}
                            </a>
                            <nav class="hidden md:flex items-center gap-8">
                                ${navHtml}
                                ${showCta && ctaText ? `<a href="${ctaUrl}" class="px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary-dark transition">${this.escapeHtml(ctaText)}</a>` : ''}
                            </nav>
                            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div x-show="mobileOpen" x-collapse class="md:hidden border-t">
                        <nav class="px-4 py-4 space-y-2">
                            ${navHtml}
                            ${showCta && ctaText ? `<a href="${ctaUrl}" class="block px-4 py-2 bg-primary text-white rounded-lg font-medium text-center">${this.escapeHtml(ctaText)}</a>` : ''}
                        </nav>
                    </div>
                </header>
            `;
        });

        // Footer Block
        this.registerRenderer('footer', (block) => {
            const logo = this.getContent(block, 'logo', '');
            const description = this.getContent(block, 'description', '');
            const linkGroups = this.getContentArray(block, 'linkGroups');
            const socialLinks = this.getContentArray(block, 'socialLinks');
            const copyright = this.getContent(block, 'copyright', '');
            const email = this.getContent(block, 'email', '');
            const phone = this.getContent(block, 'phone', '');
            const style = block.settings.style || 'default';

            const bgClass = style === 'dark' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-900';
            const mutedClass = style === 'dark' ? 'text-gray-400' : 'text-gray-600';

            const linkGroupsHtml = Array.isArray(linkGroups) && linkGroups.length > 0
                ? linkGroups.map((group: any) => `
                    <div>
                        <h3 class="font-semibold mb-4">${this.escapeHtml(group.title || '')}</h3>
                        <ul class="space-y-2">
                            ${Array.isArray(group.links) ? group.links.map((link: any) => `
                                <li><a href="${link.url || '#'}" class="${mutedClass} hover:text-primary transition">${this.escapeHtml(link.label || '')}</a></li>
                            `).join('') : ''}
                        </ul>
                    </div>
                `).join('')
                : '';

            return `
                <footer class="${bgClass} pt-16 pb-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                            <div>
                                ${logo ? `<img src="${logo}" alt="Logo" class="h-8 w-auto mb-4">` : ''}
                                ${description ? `<div class="${mutedClass} prose prose-sm">${description}</div>` : ''}
                                ${email ? `<p class="mt-4"><a href="mailto:${email}" class="${mutedClass} hover:text-primary">${email}</a></p>` : ''}
                                ${phone ? `<p><a href="tel:${phone}" class="${mutedClass} hover:text-primary">${phone}</a></p>` : ''}
                            </div>
                            ${linkGroupsHtml}
                        </div>
                        <div class="border-t ${style === 'dark' ? 'border-gray-800' : 'border-gray-200'} pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                            <p class="${mutedClass} text-sm">${this.escapeHtml(copyright)}</p>
                            ${Array.isArray(socialLinks) && socialLinks.length > 0 ? `
                                <div class="flex gap-4">
                                    ${socialLinks.map((s: any) => `<a href="${s.url || '#'}" target="_blank" class="${mutedClass} hover:text-primary">${s.platform}</a>`).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </footer>
            `;
        });

        // Menu Block
        this.registerRenderer('menu', (block) => {
            const items = this.getContentArray(block, 'items');
            const style = block.settings.style || 'horizontal';
            const alignment = block.settings.alignment || 'left';

            const alignClass = {
                left: 'justify-start',
                center: 'justify-center',
                right: 'justify-end',
                justified: 'justify-between'
            }[alignment] || 'justify-start';

            const styleClass = style === 'vertical' ? 'flex-col space-y-2' : 'flex-row flex-wrap gap-6';

            const itemsHtml = Array.isArray(items) && items.length > 0
                ? items.map((item: any) => `
                    <a href="${item.url || '#'}" ${item.isExternal ? 'target="_blank"' : ''}
                       class="${item.highlighted ? 'text-primary font-semibold' : 'text-gray-700'} hover:text-primary transition">
                        ${this.escapeHtml(item.label || '')}
                    </a>
                `).join('')
                : '';

            return `
                <nav class="py-4">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex ${alignClass} ${styleClass}">
                            ${itemsHtml}
                        </div>
                    </div>
                </nav>
            `;
        });

        // Columns Block
        this.registerRenderer('columns', (block) => {
            const columns = this.getContentArray(block, 'columns');
            const numColumns = block.settings.columns || 2;
            const gap = block.settings.gap || 'md';
            const stackOnMobile = block.settings.stackOnMobile ?? true;

            const gapClass = {
                none: 'gap-0',
                sm: 'gap-4',
                md: 'gap-8',
                lg: 'gap-12',
                xl: 'gap-16'
            }[gap] || 'gap-8';

            const columnsHtml = Array.isArray(columns) && columns.length > 0
                ? columns.map((col: any) => {
                    const bgClass = col.background === 'light' ? 'bg-gray-50' : col.background === 'white' ? 'bg-white' : col.background === 'primary-light' ? 'bg-primary/5' : '';
                    const padClass = col.padding === 'sm' ? 'p-4' : col.padding === 'md' ? 'p-6' : col.padding === 'lg' ? 'p-8' : '';
                    return `<div class="${bgClass} ${padClass} rounded-lg prose prose-lg max-w-none">${col.content || ''}</div>`;
                }).join('')
                : '';

            return `
                <section class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="grid ${stackOnMobile ? 'grid-cols-1' : ''} md:grid-cols-${numColumns} ${gapClass}">
                            ${columnsHtml}
                        </div>
                    </div>
                </section>
            `;
        });
    }

    /**
     * Extract YouTube video ID from URL
     */
    private static extractYouTubeId(url: string): string {
        const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
        return match ? match[1] : '';
    }

    /**
     * Extract Vimeo video ID from URL
     */
    private static extractVimeoId(url: string): string {
        const match = url.match(/(?:vimeo\.com\/)(\d+)/);
        return match ? match[1] : '';
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
                case 'event-list':
                    this.initEventList(block);
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
                case 'countdown':
                    this.initCountdown(block);
                    break;
                case 'slider':
                    this.initSlider(block);
                    break;
                case 'stats-counter':
                    this.initStatsCounter(block);
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

    /**
     * Initialize event list with API data
     */
    private static async initEventList(block: Block): Promise<void> {
        const container = document.getElementById(`event-list-${block.id}`);
        if (!container) return;

        const source = container.dataset.source || 'upcoming';
        const limit = parseInt(container.dataset.limit || '5', 10);
        const showDate = container.dataset.showDate === 'true';
        const showVenue = container.dataset.showVenue === 'true';
        const showPrice = container.dataset.showPrice === 'true';
        const emptyMessage = container.dataset.emptyMessage || 'No events found';

        try {
            const response = await fetch(`${this.config.apiEndpoint}/events?filter=${source}&limit=${limit}`);
            if (!response.ok) return;

            const data = await response.json();
            const events = data.data || [];

            if (events.length === 0) {
                container.innerHTML = `<p class="text-center text-gray-500 py-8">${this.escapeHtml(emptyMessage)}</p>`;
                return;
            }

            container.innerHTML = events.map((event: any) => `
                <a href="/event/${event.slug}" class="flex gap-4 p-4 bg-white rounded-lg shadow hover:shadow-md transition group">
                    <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-200">
                        ${event.image ? `<img src="${event.image}" alt="${this.escapeHtml(event.title)}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">` : ''}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 group-hover:text-primary transition truncate">${this.escapeHtml(event.title)}</h3>
                        ${showDate && event.date_formatted ? `<p class="text-sm text-primary font-medium">${event.date_formatted}</p>` : ''}
                        ${showVenue && event.venue ? `<p class="text-sm text-gray-500">${event.venue}</p>` : ''}
                        ${showPrice && event.price_from ? `<p class="text-sm font-medium text-gray-900 mt-1">From ${event.currency || '€'}${event.price_from}</p>` : ''}
                    </div>
                </a>
            `).join('');
        } catch (error) {
            console.error('[PageBuilder] Failed to load event list:', error);
        }
    }

    /**
     * Initialize countdown timer
     */
    private static initCountdown(block: Block): void {
        const container = document.getElementById(`countdown-${block.id}`);
        if (!container) return;

        const targetDate = container.dataset.target;
        const expiredMessage = container.dataset.expiredMessage || 'Event has started!';

        if (!targetDate) return;

        const target = new Date(targetDate).getTime();
        const daysEl = container.querySelector('[data-days]');
        const hoursEl = container.querySelector('[data-hours]');
        const minutesEl = container.querySelector('[data-minutes]');
        const secondsEl = container.querySelector('[data-seconds]');

        const updateCountdown = () => {
            const now = Date.now();
            const distance = target - now;

            if (distance < 0) {
                container.innerHTML = `<p class="text-2xl font-bold text-primary">${expiredMessage}</p>`;
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
            if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
            if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
            if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
        };

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    /**
     * Initialize slider/carousel
     */
    private static initSlider(block: Block): void {
        const container = document.getElementById(`slider-${block.id}`);
        if (!container) return;

        const slides = container.querySelectorAll('.slide');
        if (slides.length <= 1) return;

        const autoplay = container.dataset.autoplay === 'true';
        const speed = parseInt(container.dataset.speed || '5000', 10);
        let currentIndex = 0;
        let interval: ReturnType<typeof setInterval> | null = null;

        const showSlide = (index: number) => {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
                (slide as HTMLElement).style.opacity = i === index ? '1' : '0';
            });

            const dots = container.querySelectorAll('.slider-dots button');
            dots.forEach((dot, i) => {
                dot.classList.toggle('bg-white', i === index);
                dot.classList.toggle('bg-white/50', i !== index);
            });

            currentIndex = index;
        };

        const nextSlide = () => {
            showSlide((currentIndex + 1) % slides.length);
        };

        const prevSlide = () => {
            showSlide((currentIndex - 1 + slides.length) % slides.length);
        };

        // Navigation buttons
        const prevBtn = container.querySelector('.slider-prev');
        const nextBtn = container.querySelector('.slider-next');
        prevBtn?.addEventListener('click', () => {
            prevSlide();
            resetInterval();
        });
        nextBtn?.addEventListener('click', () => {
            nextSlide();
            resetInterval();
        });

        // Dot navigation
        container.querySelectorAll('.slider-dots button').forEach((dot, index) => {
            dot.addEventListener('click', () => {
                showSlide(index);
                resetInterval();
            });
        });

        const resetInterval = () => {
            if (interval) clearInterval(interval);
            if (autoplay) {
                interval = setInterval(nextSlide, speed);
            }
        };

        // Start autoplay
        if (autoplay) {
            interval = setInterval(nextSlide, speed);
        }

        // Pause on hover
        container.addEventListener('mouseenter', () => {
            if (interval) clearInterval(interval);
        });
        container.addEventListener('mouseleave', () => {
            if (autoplay) {
                interval = setInterval(nextSlide, speed);
            }
        });
    }

    /**
     * Initialize stats counter animation
     */
    private static initStatsCounter(block: Block): void {
        const container = document.getElementById(`stats-${block.id}`);
        if (!container) return;

        const animate = container.dataset.animate === 'true';
        if (!animate) return;

        const countElements = container.querySelectorAll('[data-count-to]');
        let animated = false;

        const animateCount = (el: Element) => {
            const target = parseInt(el.getAttribute('data-count-to') || '0', 10);
            const duration = 2000;
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;

            const updateCount = () => {
                current += increment;
                if (current < target) {
                    el.textContent = Math.floor(current).toString();
                    requestAnimationFrame(updateCount);
                } else {
                    el.textContent = target.toString();
                }
            };

            requestAnimationFrame(updateCount);
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !animated) {
                    animated = true;
                    countElements.forEach(el => animateCount(el));
                    observer.disconnect();
                }
            });
        }, { threshold: 0.5 });

        observer.observe(container);
    }
}
