<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Calendar\CalendarEvent;
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

    public function testPrefixTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([['field' => 'summary', 'action' => 'prefix', 'value' => '[P] ']]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('[P] Technikprobe', (string) $event->originalEvent->SUMMARY);
    }

    public function testSuffixTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([['field' => 'summary', 'action' => 'suffix', 'value' => ' [S]']]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('Technikprobe [S]', (string) $event->originalEvent->SUMMARY);
    }

    public function testReplaceTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([['field' => 'description', 'action' => 'replace', 'search' => 'Team', 'replace' => 'Crew']]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('Technik Crew', (string) $event->originalEvent->DESCRIPTION);
    }

    public function testReplaceRegexTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([['field' => 'location', 'action' => 'replace_regex', 'pattern' => '/Saal/i', 'replacement' => 'Halle']]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('Halle A', (string) $event->originalEvent->LOCATION);
    }

    public function testRemoveTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([['field' => 'url', 'action' => 'remove']]);

        (new TransformEngine())->apply($event, $rule);

        self::assertFalse(isset($event->originalEvent->URL));
    }

    public function testCategoriesAddAndRemoveTransforms(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['field' => 'categories', 'action' => 'add', 'value' => 'Extra'],
            ['field' => 'categories', 'action' => 'remove', 'value' => 'Dienst'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertStringContainsString('Extra', (string) $event->originalEvent->CATEGORIES);
        self::assertStringNotContainsString('Dienst', (string) $event->originalEvent->CATEGORIES);
    }

    public function testStartAndEndModifyTransform(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['field' => 'start', 'action' => 'modify', 'value' => '+1 day'],
            ['field' => 'end', 'action' => 'modify', 'value' => '+1 day'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260502T090000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502T100000', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformUsesIndependentReferences(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '-20m',
                ],
                'end' => [
                    'reference' => 'current_start',
                    'offset' => '10m',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T084000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T091000', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformSupportsCurrentEndReference(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_end',
                    'offset' => '-30m',
                ],
                'end' => [
                    'reference' => 'current_end',
                    'offset' => '+15m',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T093000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T101500', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformSupportsSecondsMinutesAndHours(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '+30s',
                ],
                'end' => [
                    'reference' => 'current_end',
                    'offset' => '-2h',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T090030', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T080000', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformCanMoveBackwardUsingCurrentStartAndCurrentEnd(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '-1h',
                ],
                'end' => [
                    'reference' => 'current_end',
                    'offset' => '-30m',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T080000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T093000', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformUpdatesDurationWhenPresent(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:duration@example
DTSTART:20260501T090000Z
DURATION:PT1H
SUMMARY:Duration event
END:VEVENT
END:VCALENDAR
ICS;

        $event = (new CalendarParser())->parseEvents($ics)[0];
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '+15m',
                ],
                'end' => [
                    'reference' => 'current_start',
                    'offset' => '+45m',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T091500', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T094500', (string) $event->originalEvent->DTEND);
        self::assertSame('PT30M', (string) $event->originalEvent->DURATION);
    }

    public function testAdjustTimesTransformSupportsShortDurationOutputForSeconds(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:duration-seconds@example
DTSTART:20260501T090000Z
DURATION:PT1H
SUMMARY:Duration seconds event
END:VEVENT
END:VCALENDAR
ICS;

        $event = (new CalendarParser())->parseEvents($ics)[0];
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '+30s',
                ],
                'end' => [
                    'reference' => 'current_start',
                    'offset' => '+90s',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T090030', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T090130', (string) $event->originalEvent->DTEND);
        self::assertSame('PT1M', (string) $event->originalEvent->DURATION);
    }

    public function testAdjustTimesTransformClampsEndBeforeStart(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => [
                    'reference' => 'current_start',
                    'offset' => '+30m',
                ],
                'end' => [
                    'reference' => 'current_start',
                    'offset' => '-10m',
                ],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T093000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T093000', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformIgnoresAllDayEvents(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:all-day@example
DTSTART;VALUE=DATE:20260501
DTEND;VALUE=DATE:20260502
SUMMARY:All day
END:VEVENT
END:VCALENDAR
ICS;

        $event = (new CalendarParser())->parseEvents($ics)[0];
        $rule = $this->rule([
            [
                'field' => 'time',
                'action' => 'adjust_times',
                'start' => ['offset' => '-20m'],
                'end' => ['offset' => '10m'],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502', (string) $event->originalEvent->DTEND);
    }

    private function firstEvent(): CalendarEvent
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        return (new CalendarParser())->parseEvents($ics)[0];
    }

    private function rule(array $transforms): FilterRuleConfig
    {
        return new FilterRuleConfig(
            name: 'transform',
            action: 'keep',
            match: ['summary' => ['contains' => 'Technik']],
            transforms: $transforms,
        );
    }
}
