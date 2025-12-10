# Editable Predefined Pages Guide

This document explains how to implement predefined pages (About Us, FAQ, Team, etc.) that tenants can customize while maintaining consistent structure.

---

## Overview

Predefined pages are:
1. **System Pages** - Created automatically with default content
2. **Editable** - Tenants can modify content, images, and settings
3. **Structured** - Follow a predefined block layout
4. **Protected** - Cannot be deleted (only customized)

---

## Part 1: Page Types & Structure

### System Page Model

```typescript
// TenantPage model attributes
interface SystemPage {
    tenant_id: number;
    slug: string;           // 'about-us', 'faq', 'team', etc.
    title: TranslatableField;
    page_type: 'builder';   // Always use builder for flexibility
    layout: PageLayout;
    is_system: true;        // Cannot be deleted
    is_published: boolean;
    menu_location: 'header' | 'footer' | 'none';
    menu_order: number;
    meta: Record<string, any>;
    seo_title: string | null;
    seo_description: string | null;
}

interface TranslatableField {
    en: string;
    ro?: string;
    [lang: string]: string | undefined;
}

interface PageLayout {
    blocks: Block[];
}
```

### Common Predefined Pages

| Page | Slug | Purpose |
|------|------|---------|
| About Us | `about-us` | Company information |
| Team | `team` | Team member profiles |
| FAQ | `faq` | Frequently asked questions |
| Contact | `contact` | Contact information & form |
| Privacy Policy | `privacy` | Privacy policy (legal) |
| Terms & Conditions | `terms` | Terms of service (legal) |
| Careers | `careers` | Job listings |
| Press | `press` | Media & press resources |
| Partners | `partners` | Partner showcase |

---

## Part 2: About Us Page

### Default Structure

```typescript
const aboutUsLayout: PageLayout = {
    blocks: [
        // Hero Section
        {
            id: 'about_hero',
            type: 'hero',
            settings: {
                height: 'medium',
                alignment: 'center',
                backgroundType: 'gradient',
            },
            content: {
                en: {
                    title: 'About Us',
                    subtitle: 'Learn more about our story and mission',
                }
            }
        },

        // Story Section
        {
            id: 'about_story',
            type: 'text-image',
            settings: {
                imagePosition: 'right',
                imageUrl: '',  // Tenant uploads
            },
            content: {
                en: {
                    title: 'Our Story',
                    content: `<p>Founded in [YEAR], we started with a simple mission:
                              to bring the best events to our community.</p>
                              <p>Today, we continue to deliver unforgettable experiences...</p>`,
                }
            }
        },

        // Mission & Values
        {
            id: 'about_mission',
            type: 'feature-cards',
            settings: {
                columns: 3,
                style: 'icon-top',
            },
            content: {
                en: {
                    title: 'Our Values',
                    subtitle: 'What drives us every day',
                    features: [
                        {
                            icon: 'star',
                            title: 'Quality',
                            description: 'We never compromise on the quality of our events.',
                        },
                        {
                            icon: 'heart',
                            title: 'Passion',
                            description: 'Our team is passionate about creating memorable experiences.',
                        },
                        {
                            icon: 'users',
                            title: 'Community',
                            description: 'We believe in building strong community connections.',
                        }
                    ]
                }
            }
        },

        // Stats Section
        {
            id: 'about_stats',
            type: 'stats',
            settings: {
                columns: 4,
                style: 'simple',
                backgroundColor: 'primary',
            },
            content: {
                en: {
                    stats: [
                        { value: '500+', label: 'Events Organized' },
                        { value: '50K+', label: 'Happy Attendees' },
                        { value: '100+', label: 'Partner Venues' },
                        { value: '10', label: 'Years Experience' },
                    ]
                }
            }
        },

        // CTA
        {
            id: 'about_cta',
            type: 'cta-banner',
            settings: {
                backgroundColor: 'gradient',
                buttonLink: '/events',
            },
            content: {
                en: {
                    title: 'Ready to Experience Something Amazing?',
                    subtitle: 'Check out our upcoming events',
                    buttonText: 'View Events',
                }
            }
        }
    ]
};
```

