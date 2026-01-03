# Multi-Language Support (i18n) Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Platform is currently English-only, limiting:
1. **Market reach**: Can't serve non-English speaking customers
2. **User experience**: Customers can't interact in their native language
3. **Organizer flexibility**: Events in non-English regions need localized content
4. **Romanian market**: eFactura integration suggests Romanian market focus

### What This Feature Does
- Support for 10+ languages including Romanian
- Per-tenant language configuration
- Customer language preference
- Database content translation (events, emails)
- RTL support for Arabic/Hebrew
- Dynamic language detection

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000040_create_translations_tables.php
Schema::create('translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained();
    $table->string('locale', 5);
    $table->string('group');
    $table->string('key');
    $table->text('value');
    $table->timestamps();

    $table->unique(['tenant_id', 'locale', 'group', 'key']);
    $table->index(['locale', 'group']);
});

Schema::create('event_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->string('locale', 5);
    $table->string('name');
    $table->text('description')->nullable();
    $table->text('short_description')->nullable();
    $table->string('meta_title')->nullable();
    $table->text('meta_description')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'locale']);
});

Schema::create('email_template_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('email_template_id')->constrained()->onDelete('cascade');
    $table->string('locale', 5);
    $table->string('subject');
    $table->text('content');
    $table->timestamps();

    $table->unique(['email_template_id', 'locale']);
});

// Add locale preferences
Schema::table('customers', function (Blueprint $table) {
    $table->string('preferred_locale', 5)->default('en');
});

Schema::table('tenants', function (Blueprint $table) {
    $table->string('default_locale', 5)->default('en');
    $table->json('supported_locales')->nullable();
});
```

### 2. Configuration

```php
// config/localization.php
return [
    'supported_locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false],
        'ro' => ['name' => 'Romanian', 'native' => 'Română', 'rtl' => false],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'rtl' => false],
        'fr' => ['name' => 'French', 'native' => 'Français', 'rtl' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'rtl' => false],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'rtl' => false],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'rtl' => false],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'rtl' => false],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'rtl' => false],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
    ],

    'fallback_locale' => 'en',

    'detection_order' => ['query', 'session', 'cookie', 'header', 'browser'],
];
```

### 3. Translatable Trait

```php
// app/Models/Traits/HasTranslations.php
<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    public function translations(): HasMany
    {
        $translationModel = $this->getTranslationModelClass();
        $foreignKey = $this->getTranslationForeignKey();

        return $this->hasMany($translationModel, $foreignKey);
    }

    public function translate(string $attribute, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();

        $translation = $this->translations
            ->where('locale', $locale)
            ->first();

        if ($translation && isset($translation->{$attribute})) {
            return $translation->{$attribute};
        }

        // Fallback to default locale
        $fallback = $this->translations
            ->where('locale', config('localization.fallback_locale'))
            ->first();

        if ($fallback && isset($fallback->{$attribute})) {
            return $fallback->{$attribute};
        }

        // Fallback to model attribute
        return $this->{$attribute} ?? null;
    }

    public function setTranslation(string $attribute, string $locale, string $value): void
    {
        $translationModel = $this->getTranslationModelClass();
        $foreignKey = $this->getTranslationForeignKey();

        $translationModel::updateOrCreate(
            [$foreignKey => $this->id, 'locale' => $locale],
            [$attribute => $value]
        );
    }

    public function getTranslatedAttributes(): array
    {
        return $this->translatedAttributes ?? [];
    }

    protected function getTranslationModelClass(): string
    {
        return $this->translationModel ?? get_class($this) . 'Translation';
    }

    protected function getTranslationForeignKey(): string
    {
        return $this->translationForeignKey ?? strtolower(class_basename($this)) . '_id';
    }

    // Accessor for translated name
    public function getTranslatedNameAttribute(): string
    {
        return $this->translate('name') ?? $this->name ?? '';
    }

    public function getTranslatedDescriptionAttribute(): string
    {
        return $this->translate('description') ?? $this->description ?? '';
    }
}
```

### 4. Event Model Update

```php
// app/Models/Event.php
use App\Models\Traits\HasTranslations;

class Event extends Model
{
    use HasTranslations;

    protected $translatedAttributes = ['name', 'description', 'short_description'];
    protected $translationModel = EventTranslation::class;

    // When accessing name, get translated version
    public function getNameAttribute($value)
    {
        if ($this->relationLoaded('translations')) {
            return $this->translate('name') ?? $value;
        }
        return $value;
    }
}
```

### 5. Translation Service

```php
// app/Services/Localization/TranslationService.php
<?php

