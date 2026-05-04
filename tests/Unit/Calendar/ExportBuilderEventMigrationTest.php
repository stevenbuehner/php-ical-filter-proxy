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
use App\Config\Dto\EventMigrationConfig;
use App\Config\Dto\ExportConfig;
use App\Config\Dto\IncludedSourceConfig;
use App\Config\Dto\SourceConfig;
use App\Http\Logger\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExportBuilderEventMigrationTest extends TestCase
{
    public function testExportBuilderAppliesEventMigrationBeforeSerialization(): void
    {
        $sourceA = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Morning A
DESCRIPTION:First
LOCATION:Hall A
URL:https://example.com/one
CATEGORIES:Tech,Team
END:VEVENT
END:VCALENDAR
ICS;

        $sourceB = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T095500Z
DTEND:20260501T110000Z
SUMMARY:Morning B
DESCRIPTION:Second
LOCATION:Hall B
URL:https://example.com/two
CATEGORIES:Team,Music
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
            title: 'Migrated Export',
            slug: 'migrated-export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [
                new IncludedSourceConfig('s1', []),
                new IncludedSourceConfig('s2', []),
            ],
            eventMigration: new EventMigrationConfig(true, '0s', 'merge_titles_csv'),
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
        self::assertSame('Morning A, Morning B', $events[0]->summary);
        self::assertSame('First' . "\n---\n" . 'Second', $events[0]->description);
        self::assertSame('Hall A, Hall B', $events[0]->location);
        self::assertSame('Tech,Team,Music', implode(',', $events[0]->categories));
        self::assertSame('https://example.com/one', $events[0]->url);
    }

    public function testExportBuilderKeepsSeparateEventsWhenMigrationDisabled(): void
    {
        $sourceA = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:a-1@example
DTSTART:20260501T090000Z
DTEND:20260501T100000Z
SUMMARY:Morning A
END:VEVENT
END:VCALENDAR
ICS;

        $sourceB = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:b-1@example
DTSTART:20260501T095500Z
DTEND:20260501T110000Z
SUMMARY:Morning B
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
            title: 'Plain Export',
            slug: 'plain-export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [
                new IncludedSourceConfig('s1', []),
                new IncludedSourceConfig('s2', []),
            ],
            eventMigration: null,
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
        self::assertSame('Morning A', $events[0]->summary);
        self::assertSame('Morning B', $events[1]->summary);
    }
}
