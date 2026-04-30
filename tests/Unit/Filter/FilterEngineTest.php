<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Config\Dto\FilterRuleConfig;
use App\Filter\FilterEngine;
use App\Filter\MatchEvaluator;
use App\Filter\TransformEngine;
use PHPUnit\Framework\TestCase;

final class FilterEngineTest extends TestCase
{
    public function testKeepAndRemove(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig('keep-technik', 'keep', ['summary' => ['contains' => 'technik']]),
            new FilterRuleConfig('remove-probe', 'remove', ['summary' => ['contains' => 'probe']]),
        ];

        $result = (new FilterEngine(new MatchEvaluator(), new TransformEngine()))->apply($events, $rules);

        self::assertCount(1, $result->filteredEvents);
        self::assertSame('Technikabend', $result->filteredEvents[0]->summary);
    }

    public function testMatchAnyAppliesTransformToAllEvents(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);
        $events = (new CalendarParser())->parseEvents($ics);

        $rules = [
            new FilterRuleConfig('global-prefix', 'keep', ['any' => true], [
                ['field' => 'summary', 'action' => 'prefix', 'value' => '[ALL] '],
            ]),
        ];

        $result = (new FilterEngine(new MatchEvaluator(), new TransformEngine()))->apply($events, $rules);

        self::assertCount(count($events), $result->filteredEvents);
        self::assertSame('[ALL] Technikdienst Probe', $result->filteredEvents[0]->summary);
        self::assertSame('[ALL] Jugendtreffen', $result->filteredEvents[1]->summary);
        self::assertSame('[ALL] Technikabend', $result->filteredEvents[2]->summary);
    }
}
