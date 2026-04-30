<?php

declare(strict_types=1);

namespace App\Command;

use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Calendar\CalendarParser;
use App\Calendar\FeedFetcher;
use App\Config\ConfigLoader;
use App\Filter\FilterEngine;
use App\Filter\MatchEvaluator;
use App\Filter\TransformEngine;
use App\Http\Logger\FileLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'app:export:preview', description: 'Preview one export')]
final class ExportPreviewCommand extends Command
{
    public function __construct(private readonly string $projectRoot, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('export', InputArgument::REQUIRED, 'Export key or slug')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of events to list', '20')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Do not read/write source cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = (string) $input->getArgument('export');
        $limit = max(1, (int) $input->getOption('limit'));
        $noCache = (bool) $input->getOption('no-cache');

        try {
            $config = (new ConfigLoader($this->projectRoot . '/config/calendars.yaml'))->load();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Config error: %s', $exception->getMessage()));
            return Command::FAILURE;
        }
        $logger = new FileLogger($this->projectRoot . '/var/log/app.log');

        $export = null;
        foreach ($config->exports as $key => $candidate) {
            if ($key === $target || $candidate->slug === $target) {
                $export = $candidate;
                break;
            }
        }

        if ($export === null) {
            $io->error(sprintf('Export not found: %s', $target));
            return Command::FAILURE;
        }

        $feedFetcher = new FeedFetcher(
            HttpClient::create(),
            $noCache ? new FileCache($this->projectRoot . '/var/cache/tmp-preview') : new FileCache($this->projectRoot . '/var/cache/feeds'),
            new CacheKeyBuilder(),
            new TtlParser(),
            $logger,
        );

        $sourceMap = [];
        foreach ($export->includeSources as $include) {
            if (isset($config->sources[$include->source])) {
                $sourceMap[$include->source] = $config->sources[$include->source];
            }
        }

        $results = $feedFetcher->fetchAll($sourceMap);
        $parser = new CalendarParser();
        $filterEngine = new FilterEngine(new MatchEvaluator(), new TransformEngine());

        $eventsBefore = 0;
        $eventsAfter = 0;
        $successfulSources = 0;
        $deduped = [];
        $duplicatesRemoved = 0;
        $finalEvents = [];

        foreach ($export->includeSources as $include) {
            $result = $results[$include->source] ?? null;
            if ($result === null || $result->content === null) {
                continue;
            }

            try {
                $events = $parser->parseEvents($result->content);
            } catch (\Throwable) {
                continue;
            }

            $successfulSources++;
            $eventsBefore += count($events);

            $sourceConfig = $config->sources[$include->source] ?? null;
            if ($sourceConfig !== null && $sourceConfig->filters !== []) {
                $events = $filterEngine->apply($events, $sourceConfig->filters)->filteredEvents;
            }

            if ($include->filters !== []) {
                $events = $filterEngine->apply($events, $include->filters)->filteredEvents;
            }

            $eventsAfter += count($events);

            foreach ($events as $event) {
                $uid = $event->uid ?? '';
                if ($uid !== '' && isset($deduped[$uid])) {
                    $duplicatesRemoved++;
                    continue;
                }

                if ($uid !== '') {
                    $deduped[$uid] = true;
                }

                $finalEvents[] = $event;
            }
        }

        $io->section('Export Preview');
        $io->writeln(sprintf('Exportname: %s (%s)', $export->title, $export->slug));
        $io->writeln(sprintf('Sources: %d configured, %d successful', count($export->includeSources), $successfulSources));
        $io->writeln(sprintf('Events before filters: %d', $eventsBefore));
        $io->writeln(sprintf('Events after filters: %d', $eventsAfter));
        $io->writeln(sprintf('Duplicates removed: %d', $duplicatesRemoved));
        $io->writeln(sprintf('Events exported: %d', count($finalEvents)));

        $rows = [];
        foreach (array_slice($finalEvents, 0, $limit) as $event) {
            $rows[] = [
                $event->dtstart ?? '-',
                $event->summary !== '' ? $event->summary : '-',
                $event->location !== '' ? $event->location : '-',
            ];
        }

        $io->newLine();
        $io->table(['Date', 'Summary', 'Location'], $rows);

        return Command::SUCCESS;
    }
}