### Editable Fields for About Us

| Block | Editable Content | Editable Settings |
|-------|------------------|-------------------|
| Hero | title, subtitle | backgroundImage, height |
| Story | title, content, imageAlt | imageUrl, imagePosition |
| Values | title, subtitle, features[] | columns, style |
| Stats | stats[] | columns, backgroundColor |
| CTA | title, subtitle, buttonText | buttonLink, backgroundColor |

---

## Part 3: Team Page

### Team Member Block

```typescript
{
    id: 'team_grid',
    type: 'team-grid',
    settings: {
        columns: 3,
        layout: 'cards' | 'minimal' | 'detailed',
        showSocial: true,
        showBio: true,
        imageCrop: 'circle' | 'square' | 'rounded',
    },
    content: {
        en: {
            title: 'Meet Our Team',
            subtitle: 'The people behind our success',
            members: [
                {
                    id: 'member_1',
                    name: 'John Doe',
                    role: 'Founder & CEO',
                    bio: 'John founded the company in 2014 with a vision...',
                    photo: 'https://...',
                    social: {
                        linkedin: 'https://linkedin.com/in/johndoe',
                        twitter: 'https://twitter.com/johndoe',
                    }
                },
                {
                    id: 'member_2',
                    name: 'Jane Smith',
                    role: 'Head of Events',
                    bio: 'Jane brings 15 years of event planning experience...',
                    photo: 'https://...',
                    social: {
                        linkedin: 'https://linkedin.com/in/janesmith',
                    }
                }
            ]
        }
    }
}
```

### Team Grid Renderer

