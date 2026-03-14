<?php

namespace App\Filament\Resources\Events\Widgets;

use Filament\Widgets\Widget;

class EventFormAnchorMenu extends Widget
{
    protected string $view = 'filament.events.widgets.event-form-anchor-menu';

    // full width, sits above the form
    protected int|string|array $columnSpan = 'full';
}