namespace App\Services\Localization;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    public function get(string $key, ?string $locale = null, ?int $tenantId = null): ?string
    {
        $locale = $locale ?? app()->getLocale();

        $cacheKey = "translation:{$tenantId}:{$locale}:{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $locale, $tenantId) {
            [$group, $item] = explode('.', $key, 2);

            $translation = Translation::where('locale', $locale)
                ->where('group', $group)
                ->where('key', $item)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->first();

            return $translation?->value;
        });
    }

    public function set(string $key, string $value, string $locale, ?int $tenantId = null): void
    {
        [$group, $item] = explode('.', $key, 2);

        Translation::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'locale' => $locale,
                'group' => $group,
                'key' => $item,
            ],
            ['value' => $value]
        );

        Cache::forget("translation:{$tenantId}:{$locale}:{$key}");
    }

    public function getForGroup(string $group, string $locale, ?int $tenantId = null): array
    {
        return Translation::where('locale', $locale)
            ->where('group', $group)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->pluck('value', 'key')
            ->toArray();
    }

    public function getSupportedLocales(): array
    {
        return config('localization.supported_locales', []);
    }

    public function isRtl(string $locale): bool
    {
        return config("localization.supported_locales.{$locale}.rtl", false);
    }
}
```

### 6. Locale Detection Middleware

```php
// app/Http/Middleware/SetLocale.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->detectLocale($request);

        if ($this->isSupported($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    protected function detectLocale(Request $request): string
    {
        $order = config('localization.detection_order', []);

        foreach ($order as $method) {
            $locale = match ($method) {
                'query' => $request->query('lang'),
                'session' => session('locale'),
                'cookie' => $request->cookie('locale'),
                'header' => $request->header('Accept-Language'),
                'browser' => $request->getPreferredLanguage(array_keys(config('localization.supported_locales'))),
                'customer' => $request->user('customer')?->preferred_locale,
                default => null,
            };

            if ($locale && $this->isSupported($locale)) {
                return $locale;
            }
        }

        return config('localization.fallback_locale', 'en');
    }

    protected function isSupported(string $locale): bool
    {
        return array_key_exists($locale, config('localization.supported_locales', []));
    }
}
```

### 7. Controller

```php
// app/Http/Controllers/Api/TenantClient/LocaleController.php
class LocaleController extends Controller
{
    public function getLocales(): JsonResponse
    {
        $locales = config('localization.supported_locales');

        return response()->json([
            'locales' => collect($locales)->map(fn($l, $k) => [
                'code' => $k,
                'name' => $l['name'],
                'native' => $l['native'],
                'rtl' => $l['rtl'],
            ])->values(),
            'current' => app()->getLocale(),
        ]);
    }

    public function setLocale(Request $request): JsonResponse
    {
        $request->validate(['locale' => 'required|string|size:2']);

        $locale = $request->locale;

        if (!array_key_exists($locale, config('localization.supported_locales'))) {
            return response()->json(['error' => 'Unsupported locale'], 400);
        }

        // Update customer preference if authenticated
        if ($customer = $request->user('customer')) {
            $customer->update(['preferred_locale' => $locale]);
        }

        // Set session and cookie
        session(['locale' => $locale]);

        return response()->json(['locale' => $locale])
            ->cookie('locale', $locale, 60 * 24 * 365);
    }

    public function getTranslations(Request $request, string $group): JsonResponse
    {
        $locale = $request->query('locale', app()->getLocale());
        $tenantId = $request->attributes->get('tenant_id');

        $service = app(TranslationService::class);
        $translations = $service->getForGroup($group, $locale, $tenantId);

        return response()->json(['translations' => $translations]);
    }
}
```

### 8. Routes

```php
Route::prefix('tenant-client')->middleware(['tenant'])->group(function () {
    Route::get('/locales', [LocaleController::class, 'getLocales']);
    Route::post('/locale', [LocaleController::class, 'setLocale']);
    Route::get('/translations/{group}', [LocaleController::class, 'getTranslations']);
});
```

### 9. Language Files Structure

```
resources/lang/
├── en/
│   ├── events.php
│   ├── tickets.php
│   ├── checkout.php
│   ├── emails.php
│   └── common.php
├── ro/
│   ├── events.php
│   ├── tickets.php
│   ├── checkout.php
│   ├── emails.php
│   └── common.php
└── ... (other locales)
```

### 10. Blade Directive for RTL

```php
// AppServiceProvider.php
Blade::directive('rtl', function () {
    return "<?php if(config('localization.supported_locales.' . app()->getLocale() . '.rtl')): ?>";
});

Blade::directive('endrtl', function () {
    return '<?php endif; ?>';
});
```

---

## Testing Checklist

1. [ ] Locale detection works (query, header, cookie)
2. [ ] Customer preference is saved
3. [ ] Event translations are retrieved correctly
4. [ ] Fallback to default locale works
5. [ ] RTL languages display correctly
6. [ ] Email templates use correct locale
7. [ ] API returns translated content
8. [ ] Cache is cleared on translation update
9. [ ] Tenant-specific translations work
10. [ ] Language switcher works in frontend
