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

final class FeedFetcherSourceFiltersAndTransformsTest extends TestCase
{
    public function testSourceKeepAndRemoveFiltersAreAppliedBeforeCaching(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig(type: 'match', match: ['summary' => ['contains' => 'Technik']], onMatch: 'keep'),
            new FilterRuleConfig(type: 'match', match: ['summary' => ['contains' => 'Jugend']], onMatch: 'remove'),
        ]);

        self::assertCount(2, $events);
        self::assertStringContainsString('Technik', $events[0]->summary);
        self::assertStringContainsString('Technik', $events[1]->summary);
    }

    public function testSourceTransformsAreApplied(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig(
                type: 'match',
                match: ['any' => true],
                onMatch: 'transform',
                transform: [
                    ['type' => 'prefix_text', 'field' => 'summary', 'value' => '[SRC] '],
                    ['type' => 'suffix_text', 'field' => 'summary', 'value' => ' [OK]'],
                    ['type' => 'replace_regex', 'field' => 'location', 'pattern' => '/Saal|Studio/i', 'replacement' => 'Halle'],
                    ['type' => 'categories_add', 'value' => 'Source'],
                    ['type' => 'adjust_times', 'start' => ['reference' => 'current_start', 'offset' => '+1 day']],
                ],
            ),
        ]);

        self::assertSame('[SRC] Technikdienst Probe [OK]', $events[0]->summary);
        self::assertSame('[SRC] Jugendtreffen [OK]', $events[1]->summary);
        self::assertSame('Halle', $events[0]->location);
        self::assertStringContainsString('Source', implode(',', $events[0]->categories));
        self::assertSame('20260502T090000', $events[0]->dtstart);
    }

    /** @return list<\App\Calendar\CalendarEvent> */
    private function fetchWithFilters(array $filters): array
    {
        $ics = file_get_contents(__DIR__ . '/../../Fixtures/filter-summary.ics');
        self::assertNotFalse($ics);

        $source = new SourceConfig(
            id: 's1',
            label: 'S1',
            url: 'https://example.com/source.ics',
            cacheTtl: '15m',
            filters: $filters,
        );

        $http = new MockHttpClient([new MockResponse($ics, ['http_code' => 200])]);
        $logger = new class () implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $cacheDir = sys_get_temp_dir() . '/ical_source_filters_' . uniqid('', true);
        $fetcher = new FeedFetcher($http, new FileCache($cacheDir), new CacheKeyBuilder(), new TtlParser(), $logger);
        $result = $fetcher->fetchAll(['s1' => $source])['s1'];

        self::assertNotNull($result->content);

        return (new CalendarParser())->parseEvents($result->content);
    }
}
