<?php

declare(strict_types=1);

namespace App\Rule;

use App\Rule\Contract\FilterTypeInterface;
use App\Rule\Contract\TransformTypeInterface;

final readonly class RuleRegistry
{
    /**
     * @param array<string, FilterTypeInterface> $filters
     * @param array<string, TransformTypeInterface> $transformations
     */
    public function __construct(
        private array $filters,
        private array $transformations,
    ) {
    }

    public function filter(string $type): FilterTypeInterface
    {
        if (!isset($this->filters[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown filter type: %s', $type));
        }

        return $this->filters[$type];
    }

    public function transform(string $type): TransformTypeInterface
    {
        if (!isset($this->transformations[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown transform type: %s', $type));
        }

        return $this->transformations[$type];
    }
}
