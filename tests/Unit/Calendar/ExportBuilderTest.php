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
}
