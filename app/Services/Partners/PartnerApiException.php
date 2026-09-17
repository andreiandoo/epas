<?php

namespace App\Services\Partners;

/**
 * A request a partner must fix, reported as { "error": { "code", "message", "errors"? } }.
 */
class PartnerApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
