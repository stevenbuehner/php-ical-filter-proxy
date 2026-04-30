<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sources:list', description: 'Lists configured sources')]
final class ListSourcesCommand extends Command
{
    public function __construct(private readonly string $projectRoot, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = (new ConfigLoader($this->projectRoot . '/config/calendars.yaml'))->load();

        $rows = [];
        foreach ($config->sources as $key => $source) {
            $url = strlen($source->url) > 60 ? substr($source->url, 0, 57) . '...' : $source->url;
            $rows[] = [$key, $source->label, $url, $source->cacheTtl];
        }

        $io->table(['source key', 'label', 'url', 'cache_ttl'], $rows);

        return Command::SUCCESS;
    }
}
