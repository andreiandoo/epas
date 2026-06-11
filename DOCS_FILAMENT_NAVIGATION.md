# Filament Navigation - Submenus and Groups

## Creating Submenus (Parent-Child Navigation)

To make one resource appear as a submenu under another resource, use the `$navigationParentItem` property.

### Example: Email History as Submenu of Email Templates

```php
<?php

namespace App\Filament\Resources\EmailLogs;

use Filament\Resources\Resource;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    // Navigation configuration
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Email History';

    // Make this a submenu item under "Email Templates"
    protected static ?string $navigationParentItem = 'Email Templates';

    // ... rest of resource
}
```

**Result**: Email History will now appear as a submenu item when you click on Email Templates in the sidebar.

---

## Navigation Properties Reference

### Basic Navigation Properties

```php
// The icon displayed in the sidebar
protected static ?string $navigationIcon = 'heroicon-o-document-text';

// The label shown in the sidebar (defaults to plural model name)
protected static ?string $navigationLabel = 'Email Templates';

// The group this resource belongs to
protected static ?string $navigationGroup = 'Communications';

// Sort order within the group (lower numbers appear first)
protected static ?int $navigationSort = 10;

// Hide from navigation entirely
protected static bool $shouldRegisterNavigation = false;
```

### Parent-Child Navigation

```php
// Make this resource appear as a child of another resource
protected static ?string $navigationParentItem = 'Parent Resource Label';

// Example:
protected static ?string $navigationParentItem = 'Email Templates';
```

**Important**: The `$navigationParentItem` value must match the `$navigationLabel` (or plural model name) of the parent resource.

---

## Navigation Groups

Resources are automatically grouped by the `$navigationGroup` property.

### Example: Multiple Resources in "Communications" Group

```php
// EmailTemplateResource.php
protected static ?string $navigationGroup = 'Communications';
protected static ?int $navigationSort = 10;

// EmailLogResource.php
protected static ?string $navigationGroup = 'Communications';
protected static ?int $navigationSort = 20;
protected static ?string $navigationParentItem = 'Email Templates';

// SmsTemplateResource.php (hypothetical)
protected static ?string $navigationGroup = 'Communications';
protected static ?int $navigationSort = 30;
```

**Result**: All three resources appear under the "Communications" section in the sidebar.

---

## Advanced: Custom Navigation

For more complex navigation structures, you can customize navigation in your Panel configuration.

### In `app/Providers/Filament/AdminPanelProvider.php`:

```php
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

public function panel(Panel $panel): Panel
{
    return $panel
        ->navigation(function () {
            return [
                NavigationGroup::make('Communications')
                    ->items([
                        NavigationItem::make('Email Templates')
                            ->icon('heroicon-o-document-text')
                            ->url(EmailTemplateResource::getUrl('index'))
                            ->isActiveWhen(fn () => request()->routeIs('filament.admin.resources.email-templates.*')),

                        NavigationItem::make('Email History')
                            ->icon('heroicon-o-envelope')
                            ->url(EmailLogResource::getUrl('index'))
                            ->parentItem('Email Templates'), // Creates submenu

                        NavigationItem::make('SMS Templates')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->url('#'),
                    ])
                    ->collapsible(),

                NavigationGroup::make('Settings')
                    ->items([
                        // ... more items
                    ]),
            ];
        });
}
```

---

## Collapsible Groups

By default, navigation groups are collapsible. Users can click the group header to collapse/expand the section.

To make a group always expanded:

```php
// In Panel configuration
NavigationGroup::make('Communications')
    ->items([...])
    ->collapsible(false); // Always expanded
```

---

## Badges on Navigation Items

Display badges (e.g., counts) on navigation items:

```php
public static function getNavigationBadge(): ?string
{
    return static::getModel()::where('status', 'pending')->count();
}

public static function getNavigationBadgeColor(): ?string
{
    return 'warning'; // or 'success', 'danger', 'info', etc.
}
```

**Example**: Show number of pending email logs:

```php
// In EmailLogResource
public static function getNavigationBadge(): ?string
{
    $pending = static::getModel()::where('status', 'pending')->count();
    return $pending > 0 ? (string) $pending : null;
}

public static function getNavigationBadgeColor(): ?string
{
    return 'warning';
}
```

---

## Complete Example: Multi-Level Navigation

```php
// 1. Parent Resource: EmailTemplateResource.php
class EmailTemplateResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Communications';
    protected static ?string $navigationLabel = 'Email Templates';
    protected static ?int $navigationSort = 10;
}

// 2. Child Resource: EmailLogResource.php
class EmailLogResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Communications';
    protected static ?string $navigationLabel = 'Email History';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationParentItem = 'Email Templates'; // Creates submenu

    public static function getNavigationBadge(): ?string
    {
        $failed = static::getModel()::where('status', 'failed')->count();
        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}

// 3. Another Child Resource: EmailAttachmentResource.php (hypothetical)
class EmailAttachmentResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';
    protected static ?string $navigationGroup = 'Communications';
    protected static ?string $navigationLabel = 'Attachments';
    protected static ?int $navigationSort = 30;
    protected static ?string $navigationParentItem = 'Email Templates'; // Also under Email Templates
}
```

**Result**:
```
Communications ▼
  ▼ Email Templates
      Email History (badge: 3)
      Attachments
  SMS Templates
```

---

## Icons

All Heroicons are available. Common icons:

- `heroicon-o-document-text` - Document/template
- `heroicon-o-envelope` - Email
- `heroicon-o-chat-bubble-left-right` - Messages
- `heroicon-o-users` - Users/groups
- `heroicon-o-cog` - Settings
- `heroicon-o-folder` - Folders
- `heroicon-o-clipboard-document-list` - Lists
- `heroicon-o-shield-check` - Security

Browse all icons at: https://heroicons.com

---

## Tips

1. **Consistent Naming**: Ensure `$navigationParentItem` exactly matches the parent's `$navigationLabel`
2. **Sort Order**: Use increments of 10 (10, 20, 30) to easily insert items later
3. **Group Organization**: Keep related resources in the same navigation group
4. **Icon Consistency**: Use consistent icon styles (all outline `o-` or all solid `s-`)
5. **Badges**: Use sparingly to highlight important counts or statuses

---

## Common Patterns

### Pattern 1: Master-Detail
```php
// Master: ProductResource
protected static ?string $navigationLabel = 'Products';

// Detail: ProductVariantResource
protected static ?string $navigationParentItem = 'Products';
```

### Pattern 2: Logs/History
```php
// Main: EventResource
protected static ?string $navigationLabel = 'Events';

// History: EventLogResource
protected static ?string $navigationParentItem = 'Events';
protected static ?string $navigationLabel = 'Event History';
```

### Pattern 3: Settings Submenu
```php
// Settings group with multiple submenus
protected static ?string $navigationGroup = 'Settings';
protected static ?string $navigationParentItem = 'System Configuration';
```
