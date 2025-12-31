<?php

namespace App\Services\Tracking;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

class SchemaValidator
{
    protected array $schema;
    protected array $eventSchemas = [];
    protected array $requiredEnvelopeFields;

    public function __construct()
    {
        $this->loadSchema();
    }

    /**
     * Load the schema registry from YAML file.
     */
    protected function loadSchema(): void
    {
        $this->schema = Cache::remember('tx_schema_registry', 3600, function () {
            $path = config_path('tracking/schema_registry_v1.yaml');
            if (!file_exists($path)) {
                throw new \RuntimeException('Schema registry not found: ' . $path);
            }
            return Yaml::parseFile($path);
        });

        $this->requiredEnvelopeFields = [
            'event_id',
            'event_name',
            'event_version',
            'occurred_at',
            'tenant_id',
            'source_system',
        ];

        // Build event schemas index
        foreach ($this->schema['events'] ?? [] as $event) {
            $this->eventSchemas[$event['name']] = $event;
        }
    }

    /**
     * Validate an event envelope.
     *
     * @param array $event The event data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $event): array
    {
        $errors = [];

        // Validate required envelope fields
        $envelopeErrors = $this->validateEnvelope($event);
        $errors = array_merge($errors, $envelopeErrors);

        // Get event schema
        $eventName = $event['event_name'] ?? null;
        $eventSchema = $this->eventSchemas[$eventName] ?? null;

        if (!$eventSchema) {
            $errors[] = "Unknown event type: {$eventName}";
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate source system
        $sourceSystem = $event['source_system'] ?? null;
        if (!in_array($sourceSystem, $eventSchema['sources'] ?? ['web', 'mobile', 'backend'])) {
            $errors[] = "Invalid source_system '{$sourceSystem}' for event '{$eventName}'";
        }

        // Validate required entities
        $entityErrors = $this->validateRequiredEntities($event, $eventSchema);
        $errors = array_merge($errors, $entityErrors);

        // Validate payload schema
        $payloadErrors = $this->validatePayload($event['payload'] ?? [], $eventSchema['payload_schema'] ?? []);
        $errors = array_merge($errors, $payloadErrors);

        // Validate consent based on scope
        $consentErrors = $this->validateConsent($event, $eventSchema);
        $errors = array_merge($errors, $consentErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate envelope required fields.
     */
    protected function validateEnvelope(array $event): array
    {
        $errors = [];

        foreach ($this->requiredEnvelopeFields as $field) {
            if (empty($event[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate source_system enum
        $validSources = ['web', 'mobile', 'scanner', 'backend', 'payments', 'shop', 'wallet'];
        if (isset($event['source_system']) && !in_array($event['source_system'], $validSources)) {
            $errors[] = "Invalid source_system: {$event['source_system']}";
        }

        // For web/mobile, visitor_id and session_id are required
        if (in_array($event['source_system'] ?? '', ['web', 'mobile'])) {
            if (empty($event['visitor_id'])) {
                $errors[] = 'visitor_id is required for web/mobile events';
            }
            if (empty($event['session_id'])) {
                $errors[] = 'session_id is required for web/mobile events';
            }
        }

        // Validate event_id format (UUID)
        if (isset($event['event_id']) && !$this->isValidUuid($event['event_id'])) {
            $errors[] = 'event_id must be a valid UUID';
        }

        // Validate occurred_at format
        if (isset($event['occurred_at'])) {
            try {
                new \DateTime($event['occurred_at']);
            } catch (\Exception $e) {
                $errors[] = 'occurred_at must be a valid ISO8601 datetime';
            }
        }

        // Validate consent_snapshot structure
        if (isset($event['consent_snapshot']) && !is_array($event['consent_snapshot'])) {
            $errors[] = 'consent_snapshot must be an object';
        }

        return $errors;
    }

    /**
     * Validate required entities for an event type.
     */
    protected function validateRequiredEntities(array $event, array $eventSchema): array
    {
        $errors = [];
        $entities = $event['entities'] ?? [];
        $requiredEntities = $eventSchema['required_entities'] ?? [];

        foreach ($requiredEntities as $entityKey) {
            if (empty($entities[$entityKey])) {
                $errors[] = "Missing required entity: {$entityKey}";
            }
        }

        return $errors;
    }

    /**
     * Validate payload against schema.
     */
    protected function validatePayload(array $payload, array $payloadSchema): array
    {
        $errors = [];

        foreach ($payloadSchema as $field => $spec) {
            $value = $payload[$field] ?? null;
            $type = $spec['type'] ?? 'any';

            // Skip validation for optional fields that are null
            if ($value === null) {
                continue;
            }

            $fieldErrors = $this->validateFieldType($field, $value, $type, $spec);
            $errors = array_merge($errors, $fieldErrors);
        }

        return $errors;
    }

    /**
     * Validate a field against its expected type.
     */
    protected function validateFieldType(string $field, $value, string $type, array $spec): array
    {
        $errors = [];

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "Field '{$field}' must be a string";
                }
                break;

            case 'int':
            case 'integer':
                if (!is_int($value) && !is_numeric($value)) {
                    $errors[] = "Field '{$field}' must be an integer";
                }
                break;

            case 'number':
            case 'float':
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$field}' must be a number";
                }
                break;

            case 'bool':
            case 'boolean':
                if (!is_bool($value)) {
                    $errors[] = "Field '{$field}' must be a boolean";
                }
                break;

            case 'enum':
                $allowedValues = $spec['values'] ?? [];
                if (!in_array($value, $allowedValues)) {
                    $errors[] = "Field '{$field}' must be one of: " . implode(', ', $allowedValues);
                }
                break;

            case 'object':
                if (!is_array($value)) {
                    $errors[] = "Field '{$field}' must be an object";
                }
                break;

            case 'array_string':
                if (!is_array($value)) {
                    $errors[] = "Field '{$field}' must be an array";
                } else {
                    foreach ($value as $item) {
                        if (!is_string($item)) {
                            $errors[] = "Field '{$field}' must be an array of strings";
                            break;
                        }
                    }
                }
                break;

            case 'any':
                // No validation
                break;
        }

        return $errors;
    }

