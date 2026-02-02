<?php

namespace App\Exceptions\Microservices;

class FeatureFlagException extends MicroserviceException
{
    public function __construct(string $featureKey, string $message = "", array $context = [])
    {
        parent::__construct(
            $message ?: "Feature flag error: {$featureKey}",
            array_merge(['feature_key' => $featureKey], $context),
            400
        );
    }
}
