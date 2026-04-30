<?php

declare(strict_types=1);

namespace App\Cache;

final class TtlParser
{
    public function parse(string $ttl): int
    {
        if (!preg_match('/^([1-9][0-9]*)([smhd])$/', $ttl, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid TTL value: %s', $ttl));
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => throw new \InvalidArgumentException(sprintf('Unsupported TTL unit: %s', $unit)),
        };
    }
}
