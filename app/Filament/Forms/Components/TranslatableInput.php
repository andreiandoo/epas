<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;

class TranslatableInput extends Component
{
    protected string $view = 'filament.forms.components.translatable-input';

    protected array $locales = ['en', 'ro'];

    protected array $localeLabels = [
        'en' => 'English',
        'ro' => 'Romanian',
    ];

    protected string $inputType = 'text'; // text, textarea, richEditor

    protected ?int $rows = null;

    protected bool $isRequired = false;

    protected ?int $maxLength = null;

    protected ?string $placeholder = null;

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);

        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function ($state) {
            if (is_array($state)) {
                // Filter out empty values but keep the structure
                return array_filter($state, fn ($value) => $value !== null && $value !== '');
            }
            return $state;
        });

        $this->afterStateHydrated(function (Component $component, $state) {
            if (is_string($state)) {
                // If it's a plain string, treat it as English
                $component->state(['en' => $state, 'ro' => '']);
            } elseif (is_array($state)) {
                // Ensure both locales exist
                $component->state([
                    'en' => $state['en'] ?? '',
                    'ro' => $state['ro'] ?? '',
                ]);
            } else {
                $component->state(['en' => '', 'ro' => '']);
            }
        });
    }

    public function textarea(int $rows = 3): static
    {
        $this->inputType = 'textarea';
        $this->rows = $rows;

        return $this;
    }

    public function richEditor(): static
    {
        $this->inputType = 'richEditor';

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->isRequired = $required;

        return $this;
    }

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getLocales(): array
    {
        return $this->locales;
    }

    public function getLocaleLabels(): array
    {
        return $this->localeLabels;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function getIsRequired(): bool
    {
        return $this->isRequired;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getChildComponents(): array
    {
        $tabs = [];

        foreach ($this->locales as $locale) {
            $input = match ($this->inputType) {
                'textarea' => Textarea::make($locale)
                    ->label($this->localeLabels[$locale])
                    ->rows($this->rows ?? 3)
                    ->required($this->isRequired && $locale === 'en')
                    ->maxLength($this->maxLength)
                    ->placeholder($this->placeholder),

                'richEditor' => RichEditor::make($locale)
                    ->label($this->localeLabels[$locale])
                    ->required($this->isRequired && $locale === 'en'),

                default => TextInput::make($locale)
                    ->label($this->localeLabels[$locale])
                    ->required($this->isRequired && $locale === 'en')
                    ->maxLength($this->maxLength)
                    ->placeholder($this->placeholder),
            };

            $tabs[] = Tab::make($this->localeLabels[$locale])
                ->icon($locale === 'en' ? 'heroicon-o-globe-alt' : 'heroicon-o-flag')
                ->schema([$input]);
        }

        return [
            Tabs::make('translations')
                ->tabs($tabs)
                ->contained(false),
        ];
    }
}
