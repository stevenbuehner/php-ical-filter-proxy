<?php

declare(strict_types=1);

namespace App\Http;

use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Calendar\ExportBuilder;
use App\Calendar\FeedFetcher;
use App\Calendar\CalendarParser;
use App\Config\ConfigLoader;
use App\Http\Logger\FileLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;

final readonly class CalendarController
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    public function feed(string $slug, string $token): Response
    {
        $logger = new FileLogger($this->projectRoot . '/var/log/app.log');
        try {
            $config = (new ConfigLoader($this->projectRoot . '/config/calendars.yaml'))->load();
        } catch (\Throwable $exception) {
            $logger->error('config_load_failed', ['message' => $exception->getMessage()]);
            return new Response('Configuration error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $export = null;
        foreach ($config->exports as $candidate) {
            if ($candidate->slug === $slug) {
                $export = $candidate;
                break;
            }
        }

        if ($export === null || !hash_equals($export->token, $token)) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $exportCache = new FileCache($this->projectRoot . '/var/cache/exports');
        $cacheKeyBuilder = new CacheKeyBuilder();
        $exportCacheKey = $cacheKeyBuilder->forExportConfig($export);

        $icsContent = null;
        if ($exportCache->isFresh($exportCacheKey)) {
            $icsContent = $exportCache->get($exportCacheKey);
        }

        if ($icsContent === null) {
            $fetcher = new FeedFetcher(
                httpClient: HttpClient::create(),
                sourceCache: new FileCache($this->projectRoot . '/var/cache/feeds'),
                cacheKeyBuilder: $cacheKeyBuilder,
                ttlParser: new TtlParser(),
                logger: $logger,
            );

            try {
                $builder = new ExportBuilder($fetcher, new CalendarParser(), $logger);
                $buildResult = $builder->build($config, $export);
            } catch (\Throwable $exception) {
                $logger->error('export_generation_runtime_error', ['slug' => $slug, 'message' => $exception->getMessage()]);
                return new Response('Service unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
            }

            if ($buildResult->successfulSources === 0 || $buildResult->icsContent === null) {
                return new Response('No source available', Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $icsContent = $buildResult->icsContent;
            $ttlSeconds = $export->cacheTtl !== '' ? (new TtlParser())->parse($export->cacheTtl) : 600;
            $exportCache->set($exportCacheKey, $icsContent, $ttlSeconds);
        }

        $response = new Response($icsContent, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s.ics"', $slug));
        $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=60');

        return $response;
    }
}
