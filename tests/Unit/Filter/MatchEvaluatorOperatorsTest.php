<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Filter\MatchEvaluator;
use PHPUnit\Framework\TestCase;

final class MatchEvaluatorOperatorsTest extends TestCase
{
    public function testOperatorsAndRelativeDateWork(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);
        $event = (new CalendarParser())->parseEvents($ics)[0];

        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['summary' => ['contains_any' => ['foo', 'technik']]]));
        self::assertTrue($eval->matches($event, ['summary' => ['contains_all' => ['technik', 'probe']]]));
        self::assertTrue($eval->matches($event, ['summary' => ['not_contains' => 'intern']]));
        self::assertTrue($eval->matches($event, ['summary' => ['equals' => 'technikprobe']]));
        self::assertTrue($eval->matches($event, ['summary' => ['not_equals' => 'anderes']]));
        self::assertTrue($eval->matches($event, ['summary' => ['regex' => '/TECHNIK/i']]));
        self::assertTrue($eval->matches($event, ['categories' => ['equals' => ['Technik', 'Dienst']]]));
        self::assertTrue($eval->matches($event, ['date' => ['from' => '-12 months', 'until' => '+12 months']]));
    }

    public function testEmptyMatchDoesNotMatch(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);
        $event = (new CalendarParser())->parseEvents($ics)[0];

        self::assertFalse((new MatchEvaluator())->matches($event, []));
    }

    public function testAnyMatchesAllEvents(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);
        $event = (new CalendarParser())->parseEvents($ics)[0];

        self::assertTrue((new MatchEvaluator())->matches($event, ['any' => true]));
    }
}
