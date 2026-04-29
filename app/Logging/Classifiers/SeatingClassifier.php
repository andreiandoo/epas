<?php

namespace App\Logging\Classifiers;

class SeatingClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['SeatHold', 'seat hold', 'seat_hold'])) {
            $sub = match (true) {
                $this->messageContains($record, ['already_held_or_sold', 'already held']) => 'already_held',
                $this->messageContains($record, 'version mismatch') => 'version_mismatch',
                $this->messageContains($record, 'lock timeout') => 'lock_timeout',
                $this->messageContains($record, 'expired') => 'hold_expired',
                default => 'seat_hold_generic',
            };
            return new ClassificationResult('seating', $sub);
        }
        if ($this->messageContains($record, ['inventory drift', 'seat inventory', 'event_seat'])) {
            return new ClassificationResult('seating', 'inventory_drift');
        }
        if ($this->fileContains($record, ['Seating', 'SeatHoldService', 'SeatInventoryRepository'])) {
            return new ClassificationResult('seating', 'seating_generic');
        }
        return null;
    }
}