```typescript
PageBuilderModule.registerRenderer('team-grid', (block, config) => {
    const settings = block.settings;
    const content = PageBuilderModule.getContent(block, 'title', 'Our Team');
    const members = PageBuilderModule.getContentArray<TeamMember>(block, 'members', []);

    const imageClass = {
        circle: 'rounded-full',
        square: 'rounded-none',
        rounded: 'rounded-xl',
    }[settings.imageCrop] || 'rounded-full';

    const membersHtml = members.map(member => `
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
            ${member.photo ? `
                <div class="aspect-square overflow-hidden">
                    <img src="${member.photo}" alt="${member.name}"
                         class="w-full h-full object-cover ${settings.layout === 'minimal' ? imageClass : ''}">
                </div>
            ` : `
                <div class="aspect-square bg-gray-200 flex items-center justify-center">
                    <span class="text-4xl font-bold text-gray-400">
                        ${member.name.split(' ').map(n => n[0]).join('')}
                    </span>
                </div>
            `}

            <div class="p-6 text-center">
                <h3 class="text-xl font-bold text-gray-900">${member.name}</h3>
                <p class="text-primary font-medium mb-3">${member.role}</p>

                ${settings.showBio && member.bio ? `
                    <p class="text-gray-600 text-sm mb-4">${member.bio}</p>
                ` : ''}

                ${settings.showSocial && member.social ? `
                    <div class="flex justify-center gap-3">
                        ${member.social.linkedin ? `
                            <a href="${member.social.linkedin}" target="_blank"
                               class="text-gray-400 hover:text-primary">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">...</svg>
                            </a>
                        ` : ''}
                        ${member.social.twitter ? `
                            <a href="${member.social.twitter}" target="_blank"
                               class="text-gray-400 hover:text-primary">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">...</svg>
                            </a>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        </div>
    `).join('');

    return `
        <section class="py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">${content}</h2>
                    ${PageBuilderModule.getContent(block, 'subtitle', '')
                        ? `<p class="text-lg text-gray-600">${PageBuilderModule.getContent(block, 'subtitle', '')}</p>`
                        : ''}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${settings.columns || 3} gap-8">
                    ${membersHtml}
                </div>
            </div>
        </section>
    `;
});
```

---

## Part 4: FAQ Page

### FAQ Block Structure

```typescript
{
    id: 'faq_section',
    type: 'faq',
    settings: {
        style: 'accordion' | 'cards' | 'simple',
        allowMultipleOpen: false,
        showCategories: true,
        searchable: true,
    },
    content: {
        en: {
            title: 'Frequently Asked Questions',
            subtitle: 'Find answers to common questions',
            searchPlaceholder: 'Search questions...',
            categories: [
                {
                    id: 'general',
                    name: 'General',
                    questions: [
                        {
                            id: 'q1',
                            question: 'How do I purchase tickets?',
                            answer: 'You can purchase tickets directly through our website...',
                        },
                        {
                            id: 'q2',
                            question: 'Can I get a refund?',
                            answer: 'Refund policies vary by event. Generally...',
                        }
                    ]
                },
                {
                    id: 'events',
                    name: 'Events',
                    questions: [
                        {
                            id: 'q3',
                            question: 'How do I find events near me?',
                            answer: 'Use the search function on our events page...',
                        }
                    ]
                }
            ]
        }
    }
}
```

### FAQ Accordion Renderer

```typescript
PageBuilderModule.registerRenderer('faq', (block, config) => {
    const settings = block.settings;
    const title = PageBuilderModule.getContent(block, 'title', 'FAQ');
    const categories = PageBuilderModule.getContentArray<FAQCategory>(block, 'categories', []);

    const questionsHtml = categories.map(category => `
        ${settings.showCategories ? `
            <h3 class="text-xl font-semibold text-gray-900 mt-8 mb-4">${category.name}</h3>
        ` : ''}

        <div class="space-y-4">
            ${category.questions.map(q => `
                <div class="border border-gray-200 rounded-lg overflow-hidden"
                     x-data="{ open: false }">
                    <button @click="open = !open"
                            class="w-full px-6 py-4 text-left flex justify-between items-center hover:bg-gray-50">
                        <span class="font-medium text-gray-900">${q.question}</span>
                        <svg class="w-5 h-5 text-gray-500 transform transition-transform"
                             :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="px-6 pb-4">
                        <p class="text-gray-600">${q.answer}</p>
                    </div>
                </div>
            `).join('')}
        </div>
    `).join('');

    return `
        <section class="py-16">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">${title}</h2>
                </div>

                ${settings.searchable ? `
                    <div class="mb-8">
                        <input type="text" placeholder="${PageBuilderModule.getContent(block, 'searchPlaceholder', 'Search...')}"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary">
                    </div>
                ` : ''}

                ${questionsHtml}
            </div>
        </section>
    `;
});
```

---

## Part 5: Feature Cards Block

### Reusable for Multiple Pages

```typescript
{
    id: 'features_1',
    type: 'feature-cards',
    settings: {
        columns: 3,
        style: 'icon-top' | 'icon-left' | 'minimal' | 'bordered',
        iconStyle: 'circle' | 'square' | 'none',
        iconColor: 'primary' | 'secondary' | 'gradient',
    },
    content: {
        en: {
            title: 'Why Choose Us',
            subtitle: 'Here\'s what makes us different',
            features: [
                {
                    icon: 'shield',
                    title: 'Secure Payments',
                    description: 'Your transactions are protected with industry-standard encryption.',
                },
                {
                    icon: 'clock',
                    title: 'Instant Delivery',
                    description: 'Receive your tickets immediately after purchase.',
                },
                {
                    icon: 'support',
                    title: '24/7 Support',
                    description: 'Our team is here to help whenever you need us.',
                }
            ]
        }
    }
}
```

### Available Icons

```typescript
const icons: Record<string, string> = {
    star: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
    </svg>`,
    heart: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
    </svg>`,
    users: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>`,
    shield: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>`,
    clock: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>`,
    support: `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>`,
    // ... more icons
};
```

---

## Part 6: Stats Block

### Stats Block Structure

```typescript
{
    id: 'stats_1',
    type: 'stats',
    settings: {
        columns: 4,
        style: 'simple' | 'cards' | 'bordered',
        backgroundColor: 'white' | 'gray' | 'primary' | 'gradient',
        animated: true,  // Count up animation
    },
    content: {
        en: {
            title: '',  // Optional
            stats: [
                { value: '500+', label: 'Events Hosted' },
                { value: '50,000+', label: 'Tickets Sold' },
                { value: '98%', label: 'Satisfaction Rate' },
                { value: '24/7', label: 'Support Available' },
            ]
        }
    }
}
```

### Stats Block Renderer

```typescript
PageBuilderModule.registerRenderer('stats', (block, config) => {
    const settings = block.settings;
    const stats = PageBuilderModule.getContentArray<StatItem>(block, 'stats', []);

    const bgClass = {
        white: 'bg-white',
        gray: 'bg-gray-100',
        primary: 'bg-primary text-white',
        gradient: 'bg-gradient-to-r from-primary to-secondary text-white',
    }[settings.backgroundColor] || 'bg-white';

    const textClass = ['primary', 'gradient'].includes(settings.backgroundColor || '')
        ? 'text-white'
        : 'text-gray-900';

    const statsHtml = stats.map(stat => `
        <div class="text-center p-6">
            <div class="text-4xl md:text-5xl font-bold ${textClass}"
                 ${settings.animated ? `data-count-up="${stat.value}"` : ''}>
                ${stat.value}
            </div>
            <div class="mt-2 text-sm uppercase tracking-wide opacity-80">
                ${stat.label}
            </div>
        </div>
    `).join('');

    return `
        <section class="${bgClass} py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-${settings.columns || 4} gap-8">
                    ${statsHtml}
                </div>
            </div>
        </section>
    `;
});
```

---

## Part 7: Creating Default System Pages

### PHP Model Method

```php
// In TenantPage.php
public static function createDefaultPages(Tenant $tenant): void
{
    // Home Page (see HOMEPAGE_PERSONALIZATION.md)
    self::createHomePage($tenant);

    // About Us
    self::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'about-us'],
        [
            'title' => ['en' => 'About Us', 'ro' => 'Despre Noi'],
            'page_type' => self::TYPE_BUILDER,
            'is_system' => true,
            'is_published' => true,
            'menu_location' => 'footer',
            'menu_order' => 1,
            'layout' => self::getAboutUsDefaultLayout(),
        ]
    );

    // Contact
    self::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'contact'],
        [
            'title' => ['en' => 'Contact', 'ro' => 'Contact'],
            'page_type' => self::TYPE_BUILDER,
            'is_system' => true,
            'is_published' => true,
            'menu_location' => 'footer',
            'menu_order' => 2,
            'layout' => self::getContactDefaultLayout(),
        ]
    );

    // FAQ
    self::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'faq'],
        [
            'title' => ['en' => 'FAQ', 'ro' => 'Întrebări Frecvente'],
            'page_type' => self::TYPE_BUILDER,
            'is_system' => true,
            'is_published' => false, // Disabled by default
            'menu_location' => 'none',
            'layout' => self::getFAQDefaultLayout(),
        ]
    );

    // Terms & Conditions
    self::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'terms'],
        [
            'title' => ['en' => 'Terms & Conditions', 'ro' => 'Termeni și Condiții'],
            'page_type' => self::TYPE_CONTENT,  // Simple HTML content
            'is_system' => true,
            'is_published' => true,
            'menu_location' => 'footer',
            'menu_order' => 10,
            'content' => [
                'en' => '<h2>Terms of Service</h2><p>Last updated: [DATE]</p>...',
                'ro' => '<h2>Termeni și Condiții</h2><p>Ultima actualizare: [DATA]</p>...',
            ],
        ]
    );

    // Privacy Policy
    self::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'privacy'],
        [
            'title' => ['en' => 'Privacy Policy', 'ro' => 'Politica de Confidențialitate'],
            'page_type' => self::TYPE_CONTENT,
            'is_system' => true,
            'is_published' => true,
            'menu_location' => 'footer',
            'menu_order' => 11,
            'content' => [
                'en' => '<h2>Privacy Policy</h2><p>Last updated: [DATE]</p>...',
                'ro' => '<h2>Politica de Confidențialitate</h2><p>Ultima actualizare: [DATA]</p>...',
            ],
        ]
    );
}
```

---

## Part 8: Admin Interface for Editing Pages

### Page Editor Requirements

1. **Block Selection Panel**
   - List of available block types
   - Drag to add to page

2. **Block Settings Panel**
   - Settings form per block type
   - Real-time preview

3. **Content Editor**
   - Multi-language tabs
   - Rich text editor for content fields
   - Image uploader

4. **Page Settings**
   - SEO title/description
   - Menu placement
   - Publish/unpublish toggle

### Block Editing Flow

```
1. Select Block Type → Opens Settings Panel
2. Configure Settings → Updates Preview
3. Edit Content → Per-language tabs
4. Reorder Blocks → Drag & drop
5. Save Page → API call to update
```

---

## Part 9: API Endpoints for Pages

### List System Pages

```
GET /api/tenant-client/pages
Authorization: Bearer {token}
```

Response:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "slug": "about-us",
            "title": "About Us",
            "pageType": "builder",
            "isSystem": true,
            "isPublished": true,
            "menuLocation": "footer",
            "menuOrder": 1
        }
    ]
}
```

