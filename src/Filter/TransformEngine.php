<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;

final class TransformEngine
{
    public function apply(CalendarEvent $event, FilterRuleConfig $rule): void
    {
        $transforms = $rule->transforms;
        if ($transforms === []) {
            return;
        }

        foreach ($transforms as $transform) {
            if (!is_array($transform)) {
                continue;
            }

            $field = strtolower(trim((string) ($transform['field'] ?? '')));
            $action = strtolower(trim((string) ($transform['action'] ?? '')));

            if ($field === '' || $action === '') {
                continue;
            }

            if ($field === 'time' && in_array($action, ['adjust', 'adjust_times'], true)) {
                $this->adjustTimes($event, $transform);
                continue;
            }

            $this->applyTransform($event, $field, $action, $transform);
        }
    }

    private function applyTransform(CalendarEvent $event, string $field, string $action, array $transform): void
    {
        if ($field === 'categories') {
            $this->transformCategories($event, $action, $transform);
            return;
        }

        if ($field === 'start' && $action === 'modify') {
            $this->modifyDate($event, 'DTSTART', (string) ($transform['value'] ?? ''));
            return;
        }

        if ($field === 'end' && $action === 'modify') {
            $this->modifyDate($event, 'DTEND', (string) ($transform['value'] ?? ''));
            return;
        }

        $property = match ($field) {
            'summary' => 'SUMMARY',
            'description' => 'DESCRIPTION',
            'location' => 'LOCATION',
            'url' => 'URL',
            default => null,
        };

        if ($property === null) {
            return;
        }

        $this->transformTextProperty($event, $property, $action, $transform);
    }

    private function transformTextProperty(CalendarEvent $event, string $property, string $action, array $transform): void
    {
        if ($action === 'remove') {
            unset($event->originalEvent->{$property});
            return;
        }

        $current = isset($event->originalEvent->{$property}) ? (string) $event->originalEvent->{$property} : '';

        $next = match ($action) {
            'prefix' => (string) ($transform['value'] ?? '') . $current,
            'suffix' => $current . (string) ($transform['value'] ?? ''),
            'replace' => str_ireplace(
                (string) ($transform['search'] ?? ''),
                (string) ($transform['replace'] ?? ''),
                $current
            ),
            'replace_regex' => $this->replaceRegex(
                $current,
                (string) ($transform['pattern'] ?? ''),
                (string) ($transform['replacement'] ?? '')
            ),
            default => $current,
        };

        if (!isset($event->originalEvent->{$property})) {
            $event->originalEvent->add($property, $next);
            return;
        }

        $event->originalEvent->{$property} = $next;
    }

    private function transformCategories(CalendarEvent $event, string $action, array $transform): void
    {
        $current = [];
        if (isset($event->originalEvent->CATEGORIES)) {
            $current = array_values(array_filter(array_map('trim', explode(',', (string) $event->originalEvent->CATEGORIES))));
        }

        if ($action === 'add') {
            $value = trim((string) ($transform['value'] ?? ''));
            if ($value !== '' && !in_array($value, $current, true)) {
                $current[] = $value;
            }
        }

        if ($action === 'remove') {
            $value = trim((string) ($transform['value'] ?? ''));
            if ($value !== '') {
                $current = array_values(array_filter($current, static fn (string $item): bool => strcasecmp($item, $value) !== 0));
            }
        }

        if ($current === []) {
            unset($event->originalEvent->CATEGORIES);
            return;
        }

        $serialized = implode(',', $current);
        if (!isset($event->originalEvent->CATEGORIES)) {
            $event->originalEvent->add('CATEGORIES', $serialized);
            return;
        }

        $event->originalEvent->CATEGORIES = $serialized;
    }

    private function modifyDate(CalendarEvent $event, string $property, string $modifier): void
    {
        if ($modifier === '' || !isset($event->originalEvent->{$property})) {
            return;
        }

        try {
            $current = new \DateTimeImmutable((string) $event->originalEvent->{$property});
            $next = $current->modify($modifier);
            if ($next === false) {
                return;
            }

            $event->originalEvent->{$property} = $next->format('Ymd\\THis');
        } catch (\Exception) {
        }
    }

