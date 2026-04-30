<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Filter\MatchEvaluator;
use PHPUnit\Framework\TestCase;

final class MatchEvaluatorTest extends TestCase
{
    public function testContainsAndDateMatching(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);

        $event = (new CalendarParser())->parseEvents($ics)[0];
        $evaluator = new MatchEvaluator();

        self::assertTrue($evaluator->matches($event, [
            'summary' => ['contains' => 'technik'],
            'date' => ['from' => '2026-01-01', 'until' => '2026-12-31'],
        ]));
    }
}
