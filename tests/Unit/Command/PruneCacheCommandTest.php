<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\PruneCacheCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneCacheCommandTest extends TestCase
{
    public function testPruneCacheDefaultAgeOneWeek(): void
    {
        $root = $this->makeProjectRoot();
        $old = $root . '/var/cache/feeds/old.cache';
        $new = $root . '/var/cache/feeds/new.cache';

        file_put_contents($old, 'old');
        file_put_contents($new, 'new');
        touch($old, time() - 8 * 86400);
        touch($new, time() - 60);

        $tester = new CommandTester(new PruneCacheCommand($root));
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertFileDoesNotExist($old);
        self::assertFileExists($new);
        self::assertStringContainsString('age=1w', $tester->getDisplay());
    }

    public function testPruneCacheInvalidAgeReturnsInvalidExitCode(): void
    {
        $root = $this->makeProjectRoot();
        $tester = new CommandTester(new PruneCacheCommand($root));

        $exit = $tester->execute(['--age' => 'abc']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('Invalid age format', $tester->getDisplay());
    }

    private function makeProjectRoot(): string
    {
        $root = sys_get_temp_dir() . '/ical_cmd_' . uniqid('', true);
        mkdir($root . '/var/cache/feeds', 0777, true);
        mkdir($root . '/var/cache/exports', 0777, true);

        return $root;
    }
}
