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

    public function testTransformOperationsHandleMissingAndCustomProperties(): void
    {
        $event = $this->eventWithoutSummaryAndWithCustomProperty();
        $rule = $this->rule([
            ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[P] '],
            ['type' => 'suffix_text', 'field' => 'summary', 'value' => ' [S]'],
            ['type' => 'replace_text', 'field' => 'description', 'search' => 'missing', 'replace' => 'present'],
            ['type' => 'replace_regex', 'field' => 'description', 'pattern' => '/missing/', 'replacement' => 'present'],
            ['type' => 'remove_property', 'field' => 'x-custom'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('[P]  [S]', (string) ($event->originalEvent->SUMMARY ?? ''));
        self::assertSame('present', (string) $event->originalEvent->DESCRIPTION);
        self::assertFalse(isset($event->originalEvent->X_CUSTOM));
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

        self::assertSame('20260501T084000Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T091000Z', (string) $event->originalEvent->DTEND);
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

        self::assertSame('20260501T093000Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T101500Z', (string) $event->originalEvent->DTEND);
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

        self::assertSame('20260501T090030Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T090030Z', (string) $event->originalEvent->DTEND);
    }

    public function testAdjustTimesTransformUpdatesDurationWhenPresent(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:duration@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
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

        self::assertSame('20260501T091500Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T094500Z', (string) $event->originalEvent->DTEND);
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

    public function testAdjustTimesTransformFallsBackOnInvalidReferencesAndOffsets(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            [
                'type' => 'adjust_times',
                'start' => ['reference' => 'invalid_reference', 'offset' => 'bad'],
                'end' => ['reference' => 'current_end', 'offset' => 'bad'],
            ],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T090000Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T100000Z', (string) $event->originalEvent->DTEND);
    }

    public function testCategoriesRemoveDeletesLastCategoryProperty(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:cat-remove@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
CATEGORIES:Technik
SUMMARY:Category
END:VEVENT
END:VCALENDAR
ICS;

        $event = (new CalendarParser())->parseEvents($ics)[0];
        $rule = $this->rule([
            ['type' => 'categories_remove', 'value' => 'Technik'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertFalse(isset($event->originalEvent->CATEGORIES));
    }

    public function testModifyDatetimeIgnoresInvalidFieldAndModifier(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['type' => 'modify_datetime', 'field' => 'unknown', 'value' => '+1 day'],
            ['type' => 'modify_datetime', 'field' => 'start', 'value' => ''],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260501T090000Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260501T100000Z', (string) $event->originalEvent->DTEND);
    }

    public function testModifyDatetimeTransformAppliesToStartAndEnd(): void
    {
        $event = $this->firstEvent();
        $rule = $this->rule([
            ['type' => 'modify_datetime', 'field' => 'start', 'value' => '+1 day'],
            ['type' => 'modify_datetime', 'field' => 'end', 'value' => '+1 day'],
        ]);

        (new TransformEngine())->apply($event, $rule);

        self::assertSame('20260502T090000Z', (string) $event->originalEvent->DTSTART);
        self::assertSame('20260502T100000Z', (string) $event->originalEvent->DTEND);
    }

    private function firstEvent(): CalendarEvent
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        return (new CalendarParser())->parseEvents($ics)[0];
    }

    private function eventWithoutSummaryAndWithCustomProperty(): CalendarEvent
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:custom@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
DESCRIPTION:missing
X-CUSTOM:value
END:VEVENT
END:VCALENDAR
ICS;

        return (new CalendarParser())->parseEvents($ics)[0];
    }

    private function rule(array $transform): FilterRuleConfig
    {
        return new FilterRuleConfig(type: 'match', match: ['any' => true], onMatch: 'transform', transform: $transform);
    }
}
