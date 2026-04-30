<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Config\Dto\SourceConfig;
use App\Filter\FilterEngine;
use App\Filter\MatchEvaluator;
use App\Filter\TransformEngine;
use App\Http\Logger\LoggerInterface;
use Sabre\VObject\Component\VCalendar;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class FeedFetcher
{
    private const DEFAULT_TIMEOUT_SECONDS = 10.0;
    private const MAX_FEED_BYTES = 5_242_880;

    public function __construct(
        private HttpClientInterface $httpClient,
        private FileCache $sourceCache,
        private CacheKeyBuilder $cacheKeyBuilder,
        private TtlParser $ttlParser,
        private LoggerInterface $logger,
        private float $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
    ) {
    }

    /**
     * @param array<string, SourceConfig> $sources
     * @return array<string, FeedFetchResult>
     */
    public function fetchAll(array $sources): array
    {
        $results = [];
        $requests = [];

        foreach ($sources as $sourceKey => $sourceConfig) {
            $cacheKey = $this->cacheKeyBuilder->forSourceConfig($sourceConfig);
            $this->logger->info('feed_fetch_start', ['source' => $sourceKey, 'url' => $sourceConfig->url]);

            if ($this->sourceCache->isFresh($cacheKey)) {
                $cachedContent = $this->sourceCache->get($cacheKey);
                if ($cachedContent !== null) {
                    $this->logger->info('cache_hit', ['source' => $sourceKey]);
                    $results[$sourceKey] = new FeedFetchResult($sourceKey, $cachedContent, true, null);
                    continue;
                }
            }
            $this->logger->info('cache_miss', ['source' => $sourceKey]);

            $requests[$sourceKey] = [
                'response' => $this->httpClient->request('GET', $sourceConfig->url, [
                    'timeout' => $this->timeoutSeconds,
                    'max_duration' => $this->timeoutSeconds,
                    'headers' => [
                        'Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.1',
                    ],
                ]),
                'source' => $sourceConfig,
                'cache_key' => $cacheKey,
            ];
        }

        if ($requests === []) {
            return $results;
        }

        $responseMap = [];
        foreach ($requests as $sourceKey => $request) {
            $responseMap[spl_object_id($request['response'])] = $sourceKey;
        }

        try {
            foreach ($this->httpClient->stream(array_column($requests, 'response')) as $response => $chunk) {
                if (!$chunk->isLast()) {
                    continue;
                }

                $sourceKey = $responseMap[spl_object_id($response)] ?? null;
                if ($sourceKey === null) {
                    continue;
                }

                $results[$sourceKey] = $this->finalizeResponse(
                    $sourceKey,
                    $requests[$sourceKey]['source'],
                    $requests[$sourceKey]['cache_key'],
                    $response,
                );
                $this->logger->info('feed_fetch_end', ['source' => $sourceKey, 'from_cache' => $results[$sourceKey]->fromCache, 'has_error' => $results[$sourceKey]->error !== null]);
            }
        } catch (ExceptionInterface $exception) {
            $this->logger->error('feed_fetch_stream_exception', ['message' => $exception->getMessage()]);
        }

        foreach (array_keys($sources) as $sourceKey) {
            if (!array_key_exists($sourceKey, $results)) {
                $results[$sourceKey] = new FeedFetchResult($sourceKey, null, false, 'Unknown fetch error.');
            }
        }

        return $results;
    }

    private function finalizeResponse(string $sourceKey, SourceConfig $sourceConfig, string $cacheKey, ResponseInterface $response): FeedFetchResult
    {
        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->warning('feed_fetch_http_error', ['source' => $sourceKey, 'status' => $statusCode]);
                return $this->fallbackWithStaleCache($sourceKey, $cacheKey, sprintf('HTTP %d for source %s', $statusCode, $sourceConfig->url));
            }

            $content = $response->getContent();
            if (strlen($content) > self::MAX_FEED_BYTES) {
                return $this->fallbackWithStaleCache($sourceKey, $cacheKey, sprintf('Feed exceeds max size (%d bytes).', self::MAX_FEED_BYTES));
            }

            $normalizedContent = $this->applySourceFilters($content, $sourceConfig);
            $ttl = $this->resolveTtlSeconds($sourceConfig->cacheTtl);
            $this->sourceCache->set($cacheKey, $normalizedContent, $ttl);

            return new FeedFetchResult($sourceKey, $normalizedContent, false, null);
        } catch (ExceptionInterface | \RuntimeException | \InvalidArgumentException $exception) {
            $this->logger->error('feed_fetch_exception', ['source' => $sourceKey, 'message' => $exception->getMessage()]);
            return $this->fallbackWithStaleCache($sourceKey, $cacheKey, $exception->getMessage());
        }
    }

    private function resolveTtlSeconds(string $ttl): int
    {
        if ($ttl === '') {
            return 900;
        }

        return $this->ttlParser->parse($ttl);
    }

    private function fallbackWithStaleCache(string $sourceKey, string $cacheKey, string $error): FeedFetchResult
    {
        $staleContent = $this->sourceCache->getAny($cacheKey);
        if ($staleContent !== null) {
            return new FeedFetchResult($sourceKey, $staleContent, true, $error);
        }

        return new FeedFetchResult($sourceKey, null, false, $error);
    }

    private function applySourceFilters(string $content, SourceConfig $sourceConfig): string
    {
        if ($sourceConfig->filters === []) {
            return $content;
        }

        $parser = new CalendarParser();
        $calendar = $parser->parse($content);
        $events = $parser->extractEvents($calendar);
        $filtered = (new FilterEngine(new MatchEvaluator(), new TransformEngine()))->apply($events, $sourceConfig->filters);

        $result = new VCalendar();
        foreach ($filtered->filteredEvents as $event) {
            $result->add(clone $event->originalEvent);
        }

        return $result->serialize();
    }
}
