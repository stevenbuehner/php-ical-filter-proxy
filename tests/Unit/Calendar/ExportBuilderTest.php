<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Calendar\CalendarParser;
use App\Calendar\ExportBuilder;
use App\Calendar\FeedFetcher;
use App\Config\Dto\AppConfig;
use App\Config\Dto\ExportConfig;
use App\Config\Dto\IncludedSourceConfig;
use App\Config\Dto\SourceConfig;
use App\Config\Dto\FilterRuleConfig;
use App\Http\Logger\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExportBuilderTest extends TestCase
{
    public function testExportTitleIsWrittenAsCalendarName(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/simple.ics');
        self::assertNotFalse($ics);

        $httpClient = new MockHttpClient([
            new MockResponse($ics, ['http_code' => 200]),
        ]);

        $cacheDir = sys_get_temp_dir() . '/ical_feed_cache_' . uniqid('', true);
        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $feedFetcher = new FeedFetcher(
            $httpClient,
            new FileCache($cacheDir),
            new CacheKeyBuilder(),
            new TtlParser(),
            $logger,
        );

        $builder = new ExportBuilder($feedFetcher, new CalendarParser(), $logger);

        $source = new SourceConfig(
            id: 's1',
            label: 'Source 1',
            url: 'https://example.com/source.ics',
            cacheTtl: '15m',
            filters: [],
        );

        $export = new ExportConfig(
            id: 'e1',
            title: 'Handballtermine Kinder',
            slug: 'handball-kinder',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [new IncludedSourceConfig('s1', [])],
        );

        $config = new AppConfig(
            sources: ['s1' => $source],
            exports: ['e1' => $export],
        );

        $result = $builder->build($config, $export);

        self::assertNotNull($result->icsContent);
        self::assertStringContainsString("X-WR-CALNAME:Handballtermine Kinder", $result->icsContent);
        self::assertStringContainsString("NAME:Handballtermine Kinder", $result->icsContent);
    }

    public function testExportBuilderAppliesSourceFiltersAndTransforms(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);

        $httpClient = new MockHttpClient([
            new MockResponse($ics, ['http_code' => 200]),
        ]);

        $cacheDir = sys_get_temp_dir() . '/ical_feed_cache_' . uniqid('', true);
        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $feedFetcher = new FeedFetcher(
            $httpClient,
            new FileCache($cacheDir),
            new CacheKeyBuilder(),
            new TtlParser(),
            $logger,
        );

        $builder = new ExportBuilder($feedFetcher, new CalendarParser(), $logger);

        $source = new SourceConfig(
            id: 's1',
            label: 'Source 1',
            url: 'https://example.com/source.ics',
            cacheTtl: '15m',
            filters: [],
        );

        $export = new ExportConfig(
            id: 'e1',
            title: 'Filtered Export',
            slug: 'filtered-export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [
                new IncludedSourceConfig(
                    's1',
                    [
                        new FilterRuleConfig(
                            type: 'match',
                            match: ['summary' => ['contains' => 'Jugend']],
                            onMatch: 'remove',
                        ),
                        new FilterRuleConfig(
                            type: 'match',
                            match: ['any' => true],
                            onMatch: 'transform',
                            transform: [
                                ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[EXPORT] '],
                                ['type' => 'replace_text', 'field' => 'location', 'search' => 'Saal', 'replace' => 'Halle'],
                            ],
                        ),
                    ],
                ),
            ],
        );

        $config = new AppConfig(
            sources: ['s1' => $source],
            exports: ['e1' => $export],
        );

        $result = $builder->build($config, $export);

        self::assertNotNull($result->icsContent);

        $events = (new CalendarParser())->parseEvents($result->icsContent);
        self::assertCount(2, $events);
        self::assertSame('[EXPORT] Technikdienst Probe', $events[0]->summary);
        self::assertSame('[EXPORT] Technikabend', $events[1]->summary);
        self::assertSame('Halle', $events[0]->location);
        self::assertSame('Studio', $events[1]->location);
        self::assertStringNotContainsString('Jugendtreffen', $result->icsContent);
    }

    public function testExportBuilderAppliesExportFiltersAfterMergingSources(): void
    {
        $sourceA = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
SUMMARY:Team A
LOCATION:Hall A
END:VEVENT
END:VCALENDAR
ICS;

        $sourceB = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T100000Z
SUMMARY:Team B
LOCATION:Hall B
END:VEVENT
END:VCALENDAR
ICS;

        $httpClient = new MockHttpClient([
            new MockResponse($sourceA, ['http_code' => 200]),
            new MockResponse($sourceB, ['http_code' => 200]),
        ]);

        $cacheDir = sys_get_temp_dir() . '/ical_feed_cache_' . uniqid('', true);
        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $feedFetcher = new FeedFetcher(
            $httpClient,
            new FileCache($cacheDir),
            new CacheKeyBuilder(),
            new TtlParser(),
            $logger,
        );

        $builder = new ExportBuilder($feedFetcher, new CalendarParser(), $logger);

        $sourceConfigA = new SourceConfig('s1', 'Source A', 'https://example.com/a.ics', '15m', []);
        $sourceConfigB = new SourceConfig('s2', 'Source B', 'https://example.com/b.ics', '15m', []);

        $export = new ExportConfig(
            id: 'e1',
            title: 'Export Filtered',
            slug: 'export-filtered',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [
                new IncludedSourceConfig('s1', []),
                new IncludedSourceConfig('s2', []),
            ],
            filters: [
                new FilterRuleConfig(
                    type: 'match',
                    match: ['summary' => ['contains' => 'Team B']],
                    onMatch: 'remove',
                ),
            ],
        );

        $config = new AppConfig(
            sources: [
                's1' => $sourceConfigA,
                's2' => $sourceConfigB,
            ],
            exports: ['e1' => $export],
        );

        $result = $builder->build($config, $export);

        self::assertNotNull($result->icsContent);

        $events = (new CalendarParser())->parseEvents($result->icsContent);
        self::assertCount(1, $events);
        self::assertSame('Team A', $events[0]->summary);
        self::assertSame('Hall A', $events[0]->location);
        self::assertStringNotContainsString('Team B', $result->icsContent);
    }

    public function testExportBuilderKeepFiltersActAsWhitelistOnExport(): void
    {
        $sourceA = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
SUMMARY:Kinder Handball
END:VEVENT
BEGIN:VEVENT
UID:a-2@example
DTSTART:20260501T100000Z
SUMMARY:Teenkreis
END:VEVENT
END:VCALENDAR
ICS;

        $sourceB = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T110000Z
SUMMARY:Kinderturnen
END:VEVENT
END:VCALENDAR
ICS;

        $httpClient = new MockHttpClient([
            new MockResponse($sourceA, ['http_code' => 200]),
            new MockResponse($sourceB, ['http_code' => 200]),
        ]);

        $cacheDir = sys_get_temp_dir() . '/ical_feed_cache_' . uniqid('', true);
        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $feedFetcher = new FeedFetcher(
            $httpClient,
            new FileCache($cacheDir),
            new CacheKeyBuilder(),
            new TtlParser(),
            $logger,
        );

        $builder = new ExportBuilder($feedFetcher, new CalendarParser(), $logger);

        $sourceConfigA = new SourceConfig('s1', 'Source A', 'https://example.com/a.ics', '15m', []);
        $sourceConfigB = new SourceConfig('s2', 'Source B', 'https://example.com/b.ics', '15m', []);

        $export = new ExportConfig(
            id: 'e1',
            title: 'Kinder Handball',
            slug: 'kinder-handball',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [
                new IncludedSourceConfig('s1', []),
                new IncludedSourceConfig('s2', []),
            ],
            filters: [
                new FilterRuleConfig(
                    type: 'match',
                    match: ['summary' => ['contains' => 'Kinder']],
                    onMatch: 'keep',
                ),
            ],
        );

        $config = new AppConfig(
            sources: [
                's1' => $sourceConfigA,
                's2' => $sourceConfigB,
            ],
            exports: ['e1' => $export],
        );

        $result = $builder->build($config, $export);

        self::assertNotNull($result->icsContent);

        $events = (new CalendarParser())->parseEvents($result->icsContent);
        self::assertCount(2, $events);
        self::assertSame('Kinder Handball', $events[0]->summary);
        self::assertSame('Kinderturnen', $events[1]->summary);
        self::assertStringNotContainsString('Teenkreis', $result->icsContent);
    }
}