    /**
     * Validate consent requirements based on event scope.
     */
    protected function validateConsent(array $event, array $eventSchema): array
    {
        $errors = [];
        $scope = $eventSchema['scope'] ?? 'necessary';
        $consent = $event['consent_snapshot'] ?? [];

        // Necessary events don't require consent
        if ($scope === 'necessary') {
            return [];
        }

        // Analytics events require analytics consent
        if ($scope === 'analytics') {
            if (!($consent['analytics'] ?? false)) {
                $errors[] = "Event '{$event['event_name']}' requires analytics consent";
            }
        }

        // Marketing events require marketing consent
        if ($scope === 'marketing') {
            if (!($consent['marketing'] ?? false)) {
                $errors[] = "Event '{$event['event_name']}' requires marketing consent";
            }
        }

        return $errors;
    }

    /**
     * Check if a string is a valid UUID.
     */
    protected function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    /**
     * Get the consent scope for an event type.
     */
    public function getEventScope(string $eventName): string
    {
        return $this->eventSchemas[$eventName]['scope'] ?? 'necessary';
    }

    /**
     * Get all registered event names.
     */
    public function getEventNames(): array
    {
        return array_keys($this->eventSchemas);
    }

    /**
     * Get schema for a specific event type.
     */
    public function getEventSchema(string $eventName): ?array
    {
        return $this->eventSchemas[$eventName] ?? null;
    }

    /**
     * Check if an event type is registered.
     */
    public function isKnownEvent(string $eventName): bool
    {
        return isset($this->eventSchemas[$eventName]);
    }

    /**
     * Clear the cached schema.
     */
    public function clearCache(): void
    {
        Cache::forget('tx_schema_registry');
        $this->loadSchema();
    }
}
