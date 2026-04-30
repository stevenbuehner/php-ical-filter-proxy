<?php

declare(strict_types=1);

namespace App\Calendar;

use Sabre\VObject\Component\VEvent;

final readonly class CalendarEvent
{
    /**
     * @param list<string> $categories
     */
    public function __construct(
        public ?string $uid,
        public string $summary,
        public string $description,
        public string $location,
        public string $url,
        public array $categories,
        public ?string $dtstart,
        public ?string $dtend,
        public VEvent $originalEvent,
    ) {
    }

    public static function fromVEvent(VEvent $event): self
    {
        return new self(
            uid: self::nullableScalar($event, 'UID'),
            summary: self::stringOrEmpty($event, 'SUMMARY'),
            description: self::stringOrEmpty($event, 'DESCRIPTION'),
            location: self::stringOrEmpty($event, 'LOCATION'),
            url: self::stringOrEmpty($event, 'URL'),
            categories: self::categories($event),
            dtstart: self::nullableScalar($event, 'DTSTART'),
            dtend: self::nullableScalar($event, 'DTEND'),
            originalEvent: $event,
        );
    }

    private static function stringOrEmpty(VEvent $event, string $property): string
    {
        return self::nullableScalar($event, $property) ?? '';
    }

    private static function nullableScalar(VEvent $event, string $property): ?string
    {
        if (!isset($event->{$property})) {
            return null;
        }

        $value = trim((string) $event->{$property});

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function categories(VEvent $event): array
    {
        if (!isset($event->CATEGORIES)) {
            return [];
        }

        $raw = (string) $event->CATEGORIES;
        if ($raw === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $value): string => trim($value),
            explode(',', $raw)
        );

        return array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));
    }
}
