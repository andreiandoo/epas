<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\NewsletterResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceContactTag;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceEvent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class NewsletterResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceNewsletter::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Newsletters';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->components([
                // Top-level 4-column grid: 3 cols main content, 1 col sidebar.
                // Stacks to a single column on mobile via Grid's responsive
                // defaults.
                SC\Grid::make(['default' => 1, 'lg' => 4])
                    ->schema([
                        // ============ MAIN COLUMN (span 3) ============
                        SC\Group::make(static::mainColumnSchema($marketplace))
                            ->columnSpan(['default' => 1, 'lg' => 3]),

                        // ============ SIDEBAR (span 1) ============
                        SC\Group::make(static::sidebarSchema($marketplace))
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
            ])->columns(1);
    }

    /**
     * Main column: Campaign Details, Recipients, Email Content, Scheduling.
     * Extracted so the layout grid stays readable.
     */
    protected static function mainColumnSchema($marketplace): array
    {
        return [
            SC\Section::make('Campaign Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Campaign Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Internal name for this campaign'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'scheduled' => 'Scheduled',
                            'sending' => 'Sending',
                            'sent' => 'Sent',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->disabled(fn ($record) => in_array($record?->status, ['sending', 'sent'])),
                ])->columns(2),

            SC\Section::make('Recipients')
                ->description('Send to contact lists, tag-filtered customers, or ticket buyers of specific events. Recipients are dedup-ed by email across all sources.')
                ->schema([
                    Forms\Components\Select::make('target_event_ids')
                        ->label('Evenimente — către cumpărătorii biletelor')
                        ->multiple()
                        ->searchable()
                        ->preload(false)
                        ->live(onBlur: true)
                        ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                            return Event::where('marketplace_client_id', $marketplace?->id)
                                ->where(function ($q) use ($search) {
                                    $q->where('title->ro', 'ilike', "%{$search}%")
                                        ->orWhere('title->en', 'ilike', "%{$search}%")
                                        ->orWhere('slug', 'ilike', "%{$search}%");
                                })
                                ->orderByDesc('event_date')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn ($e) => [
                                    $e->id => static::formatEventOption($e),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelsUsing(function (array $values) {
                            return Event::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn ($e) => [
                                    $e->id => static::formatEventOption($e),
                                ])
                                ->toArray();
                        })
                        ->helperText('Newsletter va ajunge la toți cumpărătorii cu bilete valide pe evenimentele selectate.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('target_lists')
                        ->label('Contact Lists')
                        ->multiple()
                        ->live(onBlur: true)
                        ->options(function () use ($marketplace) {
                            return MarketplaceContactList::where('marketplace_client_id', $marketplace?->id)
                                ->where('is_active', true)
                                ->pluck('name', 'id');
                        })
                        ->helperText('Trimite către listele de contacte selectate'),
                    Forms\Components\Select::make('target_tags')
                        ->label('Contact Tags')
                        ->multiple()
                        ->live(onBlur: true)
                        ->options(function () use ($marketplace) {
                            return MarketplaceContactTag::where('marketplace_client_id', $marketplace?->id)
                                ->pluck('name', 'id');
                        })
                        ->helperText('Filtrează contactele după tag-uri'),
                ])->columns(2),

            SC\Section::make('Email Content')
                    ->schema([
                        // Optional starting point: pick an existing email
                        // template (Communications → Email Templates). The
                        // afterStateUpdated hook copies its subject/body into
                        // the form fields so the organizer can tweak before
                        // sending. We do NOT keep a live link — once forked,
                        // edits to the source template don't reflect here.
                        Forms\Components\Select::make('source_email_template_id')
                            ->label('Pornește de la un template (opțional)')
                            ->options(function () use ($marketplace) {
                                return MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace?->id)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->placeholder('— niciun template —')
                            ->helperText('Aplică conținutul unui template existent. Poți edita liber după.')
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) use ($marketplace) {
                                if (!$state) return;
                                $tpl = MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace?->id)
                                    ->where('id', $state)
                                    ->first();
                                if (!$tpl) return;
                                if (empty($get('subject'))) $set('subject', $tpl->subject);
                                // Push the template body as a single HTML
                                // section if no sections exist yet — keeps
                                // pre-existing drafts intact.
                                $existing = $get('body_sections') ?? [];
                                if (empty($existing) && !empty($tpl->body_html)) {
                                    $set('body_sections', [[
                                        'type' => 'html',
                                        'html_content' => $tpl->body_html,
                                    ]]);
                                }
                                if (empty($get('body_text')) && !empty($tpl->body_text)) {
                                    $set('body_text', $tpl->body_text);
                                }
                            }),

                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('preview_text')
                            ->maxLength(255)
                            ->helperText('Preview text shown in email client (optional)')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('body_sections')
                            ->label('Secțiuni Email')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tip secțiune')
                                    ->options([
                                        'text' => 'Text / Rich Content',
                                        'html' => 'HTML personalizat',
                                        'recommended_events' => 'Evenimente recomandate',
                                        'hand_picked_events' => 'Evenimente alese',
                                        'events_next_week' => 'Evenimente săptămâna viitoare',
                                        'events_next_month' => 'Evenimente luna viitoare',
                                        'button' => 'Buton CTA',
                                        'spacer' => 'Spațiu / Separator',
                                        'image' => 'Imagine',
                                    ])
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),

                                // Text section
                                Forms\Components\RichEditor::make('content')
                                    ->label('Conținut')
                                    ->visible(fn ($get) => $get('type') === 'text')
                                    ->columnSpanFull()
                                    ->helperText('Suportă variabile: {{customer_name}}, {{customer_email}}, {{event:ID:name}}, etc.'),

                                // HTML section
                                Forms\Components\Textarea::make('html_content')
                                    ->label('Cod HTML')
                                    ->visible(fn ($get) => $get('type') === 'html')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->helperText('HTML personalizat. Poți importa template-uri externe sau scrie cod HTML direct.'),

                                // Hand-picked events
                                Forms\Components\Select::make('event_ids')
                                    ->label('Selectează evenimente')
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                                        return MarketplaceEvent::where('marketplace_client_id', $marketplace?->id)
                                            ->where('status', 'approved')
                                            ->where('is_public', true)
                                            ->where('name', 'like', "%{$search}%")
                                            ->limit(20)
                                            ->pluck('name', 'id');
                                    })
                                    ->getOptionLabelsUsing(function (array $values) {
                                        return MarketplaceEvent::whereIn('id', $values)->pluck('name', 'id');
                                    })
                                    ->visible(fn ($get) => $get('type') === 'hand_picked_events')
                                    ->columnSpanFull()
                                    ->helperText('Caută și selectează evenimentele pe care vrei să le incluzi'),

                                // Event limit (for auto-populated sections)
                                Forms\Components\TextInput::make('limit')
                                    ->label('Număr maxim de evenimente')
                                    ->numeric()
                                    ->default(4)
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->visible(fn ($get) => in_array($get('type'), ['recommended_events', 'events_next_week', 'events_next_month']))
                                    ->maxWidth('xs'),

                                // Button fields
                                Forms\Components\TextInput::make('button_text')
                                    ->label('Text buton')
                                    ->default('Click aici')
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\TextInput::make('button_url')
                                    ->label('URL buton')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\ColorPicker::make('button_color')
                                    ->label('Culoare')
                                    ->default('#A51C30')
                                    ->visible(fn ($get) => $get('type') === 'button'),

                                // Image fields
                                Forms\Components\TextInput::make('image_url')
                                    ->label('URL imagine')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('image_link')
                                    ->label('Link la click (opțional)')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Text alternativ')
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),

                                // Spacer height
                                Forms\Components\TextInput::make('height')
                                    ->label('Înălțime (px)')
                                    ->numeric()
                                    ->default(20)
                                    ->visible(fn ($get) => $get('type') === 'spacer')
                                    ->maxWidth('xs'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                'text' => 'Text / Rich Content',
                                'html' => 'HTML personalizat',
                                'recommended_events' => 'Evenimente recomandate',
                                'hand_picked_events' => 'Evenimente alese (' . count($state['event_ids'] ?? []) . ')',
                                'events_next_week' => 'Evenimente săptămâna viitoare',
                                'events_next_month' => 'Evenimente luna viitoare',
                                'button' => 'Buton: ' . ($state['button_text'] ?? 'CTA'),
                                'spacer' => 'Spațiu / Separator',
                                'image' => 'Imagine',
                                default => 'Secțiune nouă',
                            })
                            ->defaultItems(0)
                            ->addActionLabel('Adaugă secțiune')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body_text')
                            ->label('Plain Text Version')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Versiune text simplu (opțional). Se generează automat dacă lipsește.'),
                    ]),

            SC\Section::make('Scheduling')
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Send At')
                        ->helperText('Leave empty to send immediately when you click "Send Newsletter"')
                        ->minDate(now()),
                ])
                ->visible(fn ($record) => !in_array($record?->status, ['sending', 'sent'])),
        ];
    }

    /**
     * Sidebar column: Sender Information, Recipient stats, Variabile, post-send Statistics.
     */
    protected static function sidebarSchema($marketplace): array
    {
        return [
            SC\Section::make('Sender Information')
                ->schema([
                    Forms\Components\TextInput::make('from_name')
                        ->label('From Name')
                        ->default(fn () => $marketplace?->name)
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('from_email')
                        ->label('From Email')
                        ->email()
                        ->default(fn () => $marketplace?->contact_email)
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('reply_to')
                        ->label('Reply-To Email')
                        ->email()
                        ->maxLength(255),
                ]),

            SC\Section::make('Statistici email')
                ->schema([
                    Forms\Components\Placeholder::make('recipient_count')
                        ->label('Destinatari unici')
                        ->content(function (SGet $get) use ($marketplace) {
                            // Build a transient instance and use the same
                            // logic the real send-time recipient build uses,
                            // so the count here matches what'll actually be
                            // mailed.
                            $instance = new MarketplaceNewsletter();
                            $instance->marketplace_client_id = $marketplace?->id;
                            $instance->target_lists = $get('target_lists') ?? [];
                            $instance->target_tags = $get('target_tags') ?? [];
                            $instance->target_event_ids = $get('target_event_ids') ?? [];

                            $hasTargeting = !empty($instance->target_lists)
                                || !empty($instance->target_tags)
                                || !empty($instance->target_event_ids);
                            if (!$hasTargeting) {
                                return new HtmlString('<span class="text-gray-500">Selectează evenimente / liste / tag-uri</span>');
                            }

                            try {
                                $b = $instance->getRecipientBreakdown();
                            } catch (\Throwable $e) {
                                return new HtmlString('<span class="text-red-600">Eroare la calculul destinatarilor: ' . e($e->getMessage()) . '</span>');
                            }

                            $html = '<div class="space-y-1.5">';
                            $html .= '<div class="text-2xl font-bold text-primary-600">' . number_format($b['total']) . '</div>';
                            $html .= '<div class="text-xs text-gray-500 dark:text-gray-400">Email-uri unice (după dedup)</div>';

                            // Per-source breakdown helps explain why the
                            // total may differ from the user's intuition —
                            // e.g. a contact list may contain more customer
                            // accounts than the "type" suggests, or sources
                            // may overlap.
                            $rows = [];
                            if (($b['lists'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Din liste</span><span class="font-semibold">' . number_format($b['lists']) . '</span></div>';
                            }
                            if (($b['organizers'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Organizatori</span><span class="font-semibold">' . number_format($b['organizers']) . '</span></div>';
                            }
                            if (($b['tags'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Din tag-uri</span><span class="font-semibold">' . number_format($b['tags']) . '</span></div>';
                            }
                            if (($b['events'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Cumpărători evenimente</span><span class="font-semibold">' . number_format($b['events']) . '</span></div>';
                            }
                            if (!empty($rows)) {
                                $html .= '<div class="border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5 space-y-1 text-xs">' . implode('', $rows) . '</div>';
                            }
                            $html .= '</div>';

                            return new HtmlString($html);
                        }),
                    Forms\Components\Placeholder::make('targeted_events_summary')
                        ->label('Evenimente țintă')
                        ->content(function (SGet $get) {
                            $ids = $get('target_event_ids') ?? [];
                            if (empty($ids)) return '—';
                            $names = Event::whereIn('id', $ids)
                                ->limit(5)
                                ->get()
                                ->map(fn ($e) => static::formatEventOption($e))
                                ->implode("\n");
                            return new HtmlString('<div class="space-y-1 text-xs text-gray-700 dark:text-gray-300">' . nl2br(e($names)) . '</div>');
                        })
                        ->visible(fn (SGet $get) => !empty($get('target_event_ids'))),
                    Forms\Components\Placeholder::make('targeted_lists_summary')
                        ->label('Liste țintă')
                        ->content(function (SGet $get) use ($marketplace) {
                            $ids = $get('target_lists') ?? [];
                            if (empty($ids)) return '—';
                            $names = MarketplaceContactList::whereIn('id', $ids)
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->pluck('name')
                                ->implode(', ');
                            return $names ?: '—';
                        })
                        ->visible(fn (SGet $get) => !empty($get('target_lists'))),
                ]),

            SC\Section::make('Variabile disponibile')
                ->schema([
                    Forms\Components\Placeholder::make('variables_info')
                        ->label('')
                        ->content(new HtmlString(
                            '<div class="space-y-3 text-xs">' .
                            '<div>' .
                                '<p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Per destinatar:</p>' .
                                '<div class="flex flex-wrap gap-1">' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{customer_name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{customer_email}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{marketplace_name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{unsubscribe_url}}</code>' .
                                '</div>' .
                            '</div>' .
                            '<div>' .
                                '<p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Eveniment (înlocuiește ID):</p>' .
                                '<div class="flex flex-wrap gap-1">' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:date}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:venue}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:image}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:url}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:price}}</code>' .
                                '</div>' .
                            '</div>' .
                            '</div>'
                        )),
                ])
                ->collapsed(),

            // Trimitere — visible on every state, including draft, so the
            // organizer always sees the campaign's send status in the same
            // place. For an unsent draft the placeholder explains there's
            // nothing to show yet.
            SC\Section::make('Statistici trimitere')
                ->schema([
                    Forms\Components\Placeholder::make('send_stats')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) {
                                return new HtmlString('<p class="text-xs text-gray-500">Salvează newsletter-ul ca să vezi statisticile.</p>');
                            }
                            if ($record->status === 'draft') {
                                return new HtmlString('<p class="text-xs text-gray-500">Newsletter-ul nu a fost trimis încă. După trimitere apar aici: numărul de destinatari, câți au deschis emailul (open rate) și câți au făcut click (click rate).</p>');
                            }

                            $sent = (int) $record->sent_count;
                            $total = (int) $record->total_recipients;
                            $opened = (int) $record->opened_count;
                            $clicked = (int) $record->clicked_count;
                            $openRate = $record->open_rate;
                            $clickRate = $record->click_rate;

                            // Two prominent KPI tiles (Trimis + Open rate),
                            // followed by a compact details list.
                            $html = '<div class="space-y-3">';
                            $html .= '<div class="grid grid-cols-2 gap-2">';
                            $html .= '<div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-2 text-center">'
                                . '<div class="text-xs text-gray-600 dark:text-gray-400">Trimis</div>'
                                . '<div class="text-xl font-bold text-green-700 dark:text-green-400">' . number_format($sent) . '</div>'
                                . '<div class="text-[10px] text-gray-500">din ' . number_format($total) . '</div>'
                                . '</div>';
                            $html .= '<div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-2 text-center">'
                                . '<div class="text-xs text-gray-600 dark:text-gray-400">Open rate</div>'
                                . '<div class="text-xl font-bold text-blue-700 dark:text-blue-400">' . $openRate . '%</div>'
                                . '<div class="text-[10px] text-gray-500">' . number_format($opened) . ' au deschis</div>'
                                . '</div>';
                            $html .= '</div>';

                            $html .= '<div class="space-y-1.5 text-xs">';
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Total destinatari</span><span class="font-semibold">' . number_format($total) . '</span></div>';
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Trimise</span><span class="font-semibold">' . number_format($sent) . '</span></div>';
                            if ((int) $record->failed_count > 0) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Eșuate</span><span class="font-semibold text-red-600">' . number_format((int) $record->failed_count) . '</span></div>';
                            }
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Deschise</span><span class="font-semibold">' . number_format($opened) . ' (' . $openRate . '%)</span></div>';
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Click-uri</span><span class="font-semibold">' . number_format($clicked) . ' (' . $clickRate . '%)</span></div>';
                            if ((int) $record->unsubscribed_count > 0) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Dezabonări</span><span class="font-semibold text-amber-600">' . number_format((int) $record->unsubscribed_count) . '</span></div>';
                            }
                            $html .= '</div>';

                            // Timeline strip: started/completed timestamps so
                            // the organizer can correlate the rates with when
                            // the campaign actually ran.
                            $timeline = [];
                            if ($record->scheduled_at) {
                                $timeline[] = 'Programat: ' . $record->scheduled_at->translatedFormat('d M Y, H:i');
                            }
                            if ($record->started_at) {
                                $timeline[] = 'Început: ' . $record->started_at->translatedFormat('d M Y, H:i');
                            }
                            if ($record->completed_at) {
                                $timeline[] = 'Finalizat: ' . $record->completed_at->translatedFormat('d M Y, H:i');
                            }
                            if (!empty($timeline)) {
                                $html .= '<div class="border-t border-gray-200 dark:border-gray-700 pt-2 space-y-0.5 text-[11px] text-gray-500">';
                                foreach ($timeline as $line) {
                                    $html .= '<div>' . e($line) . '</div>';
                                }
                                $html .= '</div>';
                            }

                            $html .= '</div>';
                            return new HtmlString($html);
                        }),
                ]),
        ];
    }

    /**
     * Render an event option label like "Title — City, 24 Apr 2026".
     */
    protected static function formatEventOption(Event $event): string
    {
        $title = $event->getTranslation('title', 'ro')
            ?? $event->getTranslation('title', 'en')
            ?? (is_array($event->title) ? (reset($event->title) ?: '') : ($event->title ?? ''));
        $parts = [];
        $city = $event->city ?? $event->venue?->city ?? null;
        if ($city) $parts[] = $city;
        if ($event->event_date) $parts[] = \Carbon\Carbon::parse($event->event_date)->translatedFormat('d M Y');
        elseif ($event->range_start_date) $parts[] = \Carbon\Carbon::parse($event->range_start_date)->translatedFormat('d M Y');
        $suffix = !empty($parts) ? ' — ' . implode(', ', $parts) : '';
        return ($title ?: 'Eveniment #' . $event->id) . $suffix;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'sending' => 'info',
                        'sent' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('Recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('open_rate')
                    ->label('Open Rate')
                    ->suffix('%')
                    ->visible(fn () => true),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Newsletter')
                    ->modalDescription('Are you sure you want to send this newsletter? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->createRecipients();
                        $record->startSending();
                        \App\Jobs\SendNewsletterJob::dispatch($record);
                    }),
                Action::make('schedule')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Send At')
                            ->required()
                            ->minDate(now()),
                    ])
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record, array $data) {
                        $record->createRecipients();
                        $record->schedule(new \DateTime($data['scheduled_at']));
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'scheduled')
                    ->action(fn ($record) => $record->cancel()),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->status = 'draft';
                        $new->scheduled_at = null;
                        $new->started_at = null;
                        $new->completed_at = null;
                        $new->total_recipients = 0;
                        $new->sent_count = 0;
                        $new->failed_count = 0;
                        $new->opened_count = 0;
                        $new->clicked_count = 0;
                        $new->unsubscribed_count = 0;
                        $new->save();

                        return redirect(static::getUrl('edit', ['record' => $new]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records) => $records->every(fn ($r) => $r->status === 'draft')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
        ];
    }
}
