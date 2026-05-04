<?php

declare(strict_types=1);

namespace App\Filter\Match;

final readonly class CategoryMatchEvaluator
{
    /**
     * Kategorien werden als Mengenlogik behandelt:
     * - `contains` prüft einzelne Kategorien
     * - `contains_any` / `contains_all` prüfen Listen
     * - `equals` vergleicht die Kategorien unabhängig von der Reihenfolge
     * - `empty` prüft auf eine leere Kategorienliste
     */
    public function matches(array $categories, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'contains' => $this->contains($categories, $this->toString($expected)),
            'contains_any' => $this->containsAny($categories, $this->toStringList($expected)),
            'contains_all' => $this->containsAll($categories, $this->toStringList($expected)),
            'not_contains' => !$this->contains($categories, $this->toString($expected)),
            'equals' => $this->equals($categories, $this->toStringList($expected)),
            'not_equals' => !$this->equals($categories, $this->toStringList($expected)),
            'empty' => $categories === [],
            default => false,
        };
    }

    /**
     * @param list<string> $categories
     */
    private function contains(array $categories, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        foreach ($categories as $category) {
            if (mb_stripos($category, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $categories
     * @param list<string> $needles
     */
    private function containsAny(array $categories, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($this->contains($categories, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $categories
     * @param list<string> $needles
     */
    private function containsAll(array $categories, array $needles): bool
    {
        if ($needles === []) {
            return false;
        }

        foreach ($needles as $needle) {
            if (!$this->contains($categories, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $categories
     * @param list<string> $expected
     */
    private function equals(array $categories, array $expected): bool
    {
        // Reihenfolge ist hier bewusst irrelevant, die Kategorien sollen als
        // inhaltliche Menge verglichen werden.
        $actual = array_map(static fn (string $value): string => mb_strtolower($value), $categories);
        $expectedList = array_map(static fn (string $value): string => mb_strtolower($value), $expected);
        sort($actual);
        sort($expectedList);

        return $actual === $expectedList;
    }

    private function toString(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $string = trim((string) $item);
            if ($string !== '') {
                $list[] = $string;
            }
        }

        return $list;
    }
}
