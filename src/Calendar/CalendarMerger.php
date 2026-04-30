<?php

declare(strict_types=1);

namespace App\Calendar;

final class CalendarMerger
{
    /**
     * @param list<list<CalendarEvent>> $eventLists
     * @return list<CalendarEvent>
     */
    public function merge(array $eventLists): array
    {
        $merged = [];
        foreach ($eventLists as $events) {
            foreach ($events as $event) {
                $merged[] = $event;
            }
        }

        return $merged;
    }
}
