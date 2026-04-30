<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Calendar\CalendarParser;
use App\Calendar\Deduplicator;
use PHPUnit\Framework\TestCase;

final class DeduplicatorTest extends TestCase
{
    public function testFirstWinsByUid(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/duplicates.ics');
        self::assertNotFalse($ics);

        $events = (new CalendarParser())->parseEvents($ics);
        $deduped = (new Deduplicator())->deduplicateByUid($events);

        self::assertCount(2, $deduped);
        self::assertSame('Erster Eintrag', $deduped[0]->summary);
    }
}
