<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\CacheKeyBuilder;
use App\Config\Dto\ExportConfig;
use App\Config\Dto\FilterRuleConfig;
use App\Config\Dto\IncludedSourceConfig;
use PHPUnit\Framework\TestCase;

final class CacheKeyBuilderTest extends TestCase
{
    public function testExportCacheKeyChangesWhenExportFiltersChange(): void
    {
        $builder = new CacheKeyBuilder();

        $baseExport = new ExportConfig(
            id: 'e1',
            title: 'Export',
            slug: 'export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [new IncludedSourceConfig('s1', [])],
            filters: [],
        );

        $filteredExport = new ExportConfig(
            id: 'e1',
            title: 'Export',
            slug: 'export',
            token: 'secret',
            cacheTtl: '10m',
            includeSources: [new IncludedSourceConfig('s1', [])],
            filters: [
                new FilterRuleConfig(
                    type: 'match',
                    match: ['any' => true],
                    onMatch: 'remove',
                ),
            ],
        );

        self::assertNotSame(
            $builder->forExportConfig($baseExport),
            $builder->forExportConfig($filteredExport)
        );
    }
}
