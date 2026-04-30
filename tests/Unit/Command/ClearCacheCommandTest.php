<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ClearCacheCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearCacheCommandTest extends TestCase
{
    public function testClearCacheDefaultScopeAll(): void
    {
        $root = $this->makeProjectRoot();
        file_put_contents($root . '/var/cache/feeds/a.cache', 'a');
        file_put_contents($root . '/var/cache/exports/b.cache', 'b');

        $tester = new CommandTester(new ClearCacheCommand($root));
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertFileDoesNotExist($root . '/var/cache/feeds/a.cache');
        self::assertFileDoesNotExist($root . '/var/cache/exports/b.cache');
        self::assertStringContainsString('scope=all', $tester->getDisplay());
    }

    public function testClearCacheInvalidScopeReturnsInvalidExitCode(): void
    {
        $root = $this->makeProjectRoot();
        $tester = new CommandTester(new ClearCacheCommand($root));

        $exit = $tester->execute(['--scope' => 'invalid']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('Invalid scope', $tester->getDisplay());
    }

    private function makeProjectRoot(): string
    {
        $root = sys_get_temp_dir() . '/ical_cmd_' . uniqid('', true);
        mkdir($root . '/var/cache/feeds', 0777, true);
        mkdir($root . '/var/cache/exports', 0777, true);

        return $root;
    }
}