    private function adjustTimes(CalendarEvent $event, array $transform): void
    {
        if ($this->isAllDayEvent($event)) {
            return;
        }

        $startSpec = is_array($transform['start'] ?? null) ? $transform['start'] : [];
        $endSpec = is_array($transform['end'] ?? null) ? $transform['end'] : [];

        $currentStart = $this->parseDateTimeProperty($event, 'DTSTART');
        if ($currentStart === null) {
            return;
        }

        $currentEnd = $this->parseDateTimeProperty($event, 'DTEND');
        if ($currentEnd === null) {
            $currentEnd = $currentStart;
        }

        $nextStart = $this->resolveAdjustedTime($currentStart, $currentStart, $currentEnd, $startSpec, 'current_start');
        $nextEnd = $this->resolveAdjustedTime($currentEnd, $currentStart, $currentEnd, $endSpec, 'current_end');

        if ($nextEnd < $nextStart) {
            $nextEnd = $nextStart;
        }

        $this->writeDateTimeProperty($event, 'DTSTART', $nextStart);
        $this->writeDateTimeProperty($event, 'DTEND', $nextEnd);

        if (isset($event->originalEvent->DURATION)) {
            $this->writeDurationProperty($event, $nextStart, $nextEnd);
        }
    }

    private function resolveAdjustedTime(
        \DateTimeImmutable $fallback,
        \DateTimeImmutable $currentStart,
        \DateTimeImmutable $currentEnd,
        array $spec,
        string $defaultReference,
    ): \DateTimeImmutable {
        $reference = strtolower(trim((string) ($spec['reference'] ?? $defaultReference)));
        $offset = trim((string) ($spec['offset'] ?? ''));

        $base = match ($reference) {
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            default => $fallback,
        };

        if ($offset === '') {
            return $base;
        }

        $seconds = $this->parseOffsetSeconds($offset);
        if ($seconds === null) {
            return $base;
        }

        return $base->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds') ?: $base;
    }

    private function parseOffsetSeconds(string $offset): ?int
    {
        if (!preg_match('/^([+-]?)(\d+)(s|m|h)$/', $offset, $matches)) {
            return null;
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $amount = (int) $matches[2];
        $unit = $matches[3];

        $multiplier = match ($unit) {
            's' => 1,
            'm' => 60,
            'h' => 3600,
            default => 0,
        };

        return $sign * $amount * $multiplier;
    }

    private function parseDateTimeProperty(CalendarEvent $event, string $property): ?\DateTimeImmutable
    {
        if (!isset($event->originalEvent->{$property})) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $event->originalEvent->{$property});
        } catch (\Exception) {
            return null;
        }
    }

    private function writeDateTimeProperty(CalendarEvent $event, string $property, \DateTimeImmutable $dateTime): void
    {
        if (!isset($event->originalEvent->{$property})) {
            return;
        }

        $template = (string) $event->originalEvent->{$property};
        if (preg_match('/^\d{8}$/', $template) === 1) {
            $event->originalEvent->{$property} = $dateTime->format('Ymd');
            return;
        }

        if (preg_match('/Z$/', $template) === 1) {
            $event->originalEvent->{$property} = $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
            return;
        }

        $event->originalEvent->{$property} = $dateTime->format('Ymd\\THis');
    }

    private function writeDurationProperty(CalendarEvent $event, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if (!isset($event->originalEvent->DURATION)) {
            return;
        }

        $seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($seconds < 0) {
            $seconds = 0;
        }

        $event->originalEvent->DURATION = $this->formatDurationSeconds($seconds);
    }

    private function formatDurationSeconds(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'D';
        }

        $time = '';
        if ($hours > 0) {
            $time .= $hours . 'H';
        }
        if ($minutes > 0) {
            $time .= $minutes . 'M';
        }
        if ($seconds > 0 || $time === '') {
            $time .= $seconds . 'S';
        }

        return 'P' . implode('', $parts) . 'T' . $time;
    }

    private function isAllDayEvent(CalendarEvent $event): bool
    {
        return isset($event->originalEvent->DTSTART) && preg_match('/^\d{8}$/', (string) $event->originalEvent->DTSTART) === 1;
    }

    private function replaceRegex(string $subject, string $pattern, string $replacement): string
    {
        if ($pattern === '') {
            return $subject;
        }

        $result = @preg_replace($pattern, $replacement, $subject);
        if ($result === null) {
            return $subject;
        }

        return $result;
    }
}
