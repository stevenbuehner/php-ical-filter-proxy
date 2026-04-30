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
}
