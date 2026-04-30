<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Filter\MatchEvaluator;
use PHPUnit\Framework\TestCase;

final class MatchEvaluatorFieldMatrixTest extends TestCase
{
    public function testSummaryOperators(): void
    {
        $event = $this->eventWithAllFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['summary' => ['contains' => 'Technik']]));
        self::assertTrue($eval->matches($event, ['summary' => ['contains_any' => ['foo', 'probe']]]));
        self::assertTrue($eval->matches($event, ['summary' => ['contains_all' => ['Technik', 'probe']]]));
        self::assertTrue($eval->matches($event, ['summary' => ['equals' => 'Technikprobe']]));
        self::assertTrue($eval->matches($event, ['summary' => ['not_equals' => 'Andacht']]));
        self::assertTrue($eval->matches($event, ['summary' => ['regex' => '/^Technik/i']]));
        self::assertTrue($eval->matches($event, ['summary' => ['not_contains' => 'Intern']]));
        self::assertFalse($eval->matches($event, ['summary' => ['empty' => true]]));
    }

    public function testDescriptionOperators(): void
    {
        $event = $this->eventWithAllFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['description' => ['contains' => 'Team']]));
        self::assertTrue($eval->matches($event, ['description' => ['equals' => 'Technik Team']]));
        self::assertTrue($eval->matches($event, ['description' => ['not_equals' => 'Gemeinde']]));
        self::assertTrue($eval->matches($event, ['description' => ['regex' => '/team$/i']]));
        self::assertTrue($eval->matches($event, ['description' => ['not_contains' => 'Intern']]));
        self::assertFalse($eval->matches($event, ['description' => ['empty' => true]]));
    }

    public function testLocationOperators(): void
    {
        $event = $this->eventWithAllFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['location' => ['contains' => 'Saal']]));
        self::assertTrue($eval->matches($event, ['location' => ['equals' => 'Saal A']]));
        self::assertTrue($eval->matches($event, ['location' => ['not_equals' => 'Kirche']]));
        self::assertTrue($eval->matches($event, ['location' => ['regex' => '/A$/']]));
        self::assertTrue($eval->matches($event, ['location' => ['not_contains' => 'B']]));
        self::assertFalse($eval->matches($event, ['location' => ['empty' => true]]));
    }

    public function testUrlOperators(): void
    {
        $event = $this->eventWithAllFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['url' => ['contains' => 'example.com']]));
        self::assertTrue($eval->matches($event, ['url' => ['equals' => 'https://example.com/events/1']]));
        self::assertTrue($eval->matches($event, ['url' => ['not_equals' => 'https://example.com/other']]));
        self::assertTrue($eval->matches($event, ['url' => ['regex' => '#/events/1$#']]));
        self::assertTrue($eval->matches($event, ['url' => ['not_contains' => 'forbidden']]));
        self::assertFalse($eval->matches($event, ['url' => ['empty' => true]]));
    }

    public function testCategoriesAndDateOperators(): void
    {
        $event = $this->eventWithAllFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['categories' => ['contains' => 'Technik']]));
        self::assertTrue($eval->matches($event, ['categories' => ['equals' => ['Technik', 'Dienst']]]));
        self::assertTrue($eval->matches($event, ['categories' => ['not_equals' => ['Technik']]]));
        self::assertFalse($eval->matches($event, ['categories' => ['empty' => true]]));

        self::assertTrue($eval->matches($event, ['date' => ['from' => '2026-01-01', 'until' => '2026-12-31']]));
        self::assertTrue($eval->matches($event, ['date' => ['from' => '-12 months', 'until' => '+12 months']]));
        self::assertFalse($eval->matches($event, ['date' => ['until' => '2025-12-31']]));
    }

    public function testEmptyMatchesOnMissingFields(): void
    {
        $event = $this->eventWithMissingFields();
        $eval = new MatchEvaluator();

        self::assertTrue($eval->matches($event, ['url' => ['empty' => true]]));
        self::assertTrue($eval->matches($event, ['categories' => ['empty' => true]]));
        self::assertTrue($eval->matches($event, ['summary' => ['not_contains' => 'Technik']]));
    }

    private function eventWithAllFields()
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        return (new CalendarParser())->parseEvents($ics)[0];
    }

    private function eventWithMissingFields()
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        return (new CalendarParser())->parseEvents($ics)[1];
    }
}
