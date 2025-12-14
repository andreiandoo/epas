import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

interface ShopProduct {
    id: string;
    slug: string;
    sku: string;
    title: string;
    short_description: string;
    description?: string;
    type: 'physical' | 'digital';
    price: number;
    price_cents: number;
    sale_price: number | null;
    sale_price_cents: number | null;
    display_price: number;
    currency: string;
    is_on_sale: boolean;
    discount_percentage: number;
    image_url: string | null;
    gallery?: string[];
    category: string | null;
    category_slug: string | null;
    is_in_stock: boolean;
    stock_quantity: number | null;
    is_featured: boolean;
    average_rating: number;
    review_count: number;
    variants?: ProductVariant[];
    attributes?: ProductAttribute[];
    reviews?: ProductReview[];
}

interface ProductVariant {
    id: string;
    sku: string;
    name: string;
    price_cents: number;
    sale_price_cents: number | null;
    stock_quantity: number | null;
    is_in_stock: boolean;
    image_url: string | null;
    attributes: VariantAttribute[];
}

interface VariantAttribute {
    attribute: string;
    attribute_slug: string;
    value: string;
    value_slug: string;
    color_code: string | null;
}

interface ProductAttribute {
    id: string;
    slug: string;
    name: string;
    type: string;
    values: AttributeValue[];
}

interface AttributeValue {
    id: string;
    slug: string;
    value: string;
    color_code: string | null;
}

interface ProductReview {
    id: string;
    rating: number;
    title: string;
    content: string;
    reviewer_name: string;
    verified_purchase: boolean;
    created_at: string;
    admin_response: string | null;
}

interface ShopCategory {
    id: string;
    slug: string;
    name: string;
    description: string;
    image_url: string | null;
    parent_id: string | null;
    products_count: number;
}

interface ShopFilters {
    category?: string;
    type?: 'physical' | 'digital';
    sort?: string;
    search?: string;
    min_price?: number;
    max_price?: number;
    page?: number;
}

export class ShopModule {
    name = 'shop';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private products: ShopProduct[] = [];
    private categories: ShopCategory[] = [];
    private filters: ShopFilters = {};
    private pagination = { total: 0, per_page: 12, current_page: 1, last_page: 1 };
    private cartSessionId: string;

    constructor() {
        this.cartSessionId = this.getOrCreateSessionId();
    }

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;

        // Listen for route changes
        this.eventBus.on('route:shop', () => this.loadShopPage());
        this.eventBus.on('route:shop-category', (slug: string) => this.loadCategoryPage(slug));
        this.eventBus.on('route:shop-product', (slug: string) => this.loadProductPage(slug));
        this.eventBus.on('route:shop-cart', () => this.loadCartPage());
        this.eventBus.on('route:shop-checkout', () => this.loadCheckoutPage());

