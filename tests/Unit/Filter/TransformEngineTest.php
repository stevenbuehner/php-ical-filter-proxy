<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarParser;
use App\Config\Dto\FilterRuleConfig;
use App\Filter\TransformEngine;
use PHPUnit\Framework\TestCase;

final class TransformEngineTest extends TestCase
{
    public function testAppliesTextTransformsAndCategoryAndDateModify(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        $event = (new CalendarParser())->parseEvents($ics)[0];

        $rule = new FilterRuleConfig(
            name: 'transform',
            action: 'keep',
            match: ['summary' => ['contains' => 'Technik']],
            transforms: [
                ['field' => 'summary', 'action' => 'prefix', 'value' => '[P] '],
                ['field' => 'summary', 'action' => 'suffix', 'value' => ' [S]'],
                ['field' => 'description', 'action' => 'replace', 'search' => 'Team', 'replace' => 'Crew'],
                ['field' => 'location', 'action' => 'replace_regex', 'pattern' => '/Saal/i', 'replacement' => 'Halle'],
                ['field' => 'url', 'action' => 'remove'],
                ['field' => 'categories', 'action' => 'add', 'value' => 'Extra'],
                ['field' => 'categories', 'action' => 'remove', 'value' => 'Dienst'],
                ['field' => 'start', 'action' => 'modify', 'value' => '+1 day'],
                ['field' => 'end', 'action' => 'modify', 'value' => '+1 day'],
            ]
        );

        (new TransformEngine())->apply($event, $rule);

        self::assertStringContainsString('[P] ', (string) $event->originalEvent->SUMMARY);
        self::assertStringContainsString('[S]', (string) $event->originalEvent->SUMMARY);
        self::assertStringContainsString('Crew', (string) $event->originalEvent->DESCRIPTION);
        self::assertStringContainsString('Halle', (string) $event->originalEvent->LOCATION);
        self::assertFalse(isset($event->originalEvent->URL));
        self::assertStringContainsString('Extra', (string) $event->originalEvent->CATEGORIES);
        self::assertStringNotContainsString('Dienst', (string) $event->originalEvent->CATEGORIES);
        self::assertSame('20260502T090000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502T100000', (string) $event->originalEvent->DTEND);
    }
}
