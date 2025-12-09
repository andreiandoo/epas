import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

interface BlogArticle {
    id: string;
    slug: string;
    title: string;
    subtitle: string;
    excerpt: string;
    content?: string;
    content_html?: string;
    featured_image: string | null;
    featured_image_alt: string | null;
    category: string | null;
    category_slug: string | null;
    author: string | null;
    published_at: string;
    reading_time: number;
    view_count: number;
    is_featured: boolean;
    meta_title?: string;
    meta_description?: string;
    og_title?: string;
    og_description?: string;
    og_image?: string;
    tags?: string[];
}

interface BlogCategory {
    id: string;
    slug: string;
    name: string;
    description: string;
    articles_count: number;
}

interface BlogFilters {
    category?: string;
    page?: number;
    per_page?: number;
    featured?: boolean;
}

export class BlogModule {
    name = 'blog';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private articles: BlogArticle[] = [];
    private categories: BlogCategory[] = [];
    private currentFilters: BlogFilters = {};
    private pagination: { total: number; per_page: number; current_page: number; last_page: number } | null = null;

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;

        // Listen for route changes
        this.eventBus.on('route:blog', () => this.loadBlog());
        this.eventBus.on('route:blog-article', (slug: string) => this.loadArticle(slug));

        console.log('Blog module initialized');
    }

    async loadBlog(filters: BlogFilters = {}): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('blog-list');
        if (!container) return;

        this.currentFilters = { ...this.currentFilters, ...filters };

        try {
            // Load categories if not loaded
            if (this.categories.length === 0) {
                await this.loadCategories();
            }

            const params = new URLSearchParams();
            if (this.currentFilters.category) params.set('category', this.currentFilters.category);
            if (this.currentFilters.page) params.set('page', String(this.currentFilters.page));
            if (this.currentFilters.per_page) params.set('per_page', String(this.currentFilters.per_page));
            if (this.currentFilters.featured) params.set('featured', '1');

            const response = await this.apiClient.get(`/blog?${params.toString()}`);
            this.articles = response.data.articles || [];
            this.pagination = response.data.pagination;

            container.innerHTML = this.renderBlogPage();
            this.bindEventListeners();
        } catch (error: any) {
            if (error.response?.status === 403) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <p class="text-gray-500">Blog is not available.</p>
                    </div>
                `;
            } else {
                container.innerHTML = '<p class="text-red-500">Failed to load blog. Please try again.</p>';
                console.error('Failed to load blog:', error);
            }
        }
    }

    private async loadCategories(): Promise<void> {
        if (!this.apiClient) return;

        try {
            const response = await this.apiClient.get('/blog/categories');
            this.categories = response.data.categories || [];
        } catch (error) {
            console.error('Failed to load categories:', error);
        }
    }

    private renderBlogPage(): string {
        return `
            <div class="blog-page">
                ${this.renderCategoryFilter()}
                ${this.articles.length === 0
                    ? '<p class="text-center text-gray-500 py-8">No articles found.</p>'
                    : this.renderArticlesList()
                }
                ${this.renderPagination()}
            </div>
        `;
    }

    private renderCategoryFilter(): string {
        if (this.categories.length === 0) return '';

        return `
            <div class="blog-categories mb-8">
                <div class="flex flex-wrap gap-2">
                    <button class="category-filter px-4 py-2 rounded-full text-sm font-medium transition-colors
                        ${!this.currentFilters.category ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}"
                        data-category="">
                        All
                    </button>
                    ${this.categories.map(cat => `
                        <button class="category-filter px-4 py-2 rounded-full text-sm font-medium transition-colors
                            ${this.currentFilters.category === cat.slug ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}"
                            data-category="${cat.slug}">
                            ${cat.name} (${cat.articles_count})
                        </button>
                    `).join('')}
                </div>
            </div>
        `;
    }

    private renderArticlesList(): string {
        return `
            <div class="blog-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem;">
                ${this.articles.map(article => this.renderArticleCard(article)).join('')}
            </div>
        `;
    }

    private renderArticleCard(article: BlogArticle): string {
        const publishedDate = article.published_at
            ? new Date(article.published_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
            : '';

        return `
            <article class="blog-card bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow border border-gray-100 dark:border-gray-700">
                <a href="#/blog/${article.slug}" class="block">
                    ${article.featured_image
                        ? `<div class="aspect-[16/9] overflow-hidden">
                            <img src="${article.featured_image}"
                                 alt="${article.featured_image_alt || article.title}"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                           </div>`
                        : `<div class="aspect-[16/9] bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                            <svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                            </svg>
                           </div>`
                    }
                </a>
                <div class="p-5">
                    ${article.category
                        ? `<span class="inline-block px-3 py-1 text-xs font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full mb-3">${article.category}</span>`
                        : ''
                    }
                    <a href="#/blog/${article.slug}" class="block group">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2 mb-2">
                            ${article.title}
                        </h3>
                    </a>
                    ${article.excerpt
                        ? `<p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-4">${article.excerpt}</p>`
                        : ''
                    }
                    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>${publishedDate}</span>
                        <span>${article.reading_time || 1} min read</span>
                    </div>
                </div>
            </article>
        `;
    }

    private renderPagination(): string {
        if (!this.pagination || this.pagination.last_page <= 1) return '';

        const { current_page, last_page } = this.pagination;
        const pages: (number | string)[] = [];

        // Build page numbers array
        for (let i = 1; i <= last_page; i++) {
            if (i === 1 || i === last_page || (i >= current_page - 2 && i <= current_page + 2)) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        return `
            <div class="blog-pagination flex justify-center items-center gap-2 mt-8">
                <button class="page-btn px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                    ${current_page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-gray-700'}"
                    data-page="${current_page - 1}" ${current_page === 1 ? 'disabled' : ''}>
                    Previous
                </button>
                ${pages.map(page => {
                    if (page === '...') {
                        return '<span class="px-3 py-2">...</span>';
                    }
                    return `
                        <button class="page-btn px-3 py-2 rounded-lg
                            ${page === current_page
                                ? 'bg-primary-600 text-white'
                                : 'border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700'}"
                            data-page="${page}">
                            ${page}
                        </button>
                    `;
                }).join('')}
                <button class="page-btn px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                    ${current_page === last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-gray-700'}"
                    data-page="${current_page + 1}" ${current_page === last_page ? 'disabled' : ''}>
                    Next
                </button>
            </div>
        `;
    }

    private bindEventListeners(): void {
        // Category filter
        document.querySelectorAll('.category-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const category = target.dataset.category || '';
                this.loadBlog({ category: category || undefined, page: 1 });
            });
        });

        // Pagination
        document.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const page = parseInt(target.dataset.page || '1', 10);
                if (page > 0 && page <= (this.pagination?.last_page || 1)) {
                    this.loadBlog({ page });
                }
            });
        });
    }

    async loadArticle(slug: string): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById(`blog-article-${slug}`);
        if (!container) return;

        try {
            const response = await this.apiClient.get(`/blog/${slug}`);
            const article = response.data.article;
            const related = response.data.related || [];

            container.innerHTML = this.renderArticleDetail(article, related);
            this.updateMetaTags(article);
        } catch (error: any) {
            if (error.response?.status === 404) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Article Not Found</h2>
                        <p class="text-gray-500 mb-6">The article you're looking for doesn't exist or has been removed.</p>
                        <a href="#/blog" class="tixello-btn">Back to Blog</a>
                    </div>
                `;
            } else {
                container.innerHTML = '<p class="text-red-500">Failed to load article. Please try again.</p>';
                console.error('Failed to load article:', error);
            }
        }
    }

    private renderArticleDetail(article: BlogArticle, related: BlogArticle[]): string {
        const publishedDate = article.published_at
            ? new Date(article.published_at).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
            : '';

        return `
            <article class="blog-article max-w-4xl mx-auto">
                <header class="mb-8">
                    ${article.category
                        ? `<a href="#/blog?category=${article.category_slug}" class="inline-block px-3 py-1 text-sm font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full mb-4 hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-colors">${article.category}</a>`
                        : ''
                    }
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">${article.title}</h1>
                    ${article.subtitle
                        ? `<p class="text-xl text-gray-600 dark:text-gray-400 mb-6">${article.subtitle}</p>`
                        : ''
                    }
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        ${article.author ? `<span>By ${article.author}</span>` : ''}
                        <span>${publishedDate}</span>
                        <span>${article.reading_time || 1} min read</span>
                        <span>${article.view_count} views</span>
                    </div>
                </header>

                ${article.featured_image
                    ? `<figure class="mb-8">
                        <img src="${article.featured_image}"
                             alt="${article.featured_image_alt || article.title}"
                             class="w-full rounded-xl">
                       </figure>`
                    : ''
                }

                <div class="blog-content prose prose-lg dark:prose-invert max-w-none">
                    ${article.content_html || article.content || ''}
                </div>

                ${article.tags && article.tags.length > 0
                    ? `<div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap gap-2">
                            ${article.tags.map(tag => `
                                <span class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full">#${tag}</span>
                            `).join('')}
                        </div>
                       </div>`
                    : ''
                }

                ${related.length > 0 ? this.renderRelatedArticles(related) : ''}

                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <a href="#/blog" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Blog
                    </a>
                </div>
            </article>
        `;
    }

    private renderRelatedArticles(articles: BlogArticle[]): string {
        return `
            <section class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Related Articles</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    ${articles.map(article => `
                        <a href="#/blog/${article.slug}" class="block group">
                            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                                ${article.featured_image
                                    ? `<img src="${article.featured_image}" alt="${article.title}" class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300">`
                                    : `<div class="w-full h-32 bg-gradient-to-br from-primary-500 to-primary-700"></div>`
                                }
                            </div>
                            <h3 class="mt-3 font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">${article.title}</h3>
                        </a>
                    `).join('')}
                </div>
            </section>
        `;
    }

    private updateMetaTags(article: BlogArticle): void {
        // Update page title
        document.title = article.meta_title || article.title;

        // Update meta description
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
            metaDesc.setAttribute('content', article.meta_description || article.excerpt || '');
        }

        // Update OG tags
        const ogTitle = document.querySelector('meta[property="og:title"]');
        if (ogTitle) {
            ogTitle.setAttribute('content', article.og_title || article.title);
        }

        const ogDesc = document.querySelector('meta[property="og:description"]');
        if (ogDesc) {
            ogDesc.setAttribute('content', article.og_description || article.excerpt || '');
        }

        const ogImage = document.querySelector('meta[property="og:image"]');
        if (ogImage && article.og_image) {
            ogImage.setAttribute('content', article.og_image);
        }
    }
}
