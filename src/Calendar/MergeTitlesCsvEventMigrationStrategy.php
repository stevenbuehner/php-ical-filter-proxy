<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Config\Dto\EventMigrationConfig;
use App\Config\Dto\ExportConfig;

final class MergeTitlesCsvEventMigrationStrategy implements EventMigrationStrategyInterface
{
    public function name(): string
    {
        return 'merge_titles_csv';
    }

    public function merge(array $events, ExportConfig $export, EventMigrationConfig $config): CalendarEvent
    {
        if ($events === []) {
            throw new \InvalidArgumentException('Cannot merge an empty event list.');
        }

        $mergedEvent = clone $events[0]->originalEvent;
        $mergedEvent->UID = $this->buildUid($events, $export, $config);

        $summaries = [];
        $descriptions = [];
        $locations = [];
        $categories = [];
        $urls = [];

        $earliestStart = null;
        $latestEnd = null;

        foreach ($events as $event) {
            $this->updateBounds($event, $earliestStart, $latestEnd);

            $summary = trim($event->summary);
            if ($summary !== '') {
                $summaries[] = $summary;
            }

            $description = trim($event->description);
            if ($description !== '') {
                $descriptions[] = $description;
            }

            $location = trim($event->location);
            if ($location !== '' && !in_array($location, $locations, true)) {
                $locations[] = $location;
            }

            foreach ($event->categories as $category) {
                if (!in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }

            $url = trim($event->url);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        if ($earliestStart !== null) {
            $mergedEvent->DTSTART = $this->formatDateTime($earliestStart, $events[0]->dtstart ?? '');
        }

        if ($latestEnd !== null) {
            $mergedEvent->DTEND = $this->formatDateTime($latestEnd, $events[0]->dtend ?? $events[0]->dtstart ?? '');
        } elseif (isset($mergedEvent->DTEND)) {
            unset($mergedEvent->DTEND);
        }

        $mergedEvent->SUMMARY = implode(', ', $summaries);

        if ($descriptions === []) {
            unset($mergedEvent->DESCRIPTION);
        } else {
            $mergedEvent->DESCRIPTION = implode("\n---\n", $descriptions);
        }

        if ($locations === []) {
            unset($mergedEvent->LOCATION);
        } elseif (count($locations) === 1) {
            $mergedEvent->LOCATION = $locations[0];
        } else {
            $mergedEvent->LOCATION = implode(', ', $locations);
        }

        if ($categories === []) {
            unset($mergedEvent->CATEGORIES);
        } else {
            $mergedEvent->CATEGORIES = implode(',', $categories);
        }

        if ($urls === []) {
            unset($mergedEvent->URL);
        } else {
            $mergedEvent->URL = $urls[0];
        }

        return CalendarEvent::fromVEvent($mergedEvent);
    }

    /**
     * @param list<CalendarEvent> $events
     */
    private function buildUid(array $events, ExportConfig $export, EventMigrationConfig $config): string
    {
        $signature = [
            'export' => $export->id,
            'strategy' => $config->strategy,
            'events' => array_map(
                static fn (CalendarEvent $event): array => [
                    'uid' => $event->uid,
                    'summary' => $event->summary,
                    'description' => $event->description,
                    'location' => $event->location,
                    'url' => $event->url,
                    'categories' => $event->categories,
                    'dtstart' => $event->dtstart,
                    'dtend' => $event->dtend,
                ],
                $events
            ),
        ];

        return sprintf('%s-%s@merged', $export->slug, substr(hash('sha256', json_encode($signature, JSON_THROW_ON_ERROR)), 0, 16));
    }

    private function updateBounds(CalendarEvent $event, ?\DateTimeImmutable &$earliestStart, ?\DateTimeImmutable &$latestEnd): void
    {
        $start = $this->parseDateTime($event->dtstart);
        if ($start !== null && ($earliestStart === null || $start < $earliestStart)) {
            $earliestStart = $start;
        }

        $end = $this->parseDateTime($event->dtend ?? $event->dtstart);
        if ($end !== null && ($latestEnd === null || $end > $latestEnd)) {
            $latestEnd = $end;
        }
    }

    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function formatDateTime(\DateTimeImmutable $dateTime, string $template): string
    {
        if (preg_match('/^\d{8}$/', $template) === 1) {
            return $dateTime->format('Ymd');
        }

        if (preg_match('/^\d{8}T\d{6}Z$/', $template) === 1) {
            return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        }

        return $dateTime->format('Ymd\\THis');
    }
}
