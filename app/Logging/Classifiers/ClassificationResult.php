<?php

namespace App\Logging\Classifiers;

class ClassificationResult
{
    public function __construct(
        public readonly string $category,
        public readonly ?string $subcategory = null,
    ) {}
}
