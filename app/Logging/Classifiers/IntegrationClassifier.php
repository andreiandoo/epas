<?php

namespace App\Logging\Classifiers;

class IntegrationClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->exceptionIsA($record, ['App\Exceptions\Microservices\MicroserviceException'])
            || $this->fileContains($record, ['Microservices'])) {
            return new ClassificationResult('integration', 'microservice_error');
        }
        if ($this->messageContains($record, ['webhook delivery failed', 'webhook 5xx', 'webhook timeout'])) {
            return new ClassificationResult('integration', 'webhook_failed');
        }
        if ($this->contextHas($record, 'webhook_id') || $this->contextHas($record, 'webhook_url')) {
            return new ClassificationResult('integration', 'webhook_failed');
        }
        return null;
    }
}
