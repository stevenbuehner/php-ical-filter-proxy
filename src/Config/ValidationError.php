<?php

declare(strict_types=1);

namespace App\Config;

final readonly class ValidationError
{
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
        public string $expected,
        public string $found,
        public ?int $line = null,
    ) {
    }
}
