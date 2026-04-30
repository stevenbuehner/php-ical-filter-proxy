<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use DateTimeImmutable;
use DateTimeInterface;

final class MatchEvaluator
{
    /**
     * @param array<string, mixed> $matchConfig
     */
    public function matches(CalendarEvent $event, array $matchConfig): bool
    {
        if ($matchConfig === []) {
            return false;
        }

        if (($matchConfig['any'] ?? false) === true) {
            return true;
        }

        foreach ($matchConfig as $field => $conditions) {
            if ((string) $field === 'any') {
                continue;
            }

            if (!is_array($conditions) || $conditions === []) {
                return false;
            }

            if (!$this->matchesField($event, (string) $field, $conditions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $conditions
     */
    private function matchesField(CalendarEvent $event, string $field, array $conditions): bool
    {
        foreach ($conditions as $operator => $expected) {
            if (!$this->evaluateOperator($event, $field, (string) $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateOperator(CalendarEvent $event, string $field, string $operator, mixed $expected): bool
    {
        if ($field === 'date' && in_array($operator, ['from', 'until'], true)) {
            return $this->matchesDate($event, [$operator => $expected]);
        }

        return match ($operator) {
            'contains' => $this->contains($this->fieldAsText($event, $field), $this->toString($expected)),
            'contains_any' => $this->containsAny($this->fieldAsText($event, $field), $this->toStringList($expected)),
            'contains_all' => $this->containsAll($this->fieldAsText($event, $field), $this->toStringList($expected)),
            'not_contains' => !$this->contains($this->fieldAsText($event, $field), $this->toString($expected)),
            'equals' => $this->equals($event, $field, $expected),
            'not_equals' => !$this->equals($event, $field, $expected),
            'regex' => $this->regex($this->fieldAsText($event, $field), $this->toString($expected)),
            'empty' => $this->isEmpty($event, $field),
            default => false,
        };
    }

    private function equals(CalendarEvent $event, string $field, mixed $expected): bool
    {
        if ($field === 'categories') {
            $actual = array_map(static fn (string $value): string => mb_strtolower($value), $event->categories);
            $expectedList = array_map(static fn (string $value): string => mb_strtolower($value), $this->toStringList($expected));
            sort($actual);
            sort($expectedList);

            return $actual === $expectedList;
        }

        if ($field === 'date') {
            return $this->matchesDate($event, is_array($expected) ? $expected : []);
        }

        return mb_strtolower($this->fieldAsText($event, $field)) === mb_strtolower($this->toString($expected));
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

        $compiled = $this->normalizeRegex($pattern);
        if ($compiled === null) {
            return false;
        }

        return preg_match($compiled, $value) === 1;
    }

    private function isEmpty(CalendarEvent $event, string $field): bool
    {
        if ($field === 'categories') {
            return $event->categories === [];
        }

        if ($field === 'date') {
            return $event->dtstart === null || trim($event->dtstart) === '';
        }

        return trim($this->fieldAsText($event, $field)) === '';
    }

    private function fieldAsText(CalendarEvent $event, string $field): string
    {
        return match ($field) {
            'summary' => $event->summary,
            'description' => $event->description,
            'location' => $event->location,
            'url' => $event->url,
            'categories' => implode(',', $event->categories),
            'date' => $event->dtstart ?? '',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $dateConfig
     */
    private function matchesDate(CalendarEvent $event, array $dateConfig): bool
    {
        $eventDate = $this->parseEventDate($event->dtstart);
        if ($eventDate === null) {
            return false;
        }

        if (array_key_exists('from', $dateConfig)) {
            $from = $this->resolveDateToken($dateConfig['from']);
            if ($from === null || $eventDate < $from) {
                return false;
            }
        }

        if (array_key_exists('until', $dateConfig)) {
            $until = $this->resolveDateToken($dateConfig['until']);
            if ($until === null || $eventDate > $until->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    private function parseEventDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveDateToken(mixed $token): ?DateTimeImmutable
    {
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        $token = trim($token);
        $now = new DateTimeImmutable('now');

        if (mb_strtolower($token) === 'now') {
            return $now;
        }

        if (preg_match('/^[+-].+$/', $token) === 1) {
            try {
                return $now->modify($token) ?: null;
            } catch (\Exception) {
                return null;
            }
        }

        $absolute = DateTimeImmutable::createFromFormat('Y-m-d', $token);
        if ($absolute instanceof DateTimeImmutable) {
            return $absolute->setTime(0, 0, 0);
        }

        return null;
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

    private function toString(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
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
}
