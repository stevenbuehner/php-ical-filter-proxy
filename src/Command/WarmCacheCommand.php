<?php

declare(strict_types=1);

namespace App\Command;

use App\Calendar\FeedFetcher;
use App\Cache\CacheKeyBuilder;
use App\Cache\FileCache;
use App\Cache\TtlParser;
use App\Config\ConfigLoader;
use App\Http\Logger\FileLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'app:feeds:warm-cache', description: 'Fetches all sources and warms source cache')]
final class WarmCacheCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $config = (new ConfigLoader($this->projectRoot . '/config/calendars.yaml'))->load();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Config error: %s', $exception->getMessage()));
            return Command::FAILURE;
        }
        $logger = new FileLogger($this->projectRoot . '/var/log/app.log');

        $fetcher = new FeedFetcher(
            httpClient: HttpClient::create(),
            sourceCache: new FileCache($this->projectRoot . '/var/cache/feeds'),
            cacheKeyBuilder: new CacheKeyBuilder(),
            ttlParser: new TtlParser(),
            logger: $logger,
        );

        $results = $fetcher->fetchAll($config->sources);

        $hasErrors = false;
        foreach ($results as $result) {
            if ($result->error !== null && $result->content === null) {
                $hasErrors = true;
                $io->writeln(sprintf('ERROR  %s: %s', $result->sourceKey, $result->error));
                continue;
            }

            if ($result->error !== null && $result->content !== null) {
                $io->writeln(sprintf('WARN   %s: %s (using stale cache)', $result->sourceKey, $result->error));
                continue;
            }

            $origin = $result->fromCache ? 'cache' : 'http';
            $size = $result->content !== null ? strlen($result->content) : 0;
            $io->writeln(sprintf('OK     %s: loaded from %s (%d bytes)', $result->sourceKey, $origin, $size));
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
