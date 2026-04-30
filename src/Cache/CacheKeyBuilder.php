<?php

declare(strict_types=1);

namespace App\Cache;

use App\Config\Dto\ExportConfig;
use App\Config\Dto\SourceConfig;

final class CacheKeyBuilder
{
    public function forSourceConfig(SourceConfig $sourceConfig): string
    {
        $payload = [
            'id' => $sourceConfig->id,
            'label' => $sourceConfig->label,
            'url' => $sourceConfig->url,
            'cache_ttl' => $sourceConfig->cacheTtl,
            'filters' => array_map(
                static fn ($filter): array => $filter->toArray(),
                $sourceConfig->filters
            ),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash('sha256', 'source-config|' . $json);
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
