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
