<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:exports:list', description: 'Lists configured exports')]
final class ListExportsCommand extends Command
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
        foreach ($config->exports as $key => $export) {
            $rows[] = [$key, $export->title, $export->slug, (string) count($export->includeSources), $export->cacheTtl];
        }

        $io->table(['export key', 'title', 'slug', 'sources', 'cache_ttl'], $rows);

        return Command::SUCCESS;
    }
}
