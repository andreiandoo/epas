# Template Creation Methodology for Claude

This document provides a methodology for creating tenant templates that can be passed to Claude (or any AI chat) to generate new templates.

---

## Quick Reference: Template Prompt Format

When asking Claude to create a template, use this format:

```
Create a new tenant template called "[TEMPLATE_NAME]" with these characteristics:
- Style: [modern/minimalist/bold/elegant/playful]
- Primary Color: [hex code]
- Header Style: [sticky/static] [transparent/solid] [light/dark]
- Footer Style: [columns/simple/centered]
- Typography: [font suggestions]
- Special Features: [list any special requirements]

Reference design: [optional URL or description]
```

---

## Part 1: Template Structure Overview

### Template File Structure

```typescript
// src/templates/[template-name].ts

import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

const myTemplate: TemplateConfig = {
    name: 'template-name',

    // CSS Classes (required)
    headerClass: string,
    footerClass: string,
    containerClass: string,
    heroClass: string,
    heroTitleClass: string,
    heroSubtitleClass: string,
    cardClass: string,
    cardHoverClass: string,
    primaryButtonClass: string,
    secondaryButtonClass: string,
    headingClass: string,
    subheadingClass: string,

    // Render Functions (required)
    renderHeader: (config: TixelloConfig) => string,
    renderFooter: (config: TixelloConfig) => string,
};

// Always register at the end
TemplateManager.registerTemplate('template-name', myTemplate);
export default myTemplate;
```

---

## Part 2: Required Properties

### CSS Classes Reference

| Property | Purpose | Example |
|----------|---------|---------|
| `headerClass` | Header wrapper | `'bg-white shadow-sm border-b'` |
| `footerClass` | Footer wrapper | `'bg-gray-50 border-t'` |
| `containerClass` | Content container | `'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'` |
| `heroClass` | Hero section | `'text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100'` |
| `heroTitleClass` | Hero title | `'text-4xl md:text-5xl font-bold text-gray-900 mb-4'` |
| `heroSubtitleClass` | Hero subtitle | `'text-xl text-gray-600 mb-8 max-w-2xl mx-auto'` |
| `cardClass` | Card base | `'bg-white rounded-lg shadow-md overflow-hidden'` |
| `cardHoverClass` | Card hover state | `'hover:shadow-lg transition'` |
| `primaryButtonClass` | Primary button | `'px-6 py-3 bg-primary text-white rounded-lg'` |
| `secondaryButtonClass` | Secondary button | `'px-6 py-3 border border-gray-300 rounded-lg'` |
| `headingClass` | Section headings | `'text-2xl font-bold text-gray-900'` |
| `subheadingClass` | Subheadings | `'text-lg text-gray-600'` |

### Using Dynamic Colors

Always use CSS variables for brand colors:
- `bg-primary` - Primary background color
- `text-primary` - Primary text color
- `hover:bg-primary-dark` - Darker shade for hover
- `bg-secondary` - Secondary color
- `var(--tixello-primary)` - CSS variable access

---

## Part 3: Header Implementation

### Header Requirements

1. **Must include**: Logo, navigation, cart icon with badge, account link
2. **Must be responsive**: Desktop nav + mobile menu button
3. **Must read from config**: `config.theme.logo`, `config.site.title`, `config.menus.header`

### Header Template

