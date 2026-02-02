<?php

namespace App\Exceptions\Microservices;

class WebhookDeliveryException extends MicroserviceException
{
    public function __construct(string $webhookUrl, string $message = "", array $context = [])
    {
        parent::__construct(
            $message ?: "Webhook delivery failed: {$webhookUrl}",
            array_merge(['webhook_url' => $webhookUrl], $context),
            500
        );
    }
}
