<?php

declare(strict_types=1);

namespace App\Rule;

use App\Rule\Contract\TransformTypeInterface;

final readonly class TransformTypeRegistry
{
    /**
     * @param array<string, TransformTypeInterface> $types
     */
    public function __construct(
        private array $types,
    ) {
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function get(string $type): TransformTypeInterface
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown transform type: %s', $type));
        }

        return $this->types[$type];
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->types);
    }
}
