# Tenant Client Templates

Acest director conține template-urile pentru site-urile tenant.

## Structura unui template

Fiecare template este un fișier TypeScript care exportă un `TemplateConfig`:

```typescript
import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

const myTemplate: TemplateConfig = {
    name: 'my-template',

    // CSS classes pentru diferite secțiuni
    headerClass: 'bg-white shadow-sm border-b',
    footerClass: 'bg-gray-50 border-t',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
    heroClass: 'text-center py-16 bg-gray-100',
    heroTitleClass: 'text-4xl font-bold text-gray-900 mb-4',
    heroSubtitleClass: 'text-xl text-gray-600 mb-8',
    cardClass: 'bg-white rounded-lg shadow-md',
    cardHoverClass: 'hover:shadow-lg transition',
    primaryButtonClass: 'btn-primary px-6 py-3 rounded-lg',
    secondaryButtonClass: 'btn-secondary px-6 py-3 rounded-lg',
    headingClass: 'text-2xl font-bold text-gray-900',
    subheadingClass: 'text-lg text-gray-600',

    // Funcții pentru header și footer
    renderHeader: (config: TixelloConfig): string => {
        // Return HTML string for header
        return `<header>...</header>`;
    },

    renderFooter: (config: TixelloConfig): string => {
        // Return HTML string for footer
        return `<footer>...</footer>`;
    }
};

// Înregistrează template-ul
TemplateManager.registerTemplate('my-template', myTemplate);

export default myTemplate;
```

## Crearea unui nou template

1. Creează un nou fișier în acest director (ex: `minimal.ts`)
2. Copiază structura din `default.ts` sau `modern.ts`
3. Personalizează clasele CSS și funcțiile de render
4. Importă template-ul în `index.ts`:
   ```typescript
   import './minimal';
   ```
5. Adaugă template-ul în dropdown-ul din Settings (în backend)

## Clase CSS disponibile

Template-urile pot folosi clasele dinamice definite de ConfigManager:

### Culori primare
- `bg-primary` - fundal culoare primară
- `bg-primary-dark` - fundal culoare primară dark (hover)
- `text-primary` - text culoare primară
- `border-primary` - border culoare primară

### Culori secundare
- `bg-secondary` - fundal culoare secundară
- `bg-secondary-dark` - fundal culoare secundară dark (hover)
- `text-secondary` - text culoare secundară
- `border-secondary` - border culoare secundară

### Butoane
- `btn-primary` - buton primary stilizat
- `btn-secondary` - buton secondary stilizat

### Hover states
- `hover:text-primary` - text primary la hover
- `hover:bg-primary` - fundal primary la hover
- `hover:bg-primary-dark` - fundal primary-dark la hover

## Variabile CSS

Toate culorile sunt definite ca variabile CSS în `:root`:

```css
--tixello-primary: #3B82F6;
--tixello-primary-dark: #2563eb;
--tixello-secondary: #1E40AF;
--tixello-secondary-dark: #1e3a8a;
--tixello-font: Inter;
```

## Template-uri disponibile

1. **default** - Template clasic, minimalist, cu mult alb
2. **modern** - Template dark, cu gradiente și efecte de hover

## Adăugarea în Admin

Pentru a face un template disponibil în dropdown-ul din Settings, adaugă-l în fișierul:
`app/Filament/Tenant/Pages/Settings.php`

```php
Forms\Components\Select::make('site_template')
    ->options([
        'default' => 'Default',
        'modern' => 'Modern',
        'my-template' => 'My Template', // Adaugă aici
    ])
```
