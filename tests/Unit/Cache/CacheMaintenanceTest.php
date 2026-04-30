<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\CacheMaintenance;
use PHPUnit\Framework\TestCase;

final class CacheMaintenanceTest extends TestCase
{
    public function testClearAllRemovesCacheAndMetaFiles(): void
    {
        [$feeds, $exports] = $this->makeDirs();
        $this->writeFile($feeds . '/a.cache', 'x');
        $this->writeFile($feeds . '/a.meta.json', '{}');
        $this->writeFile($exports . '/b.cache', 'y');
        $this->writeFile($exports . '/keep.txt', 'z');

        $stats = (new CacheMaintenance($feeds, $exports))->clear('all');

        self::assertSame(3, $stats['deleted_files']);
        self::assertFileDoesNotExist($feeds . '/a.cache');
        self::assertFileDoesNotExist($feeds . '/a.meta.json');
        self::assertFileDoesNotExist($exports . '/b.cache');
        self::assertFileExists($exports . '/keep.txt');
    }

    public function testClearScopeFeedsOnly(): void
    {
        [$feeds, $exports] = $this->makeDirs();
        $this->writeFile($feeds . '/a.cache', 'x');
        $this->writeFile($exports . '/b.cache', 'y');

        (new CacheMaintenance($feeds, $exports))->clear('feeds');

        self::assertFileDoesNotExist($feeds . '/a.cache');
        self::assertFileExists($exports . '/b.cache');
    }

    public function testPruneOlderThanDeletesOnlyOldFiles(): void
    {
        [$feeds, $exports] = $this->makeDirs();
        $old = $feeds . '/old.cache';
        $new = $feeds . '/new.cache';
        $this->writeFile($old, 'x');
        $this->writeFile($new, 'y');

        $now = time();
        touch($old, $now - 8 * 86400);
        touch($new, $now - 60);

        $stats = (new CacheMaintenance($feeds, $exports))->pruneOlderThan(7 * 86400, 'feeds');

        self::assertSame(1, $stats['deleted_files']);
        self::assertFileDoesNotExist($old);
        self::assertFileExists($new);
    }

    /** @return array{0:string,1:string} */
    private function makeDirs(): array
    {
        $base = sys_get_temp_dir() . '/cache_maintenance_' . uniqid('', true);
        $feeds = $base . '/feeds';
        $exports = $base . '/exports';
        mkdir($feeds, 0777, true);
        mkdir($exports, 0777, true);

        return [$feeds, $exports];
    }

    private function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }
}
