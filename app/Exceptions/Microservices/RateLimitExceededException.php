<?php

namespace App\Exceptions\Microservices;

class RateLimitExceededException extends MicroserviceException
{
    public function __construct(string $service, int $limit, array $context = [])
    {
        parent::__construct(
            "Rate limit exceeded for {$service}. Limit: {$limit} requests",
            array_merge(['service' => $service, 'limit' => $limit], $context),
            429
        );
    }
}
