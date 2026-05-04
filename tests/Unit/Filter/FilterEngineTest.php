<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Config\Dto\FilterRuleConfig;
use App\Filter\FilterEngine;
use PHPUnit\Framework\TestCase;

final class FilterEngineTest extends TestCase
{
    public function testKeepAndRemove(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig(type: 'match', match: ['summary' => ['contains' => 'Technik']], onMatch: 'keep'),
            new FilterRuleConfig(type: 'match', match: ['summary' => ['contains' => 'probe']], onMatch: 'remove'),
        ];

        $result = (new FilterEngine())->apply($events, $rules);

        self::assertCount(2, $result->filteredEvents);
        self::assertSame('Jugendtreffen', $result->filteredEvents[0]->summary);
        self::assertSame('Technikabend', $result->filteredEvents[1]->summary);
    }

    public function testMatchAnyAppliesTransformToAllEvents(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig(
                type: 'match',
                match: ['any' => true],
                onMatch: 'transform',
                transform: [
                    ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[ALL] '],
                ],
            ),
        ];

        $result = (new FilterEngine())->apply($events, $rules);

        self::assertCount(count($events), $result->filteredEvents);
        self::assertSame('[ALL] Technikdienst Probe', $result->filteredEvents[0]->summary);
        self::assertSame('[ALL] Jugendtreffen', $result->filteredEvents[1]->summary);
        self::assertSame('[ALL] Technikabend', $result->filteredEvents[2]->summary);
    }

    public function testRuleCanMatchMultipleFieldsWithAndSemantics(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig(
                type: 'match',
                match: [
                    'summary' => ['contains' => 'Technik'],
                    'description' => ['contains' => 'Team'],
                ],
                onMatch: 'transform',
                transform: [
                    ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[MATCHED] '],
                ],
            ),
        ];

        $result = (new FilterEngine())->apply($events, $rules);

        self::assertCount(2, $result->filteredEvents);
        self::assertSame('[MATCHED] Technikprobe', $result->filteredEvents[0]->summary);
        self::assertSame('Andacht', $result->filteredEvents[1]->summary);
    }

    public function testRemoveOnlyDropsMatchingEventsAndKeepsOthers(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig(
                type: 'match',
                match: ['summary' => ['contains' => 'Technikabend']],
                onMatch: 'remove',
            ),
        ];

        $result = (new FilterEngine())->apply($events, $rules);

        self::assertCount(2, $result->filteredEvents);
        self::assertSame('Technikdienst Probe', $result->filteredEvents[0]->summary);
        self::assertSame('Jugendtreffen', $result->filteredEvents[1]->summary);
    }

}