### Get Page for Editing

```
GET /api/tenant-client/pages/{slug}?edit=true
Authorization: Bearer {token}
```

Response:
```json
{
    "success": true,
    "data": {
        "slug": "about-us",
        "title": { "en": "About Us", "ro": "Despre Noi" },
        "pageType": "builder",
        "layout": {
            "blocks": [...]
        },
        "isSystem": true,
        "isPublished": true,
        "seo": {
            "title": "About Us | Company Name",
            "description": "Learn more about our company..."
        }
    }
}
```

### Update Page

```
PUT /api/tenant-client/pages/{slug}
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": { "en": "About Us", "ro": "Despre Noi" },
    "layout": {
        "blocks": [...]
    },
    "seo_title": "About Us | Company Name",
    "seo_description": "...",
    "is_published": true,
    "menu_location": "footer",
    "menu_order": 1
}
```

---

## Part 10: Rendering Predefined Pages

### In Router.ts

```typescript
private async renderPage(params: { slug: string }): Promise<void> {
    const content = this.getContentElement();
    if (!content) return;

    try {
        const pageData = await this.fetchApi(`/pages/${params.slug}`);

        if (!pageData.success) {
            this.render404();
            return;
        }

        const page = pageData.data;

        // Set page title
        document.title = page.seo?.title || `${page.title} | ${this.config.site?.title}`;

        if (page.pageType === 'builder' && page.layout?.blocks) {
            // Render using PageBuilder
            content.innerHTML = `<div id="page-content"></div>`;
            PageBuilderModule.updateLayout(page.layout, 'page-content');
        } else {
            // Render simple content page
            content.innerHTML = `
                <div class="${this.template.containerClass} py-12">
                    <h1 class="${this.template.headingClass} mb-8">${page.title}</h1>
                    <div class="prose prose-lg max-w-none">
                        ${page.content || ''}
                    </div>
                </div>
            `;
        }
    } catch (error) {
        this.render404();
    }
}
```

