<?php

namespace App\Exceptions\Microservices;

class AdapterConnectionException extends MicroserviceException
{
    public function __construct(string $adapter, string $message = "", array $context = [])
    {
        parent::__construct(
            $message ?: "Connection failed for adapter: {$adapter}",
            array_merge(['adapter' => $adapter], $context),
            503
        );
    }
}
