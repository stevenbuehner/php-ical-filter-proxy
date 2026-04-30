<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigValidator;
use App\Config\ValidationError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:config:validate', description: 'Validates config/calendars.yaml')]
final class ValidateConfigCommand extends Command
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

        $errors = (new ConfigValidator())->validateFile(
            configFile: $this->projectRoot . '/config/calendars.yaml',
            feedCacheDir: $this->projectRoot . '/var/cache/feeds',
            exportCacheDir: $this->projectRoot . '/var/cache/exports',
        );

        if ($errors !== []) {
            $io->error(sprintf('Configuration is invalid (%d issue%s).', count($errors), count($errors) === 1 ? '' : 's'));
            foreach ($errors as $i => $error) {
                $this->renderError($io, $i + 1, $error);
            }

            return Command::FAILURE;
        }

        $io->success('Configuration is valid.');

        return Command::SUCCESS;
    }

    private function renderError(SymfonyStyle $io, int $index, ValidationError $error): void
    {
        $line = $error->line !== null ? (string) $error->line : 'n/a';
        $io->writeln(sprintf('%d) [%s] %s', $index, $error->code, $error->message));
        $io->writeln(sprintf('   path: %s', $error->path));
        $io->writeln(sprintf('   line: %s', $line));
        $io->writeln(sprintf('   expected: %s', $error->expected));
        $io->writeln(sprintf('   found: %s', $error->found));
        $io->newLine();
    }
}
