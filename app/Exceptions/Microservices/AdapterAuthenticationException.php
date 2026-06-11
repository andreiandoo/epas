<?php

namespace App\Exceptions\Microservices;

class AdapterAuthenticationException extends MicroserviceException
{
    public function __construct(string $adapter, string $message = "", array $context = [])
    {
        parent::__construct(
            $message ?: "Authentication failed for adapter: {$adapter}",
            array_merge(['adapter' => $adapter], $context),
            401
        );
    }
}