        console.log('Shop module initialized');
    }

    private getOrCreateSessionId(): string {
        let sessionId = localStorage.getItem('shop_session_id');
        if (!sessionId) {
            sessionId = 'shop-' + Math.random().toString(36).substr(2, 16);
            localStorage.setItem('shop_session_id', sessionId);
        }
        return sessionId;
    }

    private formatCurrency(amount: number, currency: string = 'RON'): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    // ========================================
    // SHOP LISTING PAGE
    // ========================================
    async loadShopPage(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('shop-products');
        if (!container) return;

        // Parse filters from URL
        this.parseFiltersFromUrl();

        try {
            // Load categories and products in parallel
            const [categoriesRes, productsRes] = await Promise.all([
                this.apiClient.get('/shop/categories'),
                this.loadProducts()
            ]);

            this.categories = categoriesRes.data.data?.categories || [];

            container.innerHTML = this.renderShopPage();
            this.bindShopFilters();
            this.bindProductCards();
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Nu s-au putut incarca produsele. Incearca din nou.</p>';
            console.error('Failed to load shop:', error);
        }
    }

    private parseFiltersFromUrl(): void {
        const hash = window.location.hash;
        const searchParams = new URLSearchParams(hash.split('?')[1] || '');

        this.filters = {
            category: searchParams.get('category') || undefined,
            type: (searchParams.get('type') as 'physical' | 'digital') || undefined,
            sort: searchParams.get('sort') || 'newest',
            search: searchParams.get('search') || undefined,
            min_price: searchParams.get('min_price') ? parseInt(searchParams.get('min_price')!) : undefined,
            max_price: searchParams.get('max_price') ? parseInt(searchParams.get('max_price')!) : undefined,
            page: parseInt(searchParams.get('page') || '1'),
        };
    }

    private async loadProducts(): Promise<void> {
        if (!this.apiClient) return;

        const params: Record<string, string> = {};
        if (this.filters.category) params.category = this.filters.category;
        if (this.filters.type) params.type = this.filters.type;
        if (this.filters.sort) params.sort = this.filters.sort;
        if (this.filters.search) params.search = this.filters.search;
        if (this.filters.min_price) params.min_price = String(this.filters.min_price);
        if (this.filters.max_price) params.max_price = String(this.filters.max_price);
        if (this.filters.page) params.page = String(this.filters.page);

        const queryString = new URLSearchParams(params).toString();
        const response = await this.apiClient.get(`/shop/products${queryString ? `?${queryString}` : ''}`);

        this.products = response.data.data?.products || [];
        this.pagination = response.data.data?.pagination || { total: 0, per_page: 12, current_page: 1, last_page: 1 };
    }

    private renderShopPage(): string {
        return `
            <style>
                .shop-page {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 1.5rem 1rem;
                }
                @media (min-width: 768px) {
                    .shop-page {
                        padding: 2rem;
                    }
                }
                .shop-header {
                    margin-bottom: 2rem;
                }
                .shop-title {
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--sleek-text, #1f2937);
                    margin: 0 0 0.5rem 0;
                }
                .shop-subtitle {
                    color: var(--sleek-text-muted, #6b7280);
                    font-size: 0.95rem;
                }
                .shop-layout {
                    display: grid;
                    gap: 2rem;
                }
                @media (min-width: 1024px) {
                    .shop-layout {
                        grid-template-columns: 280px 1fr;
                    }
                }
                .shop-sidebar {
                    background: var(--sleek-surface, #ffffff);
                    border: 1px solid var(--sleek-border, #e5e7eb);
                    border-radius: 0.75rem;
                    padding: 1.5rem;
                    height: fit-content;
                    position: sticky;
                    top: 1rem;
                }
                @media (max-width: 1023px) {
                    .shop-sidebar {
                        display: none;
                    }
                    .shop-sidebar.mobile-open {
                        display: block;
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        z-index: 50;
                        overflow-y: auto;
                        border-radius: 0;
                    }
                }
                .filter-section {
                    margin-bottom: 1.5rem;
                    padding-bottom: 1.5rem;
                    border-bottom: 1px solid var(--sleek-border, #e5e7eb);
                }
                .filter-section:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                    padding-bottom: 0;
                }
                .filter-title {
                    font-weight: 600;
                    font-size: 0.9rem;
                    color: var(--sleek-text, #1f2937);
                    margin-bottom: 0.75rem;
                }
                .filter-options {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }
                .filter-option {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.5rem;
                    border-radius: 0.375rem;
                    cursor: pointer;
                    transition: background 0.2s;
                    color: var(--sleek-text-muted, #6b7280);
                    font-size: 0.9rem;
                }
                .filter-option:hover {
                    background: rgba(0,0,0,0.03);
                }
                .filter-option.active {
                    background: var(--sleek-glow, rgba(99, 102, 241, 0.1));
                    color: var(--sleek-gradient-start, #6366f1);
                    font-weight: 500;
                }
                .filter-option-count {
                    margin-left: auto;
                    font-size: 0.75rem;
                    color: var(--sleek-text-subtle, #9ca3af);
                }
                .price-range-inputs {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 0.5rem;
                }
                .price-input {
                    padding: 0.5rem;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.375rem;
                    font-size: 0.875rem;
                    width: 100%;
                }
                .price-input:focus {
                    outline: none;
                    border-color: var(--sleek-gradient-start, #6366f1);
                }
                .shop-toolbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.5rem;
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                .shop-results-count {
                    color: var(--sleek-text-muted, #6b7280);
                    font-size: 0.9rem;
                }
                .shop-sort-select {
                    padding: 0.5rem 1rem;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    background: white;
                    font-size: 0.875rem;
                    cursor: pointer;
                }
                .shop-search-box {
                    display: flex;
                    gap: 0.5rem;
                    flex: 1;
                    max-width: 300px;
                }
                .shop-search-input {
                    flex: 1;
                    padding: 0.5rem 1rem;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    font-size: 0.875rem;
                }
                .shop-search-input:focus {
                    outline: none;
                    border-color: var(--sleek-gradient-start, #6366f1);
                }
                .mobile-filter-btn {
                    display: none;
                    padding: 0.5rem 1rem;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    background: white;
                    cursor: pointer;
                    font-size: 0.875rem;
                }
                @media (max-width: 1023px) {
                    .mobile-filter-btn {
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                }
                .products-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                    gap: 1.5rem;
                }
                @media (min-width: 640px) {
                    .products-grid {
                        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                    }
                }
                .product-card {
                    background: var(--sleek-surface, #ffffff);
                    border: 1px solid var(--sleek-border, #e5e7eb);
                    border-radius: 0.75rem;
                    overflow: hidden;
                    transition: all 0.2s;
                    cursor: pointer;
                }
                .product-card:hover {
                    border-color: var(--sleek-border-light, #d1d5db);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                    transform: translateY(-2px);
                }
                .product-card-image {
                    position: relative;
                    aspect-ratio: 1;
                    background: #f3f4f6;
                    overflow: hidden;
                }
                .product-card-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    transition: transform 0.3s;
                }
                .product-card:hover .product-card-image img {
                    transform: scale(1.05);
                }
                .product-badge {
                    position: absolute;
                    top: 0.75rem;
                    left: 0.75rem;
                    padding: 0.25rem 0.5rem;
                    border-radius: 0.25rem;
                    font-size: 0.7rem;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .product-badge.sale {
                    background: #ef4444;
                    color: white;
                }
                .product-badge.out-of-stock {
                    background: #6b7280;
                    color: white;
                }
                .product-badge.featured {
                    background: #f59e0b;
                    color: white;
                }
                .product-wishlist-btn {
                    position: absolute;
                    top: 0.75rem;
                    right: 0.75rem;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    background: white;
                    border: none;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    transition: all 0.2s;
                }
                .product-wishlist-btn:hover {
                    transform: scale(1.1);
                }
                .product-wishlist-btn.active svg {
                    fill: #ef4444;
                    color: #ef4444;
                }
                .product-card-body {
                    padding: 1rem;
                }
                .product-card-category {
                    font-size: 0.75rem;
                    color: var(--sleek-text-subtle, #9ca3af);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 0.25rem;
                }
                .product-card-title {
                    font-weight: 600;
                    font-size: 0.95rem;
                    color: var(--sleek-text, #1f2937);
                    margin-bottom: 0.5rem;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .product-card-rating {
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                    margin-bottom: 0.5rem;
                    font-size: 0.8rem;
                    color: var(--sleek-text-muted, #6b7280);
                }
                .product-card-rating svg {
                    width: 14px;
                    height: 14px;
                    color: #f59e0b;
                    fill: #f59e0b;
                }
                .product-card-price {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .product-price-current {
                    font-weight: 700;
                    font-size: 1.1rem;
                    color: var(--sleek-text, #1f2937);
                }
                .product-price-original {
                    font-size: 0.9rem;
                    color: var(--sleek-text-muted, #6b7280);
                    text-decoration: line-through;
                }
                .product-discount {
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: #ef4444;
                }
                .pagination {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 0.5rem;
                    margin-top: 2rem;
                }
                .pagination-btn {
                    padding: 0.5rem 1rem;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.375rem;
                    background: white;
                    cursor: pointer;
                    font-size: 0.875rem;
                    transition: all 0.2s;
                }
                .pagination-btn:hover:not(:disabled) {
                    border-color: var(--sleek-gradient-start, #6366f1);
                    color: var(--sleek-gradient-start, #6366f1);
                }
                .pagination-btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .pagination-btn.active {
                    background: var(--sleek-gradient-start, #6366f1);
                    color: white;
                    border-color: var(--sleek-gradient-start, #6366f1);
                }
                .empty-state {
                    text-align: center;
                    padding: 4rem 2rem;
                }
                .empty-state-icon {
                    width: 80px;
                    height: 80px;
                    background: var(--sleek-surface, #f3f4f6);
                    border: 1px solid var(--sleek-border, #e5e7eb);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1.5rem;
                }
                .empty-state-icon svg {
                    width: 36px;
                    height: 36px;
                    color: var(--sleek-text-subtle, #9ca3af);
                }
                .empty-state-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: var(--sleek-text, #1f2937);
                    margin-bottom: 0.5rem;
                }
                .empty-state-desc {
                    color: var(--sleek-text-muted, #6b7280);
                }
            </style>

            <div class="shop-page">
                <div class="shop-header">
                    <h1 class="shop-title">Magazin</h1>
                    <p class="shop-subtitle">Descopera produsele noastre</p>
                </div>

                <div class="shop-layout">
                    <!-- Sidebar Filters -->
                    <aside class="shop-sidebar" id="shop-sidebar">
                        <div class="filter-section">
                            <h3 class="filter-title">Categorii</h3>
                            <div class="filter-options">
                                <div class="filter-option ${!this.filters.category ? 'active' : ''}" data-category="">
                                    Toate produsele
                                </div>
                                ${this.categories.map(cat => `
                                    <div class="filter-option ${this.filters.category === cat.slug ? 'active' : ''}" data-category="${cat.slug}">
                                        ${cat.name}
                                        <span class="filter-option-count">${cat.products_count}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="filter-section">
                            <h3 class="filter-title">Tip produs</h3>
                            <div class="filter-options">
                                <div class="filter-option ${!this.filters.type ? 'active' : ''}" data-type="">
                                    Toate
                                </div>
                                <div class="filter-option ${this.filters.type === 'physical' ? 'active' : ''}" data-type="physical">
                                    Produse fizice
                                </div>
                                <div class="filter-option ${this.filters.type === 'digital' ? 'active' : ''}" data-type="digital">
                                    Produse digitale
                                </div>
                            </div>
                        </div>

                        <div class="filter-section">
                            <h3 class="filter-title">Pret</h3>
                            <div class="price-range-inputs">
                                <input type="number" class="price-input" id="min-price" placeholder="Min" value="${this.filters.min_price || ''}">
                                <input type="number" class="price-input" id="max-price" placeholder="Max" value="${this.filters.max_price || ''}">
                            </div>
                            <button id="apply-price-filter" style="margin-top: 0.75rem; padding: 0.5rem 1rem; background: var(--sleek-gradient-start, #6366f1); color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; width: 100%;">
                                Aplica
                            </button>
                        </div>

                        <button id="clear-all-filters" style="width: 100%; padding: 0.75rem; border: 1px solid var(--sleek-border, #d1d5db); border-radius: 0.375rem; background: white; cursor: pointer; font-size: 0.875rem;">
                            Sterge filtrele
                        </button>
                    </aside>

                    <!-- Products Content -->
                    <div class="shop-content">
                        <div class="shop-toolbar">
                            <button class="mobile-filter-btn" id="mobile-filter-btn">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                </svg>
                                Filtre
                            </button>

                            <div class="shop-search-box">
                                <input type="text" class="shop-search-input" id="shop-search" placeholder="Cauta produse..." value="${this.filters.search || ''}">
                            </div>

                            <span class="shop-results-count">${this.pagination.total} produse</span>

                            <select class="shop-sort-select" id="shop-sort">
                                <option value="newest" ${this.filters.sort === 'newest' ? 'selected' : ''}>Cele mai noi</option>
                                <option value="price_asc" ${this.filters.sort === 'price_asc' ? 'selected' : ''}>Pret crescator</option>
                                <option value="price_desc" ${this.filters.sort === 'price_desc' ? 'selected' : ''}>Pret descrescator</option>
                                <option value="name_asc" ${this.filters.sort === 'name_asc' ? 'selected' : ''}>A - Z</option>
                                <option value="name_desc" ${this.filters.sort === 'name_desc' ? 'selected' : ''}>Z - A</option>
                                <option value="rating" ${this.filters.sort === 'rating' ? 'selected' : ''}>Rating</option>
                            </select>
                        </div>

                        ${this.products.length === 0 ? this.renderEmptyState() : `
                            <div class="products-grid">
                                ${this.products.map(product => this.renderProductCard(product)).join('')}
                            </div>
                            ${this.renderPagination()}
                        `}
                    </div>
                </div>
            </div>
        `;
    }

    private renderProductCard(product: ShopProduct): string {
        const displayPrice = product.is_on_sale && product.sale_price_cents
            ? product.sale_price_cents / 100
            : product.price_cents / 100;

        return `
            <div class="product-card" data-product-slug="${product.slug}">
                <div class="product-card-image">
                    ${product.image_url
                        ? `<img src="${product.image_url}" alt="${product.title}" loading="lazy">`
                        : `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f3f4f6;">
                            <svg width="48" height="48" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                           </div>`
                    }
                    ${product.is_on_sale ? '<span class="product-badge sale">Reducere</span>' : ''}
                    ${!product.is_in_stock ? '<span class="product-badge out-of-stock">Stoc epuizat</span>' : ''}
                    ${product.is_featured && product.is_in_stock && !product.is_on_sale ? '<span class="product-badge featured">Recomandat</span>' : ''}
                    <button class="product-wishlist-btn" data-product-id="${product.id}" onclick="event.stopPropagation()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                </div>
                <div class="product-card-body">
                    ${product.category ? `<span class="product-card-category">${product.category}</span>` : ''}
                    <h3 class="product-card-title">${product.title}</h3>
                    ${product.average_rating > 0 ? `
                        <div class="product-card-rating">
                            <svg viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            ${product.average_rating.toFixed(1)} (${product.review_count})
                        </div>
                    ` : ''}
                    <div class="product-card-price">
                        <span class="product-price-current">${this.formatCurrency(displayPrice, product.currency)}</span>
                        ${product.is_on_sale && product.sale_price_cents ? `
                            <span class="product-price-original">${this.formatCurrency(product.price_cents / 100, product.currency)}</span>
                            <span class="product-discount">-${product.discount_percentage}%</span>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    private renderEmptyState(): string {
        return `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h2 class="empty-state-title">Niciun produs gasit</h2>
                <p class="empty-state-desc">Incearca sa modifici filtrele sau cauta altceva</p>
            </div>
        `;
    }

    private renderPagination(): string {
        if (this.pagination.last_page <= 1) return '';

        let pages: (number | string)[] = [];
        const current = this.pagination.current_page;
        const last = this.pagination.last_page;

        // Always show first page
        pages.push(1);

        // Show ellipsis if needed
        if (current > 3) pages.push('...');

        // Show pages around current
        for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
            pages.push(i);
        }

        // Show ellipsis if needed
        if (current < last - 2) pages.push('...');

        // Always show last page if more than 1 page
        if (last > 1) pages.push(last);

        return `
            <div class="pagination">
                <button class="pagination-btn" data-page="${current - 1}" ${current === 1 ? 'disabled' : ''}>
                    &larr; Inapoi
                </button>
                ${pages.map(page => typeof page === 'number'
                    ? `<button class="pagination-btn ${page === current ? 'active' : ''}" data-page="${page}">${page}</button>`
                    : `<span style="padding: 0.5rem;">...</span>`
                ).join('')}
                <button class="pagination-btn" data-page="${current + 1}" ${current === last ? 'disabled' : ''}>
                    Inainte &rarr;
                </button>
            </div>
        `;
    }

    private bindShopFilters(): void {
        // Category filters
        document.querySelectorAll('[data-category]').forEach(el => {
            el.addEventListener('click', () => {
                const category = el.getAttribute('data-category');
                this.filters.category = category || undefined;
                this.filters.page = 1;
                this.updateUrlAndReload();
            });
        });

        // Type filters
        document.querySelectorAll('[data-type]').forEach(el => {
            el.addEventListener('click', () => {
                const type = el.getAttribute('data-type') as 'physical' | 'digital' | '';
                this.filters.type = type || undefined;
                this.filters.page = 1;
                this.updateUrlAndReload();
            });
        });

        // Sort
        document.getElementById('shop-sort')?.addEventListener('change', (e) => {
            this.filters.sort = (e.target as HTMLSelectElement).value;
            this.filters.page = 1;
            this.updateUrlAndReload();
        });

        // Search
        let searchTimeout: any;
        document.getElementById('shop-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.filters.search = (e.target as HTMLInputElement).value || undefined;
                this.filters.page = 1;
                this.updateUrlAndReload();
            }, 500);
        });

        // Price filter
        document.getElementById('apply-price-filter')?.addEventListener('click', () => {
            const minPrice = (document.getElementById('min-price') as HTMLInputElement).value;
            const maxPrice = (document.getElementById('max-price') as HTMLInputElement).value;
            this.filters.min_price = minPrice ? parseInt(minPrice) : undefined;
            this.filters.max_price = maxPrice ? parseInt(maxPrice) : undefined;
            this.filters.page = 1;
            this.updateUrlAndReload();
        });

        // Clear filters
        document.getElementById('clear-all-filters')?.addEventListener('click', () => {
            this.filters = { sort: 'newest', page: 1 };
            this.updateUrlAndReload();
        });

        // Pagination
        document.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.getAttribute('data-page') || '1');
                if (page >= 1 && page <= this.pagination.last_page) {
                    this.filters.page = page;
                    this.updateUrlAndReload();
                }
            });
        });

        // Mobile filter toggle
        document.getElementById('mobile-filter-btn')?.addEventListener('click', () => {
            document.getElementById('shop-sidebar')?.classList.toggle('mobile-open');
        });
    }

    private bindProductCards(): void {
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', () => {
                const slug = card.getAttribute('data-product-slug');
                if (slug) {
                    window.location.hash = `/shop/product/${slug}`;
                }
            });
        });

        // Wishlist buttons
        document.querySelectorAll('.product-wishlist-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const productId = btn.getAttribute('data-product-id');
                if (productId && this.apiClient) {
                    try {
                        await this.apiClient.post('/shop/wishlist/items', { product_id: productId });
                        btn.classList.toggle('active');
                    } catch (error) {
                        console.error('Failed to toggle wishlist:', error);
                    }
                }
            });
        });
    }

    private updateUrlAndReload(): void {
        const params = new URLSearchParams();
        if (this.filters.category) params.set('category', this.filters.category);
        if (this.filters.type) params.set('type', this.filters.type);
        if (this.filters.sort && this.filters.sort !== 'newest') params.set('sort', this.filters.sort);
        if (this.filters.search) params.set('search', this.filters.search);
        if (this.filters.min_price) params.set('min_price', String(this.filters.min_price));
        if (this.filters.max_price) params.set('max_price', String(this.filters.max_price));
        if (this.filters.page && this.filters.page > 1) params.set('page', String(this.filters.page));

        const queryString = params.toString();
        window.location.hash = `/shop${queryString ? `?${queryString}` : ''}`;
    }

    // ========================================
    // CATEGORY PAGE
    // ========================================
    async loadCategoryPage(slug: string): Promise<void> {
        this.filters.category = slug;
        this.filters.page = 1;
        await this.loadShopPage();
    }

    // ========================================
    // SINGLE PRODUCT PAGE
    // ========================================
    async loadProductPage(slug: string): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById(`shop-product-${slug}`);
        if (!container) return;

        try {
            const response = await this.apiClient.get(`/shop/products/${slug}`);
            const { product, related } = response.data.data;

            container.innerHTML = this.renderProductPage(product, related);
            this.bindProductPageEvents(product);
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Nu s-a putut incarca produsul.</p>';
            console.error('Failed to load product:', error);
        }
    }

    private renderProductPage(product: ShopProduct, related: ShopProduct[]): string {
        const displayPrice = product.is_on_sale && product.sale_price_cents
            ? product.sale_price_cents / 100
            : product.price_cents / 100;

        return `
            <style>
                .product-page {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 1.5rem 1rem;
                }
                @media (min-width: 768px) {
                    .product-page {
                        padding: 2rem;
                    }
                }
                .product-layout {
                    display: grid;
                    gap: 2rem;
                }
                @media (min-width: 768px) {
                    .product-layout {
                        grid-template-columns: 1fr 1fr;
                        gap: 3rem;
                    }
                }
                .product-gallery {
                    position: relative;
                }
                .product-main-image {
                    aspect-ratio: 1;
                    border-radius: 0.75rem;
                    overflow: hidden;
                    background: #f3f4f6;
                }
                .product-main-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .product-thumbnails {
                    display: flex;
                    gap: 0.75rem;
                    margin-top: 1rem;
                    overflow-x: auto;
                }
                .product-thumbnail {
                    width: 80px;
                    height: 80px;
                    border-radius: 0.5rem;
                    overflow: hidden;
                    cursor: pointer;
                    border: 2px solid transparent;
                    flex-shrink: 0;
                }
                .product-thumbnail.active {
                    border-color: var(--sleek-gradient-start, #6366f1);
                }
                .product-thumbnail img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .product-info {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }
                .product-breadcrumb {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.875rem;
                    color: var(--sleek-text-muted, #6b7280);
                }
                .product-breadcrumb a {
                    color: var(--sleek-text-muted, #6b7280);
                    text-decoration: none;
                }
                .product-breadcrumb a:hover {
                    color: var(--sleek-gradient-start, #6366f1);
                }
                .product-title {
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: var(--sleek-text, #1f2937);
                    margin: 0;
                }
                .product-rating-row {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }
                .product-stars {
                    display: flex;
                    gap: 0.125rem;
                }
                .product-stars svg {
                    width: 18px;
                    height: 18px;
                    color: #f59e0b;
                    fill: #f59e0b;
                }
                .product-stars svg.empty {
                    fill: none;
                    color: #d1d5db;
                }
                .product-review-count {
                    color: var(--sleek-text-muted, #6b7280);
                    font-size: 0.9rem;
                }
                .product-price-section {
                    display: flex;
                    align-items: baseline;
                    gap: 1rem;
                }
                .product-current-price {
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--sleek-text, #1f2937);
                }
                .product-original-price {
                    font-size: 1.25rem;
                    color: var(--sleek-text-muted, #6b7280);
                    text-decoration: line-through;
                }
                .product-discount-badge {
                    padding: 0.25rem 0.75rem;
                    background: #fef2f2;
                    color: #ef4444;
                    font-size: 0.875rem;
                    font-weight: 600;
                    border-radius: 0.375rem;
                }
                .product-description {
                    color: var(--sleek-text-muted, #6b7280);
                    line-height: 1.6;
                }
                .product-variants {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                .variant-group {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }
                .variant-label {
                    font-weight: 600;
                    font-size: 0.9rem;
                    color: var(--sleek-text, #1f2937);
                }
                .variant-options {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                }
                .variant-option {
                    padding: 0.5rem 1rem;
                    border: 2px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    cursor: pointer;
                    font-size: 0.875rem;
                    transition: all 0.2s;
                    background: white;
                }
                .variant-option:hover {
                    border-color: var(--sleek-gradient-start, #6366f1);
                }
                .variant-option.active {
                    border-color: var(--sleek-gradient-start, #6366f1);
                    background: var(--sleek-glow, rgba(99, 102, 241, 0.1));
                    color: var(--sleek-gradient-start, #6366f1);
                }
                .variant-option.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .color-option {
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    padding: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .color-option.active {
                    box-shadow: 0 0 0 3px var(--sleek-gradient-start, #6366f1);
                }
                .quantity-selector {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .quantity-btn {
                    width: 40px;
                    height: 40px;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    background: white;
                    cursor: pointer;
                    font-size: 1.25rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                }
                .quantity-btn:hover {
                    border-color: var(--sleek-gradient-start, #6366f1);
                    color: var(--sleek-gradient-start, #6366f1);
                }
                .quantity-input {
                    width: 60px;
                    height: 40px;
                    text-align: center;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    font-size: 1rem;
                }
                .add-to-cart-section {
                    display: flex;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
                .add-to-cart-btn {
                    flex: 1;
                    min-width: 200px;
                    padding: 1rem 2rem;
                    background: linear-gradient(135deg, var(--sleek-gradient-start, #6366f1), var(--sleek-gradient-end, #4f46e5));
                    color: white;
                    border: none;
                    border-radius: 0.5rem;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    box-shadow: 0 4px 12px var(--sleek-glow, rgba(99, 102, 241, 0.3));
                }
                .add-to-cart-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px var(--sleek-glow, rgba(99, 102, 241, 0.4));
                }
                .add-to-cart-btn:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }
                .wishlist-btn {
                    width: 52px;
                    height: 52px;
                    border: 1px solid var(--sleek-border, #d1d5db);
                    border-radius: 0.5rem;
                    background: white;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                }
                .wishlist-btn:hover {
                    border-color: #ef4444;
                    color: #ef4444;
                }
                .wishlist-btn.active {
                    background: #fef2f2;
                    border-color: #ef4444;
                    color: #ef4444;
                }
                .wishlist-btn.active svg {
                    fill: #ef4444;
                }
                .stock-status {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.9rem;
                }
                .stock-status.in-stock {
                    color: #10b981;
                }
                .stock-status.out-of-stock {
                    color: #ef4444;
                }
                .stock-status.low-stock {
                    color: #f59e0b;
                }
                .product-tabs {
                    margin-top: 3rem;
                    border-top: 1px solid var(--sleek-border, #e5e7eb);
                    padding-top: 2rem;
                }
                .tabs-header {
                    display: flex;
                    gap: 2rem;
                    border-bottom: 1px solid var(--sleek-border, #e5e7eb);
                    margin-bottom: 1.5rem;
                }
                .tab-btn {
                    padding: 0.75rem 0;
                    background: none;
                    border: none;
                    border-bottom: 2px solid transparent;
                    font-size: 0.95rem;
                    font-weight: 500;
                    color: var(--sleek-text-muted, #6b7280);
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .tab-btn:hover {
                    color: var(--sleek-text, #1f2937);
                }
                .tab-btn.active {
                    color: var(--sleek-gradient-start, #6366f1);
                    border-bottom-color: var(--sleek-gradient-start, #6366f1);
                }
                .tab-content {
                    display: none;
                }
                .tab-content.active {
                    display: block;
                }
                .reviews-list {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }
                .review-card {
                    padding: 1.5rem;
                    background: var(--sleek-surface, #f9fafb);
                    border-radius: 0.75rem;
                }
                .review-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 0.75rem;
                }
                .review-author {
                    font-weight: 600;
                    color: var(--sleek-text, #1f2937);
                }
                .review-verified {
                    font-size: 0.75rem;
                    color: #10b981;
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }
                .review-stars {
                    display: flex;
                    gap: 0.125rem;
                }
                .review-stars svg {
                    width: 14px;
                    height: 14px;
                    color: #f59e0b;
                    fill: #f59e0b;
                }
                .review-content {
                    color: var(--sleek-text-muted, #6b7280);
                    line-height: 1.6;
                }
                .review-date {
                    font-size: 0.8rem;
                    color: var(--sleek-text-subtle, #9ca3af);
                    margin-top: 0.75rem;
                }
                .related-products {
                    margin-top: 3rem;
                    border-top: 1px solid var(--sleek-border, #e5e7eb);
                    padding-top: 2rem;
                }
                .related-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: var(--sleek-text, #1f2937);
                    margin-bottom: 1.5rem;
                }
                .related-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 1rem;
                }
            </style>

            <div class="product-page">
                <div class="product-layout">
                    <!-- Gallery -->
                    <div class="product-gallery">
                        <div class="product-main-image" id="main-product-image">
                            ${product.image_url
                                ? `<img src="${product.image_url}" alt="${product.title}">`
                                : `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                    <svg width="64" height="64" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                   </div>`
                            }
                        </div>
                        ${product.gallery && product.gallery.length > 0 ? `
                            <div class="product-thumbnails">
                                <div class="product-thumbnail active" data-image="${product.image_url}">
                                    <img src="${product.image_url}" alt="">
                                </div>
                                ${product.gallery.map(img => `
                                    <div class="product-thumbnail" data-image="${img}">
                                        <img src="${img}" alt="">
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Info -->
                    <div class="product-info">
                        <nav class="product-breadcrumb">
                            <a href="#/shop">Magazin</a>
                            <span>/</span>
                            ${product.category ? `
                                <a href="#/shop?category=${product.category_slug}">${product.category}</a>
                                <span>/</span>
                            ` : ''}
                            <span>${product.title}</span>
                        </nav>

                        <h1 class="product-title">${product.title}</h1>

                        ${product.average_rating > 0 ? `
                            <div class="product-rating-row">
                                <div class="product-stars">
                                    ${this.renderStars(product.average_rating)}
                                </div>
                                <span class="product-review-count">${product.average_rating.toFixed(1)} (${product.review_count} recenzii)</span>
                            </div>
                        ` : ''}

                        <div class="product-price-section">
                            <span class="product-current-price">${this.formatCurrency(displayPrice, product.currency)}</span>
                            ${product.is_on_sale && product.sale_price_cents ? `
                                <span class="product-original-price">${this.formatCurrency(product.price_cents / 100, product.currency)}</span>
                                <span class="product-discount-badge">-${product.discount_percentage}%</span>
                            ` : ''}
                        </div>

                        ${product.short_description ? `
                            <p class="product-description">${product.short_description}</p>
                        ` : ''}

                        ${this.renderStockStatus(product)}

                        ${product.variants && product.variants.length > 0 && product.attributes ? `
                            <div class="product-variants" id="product-variants">
                                ${product.attributes.map(attr => `
                                    <div class="variant-group">
                                        <span class="variant-label">${attr.name}</span>
                                        <div class="variant-options" data-attribute="${attr.slug}">
                                            ${attr.values.map(val => `
                                                <button class="variant-option ${attr.type === 'color' ? 'color-option' : ''}"
                                                    data-value="${val.slug}"
                                                    ${attr.type === 'color' && val.color_code ? `style="background-color: ${val.color_code}"` : ''}>
                                                    ${attr.type !== 'color' ? val.value : ''}
                                                </button>
                                            `).join('')}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}

                        <div class="quantity-selector">
                            <span style="font-weight: 600; font-size: 0.9rem; margin-right: 0.5rem;">Cantitate:</span>
                            <button class="quantity-btn" id="qty-minus">-</button>
                            <input type="number" class="quantity-input" id="product-quantity" value="1" min="1" max="${product.stock_quantity || 99}">
                            <button class="quantity-btn" id="qty-plus">+</button>
                        </div>

                        <div class="add-to-cart-section">
                            <button class="add-to-cart-btn" id="add-to-cart-btn" ${!product.is_in_stock ? 'disabled' : ''}>
                                ${product.is_in_stock ? 'Adauga in cos' : 'Stoc epuizat'}
                            </button>
                            <button class="wishlist-btn" id="product-wishlist-btn" data-product-id="${product.id}">
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                        </div>

                        ${!product.is_in_stock ? `
                            <div style="margin-top: 1rem;">
                                <button class="notify-btn" id="stock-alert-btn" style="padding: 0.75rem 1.5rem; border: 1px solid var(--sleek-gradient-start, #6366f1); border-radius: 0.5rem; background: white; color: var(--sleek-gradient-start, #6366f1); cursor: pointer; font-size: 0.9rem;">
                                    Anunta-ma cand revine in stoc
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Tabs: Description & Reviews -->
                <div class="product-tabs">
                    <div class="tabs-header">
                        <button class="tab-btn active" data-tab="description">Descriere</button>
                        <button class="tab-btn" data-tab="reviews">Recenzii (${product.review_count})</button>
                    </div>

                    <div class="tab-content active" id="tab-description">
                        ${product.description || '<p>Nu exista descriere pentru acest produs.</p>'}
                    </div>

                    <div class="tab-content" id="tab-reviews">
                        ${product.reviews && product.reviews.length > 0 ? `
                            <div class="reviews-list">
                                ${product.reviews.map(review => `
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div>
                                                <div class="review-author">${review.reviewer_name}</div>
                                                ${review.verified_purchase ? `
                                                    <span class="review-verified">
                                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Achizitie verificata
                                                    </span>
                                                ` : ''}
                                            </div>
                                            <div class="review-stars">
                                                ${this.renderStars(review.rating)}
                                            </div>
                                        </div>
                                        ${review.title ? `<h4 style="font-weight: 600; margin-bottom: 0.5rem;">${review.title}</h4>` : ''}
                                        <p class="review-content">${review.content}</p>
                                        <div class="review-date">${new Date(review.created_at).toLocaleDateString('ro-RO')}</div>
                                        ${review.admin_response ? `
                                            <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 0.5rem; border-left: 3px solid var(--sleek-gradient-start, #6366f1);">
                                                <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem;">Raspunsul magazinului:</div>
                                                <p style="color: var(--sleek-text-muted, #6b7280); font-size: 0.9rem;">${review.admin_response}</p>
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p style="color: var(--sleek-text-muted, #6b7280);">Nu exista recenzii pentru acest produs.</p>'}
                    </div>
                </div>

                ${related && related.length > 0 ? `
                    <div class="related-products">
                        <h2 class="related-title">Produse similare</h2>
                        <div class="related-grid">
                            ${related.map(p => this.renderProductCard(p)).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    private renderStars(rating: number): string {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= Math.floor(rating)) {
                stars += '<svg viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            } else {
                stars += '<svg class="empty" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            }
        }
        return stars;
    }

    private renderStockStatus(product: ShopProduct): string {
        if (!product.is_in_stock) {
            return `
                <div class="stock-status out-of-stock">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    Stoc epuizat
                </div>
            `;
        }

        if (product.stock_quantity !== null && product.stock_quantity <= 5) {
            return `
                <div class="stock-status low-stock">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Doar ${product.stock_quantity} in stoc
                </div>
            `;
        }

        return `
            <div class="stock-status in-stock">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                In stoc
            </div>
        `;
    }

    private bindProductPageEvents(product: ShopProduct): void {
        // Thumbnails
        document.querySelectorAll('.product-thumbnail').forEach(thumb => {
            thumb.addEventListener('click', () => {
                document.querySelectorAll('.product-thumbnail').forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
                const imageUrl = thumb.getAttribute('data-image');
                const mainImage = document.querySelector('#main-product-image img') as HTMLImageElement;
                if (mainImage && imageUrl) {
                    mainImage.src = imageUrl;
                }
            });
        });

        // Quantity
        const qtyInput = document.getElementById('product-quantity') as HTMLInputElement;
        document.getElementById('qty-minus')?.addEventListener('click', () => {
            const val = parseInt(qtyInput.value) || 1;
            if (val > 1) qtyInput.value = String(val - 1);
        });
        document.getElementById('qty-plus')?.addEventListener('click', () => {
            const val = parseInt(qtyInput.value) || 1;
            const max = parseInt(qtyInput.max) || 99;
            if (val < max) qtyInput.value = String(val + 1);
        });

        // Variants
        let selectedVariant: ProductVariant | null = null;
        const selectedAttributes: Record<string, string> = {};

        document.querySelectorAll('.variant-options').forEach(group => {
            const attrSlug = group.getAttribute('data-attribute');
            group.querySelectorAll('.variant-option').forEach(option => {
                option.addEventListener('click', () => {
                    const valueSlug = option.getAttribute('data-value');
                    if (attrSlug && valueSlug) {
                        selectedAttributes[attrSlug] = valueSlug;
                        group.querySelectorAll('.variant-option').forEach(o => o.classList.remove('active'));
                        option.classList.add('active');

                        // Find matching variant
                        if (product.variants) {
                            selectedVariant = product.variants.find(v =>
                                v.attributes.every(a => selectedAttributes[a.attribute_slug] === a.value_slug)
                            ) || null;

                            // Update price if variant selected
                            if (selectedVariant) {
                                const priceEl = document.querySelector('.product-current-price');
                                if (priceEl) {
                                    const price = (selectedVariant.sale_price_cents || selectedVariant.price_cents) / 100;
                                    priceEl.textContent = this.formatCurrency(price, product.currency);
                                }

                                // Update image
                                if (selectedVariant.image_url) {
                                    const mainImage = document.querySelector('#main-product-image img') as HTMLImageElement;
                                    if (mainImage) mainImage.src = selectedVariant.image_url;
                                }
                            }
                        }
                    }
                });
            });
        });

        // Add to cart
        document.getElementById('add-to-cart-btn')?.addEventListener('click', async () => {
            if (!this.apiClient) return;

            const quantity = parseInt(qtyInput.value) || 1;
            const variantId = selectedVariant?.id || null;

            try {
                await this.apiClient.post('/shop/cart/items', {
                    product_id: product.id,
                    variant_id: variantId,
                    quantity: quantity
                }, {
                    headers: { 'X-Session-ID': this.cartSessionId }
                });

                // Show success feedback
                const btn = document.getElementById('add-to-cart-btn');
                if (btn) {
                    btn.textContent = 'Adaugat!';
                    btn.style.background = '#10b981';
                    setTimeout(() => {
                        btn.textContent = 'Adauga in cos';
                        btn.style.background = '';
                    }, 2000);
                }

                // Emit event for cart update
                if (this.eventBus) {
                    this.eventBus.emit('cart:updated');
                }
            } catch (error) {
                console.error('Failed to add to cart:', error);
                alert('Nu s-a putut adauga produsul in cos');
            }
        });

        // Wishlist
        document.getElementById('product-wishlist-btn')?.addEventListener('click', async () => {
            if (!this.apiClient) return;

            try {
                await this.apiClient.post('/shop/wishlist/items', { product_id: product.id });
                const btn = document.getElementById('product-wishlist-btn');
                btn?.classList.toggle('active');
            } catch (error) {
                console.error('Failed to toggle wishlist:', error);
            }
        });

        // Stock alert
        document.getElementById('stock-alert-btn')?.addEventListener('click', async () => {
            if (!this.apiClient) return;

            const email = prompt('Introdu adresa de email pentru a fi notificat:');
            if (email) {
                try {
                    await this.apiClient.post('/shop/stock-alerts/subscribe', {
                        product_id: product.id,
                        email: email
                    });
                    alert('Vei fi notificat cand produsul revine in stoc!');
                } catch (error) {
                    console.error('Failed to subscribe:', error);
                    alert('Nu s-a putut salva alerta');
                }
            }
        });

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(`tab-${tabId}`)?.classList.add('active');
            });
        });

        // Related products click
        this.bindProductCards();
    }

    // ========================================
    // CART PAGE (Placeholder - will use SleekClientModule)
    // ========================================
    async loadCartPage(): Promise<void> {
        // This will be handled by SleekClientModule
        console.log('Cart page - handled by SleekClientModule');
    }

    // ========================================
    // CHECKOUT PAGE (Placeholder - will use SleekClientModule)
    // ========================================
    async loadCheckoutPage(): Promise<void> {
        // This will be handled by SleekClientModule
        console.log('Checkout page - handled by SleekClientModule');
    }
}
