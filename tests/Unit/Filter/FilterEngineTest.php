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

        self::assertCount(1, $result->filteredEvents);
        self::assertSame('Technikabend', $result->filteredEvents[0]->summary);
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

    public function testStopProcessingKeepsCurrentEventAndSkipsRemainingRules(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig(
                type: 'match',
                match: ['summary' => ['contains' => 'Technik']],
                onMatch: 'transform',
                stopProcessing: true,
                transform: [
                    ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[STOP] '],
                ],
            ),
            new FilterRuleConfig(
                type: 'match',
                match: ['summary' => ['contains' => 'STOP']],
                onMatch: 'remove',
            ),
        ];

        $result = (new FilterEngine())->apply($events, $rules);

        self::assertGreaterThanOrEqual(1, count($result->filteredEvents));
        self::assertStringStartsWith('[STOP]', $result->filteredEvents[0]->summary);
    }
}
