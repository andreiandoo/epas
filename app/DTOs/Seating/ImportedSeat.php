<?php

namespace App\DTOs\Seating;

class ImportedSeat
{
    public function __construct(
        public string $externalId,
        public float $cx,
        public float $cy,
        public ?string $categoryId = null,
        public bool $isSelectable = true,
        public bool $isAllocated = false,
        public ?string $sectionId = null,
        public ?string $rowLabel = null,
        public ?string $seatLabel = null,
    ) {}

    public static function fromCircleElement(\DOMElement $circle): self
    {
        return new self(
            externalId: $circle->getAttribute('data-seat-id') ?: uniqid('seat_'),
            cx: (float) $circle->getAttribute('cx'),
            cy: (float) $circle->getAttribute('cy'),
            categoryId: $circle->getAttribute('data-seat-category-id') ?: null,
            isSelectable: $circle->getAttribute('data-is-selectable') !== '0',
            isAllocated: $circle->getAttribute('data-is-allocated') === '1',
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'cx' => $this->cx,
            'cy' => $this->cy,
            'category_id' => $this->categoryId,
            'is_selectable' => $this->isSelectable,
            'is_allocated' => $this->isAllocated,
            'section_id' => $this->sectionId,
            'row_label' => $this->rowLabel,
            'seat_label' => $this->seatLabel,
        ];
    }
}
