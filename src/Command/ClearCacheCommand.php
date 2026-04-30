<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cache:clear', description: 'Clears cache files in feeds, exports or both')]
final class ClearCacheCommand extends Command
{
    use CacheCommandSupport;

    public function __construct(private readonly string $projectRoot, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED, 'feeds|exports|all', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scope = strtolower(trim((string) $input->getOption('scope')));

        if (!$this->isValidScope($scope)) {
            $io->error($this->invalidScopeMessage());
            return Command::INVALID;
        }

        $maintenance = $this->cacheMaintenance($this->projectRoot);

        $stats = $maintenance->clear($scope);

        $io->success(sprintf(
            'Cache cleared (scope=%s): deleted %d files (%d bytes), scanned %d files.',
            $scope,
            $stats['deleted_files'],
            $stats['deleted_bytes'],
            $stats['scanned_files']
        ));

        return Command::SUCCESS;
    }
}
