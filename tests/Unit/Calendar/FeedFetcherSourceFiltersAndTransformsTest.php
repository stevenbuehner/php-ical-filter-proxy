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
    public function testSourceKeepFilterIsAppliedBeforeCaching(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig('keep-technik', 'keep', ['summary' => ['contains' => 'Technik']]),
        ]);

        self::assertCount(2, $events);
        self::assertStringContainsString('Technik', $events[0]->summary);
        self::assertStringContainsString('Technik', $events[1]->summary);
    }

    public function testSourceRemoveFilterIsAppliedBeforeCaching(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig('remove-jugend', 'remove', ['summary' => ['contains' => 'Jugend']]),
        ]);

        self::assertCount(2, $events);
        self::assertStringNotContainsString('Jugend', $events[0]->summary);
        self::assertStringNotContainsString('Jugend', $events[1]->summary);
    }

    public function testSourceTextTransformsAreApplied(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig('prefix-all', 'keep', ['any' => true], [
                ['field' => 'summary', 'action' => 'prefix', 'value' => '[SRC] '],
                ['field' => 'summary', 'action' => 'suffix', 'value' => ' [OK]'],
                ['field' => 'location', 'action' => 'replace_regex', 'pattern' => '/Saal|Studio/i', 'replacement' => 'Halle'],
            ]),
        ]);

        self::assertSame('[SRC] Technikdienst Probe [OK]', $events[0]->summary);
        self::assertSame('[SRC] Jugendtreffen [OK]', $events[1]->summary);
        self::assertSame('Halle', $events[0]->location);
    }

    public function testSourceCategoryAndDateTransformsAreApplied(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig('transform-all', 'keep', ['any' => true], [
                ['field' => 'categories', 'action' => 'add', 'value' => 'Source'],
                ['field' => 'start', 'action' => 'modify', 'value' => '+1 day'],
            ]),
        ]);

        self::assertStringContainsString('Source', implode(',', $events[0]->categories));
        self::assertSame('20260502T090000', $events[0]->dtstart);
    }

    public function testSourceTimeAdjustTransformIsApplied(): void
    {
        $events = $this->fetchWithFilters([
            new FilterRuleConfig('transform-time', 'keep', ['any' => true], [
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
            ]),
        ]);

        self::assertSame('20260501T084000', $events[0]->dtstart);
        self::assertSame('20260501T091000', $events[0]->dtend);
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
