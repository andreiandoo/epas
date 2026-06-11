<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Domain;
use App\Services\ThemeService;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Livewire\Attributes\On;

class ThemeEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Theme Editor';
    protected static \UnitEnum|string|null $navigationGroup = 'Website';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.theme-editor';

    public const MICROSERVICE_SLUG = 'website-visual-editor';

    public ?array $data = [];
    public string $previewUrl = '';

    /**
     * Hide from navigation - ThemeEditor is for tenant websites, not marketplace
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Check if the current user can access this page
     */
    public static function canAccess(): bool
    {
        // ThemeEditor is not available for marketplace
        return false;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return;
        }

        // Check microservice access
        if (!$tenant->hasMicroservice(self::MICROSERVICE_SLUG)) {
            Notification::make()
                ->warning()
                ->title('Feature not available')
                ->body('Please purchase the Website Visual Editor to access this feature.')
                ->persistent()
                ->send();

            $this->redirect(route('filament.marketplace.pages.dashboard'));
            return;
        }

        $theme = ThemeService::getTheme($tenant);

        // Flatten theme for form
        $formData = $this->flattenTheme($theme);
        $this->form->fill($formData);

        // Get preview URL from first active domain
        $domain = Domain::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if ($domain) {
            $this->previewUrl = 'https://' . $domain->domain . '?preview=1';
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Tabs::make('Theme Settings')
                    ->tabs([
                        SC\Tabs\Tab::make('Colors')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                SC\Section::make('Brand Colors')
                                    ->description('Define your brand\'s primary and secondary colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('colors.primary')
                                            ->label('Primary Color')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.primaryDark')
                                            ->label('Primary Dark')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.secondary')
                                            ->label('Secondary Color')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.secondaryDark')
                                            ->label('Secondary Dark')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.accent')
                                            ->label('Accent Color')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),

                                SC\Section::make('Background Colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('colors.background')
                                            ->label('Main Background')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.backgroundAlt')
                                            ->label('Alternate Background')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.surface')
                                            ->label('Surface (Cards)')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),

                                SC\Section::make('Text Colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('colors.text')
                                            ->label('Main Text')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.textMuted')
                                            ->label('Muted Text')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.textOnPrimary')
                                            ->label('Text on Primary')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.border')
                                            ->label('Border Color')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(4),

                                SC\Section::make('Status Colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('colors.success')
                                            ->label('Success')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.warning')
                                            ->label('Warning')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\ColorPicker::make('colors.error')
                                            ->label('Error')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Typography')
                            ->icon('heroicon-o-language')
                            ->schema([
                                SC\Section::make('Font Families')
                                    ->schema([
                                        Forms\Components\Select::make('typography.fontFamily')
                                            ->label('Body Font')
                                            ->options(ThemeService::getFontOptions())
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('typography.fontFamilyHeading')
                                            ->label('Heading Font')
                                            ->options(ThemeService::getFontOptions())
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(2),

                                SC\Section::make('Base Typography')
                                    ->schema([
                                        Forms\Components\Select::make('typography.baseFontSize')
                                            ->label('Base Font Size')
                                            ->options([
                                                '14px' => '14px (Small)',
                                                '15px' => '15px',
                                                '16px' => '16px (Default)',
                                                '17px' => '17px',
                                                '18px' => '18px (Large)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('typography.lineHeight')
                                            ->label('Line Height')
                                            ->options([
                                                '1.4' => '1.4 (Compact)',
                                                '1.5' => '1.5',
                                                '1.6' => '1.6 (Default)',
                                                '1.7' => '1.7',
                                                '1.8' => '1.8 (Loose)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(2),

                                SC\Section::make('Heading Sizes')
                                    ->schema([
                                        SC\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('typography.headings.h1.size')
                                                ->label('H1 Size')
                                                ->placeholder('3rem'),

                                            Forms\Components\Select::make('typography.headings.h1.weight')
                                                ->label('H1 Weight')
                                                ->options([
                                                    '400' => 'Normal',
                                                    '500' => 'Medium',
                                                    '600' => 'Semibold',
                                                    '700' => 'Bold',
                                                    '800' => 'Extra Bold',
                                                ]),

                                            Forms\Components\TextInput::make('typography.headings.h1.lineHeight')
                                                ->label('H1 Line Height')
                                                ->placeholder('1.2'),
                                        ]),

                                        SC\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('typography.headings.h2.size')
                                                ->label('H2 Size')
                                                ->placeholder('2.25rem'),

                                            Forms\Components\Select::make('typography.headings.h2.weight')
                                                ->label('H2 Weight')
                                                ->options([
                                                    '400' => 'Normal',
                                                    '500' => 'Medium',
                                                    '600' => 'Semibold',
                                                    '700' => 'Bold',
                                                    '800' => 'Extra Bold',
                                                ]),

                                            Forms\Components\TextInput::make('typography.headings.h2.lineHeight')
                                                ->label('H2 Line Height')
                                                ->placeholder('1.3'),
                                        ]),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Layout')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                SC\Section::make('Spacing')
                                    ->schema([
                                        Forms\Components\Select::make('spacing.containerMaxWidth')
                                            ->label('Container Max Width')
                                            ->options([
                                                '1024px' => '1024px (Narrow)',
                                                '1152px' => '1152px',
                                                '1280px' => '1280px (Default)',
                                                '1440px' => '1440px (Wide)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('spacing.sectionPadding')
                                            ->label('Section Padding')
                                            ->options([
                                                '2rem' => '2rem (Compact)',
                                                '3rem' => '3rem',
                                                '4rem' => '4rem (Default)',
                                                '5rem' => '5rem',
                                                '6rem' => '6rem (Spacious)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('spacing.cardPadding')
                                            ->label('Card Padding')
                                            ->options([
                                                '1rem' => '1rem',
                                                '1.25rem' => '1.25rem',
                                                '1.5rem' => '1.5rem (Default)',
                                                '2rem' => '2rem',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),

                                SC\Section::make('Border Radius')
                                    ->schema([
                                        Forms\Components\Select::make('borders.radius')
                                            ->label('Default Radius')
                                            ->options([
                                                '0' => 'None',
                                                '0.25rem' => 'Small',
                                                '0.5rem' => 'Medium (Default)',
                                                '0.75rem' => 'Large',
                                                '1rem' => 'Extra Large',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('borders.radiusLarge')
                                            ->label('Large Radius (Cards)')
                                            ->options([
                                                '0.5rem' => 'Medium',
                                                '0.75rem' => 'Large',
                                                '1rem' => 'Extra Large (Default)',
                                                '1.5rem' => '2X Large',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('borders.radiusButton')
                                            ->label('Button Radius')
                                            ->options([
                                                '0' => 'None (Square)',
                                                '0.25rem' => 'Small',
                                                '0.5rem' => 'Medium (Default)',
                                                '9999px' => 'Full (Pill)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Header')
                            ->icon('heroicon-o-bars-3')
                            ->schema([
                                SC\Section::make('Header Style')
                                    ->schema([
                                        Forms\Components\Select::make('header.style')
                                            ->label('Header Style')
                                            ->options([
                                                'light' => 'Light Background',
                                                'dark' => 'Dark Background',
                                                'transparent' => 'Transparent',
                                                'primary' => 'Primary Color',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Toggle::make('header.sticky')
                                            ->label('Sticky Header')
                                            ->helperText('Keep header visible when scrolling')
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('header.height')
                                            ->label('Header Height')
                                            ->options([
                                                '64px' => 'Compact (64px)',
                                                '72px' => 'Default (72px)',
                                                '80px' => 'Large (80px)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Buttons')
                            ->icon('heroicon-o-cursor-arrow-rays')
                            ->schema([
                                SC\Section::make('Button Style')
                                    ->schema([
                                        Forms\Components\Select::make('buttons.paddingX')
                                            ->label('Horizontal Padding')
                                            ->options([
                                                '1rem' => 'Small (1rem)',
                                                '1.25rem' => 'Medium',
                                                '1.5rem' => 'Default (1.5rem)',
                                                '2rem' => 'Large (2rem)',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('buttons.paddingY')
                                            ->label('Vertical Padding')
                                            ->options([
                                                '0.5rem' => 'Small',
                                                '0.625rem' => 'Medium',
                                                '0.75rem' => 'Default',
                                                '1rem' => 'Large',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),

                                        Forms\Components\Select::make('buttons.fontWeight')
                                            ->label('Font Weight')
                                            ->options([
                                                '400' => 'Normal',
                                                '500' => 'Medium',
                                                '600' => 'Semibold (Default)',
                                                '700' => 'Bold',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(fn () => $this->dispatch('theme-changed', theme: $this->getThemeData())),
                                    ])->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return;
        }

        $formData = $this->form->getState();
        $theme = $this->unflattenTheme($formData);

        ThemeService::updateTheme($tenant, $theme);

        Notification::make()
            ->success()
            ->title('Theme saved')
            ->body('Your theme settings have been updated.')
            ->send();
    }

    public function resetToDefaults(): void
    {
        $defaults = ThemeService::getDefaultTheme();
        $formData = $this->flattenTheme($defaults);
        $this->form->fill($formData);

        $this->dispatch('theme-changed', theme: $defaults);

        Notification::make()
            ->info()
            ->title('Theme reset')
            ->body('Theme has been reset to defaults. Click Save to apply.')
            ->send();
    }

    public function getThemeData(): array
    {
        $formData = $this->form->getState();
        return $this->unflattenTheme($formData);
    }

    private function flattenTheme(array $theme): array
    {
        $flat = [];

        foreach ($theme as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            $flat["{$section}.{$key}.{$subKey}"] = $subValue;
                        }
                    } else {
                        $flat["{$section}.{$key}"] = $value;
                    }
                }
            } else {
                $flat[$section] = $values;
            }
        }

        return $flat;
    }

    private function unflattenTheme(array $flat): array
    {
        $theme = [];

        foreach ($flat as $key => $value) {
            $parts = explode('.', $key);

            if (count($parts) === 2) {
                $theme[$parts[0]][$parts[1]] = $value;
            } elseif (count($parts) === 3) {
                $theme[$parts[0]][$parts[1]][$parts[2]] = $value;
            }
        }

        return $theme;
    }

    public function getTitle(): string
    {
        return 'Theme Editor';
    }
}
