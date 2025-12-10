# Homepage Personalization Guide

This document explains how to implement and use homepage personalization features in tenant templates.

---

## Overview

Homepage personalization allows tenants to customize their home page through:
1. **Page Builder Blocks** - Drag-and-drop sections
2. **Theme Configuration** - Colors, fonts, spacing
3. **Content Management** - Text, images, and media

---

## Architecture

```
Homepage Request
       ↓
Router.renderHome()
       ↓
   Check for PageBuilder layout
       ↓
   ┌───────────────────────────────────────┐
   │  If page has 'builder' type layout:   │
   │  → PageBuilderModule.updateLayout()   │
   │  → Render blocks from layout.blocks   │
   └───────────────────────────────────────┘
       ↓
   ┌───────────────────────────────────────┐
   │  If no builder layout (fallback):     │
   │  → Render default home page           │
   │  → Fetch featured events & categories │
   └───────────────────────────────────────┘
```

---

## Part 1: Page Builder Blocks

### Available Block Types for Homepage

| Block Type | Purpose | Personalizable |
|------------|---------|----------------|
| `hero` | Main banner section | Title, subtitle, image, CTA |
| `event-grid` | Event listing | Title, columns, filter |
| `featured-event` | Single event spotlight | Title, event selection |
| `category-nav` | Category navigation | Title, style |
| `text-content` | Rich text section | Title, content |
| `text-image` | Text + image combo | Title, content, image |
| `cta-banner` | Call-to-action | Title, subtitle, button |
| `newsletter` | Email signup | Title, subtitle, button |
| `testimonials` | Customer reviews | Title, testimonials |
| `partners` | Partner logos | Title, logos |
| `countdown` | Event countdown | Title, target date |
| `spacer` | Vertical space | Height, divider |
| `custom-html` | Custom code | HTML content |

### Block Data Structure

```typescript
interface Block {
    id: string;           // Unique identifier
    type: string;         // Block type (e.g., 'hero')
    settings: Record<string, any>;  // Non-translatable settings
    content: {
        en: Record<string, any>;    // English content
        ro: Record<string, any>;    // Romanian content
        // ... other languages
    };
}
```

---

## Part 2: Hero Block Configuration

### Hero Settings

```typescript
{
    id: 'hero_123',
    type: 'hero',
    settings: {
        backgroundType: 'image' | 'gradient' | 'video',
        backgroundImage: 'https://...',  // If backgroundType is 'image'
        overlayOpacity: 50,              // 0-100
        height: 'small' | 'medium' | 'large' | 'full',
        alignment: 'left' | 'center' | 'right',
        buttonLink: '/events',
        showSearch: true,
    },
    content: {
        en: {
            title: 'Welcome to Our Events',
            subtitle: 'Discover amazing experiences',
            buttonText: 'Browse Events',
        },
        ro: {
            title: 'Bine ați venit',
            subtitle: 'Descoperă experiențe unice',
            buttonText: 'Vezi Evenimente',
        }
    }
}
```

### Hero Customization Options

| Setting | Values | Description |
|---------|--------|-------------|
| `backgroundType` | `image`, `gradient`, `video` | Background style |
| `backgroundImage` | URL | Image URL for background |
| `overlayOpacity` | 0-100 | Darkness overlay |
| `height` | `small`, `medium`, `large`, `full` | Section height |
| `alignment` | `left`, `center`, `right` | Content alignment |
| `showSearch` | boolean | Show search bar |

---

## Part 3: Event Grid Configuration

### Event Grid Settings

```typescript
{
    id: 'events_456',
    type: 'event-grid',
    settings: {
        columns: 3,                    // 1, 2, 3, or 4
        source: 'upcoming' | 'featured' | 'all',
        limit: 6,                      // Number of events
        showFilters: true,             // Category filters
        showPagination: false,
    },
    content: {
        en: {
            title: 'Upcoming Events',
            subtitle: 'Don\'t miss out!',
            emptyMessage: 'No events available',
        }
    }
}
```

### Event Sources

| Source | Description |
|--------|-------------|
| `upcoming` | Future events only |
| `featured` | Events marked as featured |
| `all` | All events (past & future) |
| `past` | Past events only |
| `category:{slug}` | Events from specific category |

---

## Part 4: Implementing in Templates

### Default Homepage Structure

When creating a template with homepage support, implement `renderHome` pattern:

