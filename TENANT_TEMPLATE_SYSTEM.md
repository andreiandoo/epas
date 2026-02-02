# Tenant Template System - Documentație Completă

## 📋 Cuprins

1. [Arhitectura Template-urilor](#arhitectura-template-urilor)
2. [Structura Fișierelor](#structura-fișierelor)
3. [Cum Creezi un Template Nou](#cum-creezi-un-template-nou)
4. [API-ul Template](#apiul-template)
5. [Integrare cu Router](#integrare-cu-router)
6. [Exemple Complete](#exemple-complete)
7. [Best Practices](#best-practices)

---

## Arhitectura Template-urilor

### Concepte Cheie

Template-urile în Tixello sunt **obiecte TypeScript** care definesc:
- **Layout-ul general** (header, footer, container)
- **Clase CSS** pentru stilizare consistentă
- **Funcții de rendering** pentru componente reutilizabile
- **Configurare dinamică** bazată pe setările tenant-ului

### Flow-ul de Redare

```
TenantConfig → TemplateManager → Template Object → HTML Output → DOM
```

1. **TenantConfig** = setările tenant-ului (logo, culori, meniuri, social)
2. **TemplateManager** = alege template-ul activ
3. **Template Object** = funcțiile de rendering
4. **HTML Output** = string-uri HTML
5. **DOM** = inserare în pagină

---

## Structura Fișierelor

```
resources/tenant-client/src/
├── templates/
│   ├── index.ts              # Export toate template-urile
│   ├── TemplateManager.ts    # Logica de selecție template
│   ├── default.ts            # Template implicit
│   ├── modern.ts             # Template modern (exemplu)
│   └── [tau-template].ts     # Template-ul tău nou
├── core/
│   ├── Router.ts             # Folosește template-urile pentru rendering
│   └── ConfigManager.ts      # Configurare tenant
└── index.ts                  # Entry point
```

---

## Cum Creezi un Template Nou

### Pasul 1: Creează Fișierul Template

**Fișier:** `src/templates/tau-template.ts`

```typescript
import { TixelloConfig } from '../core/ConfigManager';

export const tauTemplate = {
    // ==========================================
    // 1. CLASE CSS (consistență stilistică)
    // ==========================================

    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
    headerClass: 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg',
    footerClass: 'bg-gray-900 text-white',

    // Typography
    headingClass: 'text-3xl font-bold text-gray-900',
    subheadingClass: 'text-xl text-gray-600',

    // Buttons
    buttonPrimaryClass: 'bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg transition',
    buttonSecondaryClass: 'bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition',

    // Cards
    cardClass: 'bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition',

    // ==========================================
    // 2. HEADER (cu meniu dinamic)
    // ==========================================

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Tixello';
        const headerMenu = config.menus?.header || [];

        // Generare meniu items
        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="text-white hover:text-purple-200 transition font-medium">
                ${item.title}
            </a>`
        ).join('');

        return `
            <header class="bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg sticky top-0 z-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-20">
                        <!-- Logo -->
                        <a href="/" class="flex items-center space-x-3">
                            ${logo
                                ? `<img src="${logo}" alt="${siteName}" class="h-10 w-auto">`
                                : `<span class="text-2xl font-bold text-white">${siteName}</span>`
                            }
                        </a>

                        <!-- Desktop Menu -->
                        <nav class="hidden md:flex items-center space-x-8">
                            <a href="/events" class="text-white hover:text-purple-200 transition font-medium">
                                Evenimente
                            </a>
                            ${menuItemsHtml}

                            <!-- Cart Icon with Badge -->
                            <a href="/cart" class="relative text-white hover:text-purple-200 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="30">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"/>
                                </svg>
                                <span id="cart-badge" class="absolute -top-2 -right-2 bg-yellow-400 text-purple-900 text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>

                            <a href="/login" class="bg-white text-purple-600 hover:bg-purple-50 px-5 py-2 rounded-full font-bold transition">
                                Contul meu
                            </a>
                        </nav>

                        <!-- Mobile Menu Button -->
                        <button class="md:hidden p-2 text-white" id="mobile-menu-btn">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>
        `;
    },

    // ==========================================
    // 3. FOOTER (cu social media)
    // ==========================================

    renderFooter: (config: TixelloConfig): string => {
        const siteName = config.site?.title || 'Tixello';
        const year = new Date().getFullYear();
        const social = config.social || {};
        const footerMenu = config.menus?.footer || [];

        // Footer menu
        const footerMenuHtml = footerMenu.map(item =>
            `<a href="${item.url}" class="text-gray-400 hover:text-white transition">${item.title}</a>`
        ).join('');

        // Social icons
        const socialHtml = [];
        if (social.facebook) socialHtml.push(`<a href="${social.facebook}" class="text-gray-400 hover:text-white transition">Facebook</a>`);
        if (social.instagram) socialHtml.push(`<a href="${social.instagram}" class="text-gray-400 hover:text-white transition">Instagram</a>`);
        if (social.twitter) socialHtml.push(`<a href="${social.twitter}" class="text-gray-400 hover:text-white transition">Twitter</a>`);

        return `
            <footer class="bg-gray-900 text-white mt-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Column 1: Brand -->
                        <div>
                            <h3 class="text-xl font-bold mb-4">${siteName}</h3>
                            <p class="text-gray-400">Platforma ta de evenimente</p>
                        </div>

                        <!-- Column 2: Links -->
                        <div>
                            <h4 class="font-bold mb-4">Link-uri</h4>
                            <div class="space-y-2">
                                ${footerMenuHtml}
                            </div>
                        </div>

                        <!-- Column 3: Social -->
                        <div>
                            <h4 class="font-bold mb-4">Social Media</h4>
                            <div class="space-y-2">
                                ${socialHtml.join('')}
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-8 border-t border-gray-800 text-center text-gray-400">
                        <p>&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                    </div>
                </div>
            </footer>
        `;
    },

    // ==========================================
    // 4. EVENT CARD (component reutilizabil)
    // ==========================================

    renderEventCard: (event: any): string => {
        const image = event.poster_url || event.hero_image_url || '/placeholder.jpg';
        const date = new Date(event.start_date).toLocaleDateString('ro-RO', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });

        return `
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                <!-- Image -->
                <div class="relative h-48 overflow-hidden">
                    <img src="${image}" alt="${event.title}" class="w-full h-full object-cover">
                    ${event.is_sold_out ? '<span class="absolute top-2 right-2 bg-red-600 text-white px-3 py-1 rounded-full text-sm font-bold">SOLD OUT</span>' : ''}
                </div>

                <!-- Content -->
                <div class="p-5">
                    <h3 class="text-xl font-bold text-gray-900 mb-2 line-clamp-2">${event.title}</h3>

                    <div class="flex items-center text-gray-600 mb-2">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                        </svg>
                        <span class="text-sm">${date}</span>
                    </div>

                    ${event.venue?.name ? `
                    <div class="flex items-center text-gray-600 mb-4">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"/>
                        </svg>
                        <span class="text-sm">${event.venue.name}</span>
                    </div>
                    ` : ''}

                    <div class="flex items-center justify-between">
                        <div>
                            ${event.price_from ? `
                                <p class="text-2xl font-bold text-purple-600">${event.price_from} ${event.currency}</p>
                            ` : `
                                <p class="text-lg font-bold text-gray-500">Gratis</p>
                            `}
                        </div>
                        <a href="/event/${event.slug}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition">
                            Detalii
                        </a>
                    </div>
                </div>
            </div>
        `;
    }
};
```

### Pasul 2: Exportă Template-ul

**Fișier:** `src/templates/index.ts`

```typescript
export { defaultTemplate } from './default';
export { modernTemplate } from './modern';
export { tauTemplate } from './tau-template'; // ← Adaugă aici
```

### Pasul 3: Configurează în TemplateManager

**Fișier:** `src/templates/TemplateManager.ts`

```typescript
import { defaultTemplate, modernTemplate, tauTemplate } from './index';

export class TemplateManager {
    private static templates = {
        'default': defaultTemplate,
        'modern': modernTemplate,
        'tau-template': tauTemplate, // ← Adaugă aici
    };

    static getTemplate(name: string = 'default') {
        return this.templates[name] || this.templates['default'];
    }
}
```

---

## API-ul Template

### Proprietăți Obligatorii

```typescript
interface Template {
    // CSS Classes
    containerClass: string;
    headerClass: string;
    footerClass: string;
    headingClass: string;
    subheadingClass: string;
    buttonPrimaryClass: string;
    cardClass: string;

    // Rendering Functions
    renderHeader: (config: TixelloConfig) => string;
    renderFooter: (config: TixelloConfig) => string;
    renderEventCard?: (event: any) => string; // Optional
}
```

### TixelloConfig Structure

```typescript
interface TixelloConfig {
    tenantId: number;
    domainId: number;
    apiEndpoint: string;

    site?: {
        title: string;
        description: string;
    };

    theme?: {
        logo: string;
        favicon: string;
        primaryColor: string;
        secondaryColor: string;
    };

    menus?: {
        header: Array<{ title: string; url: string }>;
        footer: Array<{ title: string; url: string }>;
    };

    social?: {
        facebook?: string;
        instagram?: string;
        twitter?: string;
        linkedin?: string;
        youtube?: string;
    };

    modules?: string[]; // ['events', 'tickets', 'seating']
}
```

---

## Integrare cu Router

### Cum se Folosesc Template-urile în Router.ts

```typescript
import { TemplateManager } from '../templates';

class Router {
    private template: any;

    constructor(config: TixelloConfig) {
        this.config = config;
        this.template = TemplateManager.getTemplate(config.theme?.template || 'default');
    }

    private renderHome(): void {
        const content = this.getContentElement();
        if (!content) return;

        // Folosește clase din template
        content.innerHTML = `
            <div class="${this.template.containerClass}">
                <h1 class="${this.template.headingClass}">Bine ai venit!</h1>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    ${this.events.map(event =>
                        this.template.renderEventCard
                            ? this.template.renderEventCard(event)
                            : this.defaultEventCard(event)
                    ).join('')}
                </div>
            </div>
        `;
    }
}
```

### Render Header/Footer

**În `src/core/App.ts` sau `Router.ts`:**

```typescript
private setupLayout(): void {
    const app = document.getElementById('app');
    if (!app) return;

    app.innerHTML = `
        ${this.template.renderHeader(this.config)}
        <main id="content"></main>
        ${this.template.renderFooter(this.config)}
    `;
}
```

---

## Exemple Complete

### Exemplu 1: Template cu Alpine.js

```typescript
export const alpineTemplate = {
    containerClass: 'max-w-7xl mx-auto px-4',

    renderHeader: (config: TixelloConfig): string => {
        return `
            <header x-data="{ mobileMenuOpen: false }">
                <nav class="flex items-center justify-between p-4">
                    <div>Logo</div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex space-x-4">
                        ${config.menus?.header.map(item =>
                            `<a href="${item.url}">${item.title}</a>`
                        ).join('')}
                    </div>

                    <!-- Mobile Toggle -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden">
                        <svg class="w-6 h-6">...</svg>
                    </button>
                </nav>

                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen" x-transition>
                    ${config.menus?.header.map(item =>
                        `<a href="${item.url}" class="block p-2">${item.title}</a>`
                    ).join('')}
                </div>
            </header>
        `;
    },

    // ... rest of template
};
```

### Exemplu 2: Dark Mode Template

```typescript
export const darkTemplate = {
    containerClass: 'max-w-7xl mx-auto px-4 dark:bg-gray-900',
    headerClass: 'bg-gray-800 text-white',
    cardClass: 'bg-gray-800 text-white rounded-lg shadow-xl',

    renderHeader: (config: TixelloConfig): string => {
        return `
            <header class="bg-gray-800 text-white">
                <div class="flex items-center justify-between p-4">
                    <a href="/" class="text-2xl font-bold">${config.site?.title}</a>

                    <nav class="flex items-center space-x-6">
                        <a href="/events" class="hover:text-purple-400">Evenimente</a>
                        <button id="theme-toggle" class="p-2">
                            🌙 Dark Mode
                        </button>
                    </nav>
                </div>
            </header>
        `;
    },

    renderEventCard: (event: any): string => {
        return `
            <div class="bg-gray-800 text-white rounded-lg shadow-xl overflow-hidden">
                <img src="${event.poster_url}" class="w-full h-48 object-cover">
                <div class="p-4">
                    <h3 class="text-xl font-bold mb-2">${event.title}</h3>
                    <p class="text-gray-400">${event.start_date}</p>
                </div>
            </div>
        `;
    }
};
```

---

## Best Practices

### ✅ DO

1. **Folosește clase reutilizabile**
   ```typescript
   cardClass: 'bg-white rounded-lg shadow-md p-4'
   ```

2. **Parametrizează culorile prin Tailwind**
   ```typescript
   primaryColor: 'purple-600'
   buttonPrimaryClass: 'bg-purple-600 hover:bg-purple-700'
   ```

3. **Validează config-ul**
   ```typescript
   const logo = config.theme?.logo || '/default-logo.png';
   ```

4. **Folosește funcții helper pentru componente repetitive**
   ```typescript
   renderButton: (text: string, href: string) =>
       `<a href="${href}" class="${this.buttonPrimaryClass}">${text}</a>`
   ```

5. **Optimizează pentru mobile**
   ```html
   <div class="hidden md:flex">Desktop</div>
   <div class="md:hidden">Mobile</div>
   ```

### ❌ DON'T

1. **Nu hardcoda valori**
   ```typescript
   // ❌ Greșit
   renderHeader: () => `<div class="bg-blue-500">My Site</div>`

   // ✅ Corect
   renderHeader: (config) => `<div class="bg-${config.theme.primaryColor}">${config.site.title}</div>`
   ```

2. **Nu include logică complexă în template**
   ```typescript
   // ❌ Greșit - API calls în template
   renderEvents: async () => {
       const events = await fetch('/api/events');
       return events.map(...);
   }

   // ✅ Corect - template primește datele
   renderEvents: (events: Event[]) => {
       return events.map(e => this.renderEventCard(e)).join('');
   }
   ```

3. **Nu duplica cod**
   ```typescript
   // ❌ Greșit
   renderButton1: () => `<button class="bg-blue-500...">Click</button>`
   renderButton2: () => `<button class="bg-blue-500...">Submit</button>`

   // ✅ Corect
   renderButton: (text: string) => `<button class="${this.buttonPrimaryClass}">${text}</button>`
   ```

---

## Configurare Tenant Admin

### Cum se Selectează Template-ul

**În Core Admin → Tenants → Edit:**

1. Câmp `theme_config` (JSON):
   ```json
   {
       "template": "tau-template",
       "logo": "/storage/logos/tenant-logo.png",
       "primaryColor": "purple-600",
       "secondaryColor": "pink-500"
   }
   ```

2. Template-ul se încarcă automat când tenant-ul accesează site-ul

### Testing Local

```bash
cd resources/tenant-client
npm run dev

# Edit ConfigManager.ts temporar:
const config = {
    theme: {
        template: 'tau-template'
    }
}
```

---

## Deployment

### Build & Deploy

```bash
# 1. Build tenant-client
cd resources/tenant-client
npm run build

# 2. Commit changes
git add src/templates/tau-template.ts
git commit -m "feat: Add tau-template"
git push

# 3. Deploy
cd /home/core-cuhlf/core.tixello.com
sudo git pull origin core-main
sudo php artisan optimize:clear

# 4. Regenerate tenant package
Core Admin → Tenants → [Tenant] → Regenerate Package
```

---

## Troubleshooting

### Template nu se afișează
- Verifică că e exportat în `templates/index.ts`
- Verifică că e înregistrat în `TemplateManager.ts`
- Verifică numele în `theme_config.template`

### Stiluri nu se aplică
- Verifică clasele Tailwind în `tailwind.config.js`
- Rulează `npm run build` după modificări CSS
- Clear cache: `sudo php artisan optimize:clear`

### Erori TypeScript
- Verifică tipurile în `TixelloConfig`
- Asigură-te că toate funcțiile returnează `string`
- Rulează `npm run build` pentru validare

---

## Resurse

- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Alpine.js Guide](https://alpinejs.dev/start-here)
- [TypeScript Handbook](https://www.typescriptlang.org/docs/)

---

**Creat:** 2025-11-26
**Ultima actualizare:** 2025-11-26
**Autor:** Claude Code + Andrei
