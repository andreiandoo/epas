<?php

namespace App\Logging\Classifiers;

class DatabaseClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        $sub = match (true) {
            $this->messageContains($record, ['unique constraint', 'unique violation', 'SQLSTATE[23505]']) => 'unique_violation',
            $this->messageContains($record, ['foreign key', 'violates foreign key', 'SQLSTATE[23503]']) => 'fk_violation',
            $this->messageContains($record, ['deadlock', 'SQLSTATE[40P01]']) => 'deadlock',
            $this->messageContains($record, ['lock wait timeout', 'lock timeout', 'SQLSTATE[55P03]']) => 'lock_timeout',
            $this->messageContains($record, ['could not translate host', 'connection refused', 'server has gone away', 'SQLSTATE[08']) => 'connection_lost',
            $this->messageContains($record, ['invalid input syntax for type uuid', 'invalid uuid']) => 'invalid_uuid',
            $this->messageContains($record, ['invalid input syntax']) => 'invalid_input_syntax',
            $this->messageContains($record, ['column', 'does not exist']) => 'schema_drift',
            $this->messageContains($record, ['Grouping error', 'must appear in the GROUP BY']) => 'group_by_error',
            default => null,
        };

        if ($sub) {
            return new ClassificationResult('database', $sub);
        }

        if ($this->exceptionIsA($record, [
            'Illuminate\Database\QueryException',
            'Illuminate\Database\UniqueConstraintViolationException',
            'PDOException',
        ])) {
            return new ClassificationResult('database', 'query_exception');
        }

        return null;
    }
}
