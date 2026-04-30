<?php

declare(strict_types=1);

namespace App\Calendar;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

final class CalendarParser
{
    public function parse(string $icsContent): VCalendar
    {
        try {
            $calendar = Reader::read($icsContent);
        } catch (\Throwable $exception) {
            throw new CalendarParseException(
                sprintf('Failed to parse ICS content: %s', $exception->getMessage()),
                previous: $exception
            );
        }

        if (!$calendar instanceof VCalendar) {
            throw new CalendarParseException('Parsed ICS payload is not a VCalendar object.');
        }

        return $calendar;
    }

    /**
     * @return list<CalendarEvent>
     */
    public function extractEvents(VCalendar $calendar): array
    {
        $events = [];

        foreach ($calendar->select('VEVENT') as $component) {
            if (!$component instanceof \Sabre\VObject\Component\VEvent) {
                continue;
            }

            $events[] = CalendarEvent::fromVEvent($component);
        }

        return $events;
    }

    /**
     * Parse + extract in one step and release VCalendar reference afterwards.
     *
     * @return list<CalendarEvent>
     */
    public function parseEvents(string $icsContent): array
    {
        $calendar = $this->parse($icsContent);

        try {
            return $this->extractEvents($calendar);
        } finally {
            unset($calendar);
        }
    }
}