```typescript
renderHeader: (config: TixelloConfig): string => {
    const logo = config.theme?.logo;
    const siteName = config.site?.title || 'Site Name';
    const headerMenu = config.menus?.header || [];

    // Generate dynamic menu items
    const menuItemsHtml = headerMenu.map(item =>
        `<a href="${item.url}" class="[your-link-class]">${item.title}</a>`
    ).join('');

    return `
        <header class="[your-header-class] sticky top-0 z-50">
            <div class="${this.containerClass}">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <a href="/" class="flex items-center">
                        ${logo
                            ? `<img src="${logo}" alt="${siteName}" class="h-8 w-auto">`
                            : `<span class="text-xl font-bold text-primary">${siteName}</span>`
                        }
                    </a>

                    <!-- Desktop Navigation -->
                    <nav class="hidden md:flex items-center space-x-6">
                        <a href="/events" class="[link-class]">Events</a>
                        <a href="/blog" class="[link-class]">Blog</a>
                        ${menuItemsHtml}

                        <!-- Cart with Badge (REQUIRED) -->
                        <a href="/cart" class="relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="30">
                                <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"/>
                            </svg>
                            <span id="cart-badge" class="absolute -top-2 -right-2 bg-primary text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                        </a>

                        <!-- Account Link (REQUIRED) -->
                        <a href="/login" id="account-link" class="btn-primary px-4 py-2 rounded-lg">
                            My Account
                        </a>
                    </nav>

                    <!-- Mobile Menu Button (REQUIRED) -->
                    <button class="md:hidden p-2" id="mobile-menu-btn">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>
    `;
}
```

---

## Part 4: Footer Implementation

### Footer Requirements

1. **Must include**: Site name, description, navigation links, social icons
2. **Must read from config**: `config.site`, `config.social`, `config.menus.footer`, `config.platform`
3. **Must include**: Copyright, "Powered by" attribution

### Social Icons HTML (Copy-Paste Ready)

```typescript
// Generate social icons
const socialIcons = [];

if (social.facebook) {
    socialIcons.push(`<a href="${social.facebook}" target="_blank" class="[icon-class]" aria-label="Facebook">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
    </a>`);
}

if (social.instagram) {
    socialIcons.push(`<a href="${social.instagram}" target="_blank" class="[icon-class]" aria-label="Instagram">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
        </svg>
    </a>`);
}

if (social.twitter) {
    socialIcons.push(`<a href="${social.twitter}" target="_blank" class="[icon-class]" aria-label="Twitter/X">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
        </svg>
    </a>`);
}

if (social.youtube) {
    socialIcons.push(`<a href="${social.youtube}" target="_blank" class="[icon-class]" aria-label="YouTube">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
        </svg>
    </a>`);
}

if (social.tiktok) {
    socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="[icon-class]" aria-label="TikTok">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
        </svg>
    </a>`);
}

