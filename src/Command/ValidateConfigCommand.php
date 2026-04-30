<?php

declare(strict_types=1);

namespace App\Command;

use App\Config\ConfigValidator;
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

        $validator = new ConfigValidator();
        $errors = $validator->validateFile(
            configFile: $this->projectRoot . '/config/calendars.yaml',
            feedCacheDir: $this->projectRoot . '/var/cache/feeds',
            exportCacheDir: $this->projectRoot . '/var/cache/exports',
        );

        if ($errors !== []) {
            $io->error('Configuration is invalid.');
            foreach ($errors as $error) {
                $io->writeln(sprintf(' - %s', $error));
            }

            return Command::FAILURE;
        }

        $io->success('Configuration is valid.');

        return Command::SUCCESS;
    }
}
