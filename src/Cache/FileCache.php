<?php

declare(strict_types=1);

namespace App\Cache;

final readonly class FileCache implements CacheInterface
{
    public function __construct(
        private string $baseDir,
    ) {
    }

    public function get(string $key): ?string
    {
        if (!$this->isFresh($key)) {
            return null;
        }

        $path = $this->contentPath($key);
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        return $content;
    }

    public function getAny(string $key): ?string
    {
        $path = $this->contentPath($key);
        if (!is_readable($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return $content;
    }

    public function set(string $key, string $content, int $ttlSeconds): void
    {
        $this->ensureBaseDirExists();

        if ($ttlSeconds <= 0) {
            throw new \InvalidArgumentException('TTL must be greater than 0 seconds.');
        }

        $contentPath = $this->contentPath($key);
        $metaPath = $this->metaPath($key);

        $writtenContent = @file_put_contents($contentPath, $content, LOCK_EX);
        if ($writtenContent === false) {
            throw new \RuntimeException(sprintf('Failed to write cache content: %s', $contentPath));
        }

        $meta = [
            'created_at' => time(),
            'expires_at' => time() + $ttlSeconds,
            'ttl_seconds' => $ttlSeconds,
        ];

        $metaJson = json_encode($meta, JSON_THROW_ON_ERROR);
        $writtenMeta = @file_put_contents($metaPath, $metaJson, LOCK_EX);

        if ($writtenMeta === false) {
            @unlink($contentPath);
            throw new \RuntimeException(sprintf('Failed to write cache metadata: %s', $metaPath));
        }
    }

    public function isFresh(string $key): bool
    {
        $contentPath = $this->contentPath($key);
        $metaPath = $this->metaPath($key);

        if (!is_readable($contentPath) || !is_readable($metaPath)) {
            return false;
        }

        $metaRaw = @file_get_contents($metaPath);
        if ($metaRaw === false) {
            return false;
        }

        try {
            $meta = json_decode($metaRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!is_array($meta) || !isset($meta['expires_at']) || !is_int($meta['expires_at'])) {
            return false;
        }

        return $meta['expires_at'] >= time();
    }

    public function delete(string $key): void
    {
        @unlink($this->contentPath($key));
        @unlink($this->metaPath($key));
    }

    private function ensureBaseDirExists(): void
    {
        if (is_dir($this->baseDir)) {
            return;
        }

        if (!@mkdir($this->baseDir, 0775, true) && !is_dir($this->baseDir)) {
            throw new \RuntimeException(sprintf('Failed to create cache directory: %s', $this->baseDir));
        }
    }

    private function contentPath(string $key): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $this->sanitizeKey($key) . '.cache';
    }

    private function metaPath(string $key): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $this->sanitizeKey($key) . '.meta.json';
    }

    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?? '';
    }
}
