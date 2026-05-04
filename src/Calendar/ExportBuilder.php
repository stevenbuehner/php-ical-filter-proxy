<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Config\Dto\AppConfig;
use App\Config\Dto\ExportConfig;
use App\Filter\FilterEngine;
use App\Http\Logger\LoggerInterface;
use Sabre\VObject\Component\VCalendar;

final readonly class ExportBuilder
{
    public function __construct(
        private FeedFetcher $feedFetcher,
        private CalendarParser $calendarParser,
        private LoggerInterface $logger,
    ) {
    }

    public function build(AppConfig $config, ExportConfig $export): ExportBuildResult
    {
        $sourceMap = [];
        foreach ($export->includeSources as $includedSource) {
            if (!isset($config->sources[$includedSource->source])) {
                continue;
            }
            $sourceMap[$includedSource->source] = $config->sources[$includedSource->source];
        }

        $migrationEngine = new EventMigrationEngine();
        $fetchResults = $this->feedFetcher->fetchAll($sourceMap);
        $this->logger->info('export_generation_start', ['slug' => $export->slug, 'sources' => count($sourceMap)]);
        $successfulSources = 0;
        $events = [];
        $filterEngine = new FilterEngine();

        foreach ($export->includeSources as $includedSource) {
            $result = $fetchResults[$includedSource->source] ?? null;
            if ($result === null || $result->content === null) {
                continue;
            }

            try {
                $parsedEvents = $this->calendarParser->parseEvents($result->content);
            } catch (CalendarParseException) {
                $this->logger->error('parse_error', ['source' => $includedSource->source]);
                continue;
            }

            $successfulSources++;

            if ($includedSource->filters !== []) {
                $filtered = $filterEngine->apply($parsedEvents, $includedSource->filters);
                $this->logger->info('filter_statistics', ['source' => $includedSource->source, 'stats' => $filtered->statistics]);
                $parsedEvents = $filtered->filteredEvents;
            }

            foreach ($parsedEvents as $event) {
                $events[] = $event;
            }
        }

        $events = $migrationEngine->migrate($events, $export, $export->eventMigration);

        if ($successfulSources === 0) {
            return new ExportBuildResult(null, 0);
        }

        $dedup = [];
        $calendar = new VCalendar();
        if (trim($export->title) !== '') {
            $calendar->add('X-WR-CALNAME', $export->title);
            $calendar->add('NAME', $export->title);
        }

        foreach ($events as $event) {
            $uid = $event->uid ?? '';
            if ($uid !== '') {
                if (isset($dedup[$uid])) {
                    continue;
                }
                $dedup[$uid] = true;
            }

            $calendar->add(clone $event->originalEvent);
        }
        $this->logger->info('deduplication_statistics', ['slug' => $export->slug, 'events_total' => count($events), 'events_exported' => count($calendar->select('VEVENT'))]);

        return new ExportBuildResult($calendar->serialize(), $successfulSources);
    }
}
