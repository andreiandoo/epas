<?php

namespace App\Exceptions\Microservices;

use Exception;

class MicroserviceException extends Exception
{
    protected $context = [];

    public function __construct(string $message = "", array $context = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function report(): void
    {
        \Log::error($this->getMessage(), array_merge([
            'exception' => get_class($this),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ], $this->context));
    }
}
