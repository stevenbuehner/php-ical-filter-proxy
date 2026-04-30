<?php

declare(strict_types=1);

namespace App\Calendar;

final readonly class FeedFetchResult
{
    public function __construct(
        public string $sourceKey,
        public ?string $content,
        public bool $fromCache,
        public ?string $error,
    ) {
    }
}
