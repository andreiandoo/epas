<?php

namespace App\Services\EventImport\Parsers;

use App\Services\EventImport\DTOs\ImportedRow;

class IabiletParser implements ImportParserInterface
{
    public function sourceKey(): string
    {
        return 'iabilet';
    }

    public function sourceLabel(): string
    {
        return 'iabilet.ro';
    }

    public function expectedHeaders(): array
    {
        return [
            'ID comandă',
            'Dată comandă',
            'Nume client',
            'Email',
            'Telefon',
            'Tarif',
            'Loc',
            'Preț bilet',
            'Monedă',
            'Invitație',
            'Cod bare',
            'Serie',
            'Validat',
            'Status cmd.',
            'Status blt.',
        ];
    }

    public function canHandle(array $headers): bool
    {
        $normalized = array_map(fn ($h) => mb_strtolower(trim($h)), $headers);
        $required = ['id comandă', 'tarif', 'cod bare', 'preț bilet'];

        foreach ($required as $key) {
            $found = false;
            foreach ($normalized as $h) {
                if (mb_strtolower($h) === $key) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return ImportedRow[]
     */
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // Strip UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $separator = $this->detectSeparator($content);
        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($l) => trim($l) !== '');

        if (empty($lines)) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine, $separator);
        $headers = array_map('trim', $headers);

        // Build column index map
        $colMap = $this->buildColumnMap($headers);

        $rows = [];
        foreach ($lines as $line) {
            $fields = str_getcsv($line, $separator);
            if (count($fields) < 3) {
                continue; // skip malformed rows
            }

            // Pad fields to match header count
            $fields = array_pad($fields, count($headers), '');

            $rows[] = new ImportedRow(
                orderId: $this->val($fields, $colMap, 'order_id'),
                orderDate: $this->val($fields, $colMap, 'order_date'),
                clientName: $this->val($fields, $colMap, 'client_name'),
                email: $this->normalizeEmail($this->val($fields, $colMap, 'email')),
                phone: $this->val($fields, $colMap, 'phone'),
                ticketTypeName: $this->val($fields, $colMap, 'ticket_type'),
                seatLabel: $this->val($fields, $colMap, 'seat'),
                ticketPrice: $this->parsePrice($this->val($fields, $colMap, 'price')),
                currency: $this->val($fields, $colMap, 'currency') ?: 'RON',
                isInvitation: strtoupper($this->val($fields, $colMap, 'invitation') ?? '') === 'Y',
                barcode: $this->val($fields, $colMap, 'barcode'),
                fiscalSeries: $this->val($fields, $colMap, 'series'),
                validated: $this->val($fields, $colMap, 'validated'),
                orderStatus: $this->val($fields, $colMap, 'order_status'),
                ticketStatus: $this->val($fields, $colMap, 'ticket_status'),
            );
        }

        return $rows;
    }

    /**
     * Build a mapping from our internal keys to column indices.
     */
    protected function buildColumnMap(array $headers): array
    {
        $map = [];
        $mapping = [
            'order_id' => ['id comandă', 'id comanda'],
            'order_date' => ['dată comandă', 'data comanda', 'dată comandă'],
            'client_name' => ['nume client'],
            'email' => ['email', 'e-mail'],
            'phone' => ['telefon', 'phone'],
            'ticket_type' => ['tarif'],
            'seat' => ['loc'],
            'price' => ['preț bilet', 'pret bilet'],
            'currency' => ['monedă', 'moneda', 'currency'],
            'invitation' => ['invitație', 'invitatie', 'invitation'],
            'barcode' => ['cod bare', 'barcode'],
            'series' => ['serie', 'series'],
            'validated' => ['validat', 'validated'],
            'order_status' => ['status cmd.', 'status cmd', 'status comanda'],
            'ticket_status' => ['status blt.', 'status blt', 'status bilet'],
        ];

        foreach ($headers as $index => $header) {
            $normalized = mb_strtolower(trim($header));
            foreach ($mapping as $key => $candidates) {
                foreach ($candidates as $candidate) {
                    if ($normalized === $candidate) {
                        $map[$key] = $index;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    protected function val(array $fields, array $colMap, string $key): ?string
    {
        if (!isset($colMap[$key])) {
            return null;
        }

        $value = trim($fields[$colMap[$key]] ?? '');
        return $value === '' ? null : $value;
    }

    protected function detectSeparator(string $content): string
    {
        $firstLine = strtok($content, "\n");

        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');
        $semiCount = substr_count($firstLine, ';');

        // Tab-separated is most likely for iabilet exports
        if ($tabCount >= $commaCount && $tabCount >= $semiCount && $tabCount > 0) {
            return "\t";
        }

        return $semiCount > $commaCount ? ';' : ',';
    }

    protected function parsePrice(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle comma decimal separator: "80,00" => "80.00"
        $value = str_replace(',', '.', $value);
        // Remove any non-numeric chars except dots
        $value = preg_replace('/[^\d.]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function normalizeEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        return strtolower(trim($email));
    }
}
