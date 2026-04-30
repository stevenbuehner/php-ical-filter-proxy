<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Calendar\CalendarMerger;
use App\Calendar\CalendarParser;
use PHPUnit\Framework\TestCase;

final class CalendarMergerTest extends TestCase
{
    public function testMergeConcatenatesEventLists(): void
    {
        $icsA = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        $icsB = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($icsA);
        self::assertNotFalse($icsB);

        $parser = new CalendarParser();
        $merged = (new CalendarMerger())->merge([
            $parser->parseEvents($icsA),
            $parser->parseEvents($icsB),
        ]);

        self::assertCount(5, $merged);
    }
}
