<?php

namespace App\Services\WebTemplate;

use App\Models\WebTemplate;

class TemplateHealthCheck
{
    /**
     * Run health checks on a template and return issues found.
     *
     * @return array{score: int, issues: array<array{level: string, field: string, message: string}>}
     */
    public function check(WebTemplate $template): array
    {
        $issues = [];
        $maxScore = 0;
        $score = 0;

        // === Required fields ===
        $requiredChecks = [
            ['field' => 'name', 'value' => $template->name, 'message' => 'Numele template-ului lipsește'],
            ['field' => 'slug', 'value' => $template->slug, 'message' => 'Slug-ul lipsește'],
            ['field' => 'category', 'value' => $template->category, 'message' => 'Categoria nu este setată'],
            ['field' => 'description', 'value' => $template->description, 'message' => 'Descrierea lipsește'],
        ];

        foreach ($requiredChecks as $check) {
            $maxScore += 10;
            if (empty($check['value'])) {
                $issues[] = ['level' => 'error', 'field' => $check['field'], 'message' => $check['message']];
            } else {
                $score += 10;
            }
        }

        // === Demo Data checks ===
        $demoData = $template->default_demo_data ?? [];

        // Site section
        $maxScore += 10;
        if (empty($demoData['site'])) {
            $issues[] = ['level' => 'error', 'field' => 'default_demo_data.site', 'message' => 'Secțiunea "site" din demo data lipsește complet'];
        } else {
            $score += 5;
            $siteFields = ['name', 'tagline', 'email'];
            foreach ($siteFields as $f) {
                if (empty($demoData['site'][$f])) {
                    $issues[] = ['level' => 'warning', 'field' => "default_demo_data.site.{$f}", 'message' => "Câmpul site.{$f} nu este setat"];
                } else {
                    $score += (int) round(5 / count($siteFields));
                }
            }
        }

        // Hero section
        $maxScore += 10;
        if (empty($demoData['hero'])) {
            $issues[] = ['level' => 'warning', 'field' => 'default_demo_data.hero', 'message' => 'Secțiunea "hero" lipsește — pagina nu va avea o secțiune principală'];
        } else {
            $score += 5;
            if (empty($demoData['hero']['title'])) {
                $issues[] = ['level' => 'warning', 'field' => 'default_demo_data.hero.title', 'message' => 'Titlul hero lipsește'];
            } else {
                $score += 5;
            }
        }

        // Events / Content section
        $maxScore += 15;
        $eventKeys = ['events', 'featured_events', 'repertoire', 'upcoming_events'];
        $hasEvents = false;
        foreach ($eventKeys as $key) {
            if (!empty($demoData[$key]) && is_array($demoData[$key])) {
                $hasEvents = true;
                $score += 5;

                // Check event quality
                $eventsWithDates = 0;
                $eventsWithPrices = 0;
                $eventsWithTicketTypes = 0;
                foreach ($demoData[$key] as $event) {
                    if (!empty($event['date']) || !empty($event['next_show'])) $eventsWithDates++;
                    if (isset($event['price_from'])) $eventsWithPrices++;
                    if (!empty($event['ticket_types'])) $eventsWithTicketTypes++;
                }

                $count = count($demoData[$key]);
                if ($eventsWithDates < $count) {
                    $issues[] = ['level' => 'warning', 'field' => "default_demo_data.{$key}", 'message' => ($count - $eventsWithDates) . " evenimente fără dată — DemoDataTransformer nu le poate procesa"];
                } else {
                    $score += 5;
                }
                if ($eventsWithPrices < $count) {
                    $issues[] = ['level' => 'info', 'field' => "default_demo_data.{$key}", 'message' => ($count - $eventsWithPrices) . " evenimente fără preț — cardul nu va afișa informații de preț"];
                } else {
                    $score += 5;
                }

                break; // Only check first found event array
            }
        }
        if (!$hasEvents) {
            $issues[] = ['level' => 'error', 'field' => 'default_demo_data.events', 'message' => 'Nicio secțiune de evenimente găsită (events/repertoire/upcoming_events)'];
        }

        // === Color scheme ===
        $maxScore += 10;
        if (empty($template->color_scheme)) {
            $issues[] = ['level' => 'warning', 'field' => 'color_scheme', 'message' => 'Schema de culori nu este definită — se vor folosi culorile default'];
        } else {
            $score += 5;
            $expectedColors = ['primary', 'secondary'];
            foreach ($expectedColors as $color) {
                if (empty($template->color_scheme[$color])) {
                    $issues[] = ['level' => 'info', 'field' => "color_scheme.{$color}", 'message' => "Culoarea „{$color}" nu este definită"];
                } else {
                    $score += (int) round(5 / count($expectedColors));
                }
            }
        }

        // === Customizable fields ===
        $maxScore += 10;
        if (empty($template->customizable_fields)) {
            $issues[] = ['level' => 'info', 'field' => 'customizable_fields', 'message' => 'Niciun câmp personalizabil definit — wizard-ul nu va genera nimic'];
        } else {
            $score += 5;
            foreach ($template->customizable_fields as $i => $field) {
                if (empty($field['key']) || empty($field['type'])) {
                    $issues[] = ['level' => 'warning', 'field' => "customizable_fields[{$i}]", 'message' => "Câmpul #{$i} nu are cheie sau tip definit"];
                } else {
                    $score += min(5, (int) round(5 / count($template->customizable_fields)));
                }
            }
        }

        // === Tech stack ===
        $maxScore += 5;
        if (empty($template->tech_stack)) {
            $issues[] = ['level' => 'info', 'field' => 'tech_stack', 'message' => 'Tech stack-ul nu este definit'];
        } else {
            $score += 5;
        }

        // === Images ===
        $maxScore += 10;
        if (empty($template->thumbnail)) {
            $issues[] = ['level' => 'info', 'field' => 'thumbnail', 'message' => 'Nu există thumbnail — galeria va afișa un gradient generic'];
        } else {
            $score += 5;
        }
        if (empty($template->preview_image)) {
            $issues[] = ['level' => 'info', 'field' => 'preview_image', 'message' => 'Nu există preview image — OG tags vor folosi un avatar generat'];
        } else {
            $score += 5;
        }

        $percentage = $maxScore > 0 ? (int) round($score / $maxScore * 100) : 0;

        return [
            'score' => $percentage,
            'max_score' => $maxScore,
            'raw_score' => $score,
            'issues' => $issues,
            'summary' => $this->getSummary($percentage, count($issues)),
        ];
    }

    private function getSummary(int $score, int $issueCount): string
    {
        if ($score >= 90 && $issueCount === 0) return 'Excelent — template-ul este complet și gata de utilizare.';
        if ($score >= 80) return 'Bun — template-ul este funcțional, cu mici îmbunătățiri posibile.';
        if ($score >= 60) return 'Acceptabil — template-ul funcționează dar îi lipsesc câteva elemente.';
        if ($score >= 40) return 'Incomplet — template-ul are mai multe probleme care trebuie rezolvate.';
        return 'Critic — template-ul necesită completare semnificativă.';
    }
}
