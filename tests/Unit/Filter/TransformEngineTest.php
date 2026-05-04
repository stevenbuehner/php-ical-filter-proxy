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
    public function testAppliesTextCategoryAndDateTransforms(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[P] '],
            ['type' => 'suffix_text', 'field' => 'summary', 'value' => ' [S]'],
            ['type' => 'replace_text', 'field' => 'description', 'search' => 'Team', 'replace' => 'Crew'],
            ['type' => 'replace_regex', 'field' => 'location', 'pattern' => '/Saal/i', 'replacement' => 'Halle'],
            ['type' => 'remove_property', 'field' => 'url'],
            ['type' => 'categories_add', 'value' => 'Extra'],
            ['type' => 'categories_remove', 'value' => 'Dienst'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertStringContainsString('[P] ', (string) $event->originalEvent->SUMMARY);
        self::assertStringContainsString('[S]', (string) $event->originalEvent->SUMMARY);
        self::assertStringContainsString('Crew', (string) $event->originalEvent->DESCRIPTION);
        self::assertStringContainsString('Halle', (string) $event->originalEvent->LOCATION);
        self::assertFalse(isset($event->originalEvent->URL));
        self::assertStringContainsString('Extra', (string) $event->originalEvent->CATEGORIES);
        self::assertStringNotContainsString('Dienst', (string) $event->originalEvent->CATEGORIES);
    }

    public function testAdjustTimesTransformUsesIndependentReferences(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'type' => 'adjust_times',
                'start' => ['reference' => 'current_start', 'offset' => '-20m'],
                'end' => ['reference' => 'current_start', 'offset' => '10m'],
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
                'type' => 'adjust_times',
                'start' => ['reference' => 'current_end', 'offset' => '-30m'],
                'end' => ['reference' => 'current_end', 'offset' => '+15m'],
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
                'type' => 'adjust_times',
                'start' => ['reference' => 'current_start', 'offset' => '+30s'],
                'end' => ['reference' => 'current_end', 'offset' => '-2h'],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T090030', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T080000', (string) $event->originalEvent->DTEND);
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
                'type' => 'adjust_times',
                'start' => ['reference' => 'current_start', 'offset' => '+15m'],
                'end' => ['reference' => 'current_start', 'offset' => '+45m'],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T091500', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T094500', (string) $event->originalEvent->DTEND);
        self::assertSame('PT30M', (string) $event->originalEvent->DURATION);
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
                'type' => 'adjust_times',
                'start' => ['offset' => '-20m'],
                'end' => ['offset' => '10m'],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502', (string) $event->originalEvent->DTEND);
    }

    public function testModifyDatetimeTransformAppliesToStartAndEnd(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['type' => 'modify_datetime', 'field' => 'start', 'value' => '+1 day'],
            ['type' => 'modify_datetime', 'field' => 'end', 'value' => '+1 day'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260502T090000', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502T100000', (string) $event->originalEvent->DTEND);
    }

    private function firstEvent(): CalendarEvent
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        return (new CalendarParser())->parseEvents($ics)[0];
    }

    private function rule(array $transform): FilterRuleConfig
    {
        return new FilterRuleConfig(type: 'match', match: ['any' => true], onMatch: 'transform', transform: $transform);
    }
}
