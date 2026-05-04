<?php

declare(strict_types=1);

namespace App\Filter\Match;

final readonly class TextMatchEvaluator
{
    /**
     * Führt alle textbasierten Operatoren aus:
     * - `contains`
     * - `contains_any`
     * - `contains_all`
     * - `equals`
     * - `not_equals`
     * - `regex`
     * - `empty`
     *
     * Diese Klasse wird nur für Textfelder verwendet, nicht für Kategorien
     * oder Datumswerte.
     */
    public function matches(string $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'contains' => $this->contains($value, $this->toString($expected)),
            'contains_any' => $this->containsAny($value, $this->toStringList($expected)),
            'contains_all' => $this->containsAll($value, $this->toStringList($expected)),
            'not_contains' => !$this->contains($value, $this->toString($expected)),
            'equals' => mb_strtolower($value) === mb_strtolower($this->toString($expected)),
            'not_equals' => mb_strtolower($value) !== mb_strtolower($this->toString($expected)),
            'regex' => $this->regex($value, $this->toString($expected)),
            'empty' => trim($value) === '',
            default => false,
        };
    }

    private function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($this->contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $needles
     */
    private function containsAll(string $haystack, array $needles): bool
    {
        if ($needles === []) {
            return false;
        }

        foreach ($needles as $needle) {
            if (!$this->contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function regex(string $value, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        // Wenn der Pattern-String nicht direkt als PCRE gültig ist, versuchen
        // wir eine defensive Fallback-Normalisierung, damit einfache Patterns
        // nicht unnötig an der Delimiter-Form scheitern.
        $compiled = $this->normalizeRegex($pattern);
        if ($compiled === null) {
            return false;
        }

        return preg_match($compiled, $value) === 1;
    }

    private function normalizeRegex(string $pattern): ?string
    {
        $wrapped = $pattern;

        if (@preg_match($wrapped, '') === false) {
            $wrapped = '/' . str_replace('/', '\\/', $pattern) . '/i';
        }

        if (@preg_match($wrapped, '') === false) {
            return null;
        }

        return $wrapped;
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
