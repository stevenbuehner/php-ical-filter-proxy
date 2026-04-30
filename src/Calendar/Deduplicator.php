<?php

declare(strict_types=1);

namespace App\Calendar;

final class Deduplicator
{
    /**
     * @param list<CalendarEvent> $events
     * @return list<CalendarEvent>
     */
    public function deduplicateByUid(array $events): array
    {
        $seen = [];
        $result = [];

        foreach ($events as $event) {
            $uid = $event->uid ?? '';
            if ($uid !== '' && isset($seen[$uid])) {
                continue;
            }

            if ($uid !== '') {
                $seen[$uid] = true;
            }

            $result[] = $event;
        }

        return $result;
    }
}
