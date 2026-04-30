<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Calendar\CalendarParser;
use App\Calendar\FeedFetcher;
use App\Config\Dto\FilterRuleConfig;
use App\Config\Dto\SourceConfig;
use App\Http\Logger\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FeedFetcherSourceNormalizationTest extends TestCase
{
    public function testFetcherAppliesSourceFiltersBeforeCaching(): void
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);

        $source = new SourceConfig(
            id: 's1',
            label: 'S1',
            url: 'https://example.com/source.ics',
            cacheTtl: '15m',
            filters: [
                new FilterRuleConfig(
                    name: 'prefix-all',
                    action: 'keep',
                    match: ['any' => true],
                    transforms: [
                        ['field' => 'summary', 'action' => 'prefix', 'value' => '[SRC] '],
                    ],
                ),
            ],
        );

        $http = new MockHttpClient([
            new MockResponse($ics, ['http_code' => 200]),
        ]);

        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $cacheDir = sys_get_temp_dir() . '/ical_source_norm_' . uniqid('', true);
        $fetcher = new FeedFetcher($http, new FileCache($cacheDir), new CacheKeyBuilder(), new TtlParser(), $logger);

        $results = $fetcher->fetchAll(['s1' => $source]);
        $content = $results['s1']->content;

        self::assertNotNull($content);

        $events = (new CalendarParser())->parseEvents($content);
        self::assertCount(3, $events);
        self::assertSame('[SRC] Technikdienst Probe', $events[0]->summary);
        self::assertSame('[SRC] Jugendtreffen', $events[1]->summary);
        self::assertSame('[SRC] Technikabend', $events[2]->summary);
    }
}