if (social.linkedin) {
    socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="[icon-class]" aria-label="LinkedIn">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
        </svg>
    </a>`);
}
```

### Footer Template

```typescript
renderFooter: (config: TixelloConfig): string => {
    const siteName = config.site?.title || 'Site Name';
    const year = new Date().getFullYear();
    const social = config.social || {};
    const footerMenu = config.menus?.footer || [];

    // Generate footer menu
    const footerMenuHtml = footerMenu.map(item =>
        `<li><a href="${item.url}" class="[link-class]">${item.title}</a></li>`
    ).join('');

    // Generate social icons (see above)
    const socialIcons = [...]; // Use the code above

    return `
        <footer class="[footer-class] mt-16">
            <div class="${this.containerClass} py-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Brand Column -->
                    <div class="col-span-1 md:col-span-2">
                        <h3 class="text-lg font-bold mb-4">${siteName}</h3>
                        <p class="text-sm mb-4">${config.site?.description || ''}</p>
                        <div class="flex space-x-4">${socialIcons.join('')}</div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h4 class="font-semibold mb-4">Quick Links</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="/events" class="[link-class]">Events</a></li>
                            <li><a href="/past-events" class="[link-class]">Past Events</a></li>
                            <li><a href="/blog" class="[link-class]">Blog</a></li>
                            <li><a href="/account" class="[link-class]">My Account</a></li>
                        </ul>
                    </div>

                    <!-- Legal -->
                    <div>
                        <h4 class="font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="/terms" class="[link-class]">Terms & Conditions</a></li>
                            <li><a href="/privacy" class="[link-class]">Privacy Policy</a></li>
                            ${footerMenuHtml}
                        </ul>
                    </div>
                </div>

                <!-- Copyright & Powered By (REQUIRED) -->
                <div class="border-t mt-8 pt-8 flex justify-between items-center text-sm">
                    <p>&copy; ${year} ${siteName}. All rights reserved.</p>
                    <div class="flex items-center gap-2">
                        <span>Powered by</span>
                        <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank">
                            ${config.platform?.logo_light
                                ? `<img src="${config.platform.logo_light}" alt="${config.platform?.name || 'Tixello'}" class="h-4 w-auto">`
                                : `<span class="font-semibold">${config.platform?.name || 'Tixello'}</span>`
                            }
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    `;
}
```

---

## Part 5: Registration & Export

### Register the Template

At the end of your template file:

```typescript
// Register the template
TemplateManager.registerTemplate('my-template-name', myTemplate);

export default myTemplate;
```

### Add to Index

In `src/templates/index.ts`:

```typescript
export { defaultTemplate } from './default';
export { modernTemplate } from './modern';
export { myTemplate } from './my-template-name'; // Add this
```

---

## Part 6: Checklist for New Templates

Before submitting a template, verify:

- [ ] All CSS class properties are defined
- [ ] `renderHeader` function includes:
  - [ ] Logo (with fallback to text)
  - [ ] Dynamic menu from `config.menus.header`
  - [ ] Cart icon with `id="cart-badge"`
  - [ ] Account link with `id="account-link"`
  - [ ] Mobile menu button with `id="mobile-menu-btn"`
  - [ ] Responsive classes (`hidden md:flex`, etc.)
- [ ] `renderFooter` function includes:
  - [ ] Site name and description
  - [ ] Social icons (all platforms)
  - [ ] Footer menu from `config.menus.footer`
  - [ ] Copyright with dynamic year
  - [ ] "Powered by" attribution
- [ ] Template is registered with `TemplateManager.registerTemplate()`
- [ ] Template is exported and added to index
- [ ] Uses CSS variables (`bg-primary`, `text-primary`, etc.)
- [ ] Responsive design (mobile-first)
- [ ] Proper accessibility (aria-labels, semantic HTML)

---

## Part 7: Example Prompt for Claude

```
Create a tenant template called "festival" with these specifications:

**Style:**
- Music festival / concert vibe
- Dark background with vibrant accent colors
- Bold typography

**Header:**
- Transparent on scroll, solid on scroll down
- Neon-style accent on hover
- Centered logo

**Footer:**
- 3-column layout
- Gradient background matching primary colors
- Large social icons

**Colors:**
- Background: #0F0F0F
- Primary: #FF6B35
- Accent: #00FFC8

**Typography:**
- Heading font: Bold, uppercase
- Body: Clean sans-serif

Please generate the complete TypeScript template file following the TemplateConfig interface.
```

---

## Part 8: TixelloConfig Reference

```typescript
interface TixelloConfig {
    tenantId: number;
    domainId: number;
    domain: string;
    apiEndpoint: string;
    modules: string[];
    version: string;
    packageHash: string;

    theme: {
        primaryColor: string;
        secondaryColor: string;
        logo: string | null;
        favicon: string | null;
        fontFamily: string;
        colors?: { /* extended color config */ };
        typography?: { /* extended typography config */ };
        spacing?: { /* extended spacing config */ };
        borders?: { /* extended border config */ };
        shadows?: { /* extended shadow config */ };
        header?: { /* extended header config */ };
        buttons?: { /* extended button config */ };
    };

    site: {
        title: string;
        description: string;
        tagline: string;
        language: string;
        template: string;  // <- This selects which template to use
    };

    social: {
        facebook: string | null;
        instagram: string | null;
        twitter: string | null;
        youtube: string | null;
        tiktok: string | null;
        linkedin: string | null;
    };

    menus: {
        header: MenuItem[];
        footer: MenuItem[];
    };

    platform: {
        name: string;
        url: string;
        logo_light: string | null;
        logo_dark: string | null;
    };
}

interface MenuItem {
    title: string;
    slug: string;
    url: string;
}
```

---

**Document Version:** 1.0
**Last Updated:** 2024-12-10
