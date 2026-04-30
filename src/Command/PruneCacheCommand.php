<?php

declare(strict_types=1);

namespace App\Command;

use App\Cache\CacheMaintenance;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cache:prune', description: 'Prunes cache files older than the given age')]
final class PruneCacheCommand extends Command
{
    public function __construct(private readonly string $projectRoot, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'feeds|exports|all', 'all')
            ->addOption('age', null, InputOption::VALUE_REQUIRED, 'Age threshold, e.g. 1w, 3d, 12h, 30m, 120s', '1w');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scope = strtolower(trim((string) $input->getOption('scope')));
        $ageRaw = strtolower(trim((string) $input->getOption('age')));

        if (!in_array($scope, ['feeds', 'exports', 'all'], true)) {
            $io->error('Invalid scope. Allowed values: feeds, exports, all.');
            return Command::INVALID;
        }

        $ageSeconds = $this->parseAgeToSeconds($ageRaw);
        if ($ageSeconds === null) {
            $io->error('Invalid age format. Use e.g. 1w, 3d, 12h, 30m, 120s.');
            return Command::INVALID;
        }

        $maintenance = new CacheMaintenance(
            $this->projectRoot . '/var/cache/feeds',
            $this->projectRoot . '/var/cache/exports'
        );

        $stats = $maintenance->pruneOlderThan($ageSeconds, $scope);

        $io->success(sprintf(
            'Cache pruned (scope=%s, age=%s): deleted %d files (%d bytes), scanned %d files.',
            $scope,
            $ageRaw,
            $stats['deleted_files'],
            $stats['deleted_bytes'],
            $stats['scanned_files']
        ));

        return Command::SUCCESS;
    }

    private function parseAgeToSeconds(string $age): ?int
    {
        if (!preg_match('/^([1-9][0-9]*)([smhdw])$/', $age, $m)) {
            return null;
        }

        $value = (int) $m[1];
        $unit = $m[2];

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            'w' => $value * 604800,
            default => null,
        };
    }
}