---

## Part 11: Implementation Checklist

### Backend

- [ ] Add system page slugs enum/constants
- [ ] Create `createDefaultPages()` method
- [ ] Add page seeder for new tenants
- [ ] Protect system pages from deletion
- [ ] Add validation for page updates
- [ ] Create block registry with validation
- [ ] Add SEO fields support

### Frontend

- [ ] Register all block renderers
- [ ] Create page editor component
- [ ] Create block settings forms
- [ ] Add drag-and-drop reordering
- [ ] Add multi-language content editor
- [ ] Add real-time preview
- [ ] Add image upload handler
- [ ] Add SEO settings panel

### Block Types to Implement

- [ ] `hero` - Hero/banner section
- [ ] `text-content` - Rich text block
- [ ] `text-image` - Text with image
- [ ] `feature-cards` - Feature grid
- [ ] `stats` - Statistics display
- [ ] `team-grid` - Team members
- [ ] `faq` - FAQ accordion
- [ ] `contact-info` - Contact details
- [ ] `contact-form` - Contact form
- [ ] `map` - Map embed
- [ ] `cta-banner` - Call to action
- [ ] `testimonials` - Reviews/testimonials
- [ ] `partners` - Partner logos
- [ ] `gallery` - Image gallery
- [ ] `video` - Video embed
- [ ] `timeline` - Timeline/history
- [ ] `pricing` - Pricing tables

