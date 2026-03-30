<?php

namespace App\Services\EventImport\Parsers;

use App\Services\EventImport\DTOs\ImportedRow;

interface ImportParserInterface
{
    /** Unique slug for this source, e.g. 'iabilet' */
    public function sourceKey(): string;

    /** Human-readable label, e.g. 'iabilet.ro' */
    public function sourceLabel(): string;

    /** Known/expected CSV headers for this source */
    public function expectedHeaders(): array;

    /**
     * Parse uploaded file into an array of ImportedRow DTOs.
     *
     * @return ImportedRow[]
     */
    public function parse(string $filePath): array;

    /** Whether this parser can handle the given headers */
    public function canHandle(array $headers): bool;
}
