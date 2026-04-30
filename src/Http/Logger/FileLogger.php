<?php

declare(strict_types=1);

namespace App\Http\Logger;

final readonly class FileLogger implements LoggerInterface
{
    public function __construct(
        private string $logFile,
    ) {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $record = [
            'time' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }

        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