---

## Part 12: Example Complete About Us Page

```json
{
    "slug": "about-us",
    "title": { "en": "About Us", "ro": "Despre Noi" },
    "page_type": "builder",
    "is_system": true,
    "is_published": true,
    "layout": {
        "blocks": [
            {
                "id": "hero_1",
                "type": "hero",
                "settings": {
                    "height": "medium",
                    "alignment": "center",
                    "backgroundImage": "https://cdn.example.com/about-hero.jpg",
                    "overlayOpacity": 50
                },
                "content": {
                    "en": {
                        "title": "About EventCo",
                        "subtitle": "Creating unforgettable experiences since 2014"
                    },
                    "ro": {
                        "title": "Despre EventCo",
                        "subtitle": "Creăm experiențe de neuitat din 2014"
                    }
                }
            },
            {
                "id": "story_1",
                "type": "text-image",
                "settings": {
                    "imagePosition": "right",
                    "imageUrl": "https://cdn.example.com/team-photo.jpg"
                },
                "content": {
                    "en": {
                        "title": "Our Story",
                        "content": "<p>EventCo was founded in 2014 by a group of passionate event enthusiasts...</p><p>Today, we've grown to become one of the leading event platforms...</p>"
                    }
                }
            },
            {
                "id": "stats_1",
                "type": "stats",
                "settings": {
                    "columns": 4,
                    "backgroundColor": "primary",
                    "animated": true
                },
                "content": {
                    "en": {
                        "stats": [
                            { "value": "500+", "label": "Events Organized" },
                            { "value": "50K+", "label": "Happy Attendees" },
                            { "value": "100+", "label": "Partner Venues" },
                            { "value": "10", "label": "Years Experience" }
                        ]
                    }
                }
            },
            {
                "id": "values_1",
                "type": "feature-cards",
                "settings": {
                    "columns": 3,
                    "style": "icon-top",
                    "iconColor": "primary"
                },
                "content": {
                    "en": {
                        "title": "Our Values",
                        "features": [
                            {
                                "icon": "star",
                                "title": "Excellence",
                                "description": "We strive for excellence in every event we organize."
                            },
                            {
                                "icon": "heart",
                                "title": "Passion",
                                "description": "Our team is passionate about creating memorable experiences."
                            },
                            {
                                "icon": "users",
                                "title": "Community",
                                "description": "We believe in building strong community connections."
                            }
                        ]
                    }
                }
            },
            {
                "id": "cta_1",
                "type": "cta-banner",
                "settings": {
                    "backgroundColor": "gradient",
                    "buttonLink": "/events"
                },
                "content": {
                    "en": {
                        "title": "Ready for Your Next Experience?",
                        "subtitle": "Browse our upcoming events and find something amazing",
                        "buttonText": "View Events"
                    }
                }
            }
        ]
    }
}
```

---

**Document Version:** 1.0
**Last Updated:** 2024-12-10