```typescript
// In Router.ts or template-specific router
private async renderHome(): Promise<void> {
    const content = this.getContentElement();
    if (!content) return;

    // Check for Page Builder layout
    try {
        const pageData = await this.fetchApi('/pages/home');
        if (pageData.success && pageData.data?.page_type === 'builder') {
            // Use PageBuilder to render
            content.innerHTML = `<div id="page-content"></div>`;
            PageBuilderModule.updateLayout(
                pageData.data.layout as PageLayout,
                'page-content'
            );
            return;
        }
    } catch {
        // Fall back to default
    }

    // Default homepage (when no builder layout)
    content.innerHTML = this.renderDefaultHomepage();
}

private renderDefaultHomepage(): string {
    return `
        <div class="${this.template.containerClass} py-12">
            <!-- Hero Section -->
            <div class="${this.template.heroClass}">
                <h1 class="${this.template.heroTitleClass}">
                    ${this.config.site?.tagline || 'Welcome'}
                </h1>
                <p class="${this.template.heroSubtitleClass}">
                    ${this.config.site?.description || ''}
                </p>
                <a href="/events" class="${this.template.primaryButtonClass}">
                    Browse Events
                </a>
            </div>

            <!-- Featured Events -->
            <section class="mt-16">
                <h2 class="${this.template.headingClass}">Featured Events</h2>
                <div id="featured-events" class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                    <!-- Populated via API -->
                </div>
            </section>
        </div>
    `;
}
```

---

## Part 5: Theme-Based Personalization

### Using Theme Config for Homepage

```typescript
// Access theme colors
const primaryColor = config.theme.primaryColor;
const secondaryColor = config.theme.secondaryColor;

// Use CSS variables (preferred)
<div class="bg-primary text-white">...</div>
<div style="background: var(--tixello-primary)">...</div>

// Extended theme (if available)
const bgColor = config.theme.colors?.background || '#ffffff';
const textColor = config.theme.colors?.text || '#1a1a1a';
```

### Dynamic Styling Based on Config

```typescript
renderHero: (config: TixelloConfig): string => {
    const theme = config.theme;

    // Build dynamic styles
    const heroStyles = `
        background: ${theme.colors?.background || '#f9fafb'};
        padding: ${theme.spacing?.sectionPadding || '4rem'} 0;
    `;

    return `
        <section class="hero" style="${heroStyles}">
            <h1 style="font-family: ${theme.typography?.headingFont || 'inherit'}">
                ${config.site?.title}
            </h1>
        </section>
    `;
}
```

---

## Part 6: Show/Hide Sections

### Implementing Section Visibility

Create a configuration system for section visibility:

```typescript
// In TixelloConfig or TenantPage
interface HomepageConfig {
    sections: {
        hero: { visible: boolean; order: number };
        featuredEvents: { visible: boolean; order: number };
        categories: { visible: boolean; order: number };
        newsletter: { visible: boolean; order: number };
        testimonials: { visible: boolean; order: number };
    };
}

// Usage in rendering
renderHomepage(config: TixelloConfig, homepageConfig: HomepageConfig): string {
    const sections = Object.entries(homepageConfig.sections)
        .filter(([_, settings]) => settings.visible)
        .sort((a, b) => a[1].order - b[1].order);

    return sections.map(([sectionName]) => {
        switch (sectionName) {
            case 'hero': return this.renderHeroSection(config);
            case 'featuredEvents': return this.renderEventsSection(config);
            case 'categories': return this.renderCategoriesSection(config);
            case 'newsletter': return this.renderNewsletterSection(config);
            case 'testimonials': return this.renderTestimonialsSection(config);
            default: return '';
        }
    }).join('');
}
```

### Block-Based Visibility (Recommended)

With PageBuilder, visibility is controlled by including/excluding blocks:

```typescript
// Page layout with visible sections
{
    layout: {
        blocks: [
            { id: '1', type: 'hero', ... },       // Hero visible
            { id: '2', type: 'event-grid', ... }, // Events visible
            // Newsletter NOT included = hidden
        ]
    }
}
```

---

## Part 7: Image/Photo Customization

### Background Images in Hero

```typescript
{
    type: 'hero',
    settings: {
        backgroundType: 'image',
        backgroundImage: 'https://tenant-cdn.com/hero-bg.jpg',
        overlayOpacity: 40,
    }
}
```

### Text + Image Block

```typescript
{
    type: 'text-image',
    settings: {
        imageUrl: 'https://tenant-cdn.com/about-us.jpg',
        imagePosition: 'right',  // 'left' or 'right'
        imageAlt: 'About our company',
    },
    content: {
        en: {
            title: 'About Us',
            content: '<p>We are a leading event company...</p>',
        }
    }
}
```

