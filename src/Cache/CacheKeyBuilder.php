<?php

declare(strict_types=1);

namespace App\Cache;

use App\Config\Dto\ExportConfig;

final class CacheKeyBuilder
{
    public function forSourceUrl(string $url): string
    {
        return hash('sha256', 'source-url|' . trim($url));
    }

    public function forExportConfig(ExportConfig $exportConfig): string
    {
        $payload = [
            'id' => $exportConfig->id,
            'title' => $exportConfig->title,
            'slug' => $exportConfig->slug,
            'token' => $exportConfig->token,
            'cache_ttl' => $exportConfig->cacheTtl,
            'include_sources' => array_map(
                static fn ($include): array => $include->toArray(),
                $exportConfig->includeSources
            ),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash('sha256', 'export-config|' . $json);
    }
}
