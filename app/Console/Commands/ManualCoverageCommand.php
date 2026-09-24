<?php

namespace App\Console\Commands;

use App\Support\Manual\PageManual;
use Illuminate\Console\Command;

/**
 * Lists the form fields a page manual does not explain yet (and the fields the
 * manual still documents although the form no longer has them), so the manual
 * can't silently fall behind the Filament form.
 */
class ManualCoverageCommand extends Command
{
    protected $signature = 'manual:coverage
        {page=event-edit : Cheia paginii din PageManual::PAGES}
        {--all : Listează și câmpurile deja documentate}';

    protected $description = 'Compară câmpurile formularului cu cele explicate în manualul paginii (resources/manual)';

    private const FIELD_PATTERN = '/\b(TextInput|Toggle|Select|DatePicker|DateTimePicker|TimePicker|Textarea|RichEditor|MarkdownEditor|FileUpload|Repeater|CheckboxList|Radio|ColorPicker|TagsInput|KeyValue|Checkbox|ToggleButtons)::make\(\'([^\']+)\'/';

    private const GROUP_PATTERN = '/\b(Tabs\\\\Tab|Section|Fieldset)::make\(/';

    public function handle(): int
    {
        $page = (string) $this->argument('page');

        if (! PageManual::exists($page)) {
            $this->error("Pagina [{$page}] nu are manual. Disponibile: " . implode(', ', array_keys(PageManual::PAGES)));

            return self::FAILURE;
        }

        $config = PageManual::config($page);
        $manual = PageManual::load($page);

        // "grup|câmp" explained by the chapters; a chapter without `covers` counts for any group.
        $documented = [];
        $documentedNames = [];
        foreach ($manual['chapters'] as $chapter) {
            $groups = $chapter['covers'] === [] ? ['*'] : array_map([PageManual::class, 'groupKey'], $chapter['covers']);
            foreach ($chapter['fields'] as $field) {
                $documentedNames[$field] = true;
                foreach ($groups as $group) {
                    $documented[$group . '|' . $field] = true;
                }
            }
        }

        $formFields = $this->extractFormFields($config['form_sources']);
        $byGroup = [];
        $formNames = [];
        $done = 0;

        foreach ($formFields as $field) {
            $formNames[$field['field']] = true;
            $ok = isset($documented[PageManual::groupKey($field['group']) . '|' . $field['field']])
                || isset($documented['*|' . $field['field']]);
            $done += $ok ? 1 : 0;
            $byGroup[$field['group']][] = $field + ['ok' => $ok];
        }

        $this->table(
            ['Secțiune / tab', 'Documentate', 'Total'],
            array_map(
                fn (string $group, array $fields) => [$group, count(array_filter($fields, fn ($f) => $f['ok'])), count($fields)],
                array_keys($byGroup),
                $byGroup
            )
        );

        foreach ($byGroup as $group => $fields) {
            $list = $this->option('all') ? $fields : array_filter($fields, fn ($f) => ! $f['ok']);
            if ($list === []) {
                continue;
            }

            $this->newLine();
            $this->line("<comment>{$group}</comment>");
            foreach ($list as $field) {
                $mark = $field['ok'] ? '<info>OK</info>' : '<fg=red>lipsă</>';
                $this->line("  {$mark}  {$field['field']} ({$field['type']}, linia {$field['line']})");
            }
        }

        $stale = array_keys(array_diff_key($documentedNames, $formNames));
        if ($stale !== []) {
            $this->newLine();
            $this->warn('Documentate în manual, dar negăsite în formular: ' . implode(', ', $stale));
        }

        $total = count($formFields);
        $this->newLine();
        $this->info(sprintf(
            'Acoperire: %d din %d câmpuri (%d%%).',
            $done,
            $total,
            $total > 0 ? (int) round($done * 100 / $total) : 100
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<array{group: string, field: string, type: string, line: int}>
     */
    private function extractFormFields(array $sources): array
    {
        $fields = [];

        foreach ($sources as $source) {
            $lines = @file(base_path($source['path'])) ?: [];
            $inside = empty($source['from']);
            $group = '(fără secțiune)';
            $seen = [];

            foreach ($lines as $index => $line) {
                if (! $inside) {
                    $inside = str_contains($line, $source['from']);

                    continue;
                }

                if (! empty($source['to']) && str_contains($line, $source['to'])) {
                    break;
                }

                if (preg_match(self::GROUP_PATTERN, $line)
                    && (preg_match('/\$t\(\'([^\']+)\'/', $line, $label) || preg_match('/::make\(\'([^\']+)\'/', $line, $label))) {
                    $group = $label[1];
                }

                if (! preg_match_all(self::FIELD_PATTERN, $line, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $key = $group . '|' . $match[2];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $fields[] = ['group' => $group, 'field' => $match[2], 'type' => $match[1], 'line' => $index + 1];
                }
            }
        }

        return $fields;
    }
}