### Image Block (Standalone)

```typescript
{
    type: 'image',
    settings: {
        imageUrl: 'https://tenant-cdn.com/promo.jpg',
        size: 'large',        // small, medium, large, full
        alignment: 'center',  // left, center, right
        borderRadius: 'lg',   // none, sm, md, lg
        shadow: true,
        lightbox: true,       // Click to enlarge
        linkUrl: '/events',   // Optional link
    },
    content: {
        en: {
            caption: 'Special event coming soon!',
            altText: 'Promotional banner',
        }
    }
}
```

---

## Part 8: Text Customization

### Translatable Content

All text content should support multiple languages:

```typescript
{
    content: {
        en: {
            title: 'Welcome to Our Events',
            subtitle: 'Book your tickets today',
            buttonText: 'Get Started',
        },
        ro: {
            title: 'Bine ați venit la Evenimente',
            subtitle: 'Rezervă biletele acum',
            buttonText: 'Începe',
        },
        de: {
            title: 'Willkommen zu unseren Veranstaltungen',
            subtitle: 'Buchen Sie Ihre Tickets heute',
            buttonText: 'Loslegen',
        }
    }
}
```

### Accessing Localized Content

```typescript
// In PageBuilderModule
static getContent(block: Block, key: string, fallback: string = ''): string {
    // Try current language
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

// Usage
const title = PageBuilderModule.getContent(block, 'title', 'Default Title');
```

---

## Part 9: API Endpoints

### Get Home Page

```
GET /api/tenant-client/pages/home
Headers: X-Tenant-ID or hostname parameter
```

Response:
```json
{
    "success": true,
    "data": {
        "slug": "home",
        "title": "Home",
        "pageType": "builder",
        "layout": {
            "blocks": [...]
        }
    }
}
```

### Update Home Page (Admin)

```
PUT /api/tenant-client/pages/home
Authorization: Bearer {token}
Content-Type: application/json

{
    "layout": {
        "blocks": [...]
    },
    "is_published": true
}
```

---

## Part 10: Implementation Checklist

### For New Templates

- [ ] Support PageBuilder layouts in `renderHome`
- [ ] Provide fallback default homepage
- [ ] Use CSS variables for theme colors
- [ ] Support hero backgrounds (image, gradient, video)
- [ ] Support dynamic event grids
- [ ] Include newsletter section option
- [ ] Support testimonials display
- [ ] Use translatable content helpers
- [ ] Handle loading states (skeleton/pulse)
- [ ] Responsive design for all sections

### For Tenant Admin

- [ ] Page Builder UI for home page
- [ ] Block picker with all available types
- [ ] Drag-and-drop reordering
- [ ] Settings panel per block
- [ ] Multi-language content editor
- [ ] Image upload/selection
- [ ] Preview mode
- [ ] Publish/unpublish toggle

---

## Part 11: Example Complete Homepage Layout

```typescript
const defaultHomeLayout: PageLayout = {
    blocks: [
        {
            id: 'hero_1',
            type: 'hero',
            settings: {
                backgroundType: 'gradient',
                height: 'large',
                alignment: 'center',
            },
            content: {
                en: {
                    title: 'Discover Amazing Events',
                    subtitle: 'Book tickets for concerts, shows, and more',
                    buttonText: 'Explore Events',
                }
            }
        },
        {
            id: 'events_1',
            type: 'event-grid',
            settings: {
                columns: 3,
                source: 'featured',
                limit: 6,
            },
            content: {
                en: {
                    title: 'Featured Events',
                    subtitle: 'Hand-picked experiences just for you',
                }
            }
        },
        {
            id: 'cta_1',
            type: 'cta-banner',
            settings: {
                backgroundColor: 'gradient',
                buttonLink: '/events',
            },
            content: {
                en: {
                    title: 'Ready for your next adventure?',
                    subtitle: 'Browse all our upcoming events',
                    buttonText: 'View All Events',
                }
            }
        },
        {
            id: 'newsletter_1',
            type: 'newsletter',
            settings: {
                style: 'inline',
            },
            content: {
                en: {
                    title: 'Stay Updated',
                    subtitle: 'Get notified about new events and special offers',
                    buttonText: 'Subscribe',
                    placeholderText: 'Enter your email',
                }
            }
        }
    ]
};
```

---

**Document Version:** 1.0
**Last Updated:** 2024-12-10
