<?php

declare(strict_types=1);

namespace App\Cache;

interface CacheInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $content, int $ttlSeconds): void;

    public function isFresh(string $key): bool;

    public function delete(string $key): void;
}
