<?php

namespace App\Services\Chat;

use App\Models\KnowledgeBase\KbArticle;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;

class ChatContextBuilder
{
    private const ALLOWED_LANGUAGES = ['ro', 'en', 'hu', 'de', 'fr', 'es', 'it'];

    /**
     * Sanitize language code to prevent SQL injection in JSON path expressions
     */
    protected function sanitizeLanguage(string $language): string
    {
        if (in_array($language, self::ALLOWED_LANGUAGES, true)) {
            return $language;
        }

        return 'ro';
    }

    /**
     * Build the system prompt with marketplace context
     */
    public function buildSystemPrompt(MarketplaceClient $client): string
    {
        $language = $client->language ?? 'ro';
        $marketplaceName = $client->name ?? 'Marketplace';
        $supportEmail = $client->settings['support_email'] ?? '';
        $supportPhone = $client->settings['support_phone'] ?? '';
        $currency = $client->settings['currency'] ?? 'RON';

        return <<<PROMPT
Ești asistentul virtual al {$marketplaceName}. Ajuți clienții cu informații despre evenimente, bilete, comenzi, rambursări și întrebări generale despre platformă.

REGULI STRICTE:
1. Răspunde DOAR în limba română (dacă utilizatorul nu specifică altă limbă)
2. Fii concis, prietenos și profesional
3. Nu inventa informații - folosește DOAR datele din tool-uri și contextul furnizat
4. Pentru probleme complexe sau dacă nu poți ajuta, recomandă contactarea suportului la {$supportEmail} sau {$supportPhone}
5. Nu oferi informații financiare sensibile (numere de card, IBAN-uri etc.)
6. Când nu știi răspunsul, spune sincer și sugerează alternative
7. Folosește tool-urile disponibile pentru a accesa date reale despre comenzi, bilete, evenimente
8. Nu modifica niciodată date - doar citești și informezi
9. Formatează răspunsurile clar, cu liste dacă e necesar
10. Nu depăși 300 de cuvinte per răspuns

CONTEXT:
- Marketplace: {$marketplaceName}
- Monedă: {$currency}
- Email suport: {$supportEmail}
- Telefon suport: {$supportPhone}
- Limba implicită: {$language}
PROMPT;
    }

    /**
     * Search KB articles and return relevant context
     */
    public function searchKnowledgeBase(MarketplaceClient $client, string $query): string
    {
        $language = $this->sanitizeLanguage($client->language ?? 'ro');

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where(function ($q) use ($query, $language) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(content, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(question, '$.{$language}')) LIKE ?", ["%{$query}%"]);
            })
            ->orderByDesc('view_count')
            ->limit(3)
            ->get();

        if ($articles->isEmpty()) {
            return '';
        }

        $context = "ARTICOLE DIN BAZA DE CUNOȘTINȚE RELEVANTE:\n\n";

        foreach ($articles as $article) {
            if ($article->type === 'faq') {
                $context .= "Întrebare: " . ($article->getTranslation('question', $language) ?? '') . "\n";
                $context .= "Răspuns: " . ($article->getTranslation('content', $language) ?? '') . "\n\n";
            } else {
                $context .= "Titlu: " . ($article->getTranslation('title', $language) ?? '') . "\n";
                $content = $article->getTranslation('content', $language) ?? '';
                // Truncate long content to save tokens
                if (strlen($content) > 500) {
                    $content = substr($content, 0, 500) . '...';
                }
                $context .= "Conținut: " . $content . "\n\n";
            }
        }

        return $context;
    }

    /**
     * Build user context for authenticated customers
     */
    public function buildCustomerContext(MarketplaceCustomer $customer): string
    {
        return "UTILIZATOR AUTENTIFICAT:\n"
            . "- Nume: {$customer->first_name} {$customer->last_name}\n"
            . "- Email: {$customer->email}\n"
            . "- Total comenzi: {$customer->total_orders}\n";
    }

    /**
     * Convert conversation messages to OpenAI format
     */
    public function formatMessages(array $dbMessages): array
    {
        $messages = [];

        foreach ($dbMessages as $msg) {
            $entry = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];

            // If assistant had tool calls, include them
            if ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                $entry['tool_calls'] = $msg['tool_calls'];
                if (empty($entry['content'])) {
                    $entry['content'] = null;
                }
            }

            $messages[] = $entry;

            // If there are tool results, add them as tool messages
            if ($msg['role'] === 'assistant' && !empty($msg['tool_results'])) {
                foreach ($msg['tool_results'] as $result) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $result['tool_call_id'],
                        'content' => json_encode($result['output']),
                    ];
                }
            }
        }

        return $messages;
    }
}
