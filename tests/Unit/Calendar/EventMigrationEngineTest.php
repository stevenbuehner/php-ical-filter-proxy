<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Calendar\CalendarParser;
use App\Calendar\EventMigrationEngine;
use App\Config\Dto\EventMigrationConfig;
use App\Config\Dto\ExportConfig;
use PHPUnit\Framework\TestCase;

final class EventMigrationEngineTest extends TestCase
{
    public function testMergesOverlappingTimedEventsAcrossSources(): void
    {
        $events = array_merge(
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Morning A
DESCRIPTION:First
LOCATION:Hall A
URL:https://example.com/one
CATEGORIES:Tech,Team
END:VEVENT
END:VCALENDAR
ICS),
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T095500Z
DTEND:20260501T110000Z
SUMMARY:Morning B
DESCRIPTION:Second
LOCATION:Hall B
URL:https://example.com/two
CATEGORIES:Team,Music
END:VEVENT
END:VCALENDAR
ICS)
        );

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            new EventMigrationConfig(true, '0s', 'merge_titles_csv'),
        );

        self::assertCount(1, $merged);
        self::assertSame('Morning A, Morning B', $merged[0]->summary);
        self::assertSame('First' . "\n---\n" . 'Second', $merged[0]->description);
        self::assertSame('Hall A, Hall B', $merged[0]->location);
        self::assertSame('Tech,Team,Music', implode(',', $merged[0]->categories));
        self::assertSame('https://example.com/one', $merged[0]->url);
        self::assertSame('20260501T090000Z', $merged[0]->dtstart);
        self::assertSame('20260501T110000Z', $merged[0]->dtend);
    }

    public function testGapToleranceMergesNearTimedEvents(): void
    {
        $events = array_merge(
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Session A
END:VEVENT
END:VCALENDAR
ICS),
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T100500Z
DTEND:20260501T110000Z
SUMMARY:Session B
END:VEVENT
END:VCALENDAR
ICS)
        );

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            new EventMigrationConfig(true, '5m', 'merge_titles_csv'),
        );

        self::assertCount(1, $merged);
        self::assertSame('Session A, Session B', $merged[0]->summary);
    }

    public function testWithoutGapToleranceFarTimedEventsDoNotMerge(): void
    {
        $events = array_merge(
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Session A
END:VEVENT
END:VCALENDAR
ICS),
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T100600Z
DTEND:20260501T110000Z
SUMMARY:Session B
END:VEVENT
END:VCALENDAR
ICS)
        );

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            new EventMigrationConfig(true),
        );

        self::assertCount(2, $merged);
        self::assertSame('Session A', $merged[0]->summary);
        self::assertSame('Session B', $merged[1]->summary);
    }

    public function testDisabledMigrationKeepsEventsUnchanged(): void
    {
        $events = array_merge(
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Session A
END:VEVENT
END:VCALENDAR
ICS),
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T095500Z
DTEND:20260501T110000Z
SUMMARY:Session B
END:VEVENT
END:VCALENDAR
ICS)
        );

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            new EventMigrationConfig(false, '5m', 'merge_titles_csv'),
        );

        self::assertCount(2, $merged);
        self::assertSame('Session A', $merged[0]->summary);
        self::assertSame('Session B', $merged[1]->summary);
    }

    public function testNullMigrationConfigKeepsEventsUnchanged(): void
    {
        $events = $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Session A
END:VEVENT
END:VCALENDAR
ICS);

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            null,
        );

        self::assertCount(1, $merged);
        self::assertSame('Session A', $merged[0]->summary);
    }

    public function testAllDayEventsStaySeparateFromTimedEvents(): void
    {
        $events = array_merge(
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART;VALUE=DATE:20260501
DTEND;VALUE=DATE:20260502
SUMMARY:All Day Event
END:VEVENT
END:VCALENDAR
ICS),
            $this->parseEvents(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Timed Event
END:VEVENT
END:VCALENDAR
ICS)
        );

        $merged = (new EventMigrationEngine())->migrate(
            $events,
            $this->exportConfig(),
            new EventMigrationConfig(true, '5m', 'merge_titles_csv'),
        );

        self::assertCount(2, $merged);
        self::assertSame('All Day Event', $merged[0]->summary);
        self::assertSame('Timed Event', $merged[1]->summary);
    }

    private function exportConfig(): ExportConfig
    {
        return new ExportConfig(
            id: 'export-1',
            title: 'Export',
            slug: 'export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [],
        );
    }

    /**
     * @return list<\App\Calendar\CalendarEvent>
     */
    private function parseEvents(string $ics): array
    {
        return (new CalendarParser())->parseEvents($ics);
    }
}
