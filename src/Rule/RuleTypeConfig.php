<?php

declare(strict_types=1);

namespace App\Rule;

final readonly class RuleTypeConfig
{
    /**
     * @param list<string> $filters
     * @param list<string> $transformations
     */
    public function __construct(
        public array $filters,
        public array $transformations,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            filters: self::listOfStrings($data['filters'] ?? []),
            transformations: self::listOfStrings($data['transformations'] ?? []),
        );
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function listOfStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }

        return array_values(array_unique($items));
    }
}
