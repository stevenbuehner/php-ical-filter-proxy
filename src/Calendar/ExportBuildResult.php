<?php

declare(strict_types=1);

namespace App\Calendar;

final readonly class ExportBuildResult
{
    public function __construct(
        public ?string $icsContent,
        public int $successfulSources,
    ) {
    }
}
