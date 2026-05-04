<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Config\Dto\EventMigrationConfig;
use App\Config\Dto\ExportConfig;

final class EventMigrationEngine
{
    /**
     * @param array<string, EventMigrationStrategyInterface> $strategies
     */
    public function __construct(
        private array $strategies = [],
    ) {
        if ($this->strategies === []) {
            $default = new MergeTitlesCsvEventMigrationStrategy();
            $this->strategies = [$default->name() => $default];
        }
    }

    /**
     * @param list<CalendarEvent> $events
     * @return list<CalendarEvent>
     */
    public function migrate(array $events, ExportConfig $export, ?EventMigrationConfig $config): array
    {
        if ($config === null || !$config->enabled || $events === []) {
            return $events;
        }

        $strategy = $this->strategies[$config->strategy] ?? null;
        if ($strategy === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported migration strategy: %s', $config->strategy));
        }

        $gapTolerance = $config->gapToleranceSeconds();
        $allDayEvents = [];
        $timedEvents = [];

        foreach ($events as $index => $event) {
            $bucket = $this->isAllDayEvent($event) ? 'all_day' : 'timed';
            $decorated = [
                'event' => $event,
                'index' => $index,
                'start' => $this->parseDateTime($event->dtstart) ?? new \DateTimeImmutable('@0'),
                'end' => $this->parseDateTime($event->dtend ?? $event->dtstart) ?? ($this->parseDateTime($event->dtstart) ?? new \DateTimeImmutable('@0')),
            ];

            if ($bucket === 'all_day') {
                $allDayEvents[] = $decorated;
                continue;
            }

            $timedEvents[] = $decorated;
        }

        $merged = array_merge(
            $this->mergeBucket($allDayEvents, $export, $config, $strategy, $gapTolerance),
            $this->mergeBucket($timedEvents, $export, $config, $strategy, $gapTolerance),
        );

        usort($merged, function (CalendarEvent $left, CalendarEvent $right): int {
            $leftStart = $this->parseDateTime($left->dtstart) ?? new \DateTimeImmutable('@0');
            $rightStart = $this->parseDateTime($right->dtstart) ?? new \DateTimeImmutable('@0');

            $startCompare = $leftStart <=> $rightStart;
            if ($startCompare !== 0) {
                return $startCompare;
            }

            $leftAllDay = $this->isAllDayEvent($left) ? 0 : 1;
            $rightAllDay = $this->isAllDayEvent($right) ? 0 : 1;

            return $leftAllDay <=> $rightAllDay;
        });

        return $merged;
    }

    /**
     * @param list<array{event:CalendarEvent,index:int,start:\DateTimeImmutable,end:\DateTimeImmutable}> $decoratedEvents
     * @return list<CalendarEvent>
     */
    private function mergeBucket(array $decoratedEvents, ExportConfig $export, EventMigrationConfig $config, EventMigrationStrategyInterface $strategy, int $gapTolerance): array
    {
        if ($decoratedEvents === []) {
            return [];
        }

        usort($decoratedEvents, static function (array $left, array $right): int {
            $startCompare = $left['start'] <=> $right['start'];
            if ($startCompare !== 0) {
                return $startCompare;
            }

            $endCompare = $left['end'] <=> $right['end'];
            if ($endCompare !== 0) {
                return $endCompare;
            }

            return $left['index'] <=> $right['index'];
        });

        $groups = [];
        $currentGroup = [$decoratedEvents[0]['event']];
        $currentEnd = $decoratedEvents[0]['end'];

        for ($i = 1, $count = count($decoratedEvents); $i < $count; $i++) {
            $entry = $decoratedEvents[$i];
            $gapDeadline = (clone $currentEnd)->modify(sprintf('+%d seconds', $gapTolerance));

            if ($entry['start'] <= $gapDeadline) {
                $currentGroup[] = $entry['event'];
                if ($entry['end'] > $currentEnd) {
                    $currentEnd = $entry['end'];
                }
                continue;
            }

            $groups[] = $currentGroup;
            $currentGroup = [$entry['event']];
            $currentEnd = $entry['end'];
        }

        $groups[] = $currentGroup;

        $merged = [];
        foreach ($groups as $group) {
            $merged[] = count($group) === 1 ? $group[0] : $strategy->merge($group, $export, $config);
        }

        return $merged;
    }

    private function isAllDayEvent(CalendarEvent $event): bool
    {
        return is_string($event->dtstart) && preg_match('/^\d{8}$/', $event->dtstart) === 1;
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
}
