# Custom SVG Icons System

This directory contains custom SVG icons for the Tixello platform. Icons can be used in both public pages and the admin panel.

## ⚠️ IMPORTANT: Use `<x-svg-icon>` NOT `<x-icon>`

The application uses **`<x-svg-icon>`** to avoid conflicts with BladeUI Icons package (installed as a Filament dependency).

**DO NOT** use `<x-icon>` - it will cause errors!

## Usage

### Basic Usage

```blade
{{-- Use with default size (w-6 h-6) --}}
<x-svg-icon name="ticket" />

{{-- Custom size --}}
<x-svg-icon name="calendar" class="w-8 h-8" />

{{-- Custom color (uses currentColor, inherits from parent) --}}
<div class="text-blue-500">
    <x-svg-icon name="music" class="w-10 h-10" />
</div>

{{-- Inline with text --}}
<button class="flex items-center gap-2">
    <x-svg-icon name="search" class="w-5 h-5" />
    <span>Search</span>
</button>
```

### Available Icons

- **ticket** - Ticket icon
- **calendar** - Calendar/date icon
- **location** - Location/map marker icon
- **music** - Music note icon
- **user** - Single user icon
- **users** - Multiple users icon
- **star** - Star icon (for favorites/ratings)
- **heart** - Heart icon (for favorites)
- **venue** - Building/venue icon
- **event** - Event/sparkles icon
- **check** - Checkmark icon
- **x** - Close/cancel icon
- **search** - Search/magnifying glass icon
- **arrow-right** - Right arrow icon
- **konvaseats** - Seating layout icon

## Adding New Icons

### Option 1: Create a new Blade file

1. Create a new file in `resources/views/components/icons/` with the icon name (e.g., `cart.blade.php`)
2. Add your SVG markup with proper attributes:

```blade
<svg {{ $attributes->merge(['class' => 'w-6 h-6']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="YOUR_PATH_HERE"/>
</svg>
```

3. Use it: `<x-svg-icon name="cart" />`

### Important Notes

- Always use `{{ $attributes->merge(['class' => 'w-6 h-6']) }}` to allow size customization
- Use `fill="none" stroke="currentColor"` for icons that inherit text color
- Use `viewBox="0 0 24 24"` for Heroicons-style icons
- Keep stroke-width="2" for consistency

### Option 2: Copy from Heroicons

Visit [heroicons.com](https://heroicons.com/) and:
1. Choose an outline icon
2. Copy the SVG code
3. Replace the `<svg>` tag with: `<svg {{ $attributes->merge(['class' => 'w-6 h-6']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">`
4. Keep the rest of the SVG content

## Using in Filament Admin

Icons work seamlessly in Filament forms and tables:

```php
use Filament\Forms\Components\TextInput;

TextInput::make('name')
    ->label('Name')
    ->prefixIcon('heroicon-o-user') // Filament's built-in icons
    // OR use custom icon in descriptions/hints:
    ->hint(new HtmlString('<x-svg-icon name="user" class="w-4 h-4 inline" />'))
```

For custom icons in Filament navigation:

```php
// In your Filament Resource
protected static ?string $navigationIcon = 'heroicon-o-ticket';
```

**Note**: Filament uses its own icon system. To use custom SVG icons in Filament navigation, you need to register them as Heroicons-compatible. For now, use the custom icons in:
- Form hints/help text
- Table columns (custom rendering)
- Pages content
- Custom widgets

## Examples

### Public Page - Event Card

```blade
<div class="event-card">
    <div class="flex items-center gap-2 text-gray-600">
        <x-svg-icon name="calendar" class="w-5 h-5" />
        <span>{{ $event->date }}</span>
    </div>
    <div class="flex items-center gap-2 text-gray-600">
        <x-svg-icon name="location" class="w-5 h-5" />
        <span>{{ $venue->name }}</span>
    </div>
    <div class="flex items-center gap-2 text-gray-600">
        <x-svg-icon name="ticket" class="w-5 h-5" />
        <span>{{ $ticketCount }} tickets</span>
    </div>
</div>
```

### Admin - Custom Widget

```blade
<div class="stats-widget">
    <div class="stat-item">
        <x-svg-icon name="users" class="w-8 h-8 text-blue-500" />
        <span class="text-2xl font-bold">{{ $totalUsers }}</span>
        <span class="text-sm text-gray-500">Total Users</span>
    </div>
</div>
```

## Styling Icons

Icons use `currentColor` for stroke/fill, so they inherit text color:

```blade
{{-- Blue icon --}}
<div class="text-blue-500">
    <x-svg-icon name="ticket" />
</div>

{{-- Hover effect --}}
<button class="text-gray-500 hover:text-blue-600">
    <x-svg-icon name="search" class="w-6 h-6 transition" />
</button>

{{-- With background --}}
<div class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 rounded-full text-purple-600">
    <x-svg-icon name="music" class="w-6 h-6" />
</div>
```

## Troubleshooting

**Error: "Svg by name 'X' from set 'default' not found"**
- ❌ You're using `<x-icon>` instead of `<x-svg-icon>`
- ✅ Change to: `<x-svg-icon name="X" />`
- The `<x-icon>` component is reserved for BladeUI Icons (Filament's icon system)

**Icon not showing?**
- Check if the file exists in `resources/views/components/icons/`
- Verify the icon name matches the filename (without `.blade.php`)
- Clear the view cache: `php artisan view:clear` or `docker compose exec laravel.test php artisan view:clear`
- Make sure you're using `<x-svg-icon>` not `<x-icon>`

**Icon size not changing?**
- Make sure you're passing the `class` attribute
- Use Tailwind width/height classes: `w-4 h-4`, `w-6 h-6`, `w-8 h-8`, etc.

**Icon color not changing?**
- Icons inherit color from parent text color
- Use `text-{color}` classes on the icon or parent element

## Component Location

The custom icon component is located at:
- `resources/views/components/svg-icon.blade.php` (DO use this component)
- BladeUI Icons package handles `<x-icon>` (DO NOT use in your code)
