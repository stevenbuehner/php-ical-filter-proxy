<?php

declare(strict_types=1);

namespace App\Config;

final class YamlLineLocator
{
    /**
     * @param list<string> $lines
     */
    public function locate(array $lines, string $path): ?int
    {
        if ($path === '' || $path === 'root') {
            return 1;
        }

        $tokens = $this->tokenizePath($path);
        if ($tokens === []) {
            return null;
        }

        foreach ($tokens as $token) {
            $line = $this->findKeyLine($lines, $token);
            if ($line !== null) {
                return $line;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function tokenizePath(string $path): array
    {
        $normalized = preg_replace('/\[[0-9]+\]/', '', $path) ?? $path;
        $parts = array_filter(explode('.', $normalized), static fn (string $p): bool => $p !== '');

        return array_values($parts);
    }

    /** @param list<string> $lines */
    private function findKeyLine(array $lines, string $key): ?int
    {
        $pattern = '/^\s*' . preg_quote($key, '/') . '\s*:/';
        foreach ($lines as $idx => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $idx + 1;
            }
        }

        return null;
    }
}
